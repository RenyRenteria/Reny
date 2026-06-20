@php
    $statsErrors = $statsErrors ?? [];
    $salesChartError = $statsErrors['salesChart'] ?? false;
    $summaryCards = [
        [
            'label' => 'Homepage Views',
            'value' => number_format($stats['homepageViews']),
            'has_error' => $statsErrors['homepageViews'] ?? false,
            'tone' => 'small',
        ],
        [
            'label' => 'Paywall Views',
            'value' => number_format($stats['paywallViews']),
            'has_error' => $statsErrors['paywallViews'] ?? false,
            'tone' => 'small',
        ],
        [
            'label' => 'Royal Members',
            'value' => number_format($stats['royalMembers']),
            'has_error' => $statsErrors['royalMembers'] ?? false,
            'tone' => 'small',
        ],
        [
            'label' => 'Monthly Sales',
            'value' => '$'.number_format($stats['monthlySales'], 0),
            'has_error' => $statsErrors['monthlySales'] ?? false,
            'tone' => 'wide',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Stats | Reny Renteria CMS</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="admin-cms-body admin-stats-body" data-theme="neutral" data-admin-current-section="stats">
        @include('admin.partials.header', ['showSidebarToggle' => false, 'adminSection' => 'stats'])

        <main class="admin-stats-main" aria-labelledby="stats-title">
            <h1 id="stats-title" class="sr-only">Stats</h1>

            <section class="stats-summary-grid" aria-label="Monthly stats summary">
                @foreach ($summaryCards as $card)
                    <article class="stats-summary-card stats-summary-card-{{ $card['tone'] }}">
                        <p>{{ $card['label'] }}</p>
                        <strong{!! $card['has_error'] ? ' class="is-error"' : '' !!}>{{ $card['value'] }}</strong>
                    </article>
                @endforeach
            </section>

            <section class="stats-sales-panel" aria-labelledby="sales-title">
                <div class="stats-sales-head">
                    <h2 id="sales-title">SALES</h2>
                    <div class="stats-range-toggle" aria-label="Chart range">
                        <button type="button" aria-pressed="false">Yearly</button>
                        <button type="button" aria-pressed="true">Monthly</button>
                    </div>
                </div>

                <div class="stats-chart">
                    <div class="stats-y-axis" aria-hidden="true">
                        @foreach ($salesChart['ticks'] as $tick)
                            <span{!! $salesChartError ? ' class="is-error"' : '' !!}>{{ $tick['label'] }}</span>
                        @endforeach
                    </div>

                    <div class="stats-bars" aria-label="Sales by month">
                        @foreach ($salesChart['points'] as $point)
                            <div @class(['stats-bar-column', 'is-empty' => $point['is_zero'], 'is-error' => $salesChartError])>
                                <span class="stats-bar-value">{{ $point['compact'] }}</span>
                                <div
                                    class="stats-bar"
                                    style="--bar-height: {{ $point['height'] }}%;"
                                    aria-label="{{ $point['month'] }} sales: {{ $point['compact'] }}"
                                ></div>
                                <span class="stats-bar-month">{{ $point['month'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
