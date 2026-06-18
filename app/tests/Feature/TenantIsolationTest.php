<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_scope_filters_datasets_by_active_tenant(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Dataset::factory()->for($orgA)->create(['name' => 'Ventas A']);
        Dataset::factory()->for($orgB)->create(['name' => 'Ventas B']);

        $tenants = app(TenantManager::class);

        $tenants->set($orgA);
        $this->assertSame(['Ventas A'], Dataset::pluck('name')->all());

        $tenants->set($orgB);
        $this->assertSame(['Ventas B'], Dataset::pluck('name')->all());
    }

    public function test_new_dataset_inherits_active_tenant(): void
    {
        $org = Organization::factory()->create();
        app(TenantManager::class)->set($org);

        $dataset = Dataset::create(['name' => 'Sin org explícita', 'rows' => 10]);

        $this->assertSame($org->id, $dataset->organization_id);
    }

    public function test_user_only_sees_its_own_tenant_data_on_dashboard(): void
    {
        $orgA = Organization::factory()->create();
        $userA = User::factory()->for($orgA)->create();
        Dataset::factory()->for($orgA)->create(['name' => 'Dataset de A']);

        $orgB = Organization::factory()->create();
        Dataset::factory()->for($orgB)->create(['name' => 'Dataset de B']);

        $response = $this->actingAs($userA)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('organization.name', $orgA->name)
            ->has('datasets', 1)
            ->where('datasets.0.name', 'Dataset de A')
        );
    }

    public function test_demo_sandbox_is_visible_without_login(): void
    {
        $demo = Organization::factory()->demo()->create(['slug' => 'demo']);
        Dataset::factory()->for($demo)->create(['name' => 'Órdenes demo']);

        $response = $this->get('/demo');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('readOnly', true)
            ->where('organization.is_demo', true)
            ->has('datasets', 1)
            ->where('datasets.0.name', 'Órdenes demo')
        );
    }

    public function test_demo_tenant_rejects_write_requests(): void
    {
        Organization::factory()->demo()->create(['slug' => 'demo']);

        // Ruta de escritura efímera protegida igual que el sandbox.
        Route::post('/__demo_write', fn () => 'ok')->middleware(['web', 'tenant:demo']);

        $this->post('/__demo_write')->assertForbidden();
    }

    public function test_guest_cannot_reach_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
