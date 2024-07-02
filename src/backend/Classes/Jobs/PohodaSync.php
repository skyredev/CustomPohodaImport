<?php

namespace Espo\Modules\PohodaImport\Classes\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Modules\PohodaImport\Tools\Pohoda\Pohoda as PohodaTool;

class PohodySync implements JobDataLess
{
    public function __construct(
        private readonly PohodaTool $pohodaTool
    ) {
    }

    public function run(): void
    {
        $entityTypeList = [];

        foreach ($entityTypeList as $entityType) {
            $this->pohodaTool->processEntity($entityType);
        }
    }
}
