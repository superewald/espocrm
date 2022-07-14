<?php

namespace Espo\Tools\Pdf\WkPdf;

use Espo\ORM\Collection;
use Espo\Tools\Pdf\CollectionPrinter;
use Espo\Tools\Pdf\Template;
use Espo\Tools\Pdf\Contents;
use Espo\Tools\Pdf\Data;
use Espo\Tools\Pdf\IdDataMap;
use Espo\Tools\Pdf\Params;

class WkPdfCollectionPrinter implements CollectionPrinter
{
    private WkEntityProcessor $entityProcessor;

    public function __construct(WkEntityProcessor $entityProcessor)
    {
        $this->entityProcessor = $entityProcessor;
    }

    public function print(Template $template, Collection $collection, Params $params, IdDataMap $dataMap): Contents
    {
        $pdf = new WkPdf();

        foreach($collection as $entity) {
            $data = $dataMap->get($entity->getId()) ?? Data::create();

            $this->entityProcessor->process($pdf, $template, $entity, $params, $data);
        }

        return new WkPdfContents($pdf);
    }
}