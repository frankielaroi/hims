<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->staffRole = Role::create(['name' => 'Staff', 'slug' => 'staff']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($this->adminRole);
    }

    public function test_admin_can_create_new_staff()
    {
        $response = $this->actingAs($this->admin)->post('/admin/staff', [
            'name' => 'New Doctor',
            'username' => 'n.doctor',
            'email' => 'doctor@hims.gh',
            'password' => 'Password123!',
            'roles' => [$this->staffRole->id],
            'status' => 'active',
        ]);

        $response->assertRedirect('/admin/staff');
        $this->assertDatabaseHas('users', ['username' => 'n.doctor']);

        $newUser = User::where('username', 'n.doctor')->first();
        $this->assertTrue($newUser->requires_password_change);
        $this->assertTrue($newUser->roles->contains($this->staffRole));
    }

    public function test_non_admin_cannot_create_staff()
    {
        $nonAdmin = User::factory()->create();

        $response = $this->actingAs($nonAdmin)->post('/admin/staff', [
            'name' => 'Should Fail',
            'username' => 'fail.user',
            'password' => 'Password123!',
            'roles' => [$this->staffRole->id],
            'status' => 'active',
        ]);

        $response->assertStatus(403);
    }
}
