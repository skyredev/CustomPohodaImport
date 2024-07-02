<?php

namespace Espo\Modules\PohodaImport\Classes\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\Modules\PohodaImport\Tools\Pohoda\Pohoda;
use Espo\Modules\PohodaImport\Classes\Generators\IssuedInvoice as IssuedInvoiceGenerator;

class PohodaSyncIssuedInvoices implements JobDataLess
{
    const DEBUG_PREFIX = '[POHODA SYNC ISSUED INVOICES] ';

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
        private Pohoda $pohoda,
        private IssuedInvoiceGenerator $issuedInvoiceGenerator
    ) {
    }

    public function run(): void
    {
        $this->pohoda->processEntity('Invoice', [$this->issuedInvoiceGenerator, 'generateXml'], 'number');
    }
}
