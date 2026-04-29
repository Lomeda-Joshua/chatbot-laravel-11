<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SSO Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Single Sign-On integration with Project 1
    |
    */

    'project1' => [
        'url' => env('PROJECT1_URL', 'https://one.f-dci.com'),
        'api_timeout' => env('PROJECT1_API_TIMEOUT', 10),
    ],

    'session' => [
        'check_interval' => env('SSO_SESSION_CHECK_INTERVAL', 300), // 5 minutes in seconds
        'token_expiry' => env('SSO_TOKEN_EXPIRY', 1800), // 30 minutes in seconds
    ],

    'security' => [
        'require_https' => env('SSO_REQUIRE_HTTPS', false),
        'allowed_redirects' => [
            env('APP_URL', 'https://api.chatbot.f-dci.com'),
        ],
    ],


];
