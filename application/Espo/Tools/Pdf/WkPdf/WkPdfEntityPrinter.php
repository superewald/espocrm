<?php

namespace Espo\Tools\Pdf\WkPdf;

use Espo\ORM\Entity;
use Espo\Tools\Pdf\EntityPrinter;
use Espo\Tools\Pdf\Template;
use Espo\Tools\Pdf\Contents;
use Espo\Tools\Pdf\Params;
use Espo\Tools\Pdf\Data;

class WkPdfEntityPrinter implements EntityPrinter
{
    protected WkEntityProcessor $entityProcessor;

    public function __construct(WkEntityProcessor $entityProcessor)
    {
        $this->entityProcessor = $entityProcessor;
    }

    public function print(Template $template, Entity $entity, Params $params, Data $data) : Contents
    {
        $pdf = new WkPdf();

        $this->entityProcessor->process($pdf, $template, $entity, $params, $data);

        return new WkPdfContents($pdf);
    }
}