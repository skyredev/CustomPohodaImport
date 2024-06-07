<?php

namespace Espo\Modules\PohodaImport\Tools\Pohoda;

use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\Core\ORM\Entity;

class Pohoda {
	private string $username = 'APERTIA';
	private string $password = '12345';
	private string $url = 'http://95.168.223.178:4444/xml';
	const DEBUG_PREFIX = '[Espo\Modules\PohodaImport\Tools\Pohoda]';

	public function __construct(
		private EntityManager $entityManager,
		private Log $log,
	) {}
	private function debug($message, array $context = []): void
	{
		$this->log->debug(self::DEBUG_PREFIX . $message, $context);
	}

	public function processEntity(string $entityType, $generateXmlForEntity): void
	{
		$entityIds = $this->getIdsToSync($entityType);

		$this->debug(' '.$entityType.' to sync count: ' . count($entityIds));

		foreach ($entityIds as $entityId) {
			try {
				$entity = $this->getEntityToSync($entityId?->id, $entityType);

				if (!$entity) {
					continue;
				}
				if ($entity->get('processed')) {
					$this->debug($entityType.' already processed');
					continue;
				}

				$this->debug('Trying to sync received invoice "' . $entity->get('name') . '"');

				$xmlData = $generateXmlForEntity($entity);

				$this->sendXmlToPohoda($xmlData);

				$entity->set('processed', true);
				$this->entityManager->saveEntity($entity);

			} catch (\Exception $exception) {
				$this->debug('Failed to sync received invoice error msg: ' . $exception->getMessage());
			}
		}
	}

	public function getIdsToSync(string $entityType): array
	{
		return $this->entityManager
			->getRDBRepository($entityType)
			->select(['id'])
			->where(
				Cond::Equal(Cond::column('processed'), false))
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
			$itemName = htmlspecialchars(substr($item->get('name'), 0, 90));
			$unitPrice = htmlspecialchars($item->get('unitPrice'));
			$unit = htmlspecialchars($item->get('unit'));
			$quantity = htmlspecialchars($item->get('quantity'));
			$discount = htmlspecialchars($item->get('discount'));
			$taxRate = htmlspecialchars($item->get('taxRate'));
			$withTax = htmlspecialchars($item->get('withTax'));
			$unitPriceCurrency = htmlspecialchars($item->get('unitPriceCurrency'));

			$withTax = $withTax ? 'true' : 'false';

			if($taxRate == 21) {
				$rateVAT = 'high';
			}
			elseif($taxRate == 12){
				$rateVAT = 'low';
			}
			else{
				$rateVAT = 'none';
			}


			$invoiceItems .= '
            <inv:invoiceItem>
					<inv:text>' . $itemName . '</inv:text>
					<inv:quantity>' . $quantity . '</inv:quantity>
					<inv:payVAT>' . $withTax .'</inv:payVAT>
					<inv:rateVAT>' . $rateVAT . '</inv:rateVAT>
					<inv:unit>' . $unit . '</inv:unit>
					<inv:discountPercentage>' . $discount . '</inv:discountPercentage>
					<inv:homeCurrency>
						<typ:unitPrice>' . $unitPrice . '</typ:unitPrice>
					</inv:homeCurrency>
					<inv:note>' . $unitPriceCurrency . '</inv:note>
				</inv:invoiceItem>'
			;

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

		$options = [
			'http' => [
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => iconv('UTF-8', 'Windows-1250', $xmlData),
			],
		];

		$context = stream_context_create($options);
		$response = file_get_contents($this->url, false, $context);

		if ($response === false) {
			throw new \Exception("Failed to send XML to Pohoda. Error: " . error_get_last()['message'] );
		}

		$statusCode = $this->getStatusCodeFromResponse($http_response_header);

		if ($statusCode !== 200) {
			throw new \Exception("Failed to send XML to Pohoda. Status code: {$statusCode}, Response: {$response}");
		}
	}

	private function getStatusCodeFromResponse(array $responseHeaders): int
	{
		$statusLine = $responseHeaders[0] ?? '';
		preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches);

		return (int) ($matches[1] ?? 0);
	}



}
