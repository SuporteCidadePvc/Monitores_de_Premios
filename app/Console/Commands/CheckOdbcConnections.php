<?php

namespace App\Console\Commands;

use App\Domain\Operations\OdbcConnectionProbe;
use Illuminate\Console\Command;

final class CheckOdbcConnections extends Command
{
    protected $signature = 'monitores:odbc:check';

    protected $description = 'Verifica as ligações ODBC de produção e administração sem expor detalhes de ligação.';

    public function handle(OdbcConnectionProbe $odbc): int
    {
        $connections = $odbc->check();

        $this->line('Produção ODBC: '.$this->label($connections['production']));
        $this->line('Administração ODBC: '.$this->label($connections['dashboard']));

        if ($connections['production'] === 'ok' && $connections['dashboard'] === 'ok') {
            $this->info('As ligações ODBC estão disponíveis.');

            return self::SUCCESS;
        }

        $this->error('Existe pelo menos uma ligação ODBC indisponível. Verifique os System DSNs de 64 bits e a conectividade SQL Server.');

        return self::FAILURE;
    }

    private function label(string $status): string
    {
        return $status === 'ok' ? 'disponível' : 'indisponível';
    }
}
