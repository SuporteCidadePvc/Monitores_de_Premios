<?php

namespace App\Infrastructure\Exports;

use App\Domain\Admin\MonthlyPrizeHistoryQuery;
use App\Domain\Admin\MonthlyPrizeHistoryRecord;
use DateTimeImmutable;
use DateTimeZone;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

final class MonthlyPrizeHistoryXlsxExporter
{
    /** @param iterable<MonthlyPrizeHistoryRecord> $records */
    public function write(MonthlyPrizeHistoryQuery $query, iterable $records): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'monitores-premios-');

        if ($temporaryFile === false) {
            throw new RuntimeException('The XLSX temporary file could not be created.');
        }

        $path = $temporaryFile.'.xlsx';
        unlink($temporaryFile);

        $writer = new Writer;

        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Histórico de prémios');
            $sheet->setColumnWidth(14, 1, 2);
            $sheet->setColumnWidth(18, 3, 7);
            $sheet->setColumnWidth(24, 8, 9);
            $sheet->setColumnWidth(20, 10);

            $titleStyle = new Style(
                fontBold: true,
                fontSize: 14,
                fontColor: Color::WHITE,
                backgroundColor: '003F87',
            );
            $headerStyle = new Style(
                fontBold: true,
                fontColor: Color::WHITE,
                backgroundColor: '0056B3',
            );
            $numberStyle = new Style(format: '#,##0.00');
            $currencyStyle = new Style(format: '#,##0.00 "€"');
            $dateStyle = new Style(format: 'yyyy-mm-dd hh:mm');

            $writer->addRow(Row::fromValuesWithStyle(['Histórico de prémios mensais'], $titleStyle));
            $writer->addRow(Row::fromValues([$this->filterLabel($query)]));
            $writer->addRow(Row::fromValues(['Gerado em', (new DateTimeImmutable('now', new DateTimeZone((string) config('production.timezone'))))->format('Y-m-d H:i:s')]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValuesWithStyle([
                'Setor',
                'Período',
                'Produção',
                'Prémio global',
                'Prémio médio',
                'Horas totais',
                'Horas médias',
                'Regra aplicada',
                'Calculado em',
            ], $headerStyle));

            foreach ($records as $record) {
                $writer->addRow(Row::fromValuesWithStyles([
                    strtoupper($record->sectorKey),
                    new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $record->year, $record->month), new DateTimeZone((string) config('production.timezone'))),
                    $record->productionSquares,
                    $record->poolCents / 100,
                    $record->averagePrizeCents / 100,
                    $record->totalHoursHundredths / 100,
                    $record->averageHoursHundredths / 100,
                    $record->ruleVersion,
                    (new DateTimeImmutable($record->calculatedAt, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone((string) config('production.timezone'))),
                ], [
                    1 => new Style(format: 'yyyy-mm'),
                    2 => $numberStyle,
                    3 => $currencyStyle,
                    4 => $currencyStyle,
                    5 => $numberStyle,
                    6 => $numberStyle,
                    8 => $dateStyle,
                ]));
            }

            $writer->close();

            return $path;
        } catch (Throwable $exception) {
            $writer->close();
            if (is_file($path)) {
                unlink($path);
            }

            throw $exception;
        }
    }

    public function filename(MonthlyPrizeHistoryQuery $query): string
    {
        $period = match (true) {
            $query->from !== null && $query->to !== null => sprintf('%04d-%02d-a-%04d-%02d', $query->from->year, $query->from->month, $query->to->year, $query->to->month),
            $query->from !== null => sprintf('desde-%04d-%02d', $query->from->year, $query->from->month),
            $query->to !== null => sprintf('ate-%04d-%02d', $query->to->year, $query->to->month),
            default => 'todos-os-meses',
        };

        return sprintf('historico-premios-%s-%s.xlsx', $query->sectorKey ?? 'todos-os-setores', $period);
    }

    private function filterLabel(MonthlyPrizeHistoryQuery $query): string
    {
        $sector = match ($query->sectorKey) {
            'pvc' => 'PVC',
            'aluminio' => 'Alumínio',
            default => 'Todos os setores',
        };

        $from = $query->from === null ? 'sem limite' : sprintf('%04d-%02d', $query->from->year, $query->from->month);
        $to = $query->to === null ? 'sem limite' : sprintf('%04d-%02d', $query->to->year, $query->to->month);

        return "Filtros: Setor {$sector}; de {$from}; até {$to}";
    }
}
