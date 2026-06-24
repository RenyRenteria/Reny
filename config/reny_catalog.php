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
        'posts' => [
            [
                'key' => 'studio-note-from-reny',
                'title' => 'Studio note from Reny',
                'time' => 'Today',
                'body' => 'Finishing the next release window with final vocal edits, choreography notes, and visuals for the fan club first.',
                'full_body' => 'Finishing the next release window with final vocal edits, choreography notes, and visuals for the fan club first. I am keeping the first look inside the community because the next chapter should feel close, early, and built with the people who keep showing up.',
                'image_url' => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1400&q=80',
                'image_alt' => 'Warm recording studio with microphone and instruments',
                'base_likes' => 284,
                'base_replies' => 38,
            ],
            [
                'key' => 'capri-photo-drop',
                'title' => 'Capri photo drop',
                'time' => 'This week',
                'body' => 'A few frames from the travel archive are moving into the Photos tab. More country-specific drops coming next.',
                'full_body' => 'A few frames from the travel archive are moving into the Photos tab. More country-specific drops coming next, especially where fans have been organizing watch parties and meetups.',
                'image_url' => 'https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=1400&q=80',
                'image_alt' => 'Capri coastline and turquoise sea',
                'base_likes' => 319,
                'base_replies' => 51,
            ],
        ],
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
