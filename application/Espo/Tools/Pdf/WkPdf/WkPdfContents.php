<?php

namespace Espo\Tools\Pdf\WkPdf;

use mikehaertl\wkhtmlto\Pdf as WkPdf;
use Espo\Tools\Pdf\Contents;
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\Stream;

use RuntimeException;

class WkPdfContents implements Contents
{
    private $pdf;

    public function __construct(WkPdf $pdf)
    {
        $this->pdf = $pdf;
    }

    public function getStream(): StreamInterface
    {
        $resource = fopen('php://temp', 'r+');

        if ($resource === false) {
            throw new RuntimeException("Could not open temp.");
        }

        fwrite($resource, $this->getString());
        rewind($resource);

        return new Stream($resource);
    }

    public function getString(): string
    {
        return (string)$this->pdf->toString();
    }

    public function getLength(): int
    {
        return strlen($this->getString());
    }
}