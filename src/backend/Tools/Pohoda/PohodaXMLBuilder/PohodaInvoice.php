<?php

namespace Espo\Modules\PohodaImport\Tools\Pohoda\PohodaXMLBuilder;

use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\Core\ORM\Entity;
use Espo\ORM\Query\Part\Condition as Cond;

class PohodaInvoice {
    const DEBUG_PREFIX = '[' . self::class . ']';

    protected ?string $invoiceType = null;
    protected ?string $symVar = null;

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
    ) {}
    private function debug($message, array $context = []): void
    {
        $this->log->debug(self::DEBUG_PREFIX . $message, $context);
    }

    public function withInvoiceType(?string $invoiceType) :self
    {
        $this->invoiceType = $invoiceType;
        return $this;
    }

    public function withSymVar(?string $symVar) :self
    {
        $this->symVar = $symVar;
        return $this;
    }

    protected function getInvoiceStartSection()
    {
        return '<?xml version="1.0" encoding="Windows-1250"?>
                <dat:dataPack version="2.0" id="Usr01" ico="27117758" key="033efc8c-513a-4639-92fc-be4e75668d07" programVersion="13607.12 (14.3.2024)" application="Transformace" note="AutoCRM Import" xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd">
                    <dat:dataPackItem version="2.0" id="Usr01 (001)">
                        <inv:invoice version="2.0" xmlns:inv="http://www.stormware.cz/schema/version_2/invoice.xsd">';
    }

    protected function getInvoiceEndSection()
    {
        return '<?xml version="1.0" encoding="Windows-1250"?>
                <dat:dataPack version="2.0" id="Usr01" ico="27117758" key="033efc8c-513a-4639-92fc-be4e75668d07" programVersion="13607.12 (14.3.2024)" application="Transformace" note="AutoCRM Import" xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd">
                    <dat:dataPackItem version="2.0" id="Usr01 (001)">
                        <inv:invoice version="2.0" xmlns:inv="http://www.stormware.cz/schema/version_2/invoice.xsd">';
    }

    protected function getInvoiceStartHeader() : string
    {
        return '<inv:invoiceHeader xmlns:rsp="http://www.stormware.cz/schema/version_2/response.xsd" xmlns:rdc="http://www.stormware.cz/schema/version_2/documentresponse.xsd" xmlns:typ="http://www.stormware.cz/schema/version_2/type.xsd" xmlns:lst="http://www.stormware.cz/schema/version_2/list.xsd" xmlns:lStk="http://www.stormware.cz/schema/version_2/list_stock.xsd" xmlns:lAdb="http://www.stormware.cz/schema/version_2/list_addBook.xsd" xmlns:lCen="http://www.stormware.cz/schema/version_2/list_centre.xsd" xmlns:lAcv="http://www.stormware.cz/schema/version_2/list_activity.xsd" xmlns:acu="http://www.stormware.cz/schema/version_2/accountingunit.xsd" xmlns:vch="http://www.stormware.cz/schema/version_2/voucher.xsd" xmlns:int="http://www.stormware.cz/schema/version_2/intDoc.xsd" xmlns:stk="http://www.stormware.cz/schema/version_2/stock.xsd" xmlns:ord="http://www.stormware.cz/schema/version_2/order.xsd" xmlns:ofr="http://www.stormware.cz/schema/version_2/offer.xsd" xmlns:enq="http://www.stormware.cz/schema/version_2/enquiry.xsd" xmlns:vyd="http://www.stormware.cz/schema/version_2/vydejka.xsd" xmlns:pri="http://www.stormware.cz/schema/version_2/prijemka.xsd" xmlns:bal="http://www.stormware.cz/schema/version_2/balance.xsd" xmlns:pre="http://www.stormware.cz/schema/version_2/prevodka.xsd" xmlns:vyr="http://www.stormware.cz/schema/version_2/vyroba.xsd" xmlns:pro="http://www.stormware.cz/schema/version_2/prodejka.xsd" xmlns:con="http://www.stormware.cz/schema/version_2/contract.xsd" xmlns:adb="http://www.stormware.cz/schema/version_2/addressbook.xsd" xmlns:prm="http://www.stormware.cz/schema/version_2/parameter.xsd" xmlns:lCon="http://www.stormware.cz/schema/version_2/list_contract.xsd" xmlns:ctg="http://www.stormware.cz/schema/version_2/category.xsd" xmlns:ipm="http://www.stormware.cz/schema/version_2/intParam.xsd" xmlns:str="http://www.stormware.cz/schema/version_2/storage.xsd" xmlns:idp="http://www.stormware.cz/schema/version_2/individualPrice.xsd" xmlns:sup="http://www.stormware.cz/schema/version_2/supplier.xsd" xmlns:prn="http://www.stormware.cz/schema/version_2/print.xsd" xmlns:lck="http://www.stormware.cz/schema/version_2/lock.xsd" xmlns:isd="http://www.stormware.cz/schema/version_2/isdoc.xsd" xmlns:sEET="http://www.stormware.cz/schema/version_2/sendEET.xsd" xmlns:act="http://www.stormware.cz/schema/version_2/accountancy.xsd" xmlns:bnk="http://www.stormware.cz/schema/version_2/bank.xsd" xmlns:sto="http://www.stormware.cz/schema/version_2/store.xsd" xmlns:grs="http://www.stormware.cz/schema/version_2/groupStocks.xsd" xmlns:acp="http://www.stormware.cz/schema/version_2/actionPrice.xsd" xmlns:csh="http://www.stormware.cz/schema/version_2/cashRegister.xsd" xmlns:bka="http://www.stormware.cz/schema/version_2/bankAccount.xsd" xmlns:ilt="http://www.stormware.cz/schema/version_2/inventoryLists.xsd" xmlns:nms="http://www.stormware.cz/schema/version_2/numericalSeries.xsd" xmlns:pay="http://www.stormware.cz/schema/version_2/payment.xsd" xmlns:mKasa="http://www.stormware.cz/schema/version_2/mKasa.xsd" xmlns:gdp="http://www.stormware.cz/schema/version_2/GDPR.xsd" xmlns:est="http://www.stormware.cz/schema/version_2/establishment.xsd" xmlns:cen="http://www.stormware.cz/schema/version_2/centre.xsd" xmlns:acv="http://www.stormware.cz/schema/version_2/activity.xsd" xmlns:afp="http://www.stormware.cz/schema/version_2/accountingFormOfPayment.xsd" xmlns:vat="http://www.stormware.cz/schema/version_2/classificationVAT.xsd" xmlns:rgn="http://www.stormware.cz/schema/version_2/registrationNumber.xsd" xmlns:ftr="http://www.stormware.cz/schema/version_2/filter.xsd" xmlns:asv="http://www.stormware.cz/schema/version_2/accountingSalesVouchers.xsd" xmlns:arch="http://www.stormware.cz/schema/version_2/archive.xsd" xmlns:req="http://www.stormware.cz/schema/version_2/productRequirement.xsd" xmlns:mov="http://www.stormware.cz/schema/version_2/movement.xsd" xmlns:rec="http://www.stormware.cz/schema/version_2/recyclingContrib.xsd" xmlns:srv="http://www.stormware.cz/schema/version_2/service.xsd" xmlns:rul="http://www.stormware.cz/schema/version_2/rulesPairing.xsd" xmlns:lwl="http://www.stormware.cz/schema/version_2/liquidationWithoutLink.xsd" xmlns:dis="http://www.stormware.cz/schema/version_2/discount.xsd" xmlns:lqd="http://www.stormware.cz/schema/version_2/automaticLiquidation.xsd">';
    }

    protected function getInvoiceEndHeader() : string
    {
        return '</inv:invoiceHeader>';
    }

    public function generateXML() : string
    {
        $xml = '';

        $xml .= $this->getInvoiceStartSection();




        return $xml;
    }
}
