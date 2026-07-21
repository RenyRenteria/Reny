<?php

return [
    'products' => [
        'deluxe' => [
            'title' => 'Deluxe Digital Album',
            'amount_cents' => 2400,
            'kind' => 'digital',
            'unlock_type' => 'album',
        ],
        'singles' => [
            'title' => 'Singles / Digital Pack',
            'amount_cents' => 800,
            'kind' => 'digital',
            'unlock_type' => 'album',
        ],
        'royal' => [
            'title' => 'Royal Pass',
            'amount_cents' => 499,
            'kind' => 'subscription',
            'unlock_type' => null,
        ],
        'merch' => [
            'title' => 'Signature Merch',
            'amount_cents' => 4800,
            'kind' => 'merch',
            'unlock_type' => null,
        ],
        'print' => [
            'title' => 'Numbered Art Print',
            'amount_cents' => 8600,
            'kind' => 'art_drop',
            'unlock_type' => 'drop',
        ],
        'concert' => [
            'title' => 'Reny Live - Studio Night',
            'amount_cents' => 4200,
            'kind' => 'ticket',
            'unlock_type' => null,
            'event' => [
                'title' => 'Reny Live - Studio Night',
                'venue' => 'Panama City',
                'address' => 'Panama City',
                'starts_at' => '2026-08-24 20:00:00',
                'timezone' => 'America/Panama',
            ],
        ],
        'listening' => [
            'title' => 'Festival de la Rosa Dorada',
            'amount_cents' => 1500,
            'kind' => 'ticket',
            'unlock_type' => null,
            'event' => [
                'title' => 'Festival de la Rosa Dorada',
                'venue' => 'Rock & Folk Pty, Ciudad de Panama',
                'address' => 'Rock & Folk Pty, Ciudad de Panama',
                'starts_at' => '2026-12-19 19:30:00',
                'timezone' => 'America/Panama',
            ],
        ],
    ],

    'community' => [
        'live_chat' => [
            'pinned_message' => 'Bienvenidos al chat oficial de Reny. Sé amable, evita spam y reporta cualquier situación al equipo.',
        ],
        'posts' => [],
        'poll' => [
            'key' => 'which-drop-should-go-first',
            'question' => 'Which drop should go first?',
            'options' => [
                ['key' => 'studio-photos', 'label' => 'Studio photos', 'votes' => 524],
                ['key' => 'performance-stills', 'label' => 'Performance stills', 'votes' => 424],
                ['key' => 'travel-archive', 'label' => 'Travel archive', 'votes' => 300],
            ],
        ],
        'clubs' => [
            [
                'key' => 'dominican-republic',
                'name' => 'Dominican Republic',
                'flag_label' => 'DO',
                'base_members' => 8400,
                'activity' => 'Planning Santo Domingo party',
                'messages' => [
                    ['author' => 'Mia', 'text' => 'Who is going to the first meetup?'],
                    ['author' => 'Luis', 'text' => 'We should pin a date after the next Reny post.'],
                ],
            ],
            [
                'key' => 'panama',
                'name' => 'Panama',
                'flag_label' => 'PA',
                'base_members' => 6900,
                'activity' => 'Sharing radio clips',
                'messages' => [
                    ['author' => 'Ana', 'text' => 'Radio clips thread is ready for the next drop.'],
                    ['author' => 'Marco', 'text' => 'Panama City listening party list is almost full.'],
                ],
            ],
            [
                'key' => 'colombia',
                'name' => 'Colombia',
                'flag_label' => 'CO',
                'base_members' => 4200,
                'activity' => 'Building the Bogota map',
                'messages' => [
                    ['author' => 'Sofia', 'text' => 'Bogota map is open for venue ideas.'],
                    ['author' => 'Leo', 'text' => 'Medellin fans should get a second pin too.'],
                ],
            ],
        ],
    ],

    'rsvp_events' => [
        'concert' => [
            'title' => 'Reny Renteria en Concierto',
            'venue' => 'Rock & Folk Pty, Ciudad de Panama',
            'address' => 'Rock & Folk Pty, Ciudad de Panama',
            'starts_at' => '2026-09-21 19:30:00',
            'timezone' => 'America/Panama',
        ],
        'making' => [
            'title' => 'Making The Deluxe Album',
            'venue' => 'Royal Stream',
            'address' => 'Royal Stream',
            'starts_at' => '2026-08-31 19:00:00',
            'timezone' => 'America/Panama',
        ],
    ],
];
