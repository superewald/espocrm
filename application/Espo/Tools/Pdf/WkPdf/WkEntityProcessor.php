<?php

namespace Espo\Tools\Pdf\WkPdf;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Htmlizer\TemplateRendererFactory;
use Espo\Core\Htmlizer\TemplateRenderer;

use Espo\ORM\Entity;

use Espo\Tools\Pdf\Template;
use Espo\Tools\Pdf\Data;
use Espo\Tools\Pdf\Params;

class WkEntityProcessor
{
    private TemplateRendererFactory $templateRendererFactory;

    public function __construct(TemplateRendererFactory $templateRendererFactory)
    {
        $this->templateRendererFactory = $templateRendererFactory;
    }

    public function process(WkPdf $pdf, Template $template, Entity $entity, Params $params, Data $data) : void
    {
        $renderer = $this->templateRendererFactory
            ->create()
            ->setApplyAcl($params->applyAcl())
            ->setEntity($entity)
            ->setData($data->getAdditionalTemplateData());
        

        $opts = [
            'orientation' => $template->getPageOrientation(),
            'margin-bottom' => $template->getBottomMargin(),
            'margin-top' => $template->getTopMargin(),
            'margin-left' => $template->getLeftMargin(),
            'margin-right' => $template->getRightMargin(),
            'title' => $template->getTitle(),
        ];

        if($template->getPageFormat() === 'Custom') {
            $opts['page-width'] = $template->getPageWidth();
            $opts['page-height'] = $template->getPageHeight();
        } else {
            $opts['page-size'] = $template->getPageFormat();
        }

        $pdf->setOptions($opts);

        $header = (string)tempnam(sys_get_temp_dir(), 'header-tpl');
        $footer = (string)tempnam(sys_get_temp_dir(), 'footer-tpl');
        
        if($template->hasHeader()) {
            file_put_contents($header.'.html', "<!DOCTYPE html><html>".$renderer->renderTemplate($template->getHeader())."</html>");
            $pdf->setOptions([
                'header-html' => $header.'.html'
            ]);
        }

        if($template->hasFooter()) {
            file_put_contents($footer.'.html', "<!DOCTYPE html><html>".$renderer->renderTemplate($template->getFooter())."</html>");
            $pdf->setOptions([
                'footer-html' => $footer.'.html'
            ]);
        }
        
        $htmlBody = $renderer->renderTemplate($template->getBody());
        $pdf->addPage($htmlBody);

        unlink($header);
        unlink($footer);
    }
}