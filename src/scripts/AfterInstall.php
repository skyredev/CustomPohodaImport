<?php

use Espo\Core\{
    Container,
    DataManager,
    Utils\Log
};
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Selection;
use Psr\Container\NotFoundExceptionInterface;

class AfterInstall
{
    private const TAB_LIST_ENTITIES = [];
    private const DEFAULT_CONFIG = [];
    private const DEFAULT_RECORDS = [];

    private Config $config;
    private ConfigWriter $configWriter;
    private DataManager $dataManager;
    private EntityManager $entityManager;
    private Log $log;

    public function run(Container $container, array $params = []): void
    {
        $this->loadDependencies($container);

        if (empty($params['isUpgrade'])) {
            $this->addEntitiesToTabList();
        }

        $this->defaultConfig();

        $this->clearCache();
    }

    private function loadDependencies(Container $container): void
    {
        try {
            $injectableFactory = $container->getByClass(InjectableFactory::class);

            $this->config = $container->getByClass(Config::class);
            $this->configWriter = $injectableFactory->create(ConfigWriter::class);
            $this->dataManager = $container->getByClass(DataManager::class);
            $this->entityManager = $container->getByClass(EntityManager::class);
            $this->log = $container->getByClass(Log::class);
        } catch (NotFoundExceptionInterface $e) {
            throw new LogicException('Dependency not found', 0, $e);
        }
    }

    private function defaultConfig(): void
    {
        foreach (self::DEFAULT_CONFIG as $key => $value) {
            if (!$this->config->has($key)) {
                $this->configWriter->set($key, $value);
            }
        }

        $this->configWriter->save();
    }

    private function addEntitiesToTabList(): void
    {
        $tabList = $this->config->get('tabList') ?? [];

        foreach (self::TAB_LIST_ENTITIES as $entity) {
            if (!in_array($entity, $tabList, true)) {
                $tabList[] = $entity;
            }
        }

        $this->configWriter->set('tabList', $tabList);
        $this->configWriter->save();
    }

    private function createDefaultRecords(): void
    {
        foreach (self::DEFAULT_RECORDS as $entityName => $recordList) {
            foreach ($recordList as $data) {
                $this->createRecord($entityName, $data);
            }
        }
    }

    private function createRecord(string $entityType, array $data): void
    {
        $id = $data['id'] ?? null;

        if (empty($id)) {
            $this->log->warning('After Install: Record ID is empty');
            return;
        }

        $selectQuery = $this->entityManager->getQueryBuilder()
            ->select(Selection::fromString('id'))
            ->from($entityType)
            ->withDeleted()
            ->where(Cond::equal(Expr::column('id'), $id))
            ->build();

        $entity = $this->entityManager->getRDBRepository($entityType)
            ->clone($selectQuery)
            ->findOne();

        if ($entity) {
            return;
        }

        $this->entityManager->createEntity($entityType, $data);
    }


    private function clearCache(): void
    {
        try {
            $this->dataManager->clearCache();
        } catch (Exception) {
        }
    }
}
