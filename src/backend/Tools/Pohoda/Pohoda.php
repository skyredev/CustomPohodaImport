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
	private string $url;
	private string $username;
	private string $password;
	private string $receivedInvoiceEntity;
	private string $receivedInvoiceSymVar;
	private string $receivedInvoiceSymConst;
	private string $receivedInvoiceDate;

	const DEBUG_PREFIX = '[Espo\Modules\PohodaImport\Tools\Pohoda]';

	public function __construct(
		private EntityManager $entityManager,
		private Log $log,
	) {
		$integrationName = 'Pohoda';

		/** @var IntegrationEntity|null $entity */
		$entity = $this->entityManager
			->getEntityById(IntegrationEntity::ENTITY_TYPE, $integrationName);

		if ($entity === null) {
			throw new NotFound($integrationName . ' integration not found.');
		}

		if (!$entity->get('enabled')) {
			throw new Error($integrationName . ' integration is not enabled.');
		}

		$this->url = $entity->get('MserverURL') ?? throw new NotFound('"MserverURL" not found in ' . $integrationName . ' integration.');
		$this->username = $entity->get('MserverUsername') ?? throw new NotFound('"MserverUsername" not found in ' . $integrationName . ' integration.');
		$this->password = $entity->get('MserverPassword') ?? throw new NotFound('"MserverPassword" not found in ' . $integrationName . ' integration.');
		$this->receivedInvoiceEntity = $entity->get('receivedInvoiceEntity') ?? throw new NotFound('"receivedInvoiceEntity" not found in ' . $integrationName . ' integration.');
		$this->receivedInvoiceSymVar = $entity->get('receivedInvoiceSymVar') ?? throw new NotFound('"receivedInvoiceSymVar" not found in ' . $integrationName . ' integration.');
		$this->receivedInvoiceSymConst = $entity->get('receivedInvoiceSymConst') ?? throw new NotFound('"receivedInvoiceSymConst" not found in ' . $integrationName . ' integration.');
		$this->receivedInvoiceDate = $entity->get('receivedInvoiceDate') ?? throw new NotFound('"receivedInvoiceDate" not found in ' . $integrationName . ' integration.');
	}

	private function debug($message, array $context = []): void
	{
		$this->log->debug(self::DEBUG_PREFIX . $message, $context);
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
			throw new \Exception("Failed to send XML to Pohoda. Error: " . error_get_last()['message']);
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

	public function getReceivedInvoiceEntity(): string
	{
		return $this->receivedInvoiceEntity;
	}

	public function getReceivedInvoiceSymVar(): string
	{
		return $this->receivedInvoiceSymVar;
	}

	public function getReceivedInvoiceSymConst(): string
	{
		return $this->receivedInvoiceSymConst;
	}

	public function getReceivedInvoiceDate(): string
	{
		return $this->receivedInvoiceDate;
	}
}
