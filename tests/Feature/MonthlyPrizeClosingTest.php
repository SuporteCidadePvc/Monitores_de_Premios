<?php

namespace Tests\Feature;

use App\Domain\Admin\AdminUser;
use App\Domain\Prizes\PrizeMonth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MonthlyPrizeClosingTest extends TestCase
{
    private AdminUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new AdminUser(11, 'administrador', 'Administração', Hash::make('uma-password-segura'), true);
        $this->adminUserRepository->add($this->user);
    }

    public function test_monthly_closing_is_available_only_to_administrators(): void
    {
        $this->get(route('admin.monthly-prize-closing.index'))
            ->assertRedirect(route('admin.login'));

        $this->post(route('admin.monthly-prize-closing.preview'), $this->validData())
            ->assertRedirect(route('admin.login'));
    }

    public function test_form_suggests_the_previous_month(): void
    {
        $this->actingAs($this->user, 'admin')
            ->get(route('admin.monthly-prize-closing.index'))
            ->assertOk()
            ->assertSee('Fechar prémio mensal')
            ->assertSee('value="2026-08"', false);
    }

    public function test_administrator_can_preview_the_server_side_calculation(): void
    {
        $this->productionRepository->squares = 2990.0;

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.preview'), $this->validData())
            ->assertOk()
            ->assertSee('data-monthly-prize-preview', false)
            ->assertSee('2.990 Quadros')
            ->assertSee('premio-producao-v1')
            ->assertSee('2.700,00 €')
            ->assertSee('90,00 €');

        $this->assertCount(1, $this->productionRepository->calls);
        $call = $this->productionRepository->calls[0];
        $this->assertSame('pvc', $call['sector']);
        $this->assertSame('2026-08-01 00:00:00', $call['range']->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 00:00:00', $call['range']->endExclusive->format('Y-m-d H:i:s'));
        $this->assertNull($this->monthlyPrizeRepository->lastAdded);
    }

    public function test_confirmation_recalculates_and_records_the_monthly_prize(): void
    {
        $this->productionRepository->squares = 2990.0;

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.store'), $this->validData(['confirm' => '1']))
            ->assertRedirect(route('admin.monthly-prize-history.index', [
                'sector' => 'pvc',
                'from' => '2026-08',
                'to' => '2026-08',
            ]))
            ->assertSessionHas('status');

        $saved = $this->monthlyPrizeRepository->find('pvc', new PrizeMonth(2026, 8));
        $this->assertNotNull($saved);
        $this->assertSame(2990.0, $saved->squares);
        $this->assertSame(270000, $saved->poolCents);
        $this->assertSame(9000, $saved->averagePrizeCents);
        $this->assertSame($this->user, $this->monthlyPrizeRepository->lastActor);
    }

    #[DataProvider('invalidClosedPeriods')]
    public function test_current_and_future_months_are_refused(string $month): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.preview'), $this->validData(['month' => $month]))
            ->assertRedirect(route('admin.monthly-prize-closing.index'))
            ->assertSessionHasErrors('month');

        $this->assertSame([], $this->productionRepository->calls);
    }

    public static function invalidClosedPeriods(): array
    {
        return [
            'current month' => ['2026-09'],
            'future month' => ['2026-10'],
        ];
    }

    #[DataProvider('invalidHours')]
    public function test_invalid_hours_are_refused(string $total, string $average, string $field): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.preview'), $this->validData([
                'total_hours' => $total,
                'average_hours' => $average,
            ]))
            ->assertSessionHasErrors($field);

        $this->assertNull($this->monthlyPrizeRepository->lastAdded);
    }

    public static function invalidHours(): array
    {
        return [
            'zero total' => ['0', '0', 'hours'],
            'negative total' => ['-1', '0', 'total_hours'],
            'more than two decimals' => ['4800.001', '160', 'total_hours'],
            'average above total' => ['100', '100.01', 'hours'],
        ];
    }

    public function test_duplicate_is_reported_without_a_server_error(): void
    {
        $data = $this->validData(['confirm' => '1']);

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.store'), $data)
            ->assertRedirect();

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.store'), $data)
            ->assertRedirect(route('admin.monthly-prize-closing.index'))
            ->assertSessionHasErrors('closing');
    }

    public function test_explicit_confirmation_is_required_before_recording(): void
    {
        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.store'), $this->validData())
            ->assertSessionHasErrors('confirm');

        $this->assertNull($this->monthlyPrizeRepository->lastAdded);
    }

    public function test_unavailable_database_rules_are_refused_without_fallback(): void
    {
        $this->prizeRuleProvider->shouldFail = true;

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.preview'), $this->validData())
            ->assertRedirect(route('admin.monthly-prize-closing.index'))
            ->assertSessionHasErrors('closing');

        $this->assertNull($this->monthlyPrizeRepository->lastAdded);
    }

    public function test_unavailable_production_is_refused(): void
    {
        $this->productionRepository->shouldFail = true;

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.preview'), $this->validData())
            ->assertRedirect(route('admin.monthly-prize-closing.index'))
            ->assertSessionHasErrors('closing');

        $this->assertNull($this->monthlyPrizeRepository->lastAdded);
    }

    public function test_confirmation_ignores_tampered_calculated_values_and_recalculates(): void
    {
        $this->productionRepository->squares = 2300.0;

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.preview'), $this->validData())
            ->assertOk()
            ->assertSee('800,00 €');

        $this->productionRepository->squares = 3680.0;

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.monthly-prize-closing.store'), $this->validData([
                'confirm' => '1',
                'production_squares' => '999999',
                'prize_pool_cents' => '1',
                'average_prize_cents' => '1',
                'rule_version' => 'adulterada',
            ]))
            ->assertRedirect();

        $saved = $this->monthlyPrizeRepository->lastAdded;
        $this->assertNotNull($saved);
        $this->assertSame(3680.0, $saved->squares);
        $this->assertSame(800000, $saved->poolCents);
        $this->assertSame('premio-producao-v1', $saved->ruleVersion);
    }

    public function test_closing_forms_and_routes_use_csrf_protection(): void
    {
        $this->actingAs($this->user, 'admin')
            ->get(route('admin.monthly-prize-closing.index'))
            ->assertOk()
            ->assertSee('name="_token"', false);

        $this->assertContains('web', Route::getRoutes()->getByName('admin.monthly-prize-closing.preview')->gatherMiddleware());
        $this->assertContains('web', Route::getRoutes()->getByName('admin.monthly-prize-closing.store')->gatherMiddleware());
    }

    /** @param array<string, string> $overrides */
    private function validData(array $overrides = []): array
    {
        return $overrides + [
            'sector' => 'pvc',
            'month' => '2026-08',
            'total_hours' => '4800,00',
            'average_hours' => '160,00',
        ];
    }
}
