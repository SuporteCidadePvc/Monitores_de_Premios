<?php

namespace Tests\Feature;

use App\Domain\Operations\OdbcConnectionProbe;
use Illuminate\Console\Command;
use Tests\TestCase;

final class OdbcConnectionCheckCommandTest extends TestCase
{
    public function test_command_confirms_when_both_odbc_connections_are_available(): void
    {
        $this->app->instance(OdbcConnectionProbe::class, new class implements OdbcConnectionProbe
        {
            public function check(): array
            {
                return ['production' => 'ok', 'dashboard' => 'ok'];
            }
        });

        $this->artisan('monitores:odbc:check')
            ->expectsOutput('Produção ODBC: disponível')
            ->expectsOutput('Administração ODBC: disponível')
            ->expectsOutput('As ligações ODBC estão disponíveis.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_fails_without_exposing_connection_details_when_an_odbc_connection_is_unavailable(): void
    {
        $this->app->instance(OdbcConnectionProbe::class, new class implements OdbcConnectionProbe
        {
            public function check(): array
            {
                return ['production' => 'unavailable', 'dashboard' => 'ok'];
            }
        });

        $this->artisan('monitores:odbc:check')
            ->expectsOutput('Produção ODBC: indisponível')
            ->expectsOutput('Administração ODBC: disponível')
            ->expectsOutput('Existe pelo menos uma ligação ODBC indisponível. Verifique os System DSNs de 64 bits e a conectividade SQL Server.')
            ->doesntExpectOutput('BD_PREF')
            ->doesntExpectOutput('Conecta-Conexao')
            ->doesntExpectOutput('SQLSTATE')
            ->assertExitCode(Command::FAILURE);
    }
}
