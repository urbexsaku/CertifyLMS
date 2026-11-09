<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiChat\GeminiApiException;
use App\Models\Enrollment;
use App\Models\Section;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gemini APIとの通信を担当するService。
 */
final class GeminiService
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @return array{
     *     content: string,
     *     model_name: string,
     *     input_tokens: ?int,
     *     output_tokens: ?int,
     *     response_time_ms: int
     * }
     */
    public function generateResponse(
        array $messages,
        ?Section $section = null,
        ?Enrollment $enrollment = null,
    ): array {
        $startedAt = microtime(true);

        $apiKey = (string) config('ai-chat.gemini.api_key');
        $model = (string) config('ai-chat.gemini.model');

        if ($apiKey === '' || $model === '') {
            throw new GeminiApiException(
                'Gemini APIの設定が不足しています。',
                configurationError: true,
            );
        }

        try {
            $response = Http::timeout((int) config('ai-chat.gemini.timeout'))
                ->acceptJson()
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                ])
                ->post(
                    sprintf(
                        '%s/models/%s:generateContent',
                        rtrim((string) config('ai-chat.gemini.base_url'), '/'),
                        $model,
                    ),
                    [
                        'systemInstruction' => [
                            'parts' => [
                                ['text' => $this->buildSystemPrompt(
                                    $section,
                                    $enrollment,
                                )],
                            ],
                        ],
                        'contents' => $this->formatMessages($messages),
                        'generationConfig' => [
                            'temperature' => (float) config('ai-chat.gemini.temperature'),
                        ],
                    ],
                );
        } catch (Throwable $exception) {
            throw new GeminiApiException(
                'Gemini APIとの通信に失敗しました。',
                null,
                $exception,
            );
        }

        if (! $response->successful()) {
            Log::warning(
                'Gemini API request failed.',
                [
                    'status' => $response->status(),
                    'model' => $model,
                    'response_body' => $response->body(),
                ],
            );

            throw new GeminiApiException(
                'Gemini APIから正常な応答を取得できませんでした。',
                $response->status(),
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new GeminiApiException(
                'Gemini APIのレスポンス形式が不正です。',
                $response->status(),
            );
        }

        $content = $this->extractText($data);

        if ($content === '') {
            throw new GeminiApiException(
                'Gemini APIの回答本文が空です。',
                $response->status(),
            );
        }

        $usageMetadata = $data['usageMetadata'] ?? [];

        return [
            'content' => $content,
            'model_name' => $model,
            'input_tokens' => isset($usageMetadata['promptTokenCount'])
                ? (int) $usageMetadata['promptTokenCount']
                : null,
            'output_tokens' => isset($usageMetadata['candidatesTokenCount'])
                ? (int) $usageMetadata['candidatesTokenCount']
                : null,
            'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /**
     * 初回の質問と回答から会話タイトルを生成する。
     */
    public function generateTitle(
        string $question,
        string $answer,
    ): string {
        $apiKey = (string) config('ai-chat.gemini.api_key');
        $model = (string) config('ai-chat.gemini.model');

        if ($apiKey === '' || $model === '') {
            throw new GeminiApiException(
                'Gemini APIの設定が不足しています。',
                configurationError: true,
            );
        }

        $maxLength = (int) config('ai-chat.title.max_length', 30);

        try {
            $response = Http::timeout((int) config('ai-chat.gemini.timeout'))
                ->acceptJson()
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                ])
                ->post(
                    sprintf(
                        '%s/models/%s:generateContent',
                        rtrim((string) config('ai-chat.gemini.base_url'), '/'),
                        $model,
                    ),
                    [
                        'systemInstruction' => [
                            'parts' => [
                                [
                                    'text' => sprintf(
                                        'あなたは会話タイトルを作成するアシスタントです。'
                                        .'質問と回答の内容を踏まえ、会話の主題を表すタイトルを作成してください。'
                                        .'タイトルは日本語で%d文字以内にしてください。'
                                        .'タイトル以外の説明、記号、引用符は出力しないでください。',
                                        $maxLength,
                                    ),
                                ],
                            ],
                        ],
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    [
                                        'text' => "質問：\n{$question}\n\n回答：\n{$answer}",
                                    ],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.2,
                        ],
                    ],
                );
        } catch (Throwable $exception) {
            throw new GeminiApiException(
                'Gemini APIによるタイトル生成に失敗しました。',
                null,
                $exception,
            );
        }

        if (! $response->successful()) {
            throw new GeminiApiException(
                'Gemini APIによるタイトル生成に失敗しました。',
                $response->status(),
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new GeminiApiException(
                'Gemini APIのタイトル生成レスポンス形式が不正です。',
                $response->status(),
            );
        }

        $title = $this->extractText($data);

        $title = preg_replace('/\s+/u', ' ', $title) ?? '';
        $title = preg_replace(
            '/^[\s"\'「」『』]+|[\s"\'「」『』]+$/u',
            '',
            $title,
        ) ?? '';

        if ($title === '') {
            throw new GeminiApiException(
                'Gemini APIによるタイトル生成結果が空です。',
                $response->status(),
            );
        }

        return mb_substr($title, 0, $maxLength);
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function formatMessages(array $messages): array
    {
        return array_map(
            fn (array $message): array => [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [
                    ['text' => $message['content']],
                ],
            ],
            $messages,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractText(array $data): string
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];

        if (! is_array($parts)) {
            return '';
        }

        return collect($parts)
            ->filter(fn ($part): bool => is_array($part) && isset($part['text']))
            ->pluck('text')
            ->implode("\n");
    }

    private function buildSystemPrompt(
        ?Section $section,
        ?Enrollment $enrollment,
    ): string {
        $prompt = (string) config('ai-chat.prompts.system');

        if ($section !== null) {
            $section->loadMissing([
                'chapter.part.certification',
            ]);

            $certification = $section->chapter?->part?->certification;

            $context = [
                '資格名：'.($certification?->name ?? '未設定'),
                '教材タイトル：'.$section->title,
                '教材本文：'.($section->body ?? ''),
            ];

            return $prompt
                ."\n\n"
                ."以下は受講生が学習している教材情報です。回答時の参考情報として使用してください。\n"
                .implode("\n", $context);
        }

        if ($enrollment !== null) {
            $enrollment->loadMissing('certification');

            $certification = $enrollment->certification;

            if ($certification !== null) {
                $context = [
                    '資格名：'.$certification->name,
                    '資格説明：'.($certification->description ?? ''),
                ];

                return $prompt
                    ."\n\n"
                    ."以下は受講生が学習している資格情報です。回答時の参考情報として使用してください。\n"
                    .implode("\n", $context);
            }
        }

        return $prompt;
    }
}
