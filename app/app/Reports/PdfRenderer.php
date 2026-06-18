<?php

namespace App\Reports;

/**
 * Convierte HTML en bytes PDF. Abstrae el motor (Browsershot en producción) para
 * que el controlador no dependa de Chromium y los tests puedan usar un fake.
 */
interface PdfRenderer
{
    public function render(string $html): string;
}
