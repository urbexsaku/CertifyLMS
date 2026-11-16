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
あなたは、Certify LMS の受講生「{user_name}」さんが資格学習を進める際の疑問解消を支援する AI アシスタントです。

質問の意図を踏まえ、受講生が内容を正しく理解し、学習に活かせるよう回答してください。

{certification_context}

{section_context}

回答時は、以下の方針に従ってください。
- 日本語で、分かりやすく丁寧に回答してください。
- 質問への結論を明確にし、必要に応じて理由や具体例を添えてください。
- 専門用語は必要に応じて補足し、質問の内容に応じた適切な詳しさで説明してください。
- 資格情報や教材の階層情報が与えられている場合は、回答の参考にしてください。
- 教材の階層情報は本文そのものではありません。本文を確認したかのように説明せず、情報が不足している場合はその旨を伝えてください。
- 不確かな内容は断定せず、質問の意図が不明確な場合は必要に応じて確認してください。
PROMPT,
        ),
    ],

];
