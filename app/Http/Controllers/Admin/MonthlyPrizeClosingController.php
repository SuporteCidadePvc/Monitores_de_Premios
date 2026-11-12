<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Display\SectorContext;
use App\Domain\Prizes\Exceptions\MonthlyPrizeClosingNotAllowed;
use App\Domain\Prizes\MonthlyProductionLookup;
use App\Domain\Prizes\PrizeMonth;
use App\Domain\Production\Exceptions\ProductionDataUnavailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

final class MonthlyPrizeClosingController
{
    public function index(Request $request, MonthlyProductionLookup $lookup): View
    {
        if (! $request->has('month')) {
            return $this->view();
        }

        $validator = Validator::make($request->only(['sector', 'month']), [
            'sector' => ['required', Rule::in(array_keys((array) config('sectors.sectors')))],
            'month' => ['required', 'date_format:Y-m'],
        ], [
            'sector.required' => 'Selecione o setor.',
            'sector.in' => 'O setor selecionado é inválido.',
            'month.required' => 'Selecione o mês.',
            'month.date_format' => 'O mês selecionado é inválido.',
        ]);

        if ($validator->fails()) {
            return $this->view()->withErrors($validator);
        }

        $data = $validator->validated();
        $values = ['sector' => (string) $data['sector'], 'month' => (string) $data['month']];
        [$year, $month] = array_map('intval', explode('-', $values['month']));

        try {
            $period = new PrizeMonth($year, $month);
        } catch (InvalidArgumentException) {
            return $this->view(values: $values)->withErrors(['month' => 'O mês selecionado é inválido.']);
        }

        $sector = SectorContext::fromConfiguration($values['sector'], (array) config('sectors.sectors.'.$values['sector']));

        try {
            $squares = $lookup->squares($sector, $period);
        } catch (MonthlyPrizeClosingNotAllowed $exception) {
            return $this->view(values: $values)->withErrors(['month' => $this->closingMessage($exception)]);
        } catch (ProductionDataUnavailable) {
            return $this->view(values: $values)->withErrors(['production' => 'Não foi possível obter a produção do mês. Tente novamente dentro de instantes.']);
        }

        return $this->view([
            'sectorName' => $sector->name,
            'period' => $period,
            'squares' => $squares,
        ], $values);
    }

    /**
     * @param  array{sectorName: string, period: PrizeMonth, squares: float}|null  $result
     * @param  array<string, string>  $values
     */
    private function view(?array $result = null, array $values = []): View
    {
        $defaultMonth = now((string) config('production.timezone'))->subMonthNoOverflow()->format('Y-m');

        return view('admin.monthly-prize-closing', [
            'result' => $result,
            'values' => $values + [
                'sector' => 'pvc',
                'month' => $defaultMonth,
            ],
            'sectors' => (array) config('sectors.sectors'),
        ]);
    }

    private function closingMessage(MonthlyPrizeClosingNotAllowed $exception): string
    {
        return str_contains($exception->getMessage(), 'No production records')
            ? 'Não foram encontrados registos de produção para o setor e mês selecionados.'
            : 'Só é possível consultar meses anteriores ao mês corrente.';
    }
}
