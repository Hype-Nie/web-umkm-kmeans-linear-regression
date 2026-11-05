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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ml' => [
        'base_url' => env('PYTHON_ML_API_URL', 'http://127.0.0.1:8000'),
        'endpoints' => [
            'kmeans_cluster' => env('ML_API_KMEANS_CLUSTER_ENDPOINT', '/cluster/kmeans'),
            'kmeans_predict' => env('ML_API_KMEANS_PREDICT_ENDPOINT', '/predict/kmeans'),
            'regression_forecast' => env('ML_API_REGRESSION_FORECAST_ENDPOINT', '/forecast/linear-regression'),
            'regression_predict' => env('ML_API_REGRESSION_PREDICT_ENDPOINT', '/predict/linear-regression'),
            'model_info' => env('ML_API_MODEL_INFO_ENDPOINT', '/model/info'),
        ],
    ],

];
