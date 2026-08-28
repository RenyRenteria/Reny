@php
    $money = static fn (int $cents, string $currency): string => $currency.' '.number_format($cents / 100, 2);
    $numberChange = static function (array $comparison): string {
        $absolute = (int) $comparison['absolute'];
        $percent = $comparison['percent'];

        return ($absolute > 0 ? '+' : '').number_format($absolute).' · '.($percent === null ? 'N/A' : (($percent > 0 ? '+' : '').number_format($percent, 1).'%'));
    };
    $moneyChange = static function (array $comparison, string $currency) use ($money): string {
        $absolute = (int) $comparison['absolute'];
        $percent = $comparison['percent'];

        return ($absolute > 0 ? '+' : '').$money($absolute, $currency).' · '.($percent === null ? 'N/A' : (($percent > 0 ? '+' : '').number_format($percent, 1).'%'));
    };
    $exportUrl = static fn (string $report): string => route('admin.reports.export', [
        ...$range->query(),
        'report' => $report,
        'product_sort' => $productSort,
    ]);
    $retryUrl = route('admin.dashboard', [...$range->query(), 'product_sort' => $productSort]);
    $activityCards = [
        [
            'key' => 'homepageSessions',
            'label' => 'Sesiones únicas en homepage',
        ],
        [
            'key' => 'paywallViews',
            'label' => 'Bloqueos de paywall',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="reny-report-default-query" content="{{ http_build_query($range->query()) }}">

        <title>Reportes | Reny Renteria CMS</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="admin-cms-body admin-stats-body" data-theme="neutral" data-admin-current-section="stats">
        @include('admin.partials.header', ['showSidebarToggle' => false, 'adminSection' => 'stats'])

        <main class="admin-stats-main" aria-labelledby="stats-title">
            <header class="stats-page-head">
                <div>
                    <p class="stats-eyebrow">CMS · negocio</p>
                    <h1 id="stats-title">Reportes</h1>
                    <p>Del {{ $range->startLocal->isoFormat('D MMM YYYY') }} al {{ $range->endExclusiveLocal->subDay()->isoFormat('D MMM YYYY') }}</p>
                </div>
                <span class="stats-timezone" aria-label="Zona horaria usada para todos los límites de fecha">{{ $range->timezone }}</span>
            </header>

            <form class="stats-filter" method="GET" action="{{ route('admin.dashboard') }}" data-report-filter>
                <fieldset class="stats-presets">
                    <legend>Rango global</legend>
                    @foreach ([
                        '7d' => '7 días',
                        '30d' => '30 días',
                        '90d' => '90 días',
                        '12m' => '12 meses',
                        'custom' => 'Personalizado',
                    ] as $value => $label)
                        <div class="stats-preset-option">
                            <input id="stats-preset-{{ $value }}" type="radio" name="preset" value="{{ $value }}" @checked($range->preset === $value)>
                            <label for="stats-preset-{{ $value }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </fieldset>

                <div class="stats-custom-dates" data-custom-dates>
                    <label>
                        Desde
                        <input type="date" name="start" value="{{ $range->startDate() }}" @disabled($range->preset !== 'custom')>
                    </label>
                    <label>
                        Hasta
                        <input type="date" name="end" value="{{ $range->endDate() }}" @disabled($range->preset !== 'custom')>
                    </label>
                </div>

                <input type="hidden" name="product_sort" value="{{ $productSort }}">
                <button class="stats-primary-button" type="submit">Aplicar rango</button>
                <p class="stats-filter-status" role="status" aria-live="polite" data-report-status></p>
            </form>

            <div class="stats-loading" aria-hidden="true" data-report-loading>
                <span></span><span></span><span></span><span></span>
            </div>

            @if ($reports['commerce_coverage']['status'] === 'error')
                <p class="stats-module-state is-partial">No se pudo verificar la cobertura histórica de ventas.</p>
            @elseif ($reports['commerce_coverage']['data']['status'] === 'partial')
                <p class="stats-module-state is-partial">{{ $reports['commerce_coverage']['data']['message'] }} ({{ number_format($reports['commerce_coverage']['data']['legacy_orders']) }})</p>
            @endif

            @if ($reports['kpis']['status'] === 'error')
                <section class="stats-module-state is-error" role="alert">
                    <strong>KPIs no disponibles</strong>
                    <p>{{ $reports['kpis']['message'] }}</p>
                    <a href="{{ $retryUrl }}">Reintentar</a>
                </section>
            @else
                @php
                    $kpis = $reports['kpis']['data'];
                @endphp
                <section class="stats-summary-grid" aria-label="KPIs principales">
                    <article class="stats-summary-card stats-summary-card-wide">
                        <div class="stats-card-label">
                            <p>Ventas netas</p>
                        </div>
                        @foreach ($kpis['sales'] as $sales)
                            <div class="stats-card-money-row">
                                <strong>{{ $money($sales['current'], $sales['currency']) }}</strong>
                                <span @class(['is-positive' => $sales['absolute'] > 0, 'is-negative' => $sales['absolute'] < 0])>
                                    {{ $moneyChange($sales, $sales['currency']) }}
                                </span>
                            </div>
                        @endforeach
                    </article>

                    <article class="stats-summary-card">
                        <div class="stats-card-label">
                            <p>Órdenes completadas</p>
                        </div>
                        <strong>{{ number_format($kpis['orders']['current']) }}</strong>
                        <span @class(['is-positive' => $kpis['orders']['absolute'] > 0, 'is-negative' => $kpis['orders']['absolute'] < 0])>{{ $numberChange($kpis['orders']) }}</span>
                    </article>

                    <article class="stats-summary-card">
                        <div class="stats-card-label">
                            <p>Royals activos</p>
                        </div>
                        <strong>{{ number_format($kpis['royals']['current']) }}</strong>
                        <span>Snapshot actual · sin comparación histórica</span>
                    </article>

                    <article class="stats-summary-card">
                        <div class="stats-card-label">
                            <p>Nuevos usuarios</p>
                        </div>
                        <strong>{{ number_format($kpis['users']['current']) }}</strong>
                        <span @class(['is-positive' => $kpis['users']['absolute'] > 0, 'is-negative' => $kpis['users']['absolute'] < 0])>{{ $numberChange($kpis['users']) }}</span>
                    </article>
                </section>
            @endif

            <section class="stats-summary-grid" aria-label="Actividad del sitio en el rango">
                @foreach ($activityCards as $card)
                    @php
                        $metric = $activityStats[$card['key']];
                    @endphp
                    <article class="stats-summary-card" data-activity-kpi="{{ $card['key'] }}">
                        <div class="stats-card-label">
                            <p>{{ $card['label'] }}</p>
                        </div>
                        <strong @class(['is-error' => $metric['has_error']])>{{ $metric['has_error'] ? 'N/A' : number_format($metric['current']) }}</strong>
                        @if ($metric['has_error'])
                            <span>No se pudo consultar este indicador</span>
                        @else
                            <span @class(['is-positive' => $metric['absolute'] > 0, 'is-negative' => $metric['absolute'] < 0])>{{ $numberChange($metric) }}</span>
                        @endif
                    </article>
                @endforeach
            </section>

            @if ($reports['audience']['status'] === 'error')
                <section class="stats-module-state is-error" role="alert">
                    <strong>Audiencia no disponible</strong>
                    <p>{{ $reports['audience']['message'] }}</p>
                    <a href="{{ $retryUrl }}">Reintentar</a>
                </section>
            @else
                @php
                    $audience = $reports['audience']['data'];
                @endphp
                <section class="stats-report-panel" aria-labelledby="audience-title">
                    <div class="stats-panel-head">
                        <div>
                            <p class="stats-eyebrow">Personas anónimas · sesiones de 30 minutos</p>
                            <h2 id="audience-title">Audiencia</h2>
                        </div>
                        <a href="{{ $exportUrl('audience') }}">Exportar CSV</a>
                    </div>
                    @if ($audience['coverage_unavailable'])
                        <p class="stats-module-state is-unavailable">La medición de visitantes empieza con esta versión; aún no hay datos identificados en el rango.</p>
                    @elseif ($audience['coverage_partial'])
                        <p class="stats-module-state is-partial">Cobertura parcial. Visitantes identificados desde {{ $audience['available_from'] }}; {{ number_format($audience['identified_page_view_percent'] ?? 0, 1) }}% de las vistas del rango tienen identificador.</p>
                    @else
                        <p class="stats-coverage">Cobertura de identidad desde {{ $audience['available_from'] }} · {{ number_format($audience['identified_page_view_percent'] ?? 0, 1) }}% de vistas identificadas.</p>
                    @endif
                    <div class="stats-summary-grid" aria-label="Resumen de audiencia">
                        @foreach ([
                            ['key' => 'visitors', 'label' => 'Visitantes únicos'],
                            ['key' => 'sessions', 'label' => 'Sesiones'],
                            ['key' => 'page_views', 'label' => 'Vistas de página'],
                            ['key' => 'new_visitors', 'label' => 'Visitantes nuevos'],
                            ['key' => 'returning_visitors', 'label' => 'Visitantes recurrentes'],
                        ] as $card)
                            @php $metric = $audience[$card['key']]; @endphp
                            <article class="stats-summary-card">
                                <div class="stats-card-label"><p>{{ $card['label'] }}</p></div>
                                <strong>{{ number_format($metric['current']) }}</strong>
                                <span @class(['is-positive' => $metric['absolute'] > 0, 'is-negative' => $metric['absolute'] < 0])>{{ $numberChange($metric) }}</span>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <details class="stats-definitions">
                <summary>Cómo se calculan estas métricas</summary>
                <dl>
                    @if ($reports['kpis']['status'] === 'ready')
                        <div><dt>Ventas netas</dt><dd>{{ $kpis['definitions']['sales'] }}</dd></div>
                        <div><dt>Órdenes completadas</dt><dd>{{ $kpis['definitions']['orders'] }}</dd></div>
                        <div><dt>Royals activos</dt><dd>{{ $kpis['definitions']['royals'] }}</dd></div>
                        <div><dt>Nuevos usuarios</dt><dd>{{ $kpis['definitions']['users'] }}</dd></div>
                    @endif
                    <div><dt>Sesiones únicas en homepage</dt><dd>{{ $activityStats['definitions']['homepageSessions'] }}</dd></div>
                    <div><dt>Bloqueos de paywall</dt><dd>{{ $activityStats['definitions']['paywallViews'] }}</dd></div>
                    @if ($reports['audience']['status'] === 'ready')
                        <div><dt>Visitantes únicos</dt><dd>{{ $audience['definitions']['visitors'] }}</dd></div>
                        <div><dt>Sesiones</dt><dd>{{ $audience['definitions']['sessions'] }}</dd></div>
                        <div><dt>Vistas de página</dt><dd>{{ $audience['definitions']['page_views'] }}</dd></div>
                        <div><dt>Nuevos vs recurrentes</dt><dd>{{ $audience['definitions']['new_returning'] }}</dd></div>
                    @endif
                </dl>
            </details>

            @if ($reports['kpis']['status'] === 'ready')
                <div class="stats-export-row">
                    <a href="{{ $exportUrl('summary') }}">Exportar resumen CSV</a>
                </div>
            @endif

            <section class="stats-report-panel" aria-labelledby="acquisition-title">
                <div class="stats-panel-head">
                    <div>
                        <p class="stats-eyebrow">UTM · buscadores · social · referidos</p>
                        <h2 id="acquisition-title">Adquisición</h2>
                    </div>
                    <a href="{{ $exportUrl('acquisition') }}">Exportar CSV</a>
                </div>
                @if ($reports['acquisition']['status'] === 'error')
                    <div class="stats-module-state is-error" role="alert"><strong>Adquisición no disponible</strong><p>{{ $reports['acquisition']['message'] }}</p><a href="{{ $retryUrl }}">Reintentar</a></div>
                @else
                    @php $acquisition = $reports['acquisition']['data']; @endphp
                    @if ($acquisition['coverage_unavailable'])
                        <p class="stats-module-state is-unavailable">La atribución empieza con esta versión; aún no hay tráfico clasificado en el rango.</p>
                    @elseif ($acquisition['coverage_partial'])
                        <p class="stats-module-state is-partial">Cobertura parcial. La atribución está disponible desde {{ $acquisition['available_from'] }}; {{ number_format($acquisition['attributed_page_view_percent'] ?? 0, 1) }}% de las vistas del rango están clasificadas.</p>
                    @else
                        <p class="stats-coverage">Atribución disponible desde {{ $acquisition['available_from'] }} · {{ number_format($acquisition['attributed_page_view_percent'] ?? 0, 1) }}% de vistas clasificadas.</p>
                    @endif

                    @if (! $acquisition['coverage_unavailable'] && $acquisition['channels'] !== [])
                        <h3 class="stats-subsection-title">Canales y campañas</h3>
                        <div class="stats-table-scroll"><table><thead><tr><th>Fuente / medio</th><th>Campaña</th><th>Visitantes</th><th>Sesiones</th><th>Anterior</th><th>Vistas</th></tr></thead><tbody>
                            @foreach ($acquisition['channels'] as $channel)
                                <tr><th scope="row"><span>{{ $channel['traffic_source'] ?? 'Sin atribución' }}</span><small>{{ $channel['traffic_medium'] ?? 'desconocido' }}</small></th><td>{{ $channel['traffic_campaign'] ?? '—' }}</td><td>{{ number_format($channel['visitors']) }}</td><td>{{ number_format($channel['sessions']) }}</td><td>{{ number_format($channel['previous_sessions']) }}</td><td>{{ number_format($channel['page_views']) }}</td></tr>
                            @endforeach
                        </tbody></table></div>

                        <div class="stats-breakdown-grid">
                            <section aria-labelledby="device-title">
                                <h3 id="device-title" class="stats-subsection-title">Dispositivos</h3>
                                <div class="stats-table-scroll"><table><thead><tr><th>Tipo</th><th>Visitantes</th><th>Sesiones</th><th>Vistas</th></tr></thead><tbody>
                                    @foreach ($acquisition['devices'] as $device)
                                        <tr><th scope="row">{{ match ($device['device_category']) { 'desktop' => 'Computadora', 'mobile' => 'Móvil', 'tablet' => 'Tablet', default => 'Desconocido' } }}</th><td>{{ number_format($device['visitors']) }}</td><td>{{ number_format($device['sessions']) }}</td><td>{{ number_format($device['page_views']) }}</td></tr>
                                    @endforeach
                                </tbody></table></div>
                            </section>
                            <section aria-labelledby="country-title">
                                <h3 id="country-title" class="stats-subsection-title">Países</h3>
                                <div class="stats-table-scroll"><table><thead><tr><th>País</th><th>Visitantes</th><th>Sesiones</th><th>Vistas</th></tr></thead><tbody>
                                    @foreach (collect($acquisition['countries'])->take(10) as $country)
                                        <tr><th scope="row">{{ $country['country_code'] ?? 'Desconocido' }}</th><td>{{ number_format($country['visitors']) }}</td><td>{{ number_format($country['sessions']) }}</td><td>{{ number_format($country['page_views']) }}</td></tr>
                                    @endforeach
                                </tbody></table></div>
                            </section>
                        </div>
                    @elseif (! $acquisition['coverage_unavailable'])
                        <p class="stats-module-state is-empty">No hubo vistas de página en el rango.</p>
                    @endif
                @endif
            </section>

            <section class="stats-report-panel" aria-labelledby="sales-title">
                <div class="stats-panel-head">
                    <div>
                        <p class="stats-eyebrow">Comparación de igual duración</p>
                        <h2 id="sales-title">Ventas netas</h2>
                    </div>
                    <a href="{{ $exportUrl('sales') }}">Exportar CSV</a>
                </div>

                @if ($reports['sales']['status'] === 'error')
                    <div class="stats-module-state is-error" role="alert">
                        <strong>Gráfica no disponible</strong>
                        <p>{{ $reports['sales']['message'] }}</p>
                        <a href="{{ $retryUrl }}">Reintentar</a>
                    </div>
                @else
                    @php
                        $salesReport = $reports['sales']['data'];
                    @endphp
                    @if ($salesReport['is_empty'])
                        <p class="stats-module-state is-empty">La consulta terminó correctamente: no hubo ventas ni reembolsos en ambos periodos.</p>
                    @endif

                    @foreach ($salesReport['series'] as $series)
                        @php
                            $values = collect($series['points'])->flatMap(fn (array $point): array => [$point['current_cents'], $point['previous_cents']]);
                            $maxValue = max(0, (int) $values->max());
                            $minValue = min(0, (int) $values->min());
                            $span = max(1, $maxValue - $minValue);
                            $count = max(1, count($series['points']));
                            $currentPath = collect($series['points'])->map(function (array $point, int $index) use ($count, $maxValue, $span): string {
                                $x = $count === 1 ? 500 : ($index / ($count - 1)) * 1000;
                                $y = 20 + (($maxValue - $point['current_cents']) / $span) * 180;
                                return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
                            })->implode(' ');
                            $previousPath = collect($series['points'])->map(function (array $point, int $index) use ($count, $maxValue, $span): string {
                                $x = $count === 1 ? 500 : ($index / ($count - 1)) * 1000;
                                $y = 20 + (($maxValue - $point['previous_cents']) / $span) * 180;
                                return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
                            })->implode(' ');
                        @endphp
                        <article class="stats-chart-card">
                            <div class="stats-chart-meta">
                                <strong>{{ $series['currency'] }}</strong>
                                <div aria-label="Leyenda">
                                    <span class="is-current">Periodo actual</span>
                                    <span class="is-previous">Periodo anterior</span>
                                </div>
                            </div>
                            <div class="stats-line-chart">
                                <span class="stats-axis-max">{{ $money($maxValue, $series['currency']) }}</span>
                                <span class="stats-axis-min">{{ $money($minValue, $series['currency']) }}</span>
                                <svg viewBox="0 0 1000 220" role="img" aria-label="Ventas netas {{ $series['currency'] }} por {{ $salesReport['granularity'] === 'day' ? 'día' : 'mes' }}">
                                    <line x1="0" x2="1000" y1="{{ 20 + (($maxValue - 0) / $span) * 180 }}" y2="{{ 20 + (($maxValue - 0) / $span) * 180 }}" class="stats-zero-line" />
                                    <polyline points="{{ $previousPath }}" class="stats-series-previous" />
                                    <polyline points="{{ $currentPath }}" class="stats-series-current" />
                                    @foreach ($series['points'] as $index => $point)
                                        @php
                                            $x = $count === 1 ? 500 : ($index / ($count - 1)) * 1000;
                                            $currentY = 20 + (($maxValue - $point['current_cents']) / $span) * 180;
                                            $previousY = 20 + (($maxValue - $point['previous_cents']) / $span) * 180;
                                        @endphp
                                        <circle tabindex="0" cx="{{ $x }}" cy="{{ $currentY }}" r="5" class="stats-point-current">
                                            <title>{{ $point['date'] }}: {{ $money($point['current_cents'], $series['currency']) }}; comparación {{ $point['previous_date'] }}: {{ $money($point['previous_cents'], $series['currency']) }}</title>
                                        </circle>
                                        <circle tabindex="0" cx="{{ $x }}" cy="{{ $previousY }}" r="4" class="stats-point-previous">
                                            <title>{{ $point['previous_date'] }}: {{ $money($point['previous_cents'], $series['currency']) }}</title>
                                        </circle>
                                    @endforeach
                                </svg>
                            </div>
                            <div class="stats-chart-dates" aria-hidden="true">
                                <span>{{ $series['points'][0]['label'] ?? '—' }}</span>
                                <span>{{ $series['points'][count($series['points']) - 1]['label'] ?? '—' }}</span>
                            </div>
                        </article>
                    @endforeach
                @endif
            </section>

            <section class="stats-report-panel" aria-labelledby="funnel-title">
                <div class="stats-panel-head">
                    <div>
                        <p class="stats-eyebrow">Sesiones únicas · eventos como detalle</p>
                        <h2 id="funnel-title">Embudo de conversión</h2>
                    </div>
                    <a href="{{ $exportUrl('funnel') }}">Exportar CSV</a>
                </div>

                @if ($reports['funnel']['status'] === 'error')
                    <div class="stats-module-state is-error" role="alert"><strong>Embudo no disponible</strong><p>{{ $reports['funnel']['message'] }}</p><a href="{{ $retryUrl }}">Reintentar</a></div>
                @else
                    @php
                        $funnel = $reports['funnel']['data'];
                    @endphp
                    @if ($funnel['coverage_unavailable'])
                        <p class="stats-module-state is-unavailable">No disponible: todavía no existen eventos de interacción persistidos.</p>
                    @elseif ($funnel['coverage_partial'])
                        <p class="stats-module-state is-partial">{{ $funnel['coverage_message'] ?? 'Cobertura parcial. Datos disponibles desde '.$funnel['available_from'].'.' }}</p>
                    @else
                        <p class="stats-coverage">Datos disponibles desde {{ $funnel['available_from'] ?? $range->startDate() }}.</p>
                    @endif
                    <div class="stats-funnel-grid">
                        @foreach ($funnel['steps'] as $step)
                            <article>
                                <span>{{ $step['label'] }}</span>
                                <strong>{{ number_format($step['current']['sessions']) }}</strong>
                                <small>{{ number_format($step['current']['events']) }} eventos · anterior {{ number_format($step['previous']['sessions']) }} sesiones</small>
                                @if ($step['conversion'] !== null)
                                    <b>{{ number_format($step['conversion'], 1) }}% desde el paso anterior</b>
                                @elseif ($step['conversion_reason'] === 'incomparable_sessions')
                                    <b>N/A · compras sin sesión analítica trazable</b>
                                @elseif ($step['key'] !== 'visits')
                                    <b>N/A · denominador cero</b>
                                @endif
                            </article>
                        @endforeach
                    </div>
                    <div class="stats-failed-payments">
                        <span>Pagos fallidos (diagnóstico, fuera de conversión)</span>
                        <strong>{{ number_format($funnel['failed']['sessions']) }} sesiones</strong>
                        <small>{{ number_format($funnel['failed']['events']) }} eventos</small>
                    </div>
                @endif
            </section>

            <section class="stats-report-panel" aria-labelledby="products-title">
                <div class="stats-panel-head">
                    <div><p class="stats-eyebrow">Fuente transaccional</p><h2 id="products-title">Productos</h2></div>
                    <div class="stats-panel-actions">
                        <form method="GET" action="{{ route('admin.dashboard') }}">
                            @foreach ($range->query() as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                            <label>Ordenar por
                                <select name="product_sort" onchange="this.form.submit()">
                                    <option value="sales" @selected($productSort === 'sales')>ventas netas</option>
                                    <option value="units" @selected($productSort === 'units')>unidades</option>
                                </select>
                            </label>
                        </form>
                        <a href="{{ $exportUrl('products') }}">Exportar CSV</a>
                    </div>
                </div>
                @if ($reports['products']['status'] === 'error')
                    <div class="stats-module-state is-error" role="alert"><strong>Productos no disponibles</strong><p>{{ $reports['products']['message'] }}</p><a href="{{ $retryUrl }}">Reintentar</a></div>
                @elseif ($reports['products']['data'] === [])
                    <p class="stats-module-state is-empty">No hubo ventas ni reembolsos de productos en el rango.</p>
                @else
                    <div class="stats-table-scroll"><table><thead><tr><th>Producto</th><th>Tipo</th><th>Ventas netas</th><th>Unidades</th><th>Órdenes</th></tr></thead><tbody>
                        @foreach ($reports['products']['data'] as $product)
                            <tr><th scope="row"><span>{{ $product['title'] }}</span><small>{{ $product['product_key'] }}</small></th><td>{{ $product['type'] }}</td><td>{{ $money($product['net_cents'], $product['currency']) }}</td><td>{{ number_format($product['units']) }}</td><td>{{ number_format($product['orders']) }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </section>

            <section class="stats-report-panel" aria-labelledby="content-title">
                <div class="stats-panel-head"><div><p class="stats-eyebrow">Métrica etiquetada por tipo</p><h2 id="content-title">Contenido</h2></div><a href="{{ $exportUrl('content') }}">Exportar CSV</a></div>
                @if ($reports['content']['status'] === 'error')
                    <div class="stats-module-state is-error" role="alert"><strong>Contenido no disponible</strong><p>{{ $reports['content']['message'] }}</p><a href="{{ $retryUrl }}">Reintentar</a></div>
                @elseif ($reports['content']['data']['coverage_unavailable'])
                    <p class="stats-module-state is-partial">No disponible: {{ $reports['content']['data']['available_from'] ? 'los eventos de contenido se capturan desde '.$reports['content']['data']['available_from'].'.' : 'todavía no existen eventos de contenido persistidos.' }}</p>
                @elseif ($reports['content']['data']['rows'] === [])
                    <p class="stats-module-state is-empty">No hay interacciones de contenido registradas en este rango.</p>
                @else
                    @if ($reports['content']['data']['coverage_partial'])
                        <p class="stats-module-state is-partial">Cobertura parcial. Datos disponibles desde {{ $reports['content']['data']['available_from'] }}.</p>
                    @endif
                    <div class="stats-table-scroll"><table><thead><tr><th>Contenido</th><th>Tipo</th><th>Métrica</th><th>Total</th><th>Sesiones únicas</th></tr></thead><tbody>
                        @foreach ($reports['content']['data']['rows'] as $content)
                            <tr><th scope="row"><span>{{ $content['title'] }}</span><small>{{ $content['resource_key'] }}</small></th><td>{{ $content['type'] }}</td><td>{{ $content['metric'] }}</td><td>{{ number_format($content['interactions']) }}</td><td>{{ number_format($content['sessions']) }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </section>

            <section class="stats-report-panel" aria-labelledby="shows-title">
                <div class="stats-panel-head"><div><p class="stats-eyebrow">RSVP, ticketing y check-in canónicos</p><h2 id="shows-title">Shows</h2></div><a href="{{ $exportUrl('shows') }}">Exportar CSV</a></div>
                @if ($reports['shows']['status'] === 'error')
                    <div class="stats-module-state is-error" role="alert"><strong>Shows no disponibles</strong><p>{{ $reports['shows']['message'] }}</p><a href="{{ $retryUrl }}">Reintentar</a></div>
                @elseif ($reports['shows']['data'] === [])
                    <p class="stats-module-state is-empty">No hubo RSVP, tickets o check-ins en el rango.</p>
                @else
                    <div class="stats-table-scroll"><table><thead><tr><th>Show</th><th>RSVP</th><th>Tickets</th><th>Check-ins</th><th>RSVP → ticket</th><th>Ticket → check-in</th></tr></thead><tbody>
                        @foreach ($reports['shows']['data'] as $show)
                            <tr><th scope="row"><span>{{ $show['title'] }}</span><small>{{ $show['event_key'] }}</small></th><td>{{ number_format($show['rsvps']) }}</td><td>{{ number_format($show['tickets']) }}</td><td>{{ number_format($show['checkins']) }}</td><td>{{ $show['rsvp_to_ticket'] === null ? 'N/A' : number_format($show['rsvp_to_ticket'], 1).'%' }}</td><td>{{ $show['ticket_to_checkin'] === null ? 'N/A' : number_format($show['ticket_to_checkin'], 1).'%' }}<small>cohorte de tickets pagados en el rango</small></td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </section>
        </main>
    </body>
</html>
