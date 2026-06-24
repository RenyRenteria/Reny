<?php

return [
    'languages' => [
        'en' => 'English',
        'es' => 'Spanish',
    ],

    'currencies' => [
        'USD' => [
            'label' => 'US Dollar',
            'symbol' => '$',
        ],
        'EUR' => [
            'label' => 'Euro',
            'symbol' => 'EUR ',
        ],
        'GBP' => [
            'label' => 'Pound Sterling',
            'symbol' => 'GBP ',
        ],
        'DOP' => [
            'label' => 'Dominican Peso',
            'symbol' => 'RD$',
        ],
    ],

    'paypal_manage_url' => env('PAYPAL_MANAGE_SUBSCRIPTIONS_URL', 'https://www.paypal.com/myaccount/autopay/'),
];
