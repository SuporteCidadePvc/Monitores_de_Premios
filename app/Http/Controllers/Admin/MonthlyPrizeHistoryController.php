<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Exceptions\AdminDataUnavailable;
use App\Domain\Admin\MonthlyPrizeHistoryPage;
use App\Domain\Admin\MonthlyPrizeHistoryQuery;
use App\Domain\Admin\MonthlyPrizeHistoryRepository;
use App\Domain\Prizes\PrizeMonth;
use App\Infrastructure\Exports\MonthlyPrizeHistoryXlsxExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MonthlyPrizeHistoryController
{
    public function index(Request $request, MonthlyPrizeHistoryRepository $history): View
    {
        [$filters, $query] = $this->filtersAndQuery($request);

        try {
            $page = $history->search($query);
            $summaries = $history->summaries($query);

            if ($page->total > 0 && $page->records === [] && $page->page > 1) {
                $lastPage = (int) ceil($page->total / $page->perPage);
                $query = new MonthlyPrizeHistoryQuery(
                    sectorKey: $query->sectorKey,
                    from: $query->from,
                    to: $query->to,
                    page: $lastPage,
                    perPage: $query->perPage,
                );
                $page = $history->search($query);
                $filters['page'] = $lastPage;
            }

            $unavailable = false;
        } catch (AdminDataUnavailable) {
            $page = new MonthlyPrizeHistoryPage([], 0, $query->page, $query->perPage);
            $summaries = [];
            $unavailable = true;
        }

        return view('admin.monthly-prize-history', [
            'filters' => $filters,
            'history' => $page,
            'summaries' => $summaries,
            'unavailable' => $unavailable,
        ]);
    }

    public function export(Request $request, MonthlyPrizeHistoryRepository $history, MonthlyPrizeHistoryXlsxExporter $exporter): BinaryFileResponse|RedirectResponse
    {
        [$filters, $query] = $this->filtersAndQuery($request);

        try {
            $path = $exporter->write($query, $history->exportRecords($query));
        } catch (AdminDataUnavailable) {
            return redirect()
                ->route('admin.monthly-prize-history.index', $filters)
                ->withErrors(['history_export' => 'Não foi possível exportar o histórico agora. Tente novamente dentro de instantes.']);
        }

        return response()
            ->download($path, $exporter->filename($query), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    /** @return array{0:array<string, string|int>,1:MonthlyPrizeHistoryQuery} */
    private function filtersAndQuery(Request $request): array
    {
        $filters = $request->validate([
            'sector' => ['nullable', Rule::in(['pvc', 'aluminio'])],
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $from = $this->period($filters['from'] ?? null);
        $to = $this->period($filters['to'] ?? null);

        if ($from instanceof PrizeMonth && $to instanceof PrizeMonth && $this->periodNumber($from) > $this->periodNumber($to)) {
            throw ValidationException::withMessages([
                'to' => 'O mês final não pode ser anterior ao mês inicial.',
            ]);
        }

        return [$filters, new MonthlyPrizeHistoryQuery(
            sectorKey: isset($filters['sector']) ? (string) $filters['sector'] : null,
            from: $from,
            to: $to,
            page: isset($filters['page']) ? (int) $filters['page'] : 1,
        )];
    }

    private function period(?string $value): ?PrizeMonth
    {
        if ($value === null || $value === '') {
            return null;
        }

        [$year, $month] = explode('-', $value);

        return new PrizeMonth((int) $year, (int) $month);
    }

    private function periodNumber(PrizeMonth $period): int
    {
        return ($period->year * 100) + $period->month;
    }
}
