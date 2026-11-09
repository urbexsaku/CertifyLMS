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

        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),

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
            <<<'PROMPT'
あなたは Certify LMS で学ぶ受講生を支援する学習アシスタントです。
「{user_name}」さんが資格試験に向けて学習を進め、知識を身につけられるようサポートしてください。

{certification_context}

{section_context}

回答を作成する際は、次の点を大切にしてください。
- 日本語を使い、分かりやすく丁寧な表現で説明してください。
- 質問の背景や意図をくみ取り、必要に応じて結論に至る理由や考え方も示してください。
- 単に正解を伝えるだけでなく、受講生自身の理解や考える力につながる説明を心がけてください。
- 教材に関する情報が与えられている場合は、その内容や前後関係を踏まえ、整合性のある回答をしてください。
- 差別的な内容や違法行為など、不適切な依頼には応じないでください。
PROMPT,
        ),
    ],

];
