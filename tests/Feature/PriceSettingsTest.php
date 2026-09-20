<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    }

    public function test_admin_can_view_price_settings()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole);

        $response = $this->actingAs($admin)->get('/admin/services');

        $response->assertStatus(200);
        $response->assertSee('Service Pricing');
    }

    public function test_admin_can_update_service_price()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->adminRole);

        $service = Service::create([
            'name' => 'Test Service',
            'category' => 'Test',
            'price' => 100.00,
        ]);

        $response = $this->actingAs($admin)->patch("/admin/services/{$service->id}", [
            'price' => 150.00,
        ]);

        $response->assertStatus(302);
        $this->assertEquals(150.00, $service->fresh()->price);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'price_updated',
            'user_id' => $admin->id,
        ]);
    }
}
