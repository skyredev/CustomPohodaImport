<?php

namespace Espo\Modules\PohodaImport\Tools\Pohoda;

use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\Core\ORM\Entity;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;

class Pohoda
{
	private string $username;
	private string $password;
	private string $url;
	private string $headerString = '<dat:dataPack version="2.0" id="Usr01" ico="27117758" key="033efc8c-513a-4639-92fc-be4e75668d07" programVersion="13607.12 (14.3.2024)" application="Transformace" note="CRM Import" xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd">';

	const DEBUG_PREFIX = '[Espo\Modules\PohodaImport\Tools\Pohoda]';

	private array $processedEntities = [];

	public function __construct(
		private readonly EntityManager $entityManager,
		private readonly Log $log,
		private readonly Config $config,
		private readonly Metadata $metadata,
		private readonly InjectableFactory $injectableFactory
	) {
		$this->loadPohodaSettings();
	}

	private function loadPohodaSettings(): void
	{
		$pohodaImportSettings = $this->entityManager
			->getRDBRepository('PohodaImportSettings')
			->where(['id' => 'pohodaImportSettings'])
			->findOne();

		if ($pohodaImportSettings) {
			$this->username = $pohodaImportSettings->get('username') ?? '';
			$this->password = $pohodaImportSettings->get('password') ?? '';
			$this->url = $pohodaImportSettings->get('url') ?? '';
		} else {
			$this->debug('PohodaImportSettings not found');
		}
	}

	private function debug($message, array $context = []): void
	{
		$this->log->debug(self::DEBUG_PREFIX . $message, $context);
	}

	public function processEntity(string $entityType): void
	{
		$entityIds = $this->getIdsToSync($entityType);

		$this->debug(' ' . $entityType . ' to sync count: ' . count($entityIds));

		foreach ($entityIds as $entityId) {
			try {
				$entity = $this->getEntityToSync($entityId?->id, $entityType);

				if (!$entity) {
					$this->debug("Entity type: {$entityType} with ID: {$entityId->id} not found");
					continue;
				}
				if ($entity->get('processed')) {
					$this->debug($entityType . ' already processed');
					continue;
				}

				if ($duplicityCheckFieldType !== null) {
					$duplicityCheckField = htmlspecialchars($entity->get($duplicityCheckFieldType));
					if (in_array($duplicityCheckField, $this->processedEntities)) {
						$this->debug("Entity with number: {$duplicityCheckField} already processed");
						continue;
					}
				}

				$this->debug('Trying to sync ' . $entityType . ' with name: ' . $entity->get('name'));

				$xmlData = $generateXmlForEntity($entity);

				$this->sendXmlToPohoda($xmlData);

				if ($duplicityCheckFieldType !== null) {
					$this->processedEntities[] = $duplicityCheckField;
				}

				$entity->set('processed', true);
				$this->entityManager->saveEntity($entity);
			} catch (\Exception $exception) {
				$this->debug('Failed to sync ' . $entityType . ' with ID: ' . $entityId->id . '. Error: ' . $exception->getMessage());
			}
		}
	}


	public function getIdsToSync(string $entityType): array
	{
		return $this->entityManager
			->getRDBRepository($entityType)
			->select(['id'])
			->where(
				Cond::Equal(Cond::column('processed'), false)
			)
			->find()->getValueMapList();
	}

	public function getEntityToSync(string $id, string $entityType): ?Entity
	{
		$entity = $this->entityManager
			->getEntityById($entityType, $id);

		return $entity;
	}

	public function getInvoiceItems(Entity $invoice, string $entityType, string $relationName): string
	{
		$items = $this->entityManager
			->getRDBRepository($entityType)
			->getRelation($invoice, $relationName)
			->find();

		$invoiceItems = '';

		foreach ($items as $item) {
			$itemName = htmlspecialchars(mb_substr($item->get('name'), 0, 90, 'UTF-8'));
			$unitPrice = htmlspecialchars($item->get('unitPrice'));
			$unit = htmlspecialchars($item->get('unit'));
			$quantity = htmlspecialchars($item->get('quantity'));
			$discount = htmlspecialchars($item->get('discount'));
			$taxRate = htmlspecialchars($item->get('taxRate'));
			$withTax = htmlspecialchars($item->get('withTax'));
			$unitPriceCurrency = htmlspecialchars($item->get('unitPriceCurrency'));

			$withTax = $withTax ? 'true' : 'false';

			if ($taxRate == 21) {
				$rateVAT = 'high';
			} elseif ($taxRate == 12) {
				$rateVAT = 'low';
			} else {
				$rateVAT = 'none';
			}

			$invoiceItems .= <<<XML
            <inv:invoiceItem>
					<inv:text>{$itemName}</inv:text>
					<inv:quantity>{$quantity}</inv:quantity>
					<inv:payVAT>{$withTax}</inv:payVAT>
					<inv:rateVAT>{$rateVAT}</inv:rateVAT>
					<inv:unit>{$unit}</inv:unit>
					<inv:discountPercentage>{$discount}</inv:discountPercentage>
					<inv:homeCurrency>
						<typ:unitPrice>{$unitPrice}</typ:unitPrice>
					</inv:homeCurrency>
					<inv:note>{$unitPriceCurrency}</inv:note>
				</inv:invoiceItem>
XML;
		}

		return $invoiceItems;
	}
	public function sendXmlToPohoda(string $xmlData): void
	{
		$encodedCredentials = base64_encode("{$this->username}:{$this->password}");

		$headers = [
			'Content-Type: text/xml; charset=windows-1250',
			'STW-Authorization: Basic ' . $encodedCredentials,
			'Accept-Encoding: gzip, deflate',
		];

		$xmlTemplate = trim(<<<XML
    <?xml version="1.0" encoding="Windows-1250"?>
    {$this->headerString}
        <dat:dataPackItem version="2.0" id="Usr01 (001)">
            {$xmlData}
        </dat:dataPackItem>
    </dat:dataPack>
XML);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, iconv('UTF-8', 'Windows-1250', $xmlTemplate));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_ENCODING, '');

		$response = curl_exec($ch);

		if ($response === false) {
			$error = curl_error($ch);
			curl_close($ch);
			throw new \Exception("Failed to send XML to Pohoda. cURL Error: " . $error);
		}

		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($statusCode !== 200) {
			throw new \Exception("Failed to send XML to Pohoda. Status code: {$statusCode}, Response: {$response}");
		}

		$responseState = $this->getResponseState($response);

		if ($responseState !== 'ok') {
			throw new \Exception("Failed to send XML to Pohoda. Response state: {$responseState}, Response: {$response}");
		}
	}


	private function getStatusCodeFromResponse(array $responseHeaders): int
	{
		$statusLine = $responseHeaders[0] ?? '';
		preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches);

		return (int) ($matches[1] ?? 0);
	}

	private function getResponseState(string $response): string
	{
		try {
			$doc = new \DOMDocument();
			$doc->loadXML($response);
			$xpath = new \DOMXPath($doc);
			$xpath->registerNamespace('rsp', 'http://www.stormware.cz/schema/version_2/response.xsd');
			$responsePack = $xpath->query('/rsp:responsePack')->item(0);

			if ($responsePack) {
				$state = $responsePack->getAttribute('state');
				$this->debug('Parsed response state: ' . $state);
				return $state;
			} else {
				$this->debug('Failed to find responsePack element in XML.');
				return '';
			}
		} catch (\Exception $e) {
			$this->debug('Failed to parse response XML: ' . $e->getMessage());
			return '';
		}
	}
}
