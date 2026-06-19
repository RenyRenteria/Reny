@php
    $summaryCards = [
        [
            'label' => 'Homepage Views',
            'value' => number_format($stats['homepageViews']),
            'is_zero' => $stats['homepageViews'] <= 0,
            'tone' => 'small',
        ],
        [
            'label' => 'Paywall Views',
            'value' => number_format($stats['paywallViews']),
            'is_zero' => $stats['paywallViews'] <= 0,
            'tone' => 'small',
        ],
        [
            'label' => 'Royal Members',
            'value' => number_format($stats['royalMembers']),
            'is_zero' => $stats['royalMembers'] <= 0,
            'tone' => 'small',
        ],
        [
            'label' => 'Monthly Sales',
            'value' => '$'.number_format($stats['monthlySales'], 0),
            'is_zero' => $stats['monthlySales'] <= 0,
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
    <body class="admin-cms-body admin-stats-body" data-theme="neutral" data-admin-current-section="dashboard">
        @include('admin.partials.header', ['showSidebarToggle' => false, 'adminSection' => 'dashboard'])

        <main class="admin-stats-main" aria-labelledby="stats-title">
            <h1 id="stats-title" class="sr-only">Stats</h1>

            <section class="stats-summary-grid" aria-label="Monthly stats summary">
                @foreach ($summaryCards as $card)
                    <article class="stats-summary-card stats-summary-card-{{ $card['tone'] }}">
                        <p>{{ $card['label'] }}</p>
                        <strong @class(['is-zero' => $card['is_zero']])>{{ $card['value'] }}</strong>
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
                            <span @class(['is-zero' => $tick['is_zero']])>{{ $tick['label'] }}</span>
                        @endforeach
                    </div>

                    <div class="stats-bars" aria-label="Sales by month">
                        @foreach ($salesChart['points'] as $point)
                            <div @class(['stats-bar-column', 'is-zero' => $point['is_zero']])>
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
