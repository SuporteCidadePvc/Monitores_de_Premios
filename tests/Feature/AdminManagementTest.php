<?php

namespace Tests\Feature;

use App\Domain\Admin\AdminUser;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminManagementTest extends TestCase
{
    private AdminUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new AdminUser(11, 'administrador', 'Administração', Hash::make('uma-password-segura'), true);
        $this->adminUserRepository->add($this->user);
    }

    public function test_dashboard_is_available_only_to_administrators_and_renders_management_sections(): void
    {
        $this->actingAs($this->user, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Escalões de prémio')
            ->assertSee('Comunicados')
            ->assertSee('Consultar histórico de prémios')
            ->assertSee(route('admin.monthly-prize-history.index'), false)
            ->assertSeeInOrder([
                'dashboard-topbar__actions',
                route('admin.monthly-prize-history.index'),
                route('admin.logout'),
            ], false)
            ->assertSee('assets/images/message-square.svg', false)
            ->assertDontSee('Histórico de administração');
    }

    public function test_prize_rule_table_renders_the_minimum_as_a_number_without_the_square_meter_unit(): void
    {
        $this->adminManagementRepository->data['rules'] = [[
            'sectorkey' => 'pvc',
            'minimumsquares' => 2300,
            'prizepoolcents' => 80000,
            'isactive' => false,
        ]];

        $this->actingAs($this->user, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('2.300')
            ->assertDontSee('2.300 m²');
    }

    public function test_active_prize_rule_renders_edit_and_removal_controls(): void
    {
        $this->adminManagementRepository->data['rules'] = [[
            'prizeruleid' => 12,
            'sectorkey' => 'pvc',
            'minimumsquares' => 2300,
            'prizepoolcents' => 80000,
            'isactive' => true,
        ]];

        $this->actingAs($this->user, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('rule-actions', false)
            ->assertSee('rule-actions--icon', false)
            ->assertSee('assets/images/settings-admin.svg', false)
            ->assertSee('aria-label="Gerir escalão"', false)
            ->assertDontSee('<summary>Gerir escalão</summary>', false)
            ->assertSee(route('admin.prize-rules.update', 12), false)
            ->assertSee('Guardar')
            ->assertSee(route('admin.prize-rules.delete', 12), false)
            ->assertSee('Confirmo a remoção definitiva');
    }

    public function test_administrator_can_create_a_prize_rule_with_decimal_euros_converted_to_cents(): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.prize-rules.store'), [
                'sector' => 'pvc',
                'minimum_squares' => 4000,
                'prize_pool' => '1250,50',
            ])
            ->assertSessionHas('status');

        $this->assertSame(['rule:pvc:4000:125050'], $this->adminManagementRepository->operations);
    }

    public function test_administrator_can_update_an_active_prize_rule(): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.prize-rules.update', 99), [
                'minimum_squares' => 4010,
                'prize_pool' => '1260,75',
            ])
            ->assertSessionHas('status');

        $this->assertSame(['rule:update:99:4010:126075'], $this->adminManagementRepository->operations);
    }

    public function test_announcement_creation_requires_a_valid_display_window_and_is_auditable_operation(): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.announcements.store'), [
                'scope' => 'global',
                'title' => 'Paragem programada',
                'body' => 'A manutenção começa amanhã.',
                'starts_at' => '2026-09-01T08:00',
                'ends_at' => '2026-09-01T12:00',
            ])
            ->assertSessionHas('status');

        $this->assertSame(['announcement:global:Paragem programada'], $this->adminManagementRepository->operations);
    }

    public function test_active_announcement_renders_edit_and_removal_controls(): void
    {
        $this->adminManagementRepository->data['announcements'] = [[
            'announcementid' => 12,
            'announcementscope' => 'global',
            'title' => 'Paragem programada',
            'body' => 'A manutenção começa amanhã.',
            'startsat' => '2026-09-01 08:00:00',
            'endsat' => null,
            'isactive' => true,
        ]];

        $this->actingAs($this->user, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('announcement-actions', false)
            ->assertSee('rule-actions--icon', false)
            ->assertSee('assets/images/settings-admin.svg', false)
            ->assertSee('aria-label="Editar ou remover comunicado"', false)
            ->assertSee(route('admin.announcements.update', 12), false)
            ->assertSee('Guardar')
            ->assertSee(route('admin.announcements.delete', 12), false)
            ->assertSee('Confirmo a remoção definitiva');
    }

    public function test_administrator_can_update_an_active_announcement(): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.announcements.update', 99), [
                'scope' => 'pvc',
                'title' => 'Novo título',
                'body' => 'Nova mensagem.',
                'starts_at' => '2026-09-01T08:00',
                'ends_at' => '2026-09-01T12:00',
            ])
            ->assertSessionHas('status');

        $this->assertSame(['announcement:update:99:pvc:Novo título'], $this->adminManagementRepository->operations);
    }

    public function test_deactivation_requires_explicit_server_side_confirmation(): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.prize-rules.deactivate', 99), [])
            ->assertSessionHasErrors('confirm');

        $this->assertSame([], $this->adminManagementRepository->operations);
    }

    public function test_removal_requires_explicit_server_side_confirmation(): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.prize-rules.delete', 99), [])
            ->assertSessionHasErrors('confirm_delete');

        $this->assertSame([], $this->adminManagementRepository->operations);
    }

    public function test_administrator_can_remove_an_unreferenced_prize_rule(): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.prize-rules.delete', 99), ['confirm_delete' => '1'])
            ->assertSessionHas('status');

        $this->assertSame(['rule:delete:99'], $this->adminManagementRepository->operations);
    }

    public function test_announcement_removal_requires_explicit_server_side_confirmation(): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.announcements.delete', 99), [])
            ->assertSessionHasErrors('confirm_delete');

        $this->assertSame([], $this->adminManagementRepository->operations);
    }

    public function test_administrator_can_remove_an_announcement(): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.announcements.delete', 99), ['confirm_delete' => '1'])
            ->assertSessionHas('status');

        $this->assertSame(['announcement:delete:99'], $this->adminManagementRepository->operations);
    }
}
