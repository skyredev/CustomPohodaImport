<?php

namespace Espo\Modules\PohodaImport\Classes\XmlGeneration;

use Espo\ORM\Entity;

interface Generator
{
    public function generateXml(Entity $entity): string;
}
