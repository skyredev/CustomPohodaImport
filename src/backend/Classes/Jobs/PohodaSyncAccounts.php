<?php

namespace Espo\Modules\PohodaImport\Classes\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\Modules\PohodaImport\Tools\Pohoda\Pohoda;
use Espo\Modules\PohodaImport\Classes\Generators\Account as AccountGenerator;

class PohodaSyncAccounts implements JobDataLess
{
    const DEBUG_PREFIX = '[POHODA SYNC ACCOUNTS] ';

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
        private Pohoda $pohoda,
        private AccountGenerator $accountGenerator
    ) {
    }

    public function run(): void
    {
        $this->pohoda->processEntity('Account', [$this->accountGenerator, 'generateXml']);
    }
}
