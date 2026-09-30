<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Http\Requests\QaThread\IndexRequest;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\ShowAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板 Controller。3 ロール共通の閲覧導線(index / show)を提供する。
 *
 * - student: 公開中資格の質問一覧 / 詳細 + 質問投稿 / 編集 / 削除 / 解決・未解決変更
 * - coach: 担当資格の質問一覧 / 詳細
 * - admin: 全資格の質問一覧 / 詳細 + 質問削除
 *
 * 表示要素のロール差は Blade の `@can` / `auth()->user()->role` 判定で出し分ける。
 */
class QaThreadController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();
        $viewer = $request->user();

        $threads = $action(
            auth: $viewer,
            keyword: $validated['keyword'] ?? null,
            certificationId: $validated['certification_id'] ?? null,
            status: $validated['status'] ?? null,
        );

        $certifications = match ($viewer->role) {
            UserRole::Student => Certification::query()
                ->published()
                ->orderBy('name')
                ->get(),

            UserRole::Coach => $viewer->assignedCertifications()
                ->published()
                ->orderBy('name')
                ->get(),

            UserRole::Admin => Certification::query()
                ->orderBy('name')
                ->get(),

            default => collect(),
        };

        return view('qa-thread.index', [
            'threads' => $threads,
            'certifications' => $certifications,
            'filters' => $validated,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function show(QaThread $thread, ShowAction $action): View
    {
        $this->authorize('view', $thread);

        return view('qa-thread.show', [
            'thread' => $action($thread),
        ]);
    }

    public function create(): View
    {
        return view('qa-thread.create', [
            'certifications' => Certification::query()->published()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $thread = $action($request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', [
            'thread' => $thread,
        ]);
    }

    public function update(QaThread $thread, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $action(auth()->user(), $thread);

        $route = auth()->user()->role === UserRole::Admin
            ? 'admin.qa-board.index'
            : 'qa-board.index';

        return redirect()
            ->route($route)
            ->with('success', '質問を削除しました。');
    }

    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を解決済みにしました。');
    }

    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を未解決に戻しました。');
    }
}
