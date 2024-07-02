<?php

namespace Espo\Modules\PohodaImport\Classes\Generators;

use Espo\Modules\PohodaImport\Classes\XmlGeneration\Generator;
use Espo\ORM\Entity;
use Espo\Modules\PohodaImport\Tools\Pohoda\Pohoda;

class SupplierInvoice implements Generator
{
    public function __construct(
        private readonly Pohoda $pohoda
    ) {
    }

    public function generateXml(Entity $invoice): string
    {
        $number = htmlspecialchars($invoice->get('number'));
        $name = htmlspecialchars($invoice->get('name'));
        $symconst = htmlspecialchars($invoice->get('constantSymbol'));
        $symvar = htmlspecialchars($invoice->get('variableSymbol'));
        $dateInvoiced = htmlspecialchars($invoice->get('dateInvoiced'));
        $dueDate = htmlspecialchars($invoice->get('dateDue'));
        $orderNumber = htmlspecialchars($invoice->get('orderNumber'));
        $sicCode = htmlspecialchars($invoice->get('sicCode'));
        $vatId = htmlspecialchars($invoice->get('vatId'));
        $billingAddressCity = htmlspecialchars($invoice->get('billingAddressCity'));
        $billingAddressStreet = htmlspecialchars($invoice->get('billingAddressStreet'));
        $billingAddressPostalCode = htmlspecialchars($invoice->get('billingAddressPostalCode'));
        $company = htmlspecialchars($invoice->get('accountName'));

        $invoiceItems = $this->pohoda->getInvoiceItems($invoice, 'SupplierInvoiceItem', 'items');

        $dueDateXml = $dueDate ? "<inv:dateDue>{$dueDate}</inv:dateDue>" : '';

        $account = $invoice->get('account');
        if ($account) {
            if (!$sicCode) {
                $sicCode = htmlspecialchars($account->get('sicCode'));
            }
            if (!$vatId) {
                $vatId = htmlspecialchars($account->get('vatId'));
            }
            $email = htmlspecialchars($account->get('emailAddress'));
            $phone = htmlspecialchars($account->get('phoneNumber'));
        } else {
            $email = '';
            $phone = '';
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<inv:invoice version="2.0" xmlns:inv="http://www.stormware.cz/schema/version_2/invoice.xsd">
    <inv:invoiceHeader>
        <inv:invoiceType>receivedInvoice</inv:invoiceType>
        <inv:number>
            <typ:numberRequested>{$number}</typ:numberRequested>
        </inv:number>
        <inv:symVar>{$symvar}</inv:symVar>
        <inv:date>{$dateInvoiced}</inv:date>
        {$dueDateXml}
        <inv:text>{$name}</inv:text>
        <inv:partnerIdentity>
            <typ:address>
                <typ:company>{$company}</typ:company>
                <typ:street>{$billingAddressStreet}</typ:street>
                <typ:city>{$billingAddressCity}</typ:city>
                <typ:zip>{$billingAddressPostalCode}</typ:zip>
                <typ:ico>{$sicCode}</typ:ico>
                <typ:dic>{$vatId}</typ:dic>
                <typ:phone>{$phone}</typ:phone>
                <typ:email>{$email}</typ:email>
            </typ:address>
        </inv:partnerIdentity>
        <inv:numberOrder>{$orderNumber}</inv:numberOrder>
        <inv:symConst>{$symconst}</inv:symConst>
    </inv:invoiceHeader>
    <inv:invoiceDetail>
        {$invoiceItems}
    </inv:invoiceDetail>
</inv:invoice>
XML;
    }
}
