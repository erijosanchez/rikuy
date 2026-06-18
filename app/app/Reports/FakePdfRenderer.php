<?php

namespace App\Reports;

/**
 * Render de PDF sin Chromium, para tests y entornos sin navegador headless.
 * Produce un PDF mínimo válido (cabecera %PDF) que envuelve el HTML recibido,
 * suficiente para verificar la descarga sin depender de Browsershot.
 */
class FakePdfRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        return "%PDF-1.4\n% Rikuy fake PDF\n% bytes=".strlen($html)."\n%%EOF";
    }
}
