<?php

namespace Tests\Unit;

use App\Domain\Admin\MonthlyPrizeHistoryQuery;
use App\Domain\Admin\MonthlyPrizeHistoryRecord;
use App\Domain\Prizes\PrizeMonth;
use App\Infrastructure\Exports\MonthlyPrizeHistoryXlsxExporter;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

final class MonthlyPrizeHistoryXlsxExporterTest extends TestCase
{
    public function test_it_writes_a_readable_xlsx_with_filters_headers_and_typed_record_values(): void
    {
        $exporter = new MonthlyPrizeHistoryXlsxExporter;
        $path = $exporter->write(
            new MonthlyPrizeHistoryQuery('pvc', new PrizeMonth(2025, 1), new PrizeMonth(2026, 8)),
            [new MonthlyPrizeHistoryRecord(7, 'pvc', 2026, 8, 2990.5, 480000, 16000, 'premio-producao-v1', 270000, 9000, 'EUR', '2026-09-01 10:30:00')],
        );

        try {
            $this->assertFileExists($path);
            $this->assertSame('historico-premios-pvc-2025-01-a-2026-08.xlsx', $exporter->filename(new MonthlyPrizeHistoryQuery('pvc', new PrizeMonth(2025, 1), new PrizeMonth(2026, 8))));

            $reader = new Reader;
            $reader->open($path);
            $rows = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                break;
            }

            $reader->close();

            $this->assertSame('Histórico de prémios mensais', $rows[0][0]);
            $this->assertSame('Filtros: Setor PVC; de 2025-01; até 2026-08', $rows[1][0]);
            $this->assertSame(['Setor', 'Período', 'Produção', 'Prémio global', 'Prémio médio', 'Horas totais', 'Horas médias', 'Regra aplicada', 'Calculado em'], $rows[3]);
            $this->assertSame('PVC', $rows[4][0]);
            $this->assertSame(2990.5, $rows[4][2]);
            $this->assertEquals(2700.0, $rows[4][3]);
            $this->assertEquals(90.0, $rows[4][4]);
        } finally {
            if (isset($reader)) {
                $reader->close();
            }

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
