@extends('admin.layout', ['title' => 'Histórico de prémios'])

@section('content')
    <div class="dashboard-page">
        <header class="dashboard-topbar">
            <p class="dashboard-topbar__title"><img class="dashboard-topbar__logo" src="{{ asset('assets/images/Logotipo-admin.png') }}" alt="" aria-hidden="true"> Histórico de prémios</p>
            <a class="dashboard-button dashboard-button--secondary dashboard-topbar__admin-link" href="{{ route('admin.dashboard') }}">← Administração</a>
        </header>

        <main class="dashboard-main">
            <section class="dashboard-hero" aria-labelledby="history-title">
                <div class="dashboard-hero__content">
                    <p class="dashboard-kicker">Monitores de Prémios</p>
                    <h1 id="history-title">Histórico de prémios</h1>
                    <p>Consulte manualmente a evolução mensal de produção e prémios por setor.</p>
                </div>
            </section>

            <div class="dashboard-content">
                @if (session('status'))
                    <p class="dashboard-notice dashboard-notice--success" role="status">{{ session('status') }}</p>
                @endif

                <section class="dashboard-section" aria-labelledby="history-filters-title">
                    <div class="dashboard-section__heading">
                        <h2 id="history-filters-title"><span class="dashboard-section__icon" aria-hidden="true">⌕</span> Filtros</h2>
                        <p>Selecione um setor e um intervalo mensal para consultar o histórico guardado.</p>
                    </div>

                    <form class="dashboard-card dashboard-rule-create" method="GET" action="{{ route('admin.monthly-prize-history.index') }}">
                        <div class="dashboard-field">
                            <label for="history-sector">Setor</label>
                            <select id="history-sector" name="sector">
                                <option value="">Todos os setores</option>
                                <option value="pvc" @selected(($filters['sector'] ?? null) === 'pvc')>PVC</option>
                                <option value="aluminio" @selected(($filters['sector'] ?? null) === 'aluminio')>Alumínio</option>
                            </select>
                        </div>
                        <div class="dashboard-field">
                            <label for="history-from">Mês inicial</label>
                            <input id="history-from" name="from" type="month" value="{{ $filters['from'] ?? '' }}">
                        </div>
                        <div class="dashboard-field">
                            <label for="history-to">Mês final</label>
                            <input id="history-to" name="to" type="month" value="{{ $filters['to'] ?? '' }}">
                            @error('to')<p class="dashboard-form-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <button class="dashboard-button dashboard-button--primary" type="submit">Consultar histórico</button>
                    </form>
                    @error('history_export')<p class="dashboard-form-error" role="alert">{{ $message }}</p>@enderror
                </section>

                @if ($unavailable)
                    <section class="dashboard-notice dashboard-notice--warning" role="alert" data-monthly-prize-history-unavailable>
                        <h2>Histórico temporariamente indisponível</h2>
                        <p>Não foi possível consultar os prémios mensais agora. Tente novamente dentro de instantes.</p>
                    </section>
                @else
                    @php
                        $months = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
                        $periodLabels = static fn (array $periods): string => implode(' · ', array_map(static fn ($period): string => "{$months[$period->month]} de {$period->year}", $periods));
                        $lastPage = max(1, (int) ceil($history->total / $history->perPage));
                        $firstRecord = $history->total === 0 ? 0 : (($history->page - 1) * $history->perPage) + 1;
                        $lastRecord = min($history->total, $history->page * $history->perPage);
                        $historyQuery = array_filter([
                            'sector' => $filters['sector'] ?? null,
                            'from' => $filters['from'] ?? null,
                            'to' => $filters['to'] ?? null,
                        ], static fn (?string $value): bool => $value !== null && $value !== '');
                    @endphp

                    <section class="dashboard-section" aria-labelledby="history-summary-title" data-monthly-prize-history-summaries>
                        <div class="dashboard-section__heading">
                            <h2 id="history-summary-title"><span class="dashboard-section__icon" aria-hidden="true">↗</span> Indicadores de desempenho</h2>
                            <p>Os valores são calculados sobre todos os meses encontrados nos filtros, separadamente por setor.</p>
                        </div>

                        @if ($summaries === [])
                            <div class="dashboard-card monthly-prize-history-empty" data-monthly-prize-history-summary-empty>
                                <h3>Sem indicadores disponíveis</h3>
                                <p>Os indicadores aparecerão quando existirem prémios mensais registados para os filtros selecionados.</p>
                            </div>
                        @else
                            <div class="monthly-prize-history-summary-grid">
                                @foreach ($summaries as $summary)
                                    <article class="dashboard-card monthly-prize-history-summary-card">
                                        <h3>{{ strtoupper($summary->sectorKey) }}</h3>
                                        <dl class="monthly-prize-history-summary-list">
                                            <div>
                                                <dt>Meses registados</dt>
                                                <dd>{{ $summary->recordCount }}</dd>
                                            </div>
                                            <div>
                                                <dt>Produção média mensal</dt>
                                                <dd>{{ number_format($summary->averageProductionSquares, 2, ',', '.') }}</dd>
                                            </div>
                                            <div>
                                                <dt>Melhor produção</dt>
                                                <dd>{{ number_format($summary->maximumProductionSquares, 2, ',', '.') }}</dd>
                                                <small>{{ $periodLabels($summary->bestProductionPeriods) }}</small>
                                            </div>
                                            <div>
                                                <dt>Maior prémio médio</dt>
                                                <dd>{{ number_format($summary->maximumAveragePrizeCents / 100, 2, ',', '.') }} €</dd>
                                                <small>{{ $periodLabels($summary->bestAveragePrizePeriods) }}</small>
                                            </div>
                                        </dl>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="dashboard-section" aria-labelledby="history-records-title">
                        <div class="dashboard-section__heading monthly-prize-history-heading">
                            <div>
                                <h2 id="history-records-title"><span class="dashboard-section__icon" aria-hidden="true">▦</span> Registos mensais</h2>
                                <p data-monthly-prize-history-total>{{ $history->total === 1 ? '1 registo encontrado.' : "{$history->total} registos encontrados." }}</p>
                            </div>
                            @if ($history->total > 0)
                                <div class="monthly-prize-history-actions">
                                    <a class="dashboard-button dashboard-button--primary" href="{{ route('admin.monthly-prize-history.export', $historyQuery) }}">Exportar XLSX</a>
                                    <p class="monthly-prize-history-range">A mostrar {{ $firstRecord }}–{{ $lastRecord }} de {{ $history->total }}</p>
                                </div>
                            @endif
                        </div>

                        @if ($history->records === [])
                            <div class="dashboard-card monthly-prize-history-empty" data-monthly-prize-history-empty>
                                <h3>{{ $history->total === 0 ? 'Sem prémios mensais registados' : 'Não existem registos nesta página' }}</h3>
                                <p>{{ $history->total === 0 ? 'Ainda não há meses guardados para os filtros selecionados.' : 'A consulta foi ajustada para a última página disponível.' }}</p>
                            </div>
                        @else
                            <div class="dashboard-card dashboard-table-card">
                                <div class="dashboard-table-scroll">
                                    <table class="dashboard-table monthly-prize-history-table">
                                        <caption class="visually-hidden">Histórico mensal de prémios</caption>
                                        <thead>
                                            <tr>
                                                <th>Setor</th>
                                                <th>Período</th>
                                                <th>Produção</th>
                                                <th>Prémio global</th>
                                                <th>Prémio médio</th>
                                                <th>Regra aplicada</th>
                                                <th>Calculado em</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($history->records as $record)
                                                <tr>
                                                    <td><strong>{{ strtoupper($record->sectorKey) }}</strong></td>
                                                    <td>{{ $months[$record->month] }} de {{ $record->year }}</td>
                                                    <td class="dashboard-table__value">{{ number_format($record->productionSquares, 2, ',', '.') }}</td>
                                                    <td>{{ number_format($record->poolCents / 100, 2, ',', '.') }} €</td>
                                                    <td class="dashboard-table__value">{{ number_format($record->averagePrizeCents / 100, 2, ',', '.') }} €</td>
                                                    <td class="dashboard-table__muted">{{ $record->ruleVersion }}</td>
                                                    <td class="dashboard-table__muted">{{ \Carbon\CarbonImmutable::parse($record->calculatedAt, 'UTC')->setTimezone(config('production.timezone'))->format('d/m/Y H:i') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            @if ($lastPage > 1)
                                <nav class="monthly-prize-history-pagination" aria-label="Paginação do histórico de prémios">
                                    @if ($history->page > 1)
                                        <a class="dashboard-button dashboard-button--secondary" href="{{ route('admin.monthly-prize-history.index', $historyQuery + ['page' => $history->page - 1]) }}">← Anterior</a>
                                    @else
                                        <span class="monthly-prize-history-pagination__disabled">← Anterior</span>
                                    @endif
                                    <span>Página {{ $history->page }} de {{ $lastPage }}</span>
                                    @if ($history->page < $lastPage)
                                        <a class="dashboard-button dashboard-button--secondary" href="{{ route('admin.monthly-prize-history.index', $historyQuery + ['page' => $history->page + 1]) }}">Seguinte →</a>
                                    @else
                                        <span class="monthly-prize-history-pagination__disabled">Seguinte →</span>
                                    @endif
                                </nav>
                            @endif
                        @endif
                    </section>
                @endif
            </div>
        </main>
    </div>
@endsection
