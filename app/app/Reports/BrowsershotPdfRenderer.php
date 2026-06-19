<?php

namespace App\Reports;

use Spatie\Browsershot\Browsershot;

/**
 * Render real con Browsershot (Puppeteer + Chromium headless). En el contenedor
 * se apunta a la ruta del Chromium del sistema vía config; en local/CI se usa el
 * FakePdfRenderer, así que esta clase nunca se carga en tests.
 */
class BrowsershotPdfRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        $shot = Browsershot::html($html)
            ->format('A4')
            ->showBackground()
            ->margins(10, 10, 14, 10)
            ->emulateMedia('print')
            // En contenedores el /dev/shm por defecto es pequeño y Chromium
            // crashea; estos flags lo hacen estable en headless/Docker.
            ->addChromiumArguments(['disable-dev-shm-usage', 'disable-gpu']);

        if ($chrome = config('services.browsershot.chrome_path')) {
            $shot->setChromePath($chrome);
        }
        if ($node = config('services.browsershot.node_binary')) {
            $shot->setNodeBinary($node);
        }
        if ($npm = config('services.browsershot.npm_binary')) {
            $shot->setNpmBinary($npm);
        }
        if (config('services.browsershot.no_sandbox')) {
            $shot->noSandbox();
        }

        return $shot->pdf();
    }
}
