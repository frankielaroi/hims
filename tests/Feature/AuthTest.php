<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
    }

    public function test_user_can_login_with_correct_credentials()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'test.user',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'username' => 'test.user',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_failure_increments_attempts_and_locks_account()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'test.user',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'username' => 'test.user',
                'password' => 'wrong-password',
            ]);
            $response->assertSessionHasErrors('username');
        }

        $user->refresh();
        $this->assertEquals(5, $user->login_attempts);
        $this->assertEquals('locked', $user->status);

        $response = $this->post('/login', [
            'username' => 'test.user',
            'password' => 'password',
        ]);
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_mandatory_password_change_redirect()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'test.user',
            'password' => Hash::make('password'),
            'status' => 'active',
            'requires_password_change' => true,
        ]);

        $this->post('/login', [
            'username' => 'test.user',
            'password' => 'password',
        ]);

        $response = $this->get('/dashboard');
        $response->assertRedirect('/password/change');
    }

    public function test_single_active_session_enforcement()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'test.user',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        // Mock a session in the database
        DB::table('sessions')->insert([
            'id' => 'session1',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Browser A',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->post('/login', [
            'username' => 'test.user',
            'password' => 'password',
        ]);

        $this->assertDatabaseMissing('sessions', ['id' => 'session1']);
    }

    public function test_user_can_change_password()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'test.user',
            'password' => Hash::make('password'),
            'status' => 'active',
            'requires_password_change' => true,
        ]);

        $response = $this->actingAs($user)->post('/password/change', [
            'current_password' => 'password',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $response->assertRedirect('/dashboard');
        $user->refresh();
        $this->assertTrue(Hash::check('NewPass123!', $user->password));
        $this->assertFalse($user->requires_password_change);
    }
}
