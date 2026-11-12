<?php

namespace Tests\Feature;

use App\Domain\Admin\AdminUser;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminAuthenticationTest extends TestCase
{
    public function test_admin_dashboard_requires_an_authenticated_administrator(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('admin.login'));
    }

    public function test_login_form_contains_csrf_protection(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('assets/images/favicon-01.png', false)
            ->assertSee('name="_token"', false);
    }

    public function test_valid_credentials_start_an_admin_session_and_record_login(): void
    {
        $this->adminUserRepository->add(new AdminUser(
            id: 7,
            username: 'administrador',
            displayName: 'Administração',
            passwordHash: Hash::make('uma-password-segura'),
            isActive: true,
        ));

        $this->post(route('admin.login.store'), [
            'username' => 'administrador',
            'password' => 'uma-password-segura',
        ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated('admin');
        $this->assertSame(1, $this->adminUserRepository->recordedLogins);
    }

    public function test_invalid_credentials_do_not_start_a_session(): void
    {
        $this->post(route('admin.login.store'), [
            'username' => 'administrador',
            'password' => 'password-incorreta',
        ])
            ->assertSessionHasErrors('username')
            ->assertRedirect();

        $this->assertGuest('admin');
    }

    public function test_logout_requires_post_and_ends_the_admin_session(): void
    {
        $user = new AdminUser(
            id: 7,
            username: 'administrador',
            displayName: 'Administração',
            passwordHash: Hash::make('uma-password-segura'),
            isActive: true,
        );
        $this->adminUserRepository->add($user);

        $this->actingAs($user, 'admin')
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }
}
