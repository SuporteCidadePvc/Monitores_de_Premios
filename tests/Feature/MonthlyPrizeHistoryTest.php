<?php

namespace Tests\Feature;

use App\Domain\Admin\AdminUser;
use App\Domain\Admin\MonthlyPrizeHistoryPage;
use App\Domain\Admin\MonthlyPrizeHistoryRecord;
use App\Domain\Admin\MonthlyPrizeHistorySummary;
use App\Domain\Prizes\PrizeMonth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class MonthlyPrizeHistoryTest extends TestCase
{
    private AdminUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new AdminUser(11, 'administrador', 'Administração', Hash::make('uma-password-segura'), true);
        $this->adminUserRepository->add($this->user);
    }

    public function test_history_is_available_only_to_administrators(): void
    {
        $this->get(route('admin.monthly-prize-history.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_administrator_can_open_the_history_filters(): void
    {
        $this->actingAs($this->user, 'admin')
            ->get(route('admin.monthly-prize-history.index'))
            ->assertOk()
            ->assertSee('Histórico de prémios')
            ->assertSee('Todos os setores')
            ->assertSee('Consultar histórico')
            ->assertSee('data-monthly-prize-history-empty', false)
            ->assertSee('dashboard-topbar__admin-link', false)
            ->assertSee(route('admin.dashboard'), false);

        $this->assertNull($this->monthlyPrizeHistoryRepository->lastQuery->sectorKey);
        $this->assertSame(1, $this->monthlyPrizeHistoryRepository->lastQuery->page);
    }

    public function test_administrator_filters_history_by_sector_period_and_page(): void
    {
        $this->actingAs($this->user, 'admin')
            ->get(route('admin.monthly-prize-history.index', [
                'sector' => 'aluminio',
                'from' => '2025-11',
                'to' => '2026-02',
                'page' => 3,
            ]))
            ->assertOk();

        $query = $this->monthlyPrizeHistoryRepository->lastQuery;

        $this->assertSame('aluminio', $query->sectorKey);
        $this->assertSame(202511, $query->fromPeriodNumber());
        $this->assertSame(202602, $query->toPeriodNumber());
        $this->assertSame(3, $query->page);
    }

    public function test_administrator_cannot_submit_an_inverted_period_range(): void
    {
        $this->actingAs($this->user, 'admin')
            ->from(route('admin.monthly-prize-history.index'))
            ->get(route('admin.monthly-prize-history.index', [
                'from' => '2026-02',
                'to' => '2026-01',
            ]))
            ->assertRedirect(route('admin.monthly-prize-history.index'))
            ->assertSessionHasErrors('to');

        $this->assertNull($this->monthlyPrizeHistoryRepository->lastQuery);
    }

    public function test_history_data_failures_are_rendered_without_technical_details(): void
    {
        $this->monthlyPrizeHistoryRepository->shouldFail = true;

        $this->actingAs($this->user, 'admin')
            ->get(route('admin.monthly-prize-history.index'))
            ->assertOk()
            ->assertSee('data-monthly-prize-history-unavailable', false)
            ->assertSee('Histórico temporariamente indisponível')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('BD_PREF');
    }

    public function test_administrator_can_read_a_monthly_prize_history_record(): void
    {
        $this->monthlyPrizeHistoryRepository->page = new MonthlyPrizeHistoryPage([
            new MonthlyPrizeHistoryRecord(7, 'pvc', 2026, 8, 2990.5, 480000, 16000, 'premio-producao-v1', 270000, 9000, 'EUR', '2026-09-01 10:30:00'),
        ], 1, 1, 12);

        $this->actingAs($this->user, 'admin')
            ->get(route('admin.monthly-prize-history.index'))
            ->assertOk()
            ->assertSee('1 registo encontrado.')
            ->assertSee('PVC')
            ->assertSee('Agosto de 2026')
            ->assertSee('2.990,50')
            ->assertSee('2.700,00 €')
            ->assertSee('90,00 €')
            ->assertSee('premio-producao-v1')
            ->assertDontSee('data-monthly-prize-history-empty', false);
    }

    public function test_history_pagination_preserves_the_applied_filters(): void
    {
        $this->monthlyPrizeHistoryRepository->page = new MonthlyPrizeHistoryPage([
            new MonthlyPrizeHistoryRecord(7, 'pvc', 2026, 8, 2990.5, 480000, 16000, 'premio-producao-v1', 270000, 9000, 'EUR', '2026-09-01 10:30:00'),
        ], 25, 2, 12);

        $this->actingAs($this->user, 'admin')
            ->get(route('admin.monthly-prize-history.index', [
                'sector' => 'pvc',
                'from' => '2025-01',
                'to' => '2026-08',
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSee('A mostrar 13–24 de 25')
            ->assertSee('Página 2 de 3')
            ->assertSee('sector=pvc', false)
            ->assertSee('from=2025-01', false)
            ->assertSee('to=2026-08', false)
            ->assertSee('page=1', false)
            ->assertSee('page=3', false);
    }

    public function test_administrator_can_download_all_filtered_history_records_as_xlsx(): void
    {
        $this->monthlyPrizeHistoryRepository->exportRecords = [
            new MonthlyPrizeHistoryRecord(7, 'pvc', 2026, 8, 2990.5, 480000, 16000, 'premio-producao-v1', 270000, 9000, 'EUR', '2026-09-01 10:30:00'),
        ];

        $response = $this->actingAs($this->user, 'admin')
            ->get(route('admin.monthly-prize-history.export', [
                'sector' => 'pvc',
                'from' => '2025-01',
                'to' => '2026-08',
            ]));

        $response->assertDownload('historico-premios-pvc-2025-01-a-2026-08.xlsx');

        $query = $this->monthlyPrizeHistoryRepository->lastQuery;
        $this->assertSame('pvc', $query->sectorKey);
        $this->assertSame(202501, $query->fromPeriodNumber());
        $this->assertSame(202608, $query->toPeriodNumber());

        $path = $response->baseResponse->getFile()->getPathname();
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function test_history_export_failure_returns_to_the_filtered_history_page(): void
    {
        $this->monthlyPrizeHistoryRepository->shouldFail = true;

        $this->actingAs($this->user, 'admin')
            ->get(route('admin.monthly-prize-history.export', ['sector' => 'pvc']))
            ->assertRedirect(route('admin.monthly-prize-history.index', ['sector' => 'pvc']))
            ->assertSessionHasErrors('history_export');
    }

    public function test_history_displays_per_sector_performance_indicators_and_tied_best_months(): void
    {
        $this->monthlyPrizeHistoryRepository->summaries = [
            new MonthlyPrizeHistorySummary(
                sectorKey: 'pvc',
                recordCount: 3,
                averageProductionSquares: 166.666,
                maximumProductionSquares: 200.0,
                bestProductionPeriods: [new PrizeMonth(2026, 2), new PrizeMonth(2026, 3)],
                maximumAveragePrizeCents: 3000,
                bestAveragePrizePeriods: [new PrizeMonth(2026, 3)],
            ),
            new MonthlyPrizeHistorySummary(
                sectorKey: 'aluminio',
                recordCount: 1,
                averageProductionSquares: 80.0,
                maximumProductionSquares: 80.0,
                bestProductionPeriods: [new PrizeMonth(2026, 1)],
                maximumAveragePrizeCents: 4500,
                bestAveragePrizePeriods: [new PrizeMonth(2026, 1)],
            ),
        ];

        $this->actingAs($this->user, 'admin')
            ->get(route('admin.monthly-prize-history.index'))
            ->assertOk()
            ->assertSee('data-monthly-prize-history-summaries', false)
            ->assertSee('Indicadores de desempenho')
            ->assertSee('Produção média mensal')
            ->assertSee('166,67')
            ->assertSee('Fevereiro de 2026 · Março de 2026')
            ->assertSee('30,00 €')
            ->assertSee('ALUMINIO')
            ->assertSee('45,00 €')
            ->assertDontSee('data-monthly-prize-history-summary-empty', false);
    }
}
