@php
    $reportQuery = [...$period->query(), 'product_sort' => $product_sort];
    $reportErrors = $module_errors;
    $exportLabels = [
        'summary' => 'KPI summary',
        'sales' => 'Sales by date',
        'funnel' => 'Conversion funnel',
        'products' => 'Products',
        'content' => 'Content',
        'shows' => 'Shows',
    ];
    $formatVariation = static function (?array $variation, string $unit = ''): string {
        if ($variation === null) {
            return 'Comparison N/A';
        }

        $absolute = $variation['absolute_label'].$unit;

        return $absolute.' · '.$variation['percentage_label'];
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Reports | Reny Renteria CMS</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="admin-cms-body admin-stats-body" data-theme="neutral" data-admin-current-section="stats" data-analytics-screen="admin_reports">
        @include('admin.partials.header', ['showSidebarToggle' => false, 'adminSection' => 'stats'])

        <main class="admin-stats-main" aria-labelledby="stats-title">
            <header class="stats-page-heading">
                <div>
                    <p class="stats-eyebrow">BUSINESS DASHBOARD</p>
                    <h1 id="stats-title">Reports</h1>
                    <p>{{ $period->label() }} · {{ $period->timezone }}</p>
                </div>

                <details class="stats-export-menu">
                    <summary>Export CSV</summary>
                    <div>
                        @foreach ($exportLabels as $report => $label)
                            <a href="{{ route('admin.reports.export', ['report' => $report, ...$reportQuery]) }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </details>
            </header>

            <form class="stats-filter-panel" method="GET" action="{{ route('admin.dashboard') }}" data-report-filter>
                <fieldset>
                    <legend>Reporting period</legend>
                    <div class="stats-preset-options">
                        @foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days', '12m' => '12 months', 'custom' => 'Custom'] as $value => $label)
                            <label>
                                <input type="radio" name="period" value="{{ $value }}" @checked($period->preset === $value)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="stats-custom-range" data-report-custom-range @if ($period->preset !== 'custom') hidden @endif>
                    <label>
                        From
                        <input type="date" name="from" value="{{ old('from', $period->preset === 'custom' ? $period->start->toDateString() : '') }}" @required($period->preset === 'custom')>
                    </label>
                    <label>
                        To
                        <input type="date" name="to" value="{{ old('to', $period->preset === 'custom' ? $period->end->toDateString() : '') }}" @required($period->preset === 'custom')>
                    </label>
                </div>

                <input type="hidden" name="product_sort" value="{{ $product_sort }}">
                <button class="stats-apply-button" type="submit">Apply range</button>
                <p class="stats-filter-status" role="status" aria-live="polite" data-report-filter-status>
                    Comparing with {{ $period->previousLabel() }}. Timezone: {{ $period->timezone }}.
                </p>
            </form>

            @if ($errors->any())
                <div class="stats-validation-error" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="stats-summary-grid" aria-label="Report summary">
                <article class="stats-summary-card stats-summary-card-sales" data-report-module="net-sales">
                    <div class="stats-card-label">
                        <p>Net sales</p>
                        <abbr tabindex="0" title="Completed payments in the active range minus refunds recorded in the active range. Currencies stay separate.">i</abbr>
                    </div>
                    @if ($reportErrors['commerce'])
                        <strong class="is-error">N/A</strong>
                        <a class="stats-retry" href="{{ request()->fullUrl() }}">Retry</a>
                    @else
                        <div class="stats-money-stack">
                            @foreach ($kpis['sales'] as $sales)
                                <strong>{{ $sales['current'] }}</strong>
                                <span>{{ $formatVariation($sales['variation'], ' '.$sales['currency']) }} vs previous</span>
                            @endforeach
                        </div>
                    @endif
                    @include('admin.partials.report-skeleton')
                </article>

                <article class="stats-summary-card" data-report-module="completed-orders">
                    <div class="stats-card-label">
                        <p>Completed orders</p>
                        <abbr tabindex="0" title="Distinct captured payments. Refunding later does not erase the original conversion.">i</abbr>
                    </div>
                    <strong @class(['is-error' => $reportErrors['commerce']])>{{ $reportErrors['commerce'] ? 'N/A' : number_format($kpis['orders']['current']) }}</strong>
                    <span>{{ $reportErrors['commerce'] ? 'Could not load' : $formatVariation($kpis['orders']['variation']) .' vs previous' }}</span>
                    @include('admin.partials.report-skeleton')
                </article>

                <article class="stats-summary-card" data-report-module="active-royals">
                    <div class="stats-card-label">
                        <p>Active Royals</p>
                        <abbr tabindex="0" title="Memberships active now. The current data model cannot reconstruct this metric at a historical range end.">i</abbr>
                    </div>
                    <strong @class(['is-error' => $reportErrors['audience']])>{{ $reportErrors['audience'] ? 'N/A' : number_format($kpis['royals']['current']) }}</strong>
                    <span>Current snapshot · comparison N/A</span>
                    @include('admin.partials.report-skeleton')
                </article>

                <article class="stats-summary-card" data-report-module="new-users">
                    <div class="stats-card-label">
                        <p>New users</p>
                        <abbr tabindex="0" title="Fan accounts created in the active range. Staff accounts are excluded.">i</abbr>
                    </div>
                    <strong @class(['is-error' => $reportErrors['audience']])>{{ $reportErrors['audience'] ? 'N/A' : number_format($kpis['users']['current']) }}</strong>
                    <span>{{ $reportErrors['audience'] ? 'Could not load' : $formatVariation($kpis['users']['variation']) .' vs previous' }}</span>
                    @include('admin.partials.report-skeleton')
                </article>
            </section>

            <section class="stats-panel stats-sales-panel" aria-labelledby="sales-title" data-report-module="sales-chart">
                <div class="stats-section-head">
                    <div>
                        <p class="stats-eyebrow">REVENUE</p>
                        <h2 id="sales-title">Net sales over time</h2>
                    </div>
                    <div class="stats-chart-legend" aria-label="Chart legend">
                        <span><i class="is-current"></i> Active period</span>
                        <span><i class="is-previous"></i> Previous period</span>
                        <span><i class="is-negative"></i> Net refund</span>
                    </div>
                </div>

                @if ($commerce_coverage['status'] === 'partial')
                    <p class="stats-coverage-note">Partial sales coverage: {{ number_format($commerce_coverage['legacy_orders']) }} legacy completed {{ \Illuminate\Support\Str::plural('order', $commerce_coverage['legacy_orders']) }} use the recorded order creation time because no exact capture timestamp exists.</p>
                @endif

                @if ($reportErrors['commerce'])
                    <div class="stats-module-state is-error" role="alert">
                        <strong>Sales data could not be loaded.</strong>
                        <a href="{{ request()->fullUrl() }}">Retry this module</a>
                    </div>
                @else
                    @foreach ($sales_charts as $chart)
                        <article class="stats-currency-chart" aria-labelledby="sales-{{ strtolower($chart['currency']) }}">
                            <div class="stats-currency-chart-head">
                                <h3 id="sales-{{ strtolower($chart['currency']) }}">{{ $chart['currency'] }}</h3>
                                <span>Scale max {{ $chart['maximum'] }}</span>
                            </div>
                            <div class="stats-chart-frame">
                                <div class="stats-y-axis" aria-hidden="true">
                                    <span>{{ $chart['maximum'] }}</span>
                                    <span>0</span>
                                </div>
                                <div class="stats-chart-scroll" tabindex="0" aria-label="Scrollable {{ $chart['currency'] }} sales chart">
                                    <div class="stats-comparison-bars" style="--stats-points: {{ count($chart['points']) }}">
                                        @foreach ($chart['points'] as $point)
                                            <div class="stats-chart-point">
                                                <div class="stats-bar-pair">
                                                    <button
                                                        type="button"
                                                        @class(['stats-comparison-bar', 'is-current', 'is-negative' => $point['current_cents'] < 0, 'is-zero' => $point['current_cents'] === 0])
                                                        style="--bar-height: {{ $point['current_height'] }}%;"
                                                        aria-label="{{ $point['current_range'] }} active net sales {{ $point['current'] }}; comparison {{ $point['previous_range'] }} net sales {{ $point['previous'] }}"
                                                    >
                                                        <span class="stats-chart-tooltip">
                                                            <strong>{{ $point['current_range'] }}</strong>
                                                            <span>Active: {{ $point['current'] }}</span>
                                                            <span>Comparison: {{ $point['previous_range'] }} · {{ $point['previous'] }}</span>
                                                        </span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @class(['stats-comparison-bar', 'is-previous', 'is-negative' => $point['previous_cents'] < 0, 'is-zero' => $point['previous_cents'] === 0])
                                                        style="--bar-height: {{ $point['previous_height'] }}%;"
                                                        aria-label="{{ $point['previous_range'] }} comparison net sales {{ $point['previous'] }}; active {{ $point['current_range'] }} net sales {{ $point['current'] }}"
                                                    >
                                                        <span class="stats-chart-tooltip">
                                                            <strong>{{ $point['previous_range'] }}</strong>
                                                            <span>Comparison: {{ $point['previous'] }}</span>
                                                            <span>Active: {{ $point['current_range'] }} · {{ $point['current'] }}</span>
                                                        </span>
                                                    </button>
                                                </div>
                                                <small>{{ $point['label'] }}</small>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                @endif
                @include('admin.partials.report-skeleton')
            </section>

            <div class="stats-dashboard-grid">
                <section class="stats-panel" aria-labelledby="funnel-title" data-report-module="funnel">
                    <div class="stats-section-head">
                        <div>
                            <p class="stats-eyebrow">CONVERSION</p>
                            <h2 id="funnel-title">Store funnel</h2>
                        </div>
                    </div>

                    @if ($reportErrors['funnel'])
                        <div class="stats-module-state is-error" role="alert">
                            <strong>Funnel data could not be loaded.</strong>
                            <a href="{{ request()->fullUrl() }}">Retry this module</a>
                        </div>
                    @else
                        @if ($funnel['coverage']['status'] === 'partial')
                            <p class="stats-coverage-note">Partial coverage: events are available from {{ $funnel['coverage']['label'] }}.</p>
                        @elseif ($funnel['coverage']['status'] === 'unavailable')
                            <p class="stats-coverage-note is-empty">No funnel events captured yet. This is not reported as a conversion rate.</p>
                        @endif

                        <ol class="stats-funnel-list">
                            @foreach ($funnel['stages'] as $stage)
                                <li>
                                    <span>{{ $stage['label'] }}</span>
                                    <strong>{{ number_format($stage['sessions']) }} sessions</strong>
                                    <small>{{ number_format($stage['events']) }} events</small>
                                    @if (! $loop->first)
                                        <em>{{ $stage['conversion']['label'] ?? 'N/A' }} from prior step</em>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                        <div class="stats-diagnostic-row">
                            <span>Failed payments</span>
                            <strong>{{ number_format($funnel['failures']['sessions']) }} sessions</strong>
                            <small>{{ number_format($funnel['failures']['events']) }} events</small>
                        </div>
                    @endif
                    @include('admin.partials.report-skeleton')
                </section>

                <section class="stats-panel" aria-labelledby="products-title" data-report-module="products">
                    <div class="stats-section-head">
                        <div>
                            <p class="stats-eyebrow">COMMERCE</p>
                            <h2 id="products-title">Top products</h2>
                        </div>
                        <nav class="stats-sort-links" aria-label="Sort products">
                            <a @class(['is-active' => $product_sort === 'sales']) href="{{ route('admin.dashboard', [...$period->query(), 'product_sort' => 'sales']) }}">Sales</a>
                            <a @class(['is-active' => $product_sort === 'units']) href="{{ route('admin.dashboard', [...$period->query(), 'product_sort' => 'units']) }}">Units</a>
                        </nav>
                    </div>

                    @if ($reportErrors['commerce'])
                        <div class="stats-module-state is-error" role="alert">Product data could not be loaded.</div>
                    @elseif ($products === [])
                        <div class="stats-module-state is-empty">No completed or refunded products in this range.</div>
                    @else
                        <div class="stats-table-scroll" tabindex="0">
                            <table class="stats-table">
                                <thead><tr><th>Product</th><th>Net sales</th><th>Units</th><th>Orders</th></tr></thead>
                                <tbody>
                                    @foreach ($products as $product)
                                        <tr>
                                            <th scope="row"><span>{{ $product['title'] }}</span><small>{{ $product['kind'] }}</small></th>
                                            <td>{{ $product['net_sales'] }}</td>
                                            <td>{{ number_format($product['units']) }}</td>
                                            <td>{{ number_format($product['orders']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @include('admin.partials.report-skeleton')
                </section>
            </div>

            <div class="stats-dashboard-grid">
                <section class="stats-panel" aria-labelledby="content-title" data-report-module="content">
                    <div class="stats-section-head">
                        <div>
                            <p class="stats-eyebrow">ENGAGEMENT</p>
                            <h2 id="content-title">Top content</h2>
                        </div>
                    </div>

                    @if ($reportErrors['content'])
                        <div class="stats-module-state is-error" role="alert">Content data could not be loaded.</div>
                    @elseif ($content === [])
                        <div class="stats-module-state is-empty">No music or video plays captured in this range.</div>
                    @else
                        <ol class="stats-ranking-list">
                            @foreach ($content as $item)
                                <li>
                                    <span class="stats-rank">{{ $loop->iteration }}</span>
                                    <div><strong>{{ $item['title'] }}</strong><small>{{ ucfirst($item['type']) }} · {{ $item['metric'] }}</small></div>
                                    <div><strong>{{ number_format($item['events']) }}</strong><small>{{ number_format($item['sessions']) }} sessions</small></div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    @include('admin.partials.report-skeleton')
                </section>

                <section class="stats-panel" aria-labelledby="shows-title" data-report-module="shows">
                    <div class="stats-section-head">
                        <div>
                            <p class="stats-eyebrow">LIVE</p>
                            <h2 id="shows-title">Shows</h2>
                        </div>
                    </div>

                    @if ($reportErrors['shows'])
                        <div class="stats-module-state is-error" role="alert">Show data could not be loaded.</div>
                    @elseif ($shows === [])
                        <div class="stats-module-state is-empty">No shows or show activity in this range.</div>
                    @else
                        <div class="stats-table-scroll" tabindex="0">
                            <table class="stats-table stats-shows-table">
                                <thead><tr><th>Show</th><th>RSVP</th><th>Tickets</th><th>Check-ins</th><th>Rates</th></tr></thead>
                                <tbody>
                                    @foreach ($shows as $show)
                                        <tr>
                                            <th scope="row"><span>{{ $show['title'] }}</span><small>{{ $show['starts_at_label'] }}</small></th>
                                            <td>{{ number_format($show['rsvps']) }}</td>
                                            <td>{{ $show['tickets'] === null ? 'N/A' : number_format($show['tickets']) }}</td>
                                            <td>{{ $show['check_ins'] === null ? 'Not available' : number_format($show['check_ins']) }}</td>
                                            <td>
                                                <small>RSVP → ticket: {{ $show['rsvp_to_ticket']['label'] ?? 'N/A' }}</small>
                                                <small>Ticket → check-in: {{ $show['ticket_to_check_in']['label'] ?? 'N/A' }}</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @include('admin.partials.report-skeleton')
                </section>
            </div>

            <footer class="stats-report-notes">
                <p><strong>Definitions:</strong> payment totals come from orders, membership status from users, RSVP and check-ins from canonical ticket/RSVP records, and interaction reports from anonymized analytics sessions.</p>
                <p>Legacy completed orders without a capture timestamp use their recorded order creation time and are treated as partial historical coverage.</p>
            </footer>
        </main>
    </body>
</html>
