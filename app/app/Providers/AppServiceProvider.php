<?php

namespace App\Providers;

use App\Reports\BrowsershotPdfRenderer;
use App\Reports\FakePdfRenderer;
use App\Reports\PdfRenderer;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Un único tenant activo por request/ciclo de vida del contenedor.
        $this->app->singleton(TenantManager::class);

        // Motor de PDF: Browsershot real, salvo en tests o donde no hay Chromium
        // (config services.browsershot.enabled), donde se usa el fake.
        $this->app->bind(PdfRenderer::class, function () {
            $useReal = config('services.browsershot.enabled') && ! $this->app->runningUnitTests();

            return $useReal ? new BrowsershotPdfRenderer : new FakePdfRenderer;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La app corre detrás de un port-mapping/proxy (Docker, VPS) que no
        // siempre preserva el puerto del Host. Hacemos a la app autoritativa
        // sobre su URL base usando APP_URL, así asset()/route() generan URLs
        // correctas independientemente del Host entrante.
        if ($appUrl = config('app.url')) {
            URL::forceRootUrl($appUrl);

            if (Str::startsWith($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
