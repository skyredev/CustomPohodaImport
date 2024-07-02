<?php

namespace Espo\Modules\PohodaImport\Classes\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\Modules\PohodaImport\Tools\Pohoda\Pohoda;
use Espo\Modules\PohodaImport\Classes\Generators\SupplierInvoice as SupplierInvoiceGenerator;

class PohodaSyncSupplierInvoices implements JobDataLess
{
    const DEBUG_PREFIX = '[POHODA SYNC SUPPLIER INVOICES] ';

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
        private Pohoda $pohoda,
        private SupplierInvoiceGenerator $supplierInvoiceGenerator
    ) {
    }

    public function run(): void
    {
        $this->pohoda->processEntity('SupplierInvoice', [$this->supplierInvoiceGenerator, 'generateXml'], 'number');
    }
}
