<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Tenancy\TenantManager;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(TenantManager $tenants): Response
    {
        $organization = $tenants->current();

        // Dataset usa BelongsToTenant: la consulta ya viene aislada al tenant.
        $datasets = Dataset::orderBy('name')->get();

        return Inertia::render('Dashboard', [
            'organization' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'is_demo' => $organization->is_demo,
            ],
            'datasets' => $datasets->map(fn (Dataset $dataset) => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'status' => $dataset->status,
                'rows' => $dataset->rows,
                'error' => $dataset->error,
            ]),
            'readOnly' => $tenants->isDemo(),
        ]);
    }
}
