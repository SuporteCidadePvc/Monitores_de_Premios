<?php

namespace Tests\Feature;

use App\Domain\Announcements\Announcement;
use App\Domain\Announcements\AnnouncementScope;
use App\Domain\Production\ProductionRepository;
use App\Infrastructure\Cache\FileProductionSnapshotStore;
use App\Infrastructure\Production\ResilientProductionRepository;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SectorContextTest extends TestCase
{
    public function test_root_redirects_to_the_explicit_pvc_context(): void
    {
        $this->get('/')->assertRedirect('/pvc');
    }

    public function test_pvc_routes_never_render_or_link_to_the_aluminium_context(): void
    {
        $response = $this->get('/pvc');

        $response
            ->assertOk()
            ->assertSee('data-sector="pvc"', false)
            ->assertSee('Setor PVC')
            ->assertSee('data-production-squares', false)
            ->assertSee('1.234')
            ->assertSee('data-production-frames', false)
            ->assertSee('321')
            ->assertSee('assets/images/favicon-01.png', false)
            ->assertDontSee('data-sector="aluminio"', false)
            ->assertDontSee('/aluminio', false)
            ->assertDontSee('data-provisional-notice', false);
    }

    public function test_aluminium_routes_never_render_or_link_to_the_pvc_context(): void
    {
        $response = $this->get('/aluminio');

        $response
            ->assertOk()
            ->assertSee('data-sector="aluminio"', false)
            ->assertSee('Setor Alumínio')
            ->assertDontSee('data-provisional-notice', false)
            ->assertDontSee('Dados provisórios')
            ->assertDontSee('data-sector="pvc"', false)
            ->assertDontSee('/pvc/', false);
    }

    public function test_monthly_panel_renders_the_server_selected_prize_pool_and_rule_version(): void
    {
        $this->productionRepository->squares = 2990.0;

        $this->get('/pvc')
            ->assertOk()
            ->assertSee('data-prize-pool', false)
            ->assertSee('dashboard-grid', false)
            ->assertSee('Tabela de Prémios')
            ->assertSee('6 Últimos Registos de Prémios')
            ->assertSee('data-prize-history-unavailable', false)
            ->assertSee('Exemplo de cálculo:')
            ->assertSee('2.700 €')
            ->assertSee('premio-producao-v1');
    }

    public function test_aluminium_main_panel_uses_its_administrative_prize_tiers(): void
    {
        $this->productionRepository->squares = 3680.0;

        $this->get('/aluminio')
            ->assertOk()
            ->assertSee('&gt; 800', false)
            ->assertSee('1 600,00 €')
            ->assertSee('definidos por escalão para o setor de Alumínio')
            ->assertDontSee('horas trabalhadas no mês');
    }

    public function test_daily_panel_does_not_present_a_monthly_prize_as_a_daily_prize(): void
    {
        $this->productionRepository->squares = 3680.0;

        $this->get('/pvc/hoje')
            ->assertOk()
            ->assertSee('daily-display', false)
            ->assertSee('HOJE')
            ->assertSee('QUADROS')
            ->assertSee('JANELAS')
            ->assertDontSee('data-prize-pool', false)
            ->assertDontSee('premio-producao-v1');
    }

    public function test_weekly_panel_uses_the_two_metric_layout_with_weekly_data(): void
    {
        $this->productionRepository->squares = 205.0;

        $this->get('/pvc/semana')
            ->assertOk()
            ->assertSee('daily-display', false)
            ->assertSee('ESTA SEMANA')
            ->assertSee('data-production-squares', false)
            ->assertSee('205')
            ->assertSee('data-production-frames', false)
            ->assertSee('321')
            ->assertDontSee('data-prize-pool', false);
    }

    public function test_month_screen_uses_the_two_metric_layout_without_replacing_the_main_prize_panel(): void
    {
        $this->productionRepository->squares = 71.0;

        $this->get('/pvc/mes')
            ->assertOk()
            ->assertSee('daily-display', false)
            ->assertSee('ESTE MÊS')
            ->assertSee('data-production-squares', false)
            ->assertSee('71')
            ->assertSee('data-production-frames', false)
            ->assertSee('321')
            ->assertDontSee('data-prize-pool', false)
            ->assertDontSee('premio-producao-v1');
    }

    public function test_display_uses_a_server_synchronized_playlist_configuration(): void
    {
        $this->get('/pvc')
            ->assertOk()
            ->assertSee('data-playlist', false)
            ->assertSee('data-server-epoch-milliseconds', false)
            ->assertSee('data-playlist-steps', false)
            ->assertSee('display-playlist.js', false)
            ->assertSee('Logotipo.png', false)
            ->assertSee('assets/images/settings.svg', false)
            ->assertDontSee('cdn.jsdelivr.net', false)
            ->assertDontSee('display-navigation', false)
            ->assertDontSee('aria-label="Playlist do setor', false);
    }

    public function test_empty_production_is_presented_as_an_empty_state_not_as_an_error(): void
    {
        $this->productionRepository->hasData = false;

        $this->get('/pvc/hoje')
            ->assertOk()
            ->assertSee('data-production-empty', false)
            ->assertSee('Ainda sem registos de produção')
            ->assertDontSee('data-production-squares', false)
            ->assertDontSee('data-production-unavailable', false);
    }

    public function test_aluminium_daily_panel_keeps_zero_value_cards_when_production_is_empty(): void
    {
        $this->productionRepository->hasData = false;
        $this->productionRepository->squares = 0.0;
        $this->productionRepository->frames = 0;

        $this->get('/aluminio/hoje')
            ->assertOk()
            ->assertSee('daily-display', false)
            ->assertSee('data-production-squares', false)
            ->assertSee('data-production-frames', false)
            ->assertSee('<strong data-production-squares>0</strong>', false)
            ->assertSee('<strong data-production-frames>0</strong>', false)
            ->assertDontSee('data-production-empty', false)
            ->assertDontSee('Ainda sem registos de produção')
            ->assertDontSee('data-production-unavailable', false);
    }

    public function test_every_playlist_screen_preserves_the_requested_sector(): void
    {
        foreach (['pvc', 'aluminio'] as $sector) {
            foreach (['hoje', 'semana', 'mes', 'comunicado'] as $screen) {
                $this->get("/{$sector}/{$screen}")
                    ->assertOk()
                    ->assertSee("data-sector=\"{$sector}\"", false)
                    ->assertSee("data-screen=\"{$screen}\"", false);
            }
        }
    }

    public function test_announcement_screen_does_not_query_production(): void
    {
        $this->get('/pvc/comunicado')
            ->assertOk()
            ->assertSee('display-header--announcement', false)
            ->assertSee('announcement-display', false)
            ->assertSee('settings-link', false)
            ->assertSee('Sem comunicado ativo')
            ->assertSee('data-announcement-empty', false)
            ->assertDontSee('data-production-squares', false);

        $this->assertCount(0, $this->productionRepository->calls);
    }

    public function test_global_announcement_is_escaped_and_added_to_both_sector_playlists(): void
    {
        $this->announcementRepository->announcement = $this->announcement(
            scope: AnnouncementScope::Global,
            sectorKey: null,
            body: '<script>alert("x")</script> Linha segura',
        );

        $this->get('/pvc/comunicado')
            ->assertOk()
            ->assertSee('data-announcement-title', false)
            ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; Linha segura', false)
            ->assertDontSee('<script>alert("x")</script>', false);

        $this->get('/aluminio')
            ->assertOk()
            ->assertSee('&quot;screen&quot;:&quot;comunicado&quot;', false);
    }

    public function test_sector_announcement_does_not_leak_into_the_other_sector(): void
    {
        $this->announcementRepository->announcement = $this->announcement(
            scope: AnnouncementScope::Pvc,
            sectorKey: 'pvc',
            body: 'Comunicado exclusivo PVC',
        );

        $this->get('/pvc/comunicado')
            ->assertOk()
            ->assertSee('Comunicado exclusivo PVC');

        $this->get('/aluminio/comunicado')
            ->assertOk()
            ->assertSee('Sem comunicado ativo')
            ->assertDontSee('Comunicado exclusivo PVC');
    }

    public function test_playlist_skips_the_announcement_step_when_no_announcement_is_active(): void
    {
        $this->get('/pvc')
            ->assertOk()
            ->assertDontSee('/pvc/comunicado', false);
    }

    public function test_announcement_data_failures_are_rendered_without_technical_details(): void
    {
        $this->announcementRepository->shouldFail = true;

        $this->get('/pvc/comunicado')
            ->assertOk()
            ->assertSee('data-announcement-unavailable', false)
            ->assertSee('Comunicados temporariamente indisponíveis')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('Conecta-Conexao');
    }

    public function test_database_failures_are_rendered_without_technical_details(): void
    {
        $this->productionRepository->shouldFail = true;

        $this->get('/pvc/hoje')
            ->assertOk()
            ->assertSee('data-production-unavailable', false)
            ->assertSee('Dados temporariamente indisponíveis')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('BD_PREF');
    }

    public function test_display_uses_the_last_valid_snapshot_when_the_production_source_is_temporarily_unavailable(): void
    {
        $cache = Cache::store('array');
        $this->app->instance(
            ProductionRepository::class,
            new ResilientProductionRepository(
                $this->productionRepository,
                new FileProductionSnapshotStore($cache),
                $cache,
                2,
                0,
                60,
            ),
        );

        $this->get('/pvc/hoje')->assertOk();
        $this->productionRepository->shouldFail = true;

        $this->get('/pvc/hoje')
            ->assertOk()
            ->assertSee('data-production-cached', false)
            ->assertSee('Último valor válido')
            ->assertSee('data-production-stale', false)
            ->assertSee('Dados de produção desatualizados desde');
    }

    public function test_unknown_sectors_and_screens_are_rejected(): void
    {
        $this->get('/madeira')->assertNotFound();
        $this->get('/pvc/inexistente')->assertNotFound();
        $this->get('/aluminio/inexistente')->assertNotFound();
    }

    public function test_each_sector_uses_its_own_production_query_key(): void
    {
        $pvc = config('sectors.sectors.pvc');
        $aluminium = config('sectors.sectors.aluminio');

        $this->assertSame('pvc_production_current', $pvc['production_query_key']);
        $this->assertSame('aluminium_production_current', $aluminium['production_query_key']);
        $this->assertNotSame($pvc['production_query_key'], $aluminium['production_query_key']);
        $this->assertFalse($pvc['uses_provisional_production_data']);
        $this->assertFalse($aluminium['uses_provisional_production_data']);
        $this->assertNull($aluminium['provisional_notice']);
    }

    private function announcement(AnnouncementScope $scope, ?string $sectorKey, string $body): Announcement
    {
        $timezone = new DateTimeZone('Europe/Lisbon');

        return new Announcement(
            id: 1,
            scope: $scope,
            sectorKey: $sectorKey,
            title: 'Informação importante',
            body: $body,
            startsAt: new DateTimeImmutable('2020-01-01 00:00:00', $timezone),
            endsAt: new DateTimeImmutable('2099-01-01 00:00:00', $timezone),
            isActive: true,
        );
    }
}
