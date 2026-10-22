<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Inicio de sesion con Google (Google Identity Services).
    | GOOGLE_CLIENT_ID: ID de cliente OAuth de tipo "Aplicacion web" (Google Cloud).
    | La app Android usa ese mismo ID como serverClientId, asi que sus tokens
    | tambien llevan ese "aud". GOOGLE_CLIENT_IDS_EXTRA admite otros IDs separados por coma.
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_ids' => array_values(array_filter(array_map('trim', array_merge(
            [(string) env('GOOGLE_CLIENT_ID', '')],
            explode(',', (string) env('GOOGLE_CLIENT_IDS_EXTRA', ''))
        )))),
        'tokeninfo_url' => 'https://oauth2.googleapis.com/tokeninfo',
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
