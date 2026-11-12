<?php

namespace Tests\Feature;

use App\Domain\Operations\OdbcConnectionProbe;
use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_health_reports_application_and_snapshot_state_without_database_details(): void
    {
        $this->app->instance(OdbcConnectionProbe::class, new class implements OdbcConnectionProbe
        {
            public function check(): array
            {
                return ['production' => 'ok', 'dashboard' => 'ok'];
            }
        });

        $this->get(route('health'))
            ->assertOk()
            ->assertJsonPath('application', 'ok')
            ->assertJsonStructure([
                'status',
                'cache',
                'odbc' => ['production', 'dashboard'],
                'production' => [
                    'pvc' => ['state', 'last_success_at', 'last_failure_at', 'snapshot_age_seconds'],
                    'aluminio' => ['state', 'last_success_at', 'last_failure_at', 'snapshot_age_seconds'],
                ],
            ])
            ->assertDontSee('BD_PREF')
            ->assertDontSee('Conecta-Conexao')
            ->assertDontSee('SQLSTATE');
    }

    public function test_health_reports_a_degraded_status_without_database_error_details_when_odbc_is_unavailable(): void
    {
        $this->app->instance(OdbcConnectionProbe::class, new class implements OdbcConnectionProbe
        {
            public function check(): array
            {
                return ['production' => 'unavailable', 'dashboard' => 'unavailable'];
            }
        });

        $this->get(route('health'))
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('odbc.production', 'unavailable')
            ->assertJsonPath('odbc.dashboard', 'unavailable')
            ->assertDontSee('BD_PREF')
            ->assertDontSee('Conecta-Conexao')
            ->assertDontSee('SQLSTATE');
    }
}
