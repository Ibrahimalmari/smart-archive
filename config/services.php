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

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        'embedding_dimensions' => env('OLLAMA_EMBEDDING_DIMENSIONS', 0),
        'timeout' => env('OLLAMA_TIMEOUT', 30),
        'search_min_score' => env('OLLAMA_SEARCH_MIN_SCORE', 0.45),
    ],

    'document_content' => [
        'driver' => env('DOCUMENT_CONTENT_DRIVER', 'database'),
        'strict' => env('DOCUMENT_CONTENT_STRICT', false),
        'mongodb_uri' => env('DOCUMENT_CONTENT_MONGODB_URI', 'mongodb://127.0.0.1:27017'),
        'mongodb_database' => env('DOCUMENT_CONTENT_MONGODB_DATABASE', 'smart_archive'),
        'mongodb_collection' => env('DOCUMENT_CONTENT_MONGODB_COLLECTION', 'document_contents'),
    ],

];
