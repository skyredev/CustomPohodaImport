<?php

namespace Espo\Modules\PohodaImport\Classes\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Integration;
use Espo\Modules\PohodaImport\Tools\Pohoda\Pohoda as PohodaTool;

class PohodySync implements JobDataLess
{
    public function __construct(
        private readonly PohodaTool $pohodaTool,
        private readonly EntityManager $entityManager
    ) {
    }

    public function run(): void
    {
        $settings = $this->entityManager->getEntityById(Integration::ENTITY_TYPE, 'PohodaImport');

        $entityTypeList = $settings->get('entityTypeList') ?? [];

        foreach ($entityTypeList as $entityType) {
            $this->pohodaTool->processEntity($entityType);
        }
    }
}
