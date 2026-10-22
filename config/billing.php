<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Plans
    |--------------------------------------------------------------------------
    |
    | Define the subscription plans offered by your application.
    |
    | Each plan requires a Stripe price ID. You can create prices in the
    | Stripe dashboard and then add their IDs here.
    |
    */

    'plans' => [

        'free' => [
            'name' => 'Free',
            'stripe_price' => env('STRIPE_PRICE_FREE', 'price_1Free'), // placeholder
            'button' => false, // whether to display button to upgrade/downgrade
        ],

        'basic' => [
            'name' => 'Basic',
            'stripe_price' => env('STRIPE_PRICE_BASIC', 'price_1Basic'),
            'button' => true,
        ],

        'pro' => [
            'name' => 'Pro',
            'stripe_price' => env('STRIPE_PRICE_PRO', 'price_1Pro'),
            'button' => true,
        ],

    ],

];