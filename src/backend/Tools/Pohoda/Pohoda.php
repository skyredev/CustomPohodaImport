<?php

namespace Espo\Modules\PohodaImport\Tools\Pohoda;

use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\Core\ORM\Entity;

class Pohoda
{
	private string $username = 'Admin';  //ALIS //APERTIA
	private string $password = 'apertia'; // ALIS // 12345
	private string $url = 'http://666.davidstrejc.cz:666/xml'; // ALIS // http://95.168.223.178:4444/xml
	private string $headerString = '<dat:dataPack version="2.0" id="Usr01" ico="27117758" key="033efc8c-513a-4639-92fc-be4e75668d07" programVersion="13607.12 (14.3.2024)" application="Transformace" note="CRM Import" xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd">';

	// HeaderString is different for each project, to get it go to Pohoda program and export any record, then copy the header from the exported XML file

	//AUTOCRM <dat:dataPack version="2.0" id="Usr01" ico="27117758" key="033efc8c-513a-4639-92fc-be4e75668d07" programVersion="13607.12 (14.3.2024)" application="Transformace" note="Uživatelský export" xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd">

	//ALIS <dat:dataPack version="2.0" id="Usr01" ico="11223344" key="521d4e05-f032-465e-8150-f423d1b98197" programVersion="13700.208 (30.5.2024)" application="Transformace" note="Uživatelský export" xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd">

	const DEBUG_PREFIX = '[Espo\Modules\PohodaImport\Tools\Pohoda]';

	private array $processedEntities = [];

	public function __construct(
		private EntityManager $entityManager,
		private Log $log,
	) {
	}
	private function debug($message, array $context = []): void
	{
		$this->log->debug(self::DEBUG_PREFIX . $message, $context);
	}

	public function processEntity(string $entityType, $generateXmlForEntity, $duplicityCheckFieldType = null): void
	{
		$entityIds = $this->getIdsToSync($entityType);

		$this->debug(' ' . $entityType . ' to sync count: ' . count($entityIds));

		foreach ($entityIds as $entityId) {
			try {
				$entity = $this->getEntityToSync($entityId?->id, $entityType);

				if (!$entity) {
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
			->getRDBRepository($entityType)
			->where(Cond::equal(Cond::column('id'), $id))
			->findOne();

		if (!$entity) {
			$this->debug("Entity type: {$entityType} with ID: {$id} not found");
		}

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


			$invoiceItems .= '
            <inv:invoiceItem>
					<inv:text>' . $itemName . '</inv:text>
					<inv:quantity>' . $quantity . '</inv:quantity>
					<inv:payVAT>' . $withTax . '</inv:payVAT>
					<inv:rateVAT>' . $rateVAT . '</inv:rateVAT>
					<inv:unit>' . $unit . '</inv:unit>
					<inv:discountPercentage>' . $discount . '</inv:discountPercentage>
					<inv:homeCurrency>
						<typ:unitPrice>' . $unitPrice . '</typ:unitPrice>
					</inv:homeCurrency>
					<inv:note>' . $unitPriceCurrency . '</inv:note>
				</inv:invoiceItem>';
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

		$xmlTemplate = trim('
    <?xml version="1.0" encoding="Windows-1250"?>
    ' . $this->headerString . '
        <dat:dataPackItem version="2.0" id="Usr01 (001)">
            ' . trim($xmlData) . '
        </dat:dataPackItem>
    </dat:dataPack>');

		$options = [
			'http' => [
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => iconv('UTF-8', 'Windows-1250', $xmlTemplate),
			],
		];

		$context = stream_context_create($options);
		$response = file_get_contents($this->url, false, $context);

		if ($response === false) {
			throw new \Exception("Failed to send XML to Pohoda. Error: " . error_get_last()['message']);
		}

		$responseHeaders = $http_response_header;
		foreach ($responseHeaders as $header) {
			if (stripos($header, 'Content-Encoding: gzip') !== false) {
				$response = gzdecode($response);
			} elseif (stripos($header, 'Content-Encoding: deflate') !== false) {
				$response = gzinflate($response);
			}
		}

		$statusCode = $this->getStatusCodeFromResponse($responseHeaders);

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
