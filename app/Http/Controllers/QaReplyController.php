<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板の回答 Controller。回答の投稿・編集・削除を提供する。
 *
 * - student: 公開中資格のスレッドへの回答投稿 / 本人の回答の編集・削除
 * - coach: 担当資格のスレッドへの回答投稿 / 本人の回答の編集・削除
 * - admin: 回答削除
 */
class QaReplyController extends Controller
{
    public function store(StoreRequest $request, StoreAction $action, QaThread $thread): RedirectResponse
    {
        $this->authorize('create', [QaReply::class, $thread]);

        $action($thread, $request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(QaThread $thread, QaReply $reply): View
    {
        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'thread' => $thread,
            'reply' => $reply,
        ]);
    }

    public function update(QaThread $thread, QaReply $reply, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $this->authorize('update', $reply);

        $action($reply, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(QaThread $thread, QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $reply);

        $action($reply);

        $route = auth()->user()->role === UserRole::Admin
            ? 'admin.qa-board.show'
            : 'qa-board.show';

        return redirect()
            ->route($route, $thread)
            ->with('success', '回答を削除しました。');
    }
}
