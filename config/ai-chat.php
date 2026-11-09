<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI Chat Settings
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('AI_CHAT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Gemini API
    |--------------------------------------------------------------------------
    */

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),

        'base_url' => env(
            'GEMINI_BASE_URL',
            'https://generativelanguage.googleapis.com/v1beta',
        ),

        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),

        'timeout' => (int) env('GEMINI_TIMEOUT', 30),

        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Settings
    |--------------------------------------------------------------------------
    */

    'conversation' => [
        'history_limit' => (int) env('AI_CHAT_HISTORY_LIMIT', 20),

        'daily_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Title Generation
    |--------------------------------------------------------------------------
    */

    'title' => [
        'auto_generation_enabled' => (bool) env(
            'AI_CHAT_TITLE_AUTO_GENERATION_ENABLED',
            true,
        ),

        'provisional' => '新規相談',

        'max_length' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    */

    'prompts' => [
        'system' => env(
            'AI_CHAT_SYSTEM_PROMPT',
            'あなたは学習支援を行うAIアシスタントです。'
            .'学習者の理解度に配慮し、質問に対して分かりやすく丁寧に回答してください。'
            .'答えを一方的に提示するだけでなく、必要に応じて考え方や解き方も説明してください。'
            .'学習コンテキストに含まれる情報を踏まえて回答してください。',
        ),
    ],

];
