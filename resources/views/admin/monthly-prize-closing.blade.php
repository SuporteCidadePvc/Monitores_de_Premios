@extends('admin.layout', ['title' => 'Produção mensal'])

@section('content')
    <div class="dashboard-page">
        <header class="dashboard-topbar">
            <p class="dashboard-topbar__title"><img class="dashboard-topbar__logo" src="{{ asset('assets/images/Logotipo-admin.png') }}" alt="" aria-hidden="true"> Produção mensal</p>
            <div class="dashboard-topbar__actions">
                <a class="dashboard-button dashboard-button--secondary" href="{{ route('admin.monthly-prize-history.index') }}">Consultar histórico</a>
                <a class="dashboard-button dashboard-button--secondary dashboard-topbar__admin-link" href="{{ route('admin.dashboard') }}">← Administração</a>
            </div>
        </header>

        <main class="dashboard-main">
            <section class="dashboard-hero" aria-labelledby="closing-title">
                <div class="dashboard-hero__content">
                    <p class="dashboard-kicker">Monitores de Prémios</p>
                    <h1 id="closing-title">Consultar produção mensal</h1>
                    <p>Escolha o setor e o mês para ver o total de quadros produzidos.</p>
                </div>
            </section>

            <div class="dashboard-content">
                @if ($errors->any())
                    <section class="dashboard-notice dashboard-notice--warning" role="alert">
                        <h2>Não foi possível consultar a produção</h2>
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </section>
                @endif

                <section class="dashboard-section" aria-labelledby="closing-form-title">
                    <div class="dashboard-section__heading">
                        <h2 id="closing-form-title"><span class="dashboard-section__icon" aria-hidden="true">▣</span> Filtro</h2>
                        <p>A produção é consultada no servidor para o mês completo.</p>
                    </div>

                    <form class="dashboard-card dashboard-rule-create" method="GET" action="{{ route('admin.monthly-prize-closing.index') }}">
                        <div class="dashboard-field">
                            <label for="closing-sector">Setor</label>
                            <select id="closing-sector" name="sector" required>
                                @foreach ($sectors as $key => $sector)
                                    <option value="{{ $key }}" @selected($values['sector'] === $key)>{{ $sector['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="dashboard-field">
                            <label for="closing-month">Mês</label>
                            <input id="closing-month" name="month" type="month" value="{{ $values['month'] }}" required>
                        </div>
                        <button class="dashboard-button dashboard-button--primary" type="submit">Consultar</button>
                    </form>
                </section>

                @if ($result)
                    <section class="dashboard-section" aria-labelledby="closing-result-title" data-monthly-production-result>
                        <div class="dashboard-section__heading">
                            <h2 id="closing-result-title"><span class="dashboard-section__icon" aria-hidden="true">✓</span> Produção do mês</h2>
                        </div>

                        <div class="dashboard-card">
                            <dl class="monthly-prize-history-summary-list">
                                <div><dt>Setor</dt><dd>{{ $result['sectorName'] }}</dd></div>
                                <div><dt>Período</dt><dd>{{ sprintf('%02d/%04d', $result['period']->month, $result['period']->year) }}</dd></div>
                                <div><dt>Produção</dt><dd data-monthly-production-squares>{{ number_format($result['squares'], 0, ',', '.') }} Quadros</dd></div>
                            </dl>
                        </div>
                    </section>
                @endif
            </div>
        </main>
    </div>
@endsection
