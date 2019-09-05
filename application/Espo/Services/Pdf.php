<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Services;

use dawood\phpChrome\Chrome;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\Error;

use Espo\ORM\Entity;

use \Espo\Core\Htmlizer\Htmlizer;
use Mpdf\Mpdf;

class Pdf extends \Espo\Core\Services\Base
{

    protected $fontFace = 'freesans';

    protected $fontSize = 12;

    protected $removeMassFilePeriod = '1 hour';

    protected function init()
    {
        $this->addDependency('fileManager');
        $this->addDependency('acl');
        $this->addDependency('metadata');
        $this->addDependency('serviceFactory');
        $this->addDependency('dateTime');
        $this->addDependency('number');
        $this->addDependency('entityManager');
        $this->addDependency('defaultLanguage');
        $this->addDependency('log');
    }

    protected function getAcl()
    {
        return $this->getInjection('acl');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getFileManager()
    {
        return $this->getInjection('fileManager');
    }

    protected function printEntity(Entity $entity, Entity $template, Htmlizer $htmlizer)
    {
        $htmlHeader = $htmlizer->render($entity, $template->get('header'));
        $htmlBody = $htmlizer->render($entity, $template->get('body'));

        $htmlFooter = '';
        if($template->get('printFooter'))
            $htmlFooter = $htmlizer->render($entity, $template->get('footer'));

        $this->getInjection('log')->addWarning('HTML', [$htmlHeader, $htmlBody, $htmlFooter]);
        return $htmlHeader.$htmlBody.$htmlFooter;
    }

    public function generateMailMerge($entityType, $entityList, Entity $template, $name, $campaignId = null)
    {
        $htmlizer = $this->createHtmlizer();
        $chrome = new Chrome(null, '/usr/bin/google-chrome');

        if ($this->getServiceFactory()->checkExists($entityType)) {
            $service = $this->getServiceFactory()->create($entityType);
        } else {
            $service = $this->getServiceFactory()->create('Record');
        }

        $additionalContent = '';

        foreach ($entityList as $entity) {
            $service->loadAdditionalFields($entity);
            if (method_exists($service, 'loadAdditionalFieldsForPdf')) {
                $service->loadAdditionalFieldsForPdf($entity);
            }
            //$pdf->startPageGroup();
            $additionalContent .= $this->printEntity($entity, $template, $htmlizer);
        }

        $filename = \Espo\Core\Utils\Util::sanitizeFileName($name) . '.pdf';

        $attachment = $this->getEntityManager()->getEntity('Attachment');

        //$content = $pdf->output('', 'S');
        $chrome->useHtml($this->printEntity($entity, $template, $htmlizer));
        $content = $chrome->getPdf();

        $attachment->set([
            'name' => $filename,
            'relatedType' => 'Campaign',
            'type' => 'application/pdf',
            'relatedId' => $campaignId,
            'role' => 'Mail Merge',
            'contents' => $content
        ]);

        $this->getEntityManager()->saveEntity($attachment);

        return $attachment->id;
    }

    public function massGenerate($entityType, $idList, $templateId, $checkAcl = false)
    {
        if ($this->getServiceFactory()->checkExists($entityType)) {
            $service = $this->getServiceFactory()->create($entityType);
        } else {
            $service = $this->getServiceFactory()->create('Record');
        }

        $maxCount = $this->getConfig()->get('massPrintPdfMaxCount');
        if ($maxCount) {
            if (count($idList) > $maxCount) {
                throw new Error("Mass print to PDF max count exceeded.");
            }
        }

        $template = $this->getEntityManager()->getEntity('Template', $templateId);

        if (!$template) {
            throw new NotFound();
        }

        if ($checkAcl) {
            if (!$this->getAcl()->check($template)) {
                throw new Forbidden();
            }
            if (!$this->getAcl()->checkScope($entityType)) {
                throw new Forbidden();
            }
        }

        $htmlizer = $this->createHtmlizer();
        $chrome = new Chrome(null, '/usr/bin/google-chrome');

        $entityList = $this->getEntityManager()->getRepository($entityType)->where([
            'id' => $idList
        ])->find();

        $additionalContent = '';
        foreach ($entityList as $entity) {
            if ($checkAcl) {
                if (!$this->getAcl()->check($entity)) continue;
            }
            $service->loadAdditionalFields($entity);
            if (method_exists($service, 'loadAdditionalFieldsForPdf')) {
                $service->loadAdditionalFieldsForPdf($entity);
            }
            //$pdf->startPageGroup();
            $additionalContent = $this->printEntity($entity, $template, $htmlizer);
        }

        $chrome->useHtml($this->printEntity($entity, $template, $htmlizer));
        $content = $chrome->getPdf();

        $entityTypeTranslated = $this->getInjection('defaultLanguage')->translate($entityType, 'scopeNamesPlural');
        $filename = \Espo\Core\Utils\Util::sanitizeFileName($entityTypeTranslated) . '.pdf';

        $attachment = $this->getEntityManager()->getEntity('Attachment');
        $attachment->set([
            'name' => $filename,
            'type' => 'application/pdf',
            'role' => 'Mass Pdf',
            'contents' => $content
        ]);
        $this->getEntityManager()->saveEntity($attachment);

        $job = $this->getEntityManager()->getEntity('Job');
        $job->set([
            'serviceName' => 'Pdf',
            'methodName' => 'removeMassFileJob',
            'data' => [
                'id' => $attachment->id
            ],
            'executeTime' => (new \DateTime())->modify('+' . $this->removeMassFilePeriod)->format('Y-m-d H:i:s'),
            'queue' => 'q1'
        ]);
        $this->getEntityManager()->saveEntity($job);

        return $attachment->id;
    }

    public function removeMassFileJob($data)
    {
        if (empty($data->id)) {
            return;
        }
        $attachment = $this->getEntityManager()->getEntity('Attachment', $data->id);
        if (!$attachment) return;
        if ($attachment->get('role') !== 'Mass Pdf') return;
        $this->getEntityManager()->removeEntity($attachment);
    }

    public function buildFromTemplate(Entity $entity, Entity $template, $displayInline = false)
    {
        $entityType = $entity->getEntityType();

        if ($this->getServiceFactory()->checkExists($entityType)) {
            $service = $this->getServiceFactory()->create($entityType);
        } else {
            $service = $this->getServiceFactory()->create('Record');
        }

        $service->loadAdditionalFields($entity);

        if (method_exists($service, 'loadAdditionalFieldsForPdf')) {
            $service->loadAdditionalFieldsForPdf($entity);
        }

        if ($template->get('entityType') !== $entityType) {
            throw new Forbidden();
        }

        if (!$this->getAcl()->check($entity, 'read') || !$this->getAcl()->check($template, 'read')) {
            throw new Forbidden();
        }

        try {
            $htmlizer = $this->createHtmlizer();
            $chrome = new Chrome(null, '/usr/bin/google-chrome');
            $chrome->setArgument('--no-sandbox', '');

            $chrome->useHtml('<meta charset="UTF-8">'.$this->printEntity($entity, $template, $htmlizer));

            if ($displayInline) {
                $name = $entity->get('name');
                $name = \Espo\Core\Utils\Util::sanitizeFileName($name);
                $fileName = $name . '.pdf';

                $this->getInjection('log')->warning($fileName);
                //$this->getInjection('log')->warning($this->getFileManager()->getContents($chrome->getPdf()));
                //$pdf->Output($fileName, 'I');
                $pdfFile = $this->getFileManager()->getContents($chrome->getPdf($fileName));
                header("Content-type: application/pdf; charset=UTF-8");
                print $pdfFile;
                return;
            }

            return $this->getFileManager()->getContents($chrome->getPdf());
        } catch (\Exception $ex) {
            $this->getInjection('log')->error($ex->getMessage());
            return '';
        }
    }

    protected function createHtmlizer()
    {
        return new Htmlizer(
            $this->getFileManager(),
            $this->getInjection('dateTime'),
            $this->getInjection('number'),
            $this->getAcl(),
            $this->getInjection('entityManager'),
            $this->getInjection('metadata'),
            $this->getInjection('defaultLanguage')
        );
    }
}
