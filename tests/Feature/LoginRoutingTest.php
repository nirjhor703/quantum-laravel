<?php

namespace Tests\Feature;

use App\Models\Login_User;
use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('mysql');
        Artisan::call('migrate:fresh', ['--database' => 'mysql']);
    }

    public function test_login_page_posts_to_the_api_login_route_when_api_url_has_no_api_suffix(): void
    {
        config()->set('app.api_url', 'http://127.0.0.1:8001');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('/api/login', false)
            ->assertDontSee('http://127.0.0.1:8001/login', false);
    }

    public function test_valid_login_user_can_authenticate_through_the_api_login_route(): void
    {
        $role = Role::create(['name' => 'Super Admin']);
        Login_User::create([
            'user_id' => 'SA000000001',
            'name' => 'Test Admin',
            'email' => 'admin@example.test',
            'role' => $role->id,
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson(route('api.login'), [
            'email' => 'admin@example.test',
            'password' => 'secret-password',
        ])->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['token']);

        $this->assertAuthenticated();
    }
}
