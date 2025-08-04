<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => [
        'api/*', 
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'register'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        '*', // Para desarrollo - cambiar en producción
        'http://localhost:3000',
        'http://localhost:8081',
        'http://localhost:19006',
        'https://kompapay.onrender.com',
    ],

    'allowed_origins_patterns' => [
        'http://localhost:*',
        'http://127.0.0.1:*',
        'http://192.168.*',
        'http://10.0.*',
    ],

    'allowed_headers' => [
        '*',
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [
        'Authorization',
    ],

    'max_age' => 86400, // 24 horas

    'supports_credentials' => true,

];
