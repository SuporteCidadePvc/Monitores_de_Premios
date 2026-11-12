@extends('admin.layout', ['title' => 'Administração'])

@section('content')
    <div class="dashboard-page">
        <header class="dashboard-topbar">
            <p class="dashboard-topbar__title"><img class="dashboard-topbar__logo" src="{{ asset('assets/images/Logotipo-admin.png') }}" alt="" aria-hidden="true"> Painel de Administração</p>
            <div class="dashboard-topbar__actions">
                <a class="dashboard-button dashboard-button--secondary" href="{{ route('admin.monthly-prize-closing.index') }}">Produção mensal</a>
                <a class="dashboard-button dashboard-button--secondary dashboard-topbar__history-link" href="{{ route('admin.monthly-prize-history.index') }}">Consultar histórico de prémios</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="dashboard-logout" type="submit"><span aria-hidden="true">↪</span> Terminar sessão</button>
                </form>
            </div>
        </header>

        <main class="dashboard-main">
            <section class="dashboard-hero" aria-labelledby="dashboard-title">
                <div class="dashboard-hero__content">
                    <p class="dashboard-kicker">Monitores de Prémios</p>
                    <h1 id="dashboard-title">Administração</h1>
                    <p>Gestão de escalões de prémio e comunicados internos.</p>
                </div>
            </section>

            <div class="dashboard-content">
                @if (session('status'))
                    <p class="dashboard-notice dashboard-notice--success" role="status">{{ session('status') }}</p>
                @endif

                @if ($unavailable)
                    <section class="dashboard-notice dashboard-notice--warning" role="alert">
                        <h2>Dados temporariamente indisponíveis</h2>
                        <p>Não foi possível consultar a base administrativa. Nenhuma operação de escrita está disponível até a ligação recuperar.</p>
                    </section>
                @else
                    <section class="dashboard-section" aria-labelledby="prize-rules-heading">
                        <div class="dashboard-section__heading">
                            <h2 id="prize-rules-heading"><span class="dashboard-section__icon" aria-hidden="true">€</span> Escalões de prémio</h2>
                            <p>Crie e faça a gestão dos escalões ativos para cada setor.</p>
                        </div>
                        @error('rules')<p class="dashboard-form-error" role="alert">{{ $message }}</p>@enderror

                        <form class="dashboard-card dashboard-rule-create" method="POST" action="{{ route('admin.prize-rules.store') }}">
                            @csrf
                            <div class="dashboard-field"><label for="rule-sector">Setor</label><select id="rule-sector" name="sector"><option value="pvc">PVC</option><option value="aluminio">Alumínio</option></select></div>
                            <div class="dashboard-field"><label for="minimum-squares">Produção mínima</label><input id="minimum-squares" name="minimum_squares" type="number" min="0" max="10000000" value="{{ old('minimum_squares') }}" placeholder="Ex.: 400" required></div>
                            <div class="dashboard-field"><label for="prize-pool">Prémio total (€)</label><input id="prize-pool" name="prize_pool" inputmode="decimal" value="{{ old('prize_pool') }}" placeholder="Ex.: 150,00" required></div>
                            <button class="dashboard-button dashboard-button--primary" type="submit"><span aria-hidden="true">+</span> Adicionar escalão</button>
                        </form>

                        <div class="dashboard-card dashboard-table-card"><div class="dashboard-table-scroll"><table class="dashboard-table rule-table">
                            <thead><tr><th>Setor</th><th>Mínimo</th><th>Prémio</th><th>Estado</th><th class="dashboard-table__actions-heading">Ações</th></tr></thead>
                            <tbody>
                                @forelse ($rules as $rule)
                                    <tr>
                                        <td>{{ strtoupper((string) $rule['sectorkey']) }}</td>
                                        <td class="dashboard-table__muted">{{ number_format((int) $rule['minimumsquares'], 0, ',', '.') }}</td>
                                        <td class="dashboard-table__value">€ {{ number_format(((int) $rule['prizepoolcents']) / 100, 2, ',', '.') }}</td>
                                        <td><span @class(['rule-status', 'rule-status--active' => (bool) $rule['isactive']])>{{ (bool) $rule['isactive'] ? 'Ativo' : 'Inativo' }}</span></td>
                                        <td class="rule-actions-cell">
                                            @if ((bool) $rule['isactive'])
                                                <details class="rule-actions rule-actions--icon">
                                                    <summary aria-label="Gerir escalão" title="Gerir escalão"><img class="manage-icon" src="{{ asset('assets/images/settings-admin.svg') }}" alt="" aria-hidden="true"></summary>
                                                    <div class="rule-actions-panel">
                                                        <form class="rule-edit-form" method="POST" action="{{ route('admin.prize-rules.update', $rule['prizeruleid']) }}">
                                                            @csrf
                                                            <div><label for="minimum-squares-{{ $rule['prizeruleid'] }}">Mínimo</label><input id="minimum-squares-{{ $rule['prizeruleid'] }}" name="minimum_squares" type="number" min="0" max="10000000" value="{{ (int) $rule['minimumsquares'] }}" required></div>
                                                            <div><label for="prize-pool-{{ $rule['prizeruleid'] }}">Prémio (€)</label><input id="prize-pool-{{ $rule['prizeruleid'] }}" name="prize_pool" inputmode="decimal" value="{{ number_format(((int) $rule['prizepoolcents']) / 100, 2, '.', '') }}" required></div>
                                                            <button class="dashboard-button dashboard-button--secondary" type="submit">Guardar</button>
                                                        </form>
                                                        <div class="rule-danger-actions">
                                                            <form class="rule-action-form" method="POST" action="{{ route('admin.prize-rules.deactivate', $rule['prizeruleid']) }}">@csrf<label class="confirm"><input type="checkbox" name="confirm" value="1" required> Confirmo a desativação</label><button class="link-button" type="submit">Desativar</button></form>
                                                            <form class="rule-action-form" method="POST" action="{{ route('admin.prize-rules.delete', $rule['prizeruleid']) }}">@csrf<label class="confirm"><input type="checkbox" name="confirm_delete" value="1" required> Confirmo a remoção definitiva</label><button class="link-button link-button--danger" type="submit">Remover</button></form>
                                                        </div>
                                                    </div>
                                                </details>
                                            @else
                                                <span class="dashboard-table__muted">Sem ações disponíveis</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="dashboard-table__empty">Sem escalões registados.</td></tr>
                                @endforelse
                            </tbody>
                        </table></div></div>
                    </section>

                    <section class="dashboard-section dashboard-section--announcements" aria-labelledby="announcements-heading">
                        <div class="dashboard-section__heading">
                            <h2 id="announcements-heading"><span class="dashboard-section__icon" aria-hidden="true"><img src="{{ asset('assets/images/message-square.svg') }}" alt=""></span> Comunicados</h2>
                            <p>Publique, edite ou remova comunicados internos com registo de auditoria.</p>
                        </div>
                        @error('announcements')<p class="dashboard-form-error" role="alert">{{ $message }}</p>@enderror
                        @error('attachment')<p class="dashboard-form-error" role="alert">{{ $message }}</p>@enderror
                        @error('body')<p class="dashboard-form-error" role="alert">{{ $message }}</p>@enderror

                        <div class="dashboard-announcements-grid">
                            <form class="dashboard-card dashboard-announcement-create" method="POST" action="{{ route('admin.announcements.store') }}" enctype="multipart/form-data">
                                @csrf
                                <h3>Novo comunicado</h3>
                                <div class="dashboard-field"><label for="announcement-scope">Âmbito</label><select id="announcement-scope" name="scope"><option value="global">Global</option><option value="pvc">PVC</option><option value="aluminio">Alumínio</option></select></div>
                                <div class="dashboard-field-grid"><div class="dashboard-field"><label for="starts-at">Início</label><input id="starts-at" name="starts_at" type="datetime-local" value="{{ old('starts_at') }}" required></div><div class="dashboard-field"><label for="ends-at">Fim (opcional)</label><input id="ends-at" name="ends_at" type="datetime-local" value="{{ old('ends_at') }}"></div></div>
                                <div class="dashboard-field"><label for="announcement-title">Título</label><input id="announcement-title" name="title" maxlength="160" value="{{ old('title') }}" placeholder="Insira o título do comunicado..." required></div>
                                <div class="dashboard-field"><label for="announcement-body">Mensagem</label><textarea id="announcement-body" name="body" maxlength="8000" rows="4" placeholder="Escreva a mensagem aqui...">{{ old('body') }}</textarea></div>
                                <div class="dashboard-field"><label for="announcement-attachment">Imagem (opcional)</label><div class="attachment-picker"><input id="announcement-attachment" class="attachment-picker__input" name="attachment" type="file" accept="image/jpeg,image/png,image/webp" onchange="this.parentElement.querySelector('[data-attachment-name]').textContent = this.files.length ? this.files[0].name : ''"><label class="attachment-picker__button" for="announcement-attachment" title="Anexar imagem"><img src="{{ asset('assets/images/paperclip.svg') }}" alt="Anexar imagem"></label><span class="attachment-picker__name" data-attachment-name></span></div><p class="dashboard-field-hint">JPG, PNG ou WEBP até {{ intdiv((int) config('announcements.attachments.max_kilobytes'), 1024) }} MB. Com imagem, a TV mostra a imagem em vez da mensagem e a mensagem passa a ser opcional.</p></div>
                                <button class="dashboard-button dashboard-button--primary dashboard-button--full" type="submit"><span aria-hidden="true">↑</span> Publicar comunicado</button>
                            </form>

                            <div class="announcement-list">
                                @forelse ($announcements as $announcement)
                                    <article class="dashboard-card announcement-card">
                                        <div class="announcement-card__bar" @style(['background-color: #0056b3' => (bool) $announcement['isactive'], 'background-color: #727784' => ! (bool) $announcement['isactive']])></div>
                                        <div class="announcement-card__meta"><span class="announcement-scope">{{ strtoupper((string) $announcement['announcementscope']) }}</span><time datetime="{{ $announcement['startsat'] }}">{{ $announcement['startsat'] }}</time></div>
                                        <h3>{{ $announcement['title'] }}</h3>
                                        @if (! empty($announcement['attachmentid']))
                                            <img class="announcement-card__thumb" src="{{ route('admin.announcements.attachment', $announcement['announcementid']) }}?v={{ substr((string) $announcement['attachmentsha256'], 0, 12) }}" alt="Imagem do comunicado: {{ $announcement['attachmentoriginalname'] }}" loading="lazy">
                                        @endif
                                        @if (trim((string) $announcement['body']) !== '')
                                            <p>{{ \Illuminate\Support\Str::limit((string) $announcement['body'], 180) }}</p>
                                        @elseif (! empty($announcement['attachmentid']))
                                            <p class="announcement-card__attachment-name">Imagem: {{ $announcement['attachmentoriginalname'] }}</p>
                                        @endif
                                        <div class="announcement-card__footer">
                                            <span @class(['rule-status', 'rule-status--active' => (bool) $announcement['isactive']])>{{ (bool) $announcement['isactive'] ? 'Ativo' : 'Inativo' }}</span>
                                            @if ((bool) $announcement['isactive'])
                                                <details class="rule-actions rule-actions--icon announcement-actions">
                                                    <summary aria-label="Editar ou remover comunicado" title="Editar ou remover comunicado"><img class="manage-icon" src="{{ asset('assets/images/settings-admin.svg') }}" alt="" aria-hidden="true"></summary>
                                                    <div class="rule-actions-panel announcement-actions-panel">
                                                        <form class="announcement-edit-form" method="POST" action="{{ route('admin.announcements.update', $announcement['announcementid']) }}" enctype="multipart/form-data">
                                                            @csrf
                                                            <input type="hidden" name="has_attachment" value="{{ empty($announcement['attachmentid']) ? 0 : 1 }}">
                                                            <div class="announcement-edit-schedule">
                                                                <div><label for="announcement-scope-{{ $announcement['announcementid'] }}">Âmbito</label><select id="announcement-scope-{{ $announcement['announcementid'] }}" name="scope"><option value="global" @selected($announcement['announcementscope'] === 'global')>Global</option><option value="pvc" @selected($announcement['announcementscope'] === 'pvc')>PVC</option><option value="aluminio" @selected($announcement['announcementscope'] === 'aluminio')>Alumínio</option></select></div>
                                                                <div><label for="starts-at-{{ $announcement['announcementid'] }}">Início</label><input id="starts-at-{{ $announcement['announcementid'] }}" name="starts_at" type="datetime-local" value="{{ \Carbon\CarbonImmutable::parse((string) $announcement['startsat'], config('production.timezone'))->format('Y-m-d\TH:i') }}" required></div>
                                                                <div><label for="ends-at-{{ $announcement['announcementid'] }}">Fim</label><input id="ends-at-{{ $announcement['announcementid'] }}" name="ends_at" type="datetime-local" value="{{ $announcement['endsat'] === null ? '' : \Carbon\CarbonImmutable::parse((string) $announcement['endsat'], config('production.timezone'))->format('Y-m-d\TH:i') }}"></div>
                                                            </div>
                                                            <div><label for="announcement-title-{{ $announcement['announcementid'] }}">Título</label><input id="announcement-title-{{ $announcement['announcementid'] }}" name="title" maxlength="160" value="{{ $announcement['title'] }}" required></div>
                                                            <div><label for="announcement-body-{{ $announcement['announcementid'] }}">Mensagem</label><textarea id="announcement-body-{{ $announcement['announcementid'] }}" name="body" maxlength="8000" rows="4">{{ $announcement['body'] }}</textarea></div>
                                                            <div class="announcement-edit-attachment">
                                                                <label for="announcement-attachment-{{ $announcement['announcementid'] }}">{{ empty($announcement['attachmentid']) ? 'Adicionar imagem' : 'Substituir imagem' }}</label>
                                                                @if (! empty($announcement['attachmentid']))
                                                                    <p class="dashboard-field-hint">Imagem atual: {{ $announcement['attachmentoriginalname'] }}</p>
                                                                @endif
                                                                <div class="attachment-picker"><input id="announcement-attachment-{{ $announcement['announcementid'] }}" class="attachment-picker__input" name="attachment" type="file" accept="image/jpeg,image/png,image/webp" onchange="this.parentElement.querySelector('[data-attachment-name]').textContent = this.files.length ? this.files[0].name : ''"><label class="attachment-picker__button" for="announcement-attachment-{{ $announcement['announcementid'] }}" title="Anexar imagem"><img src="{{ asset('assets/images/paperclip.svg') }}" alt="Anexar imagem"></label><span class="attachment-picker__name" data-attachment-name></span></div>
                                                                @if (! empty($announcement['attachmentid']))
                                                                    <label class="confirm"><input type="checkbox" name="remove_attachment" value="1"> Remover a imagem atual (a mensagem passa a ser obrigatória)</label>
                                                                @endif
                                                            </div>
                                                            <button class="dashboard-button dashboard-button--secondary" type="submit">Guardar</button>
                                                        </form>
                                                        <div class="rule-danger-actions">
                                                            <form class="rule-action-form" method="POST" action="{{ route('admin.announcements.deactivate', $announcement['announcementid']) }}">@csrf<label class="confirm"><input type="checkbox" name="confirm" value="1" required> Confirmo a desativação</label><button class="link-button" type="submit">Desativar</button></form>
                                                            <form class="rule-action-form" method="POST" action="{{ route('admin.announcements.delete', $announcement['announcementid']) }}">@csrf<label class="confirm"><input type="checkbox" name="confirm_delete" value="1" required> Confirmo a remoção definitiva</label><button class="link-button link-button--danger" type="submit">Remover</button></form>
                                                        </div>
                                                    </div>
                                                </details>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <div class="dashboard-card announcement-empty">Sem comunicados registados.</div>
                                @endforelse
                            </div>
                        </div>
                    </section>
                @endif
            </div>
        </main>
    </div>
@endsection
