<?php

namespace Espo\Modules\PohodaImport\Classes\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\Modules\PohodaImport\Tools\Pohoda\Pohoda;
use Espo\Modules\PohodaImport\Classes\Generators\ProformaInvoice as ProformaInvoiceGenerator;

class PohodaSyncProformaInvoices implements JobDataLess
{
    const DEBUG_PREFIX = '[POHODA SYNC PROFORMA INVOICES] ';

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
        private Pohoda $pohoda,
        private ProformaInvoiceGenerator $proformaInvoiceGenerator
    ) {
    }

    public function run(): void
    {
        $this->pohoda->processEntity('ProformaInvoice', [$this->proformaInvoiceGenerator, 'generateXml'], 'number');
    }
}
