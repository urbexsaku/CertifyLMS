<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI チャット機能の有効状態を確認する Middleware。
 */
class EnsureAiChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            config('ai-chat.enabled'),
            404,
        );

        return $next($request);
    }
}
