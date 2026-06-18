<?php

namespace App\Http\Controllers;

use App\Reports\ExecutiveReport;
use App\Reports\PdfRenderer;
use App\Tenancy\TenantManager;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    /**
     * Descarga el reporte ejecutivo del tenant activo en PDF. La vista Blade se
     * renderiza a HTML y el PdfRenderer (Browsershot) la convierte a PDF.
     */
    public function executive(TenantManager $tenants, PdfRenderer $renderer): Response
    {
        $report = ExecutiveReport::for($tenants->current());

        $html = view('reports.executive', ['report' => $report->data()])->render();
        $pdf = $renderer->render($html);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$report->filename().'"',
        ]);
    }
}
