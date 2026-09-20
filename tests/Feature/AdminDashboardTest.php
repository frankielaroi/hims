<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->receptionistRole = Role::create(['name' => 'Receptionist', 'slug' => 'receptionist']);
    }

    public function test_admin_can_access_admin_dashboard()
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach($this->adminRole);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Your team');
    }

    public function test_non_admin_cannot_access_admin_dashboard()
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($this->receptionistRole);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_is_redirected_to_login()
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/login');
    }
}
