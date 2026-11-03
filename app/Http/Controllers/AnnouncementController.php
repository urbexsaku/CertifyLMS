<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Announcement\StoreRequest;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\UseCases\Announcement\IndexAction;
use App\UseCases\Announcement\ShowAction;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * お知らせ配信履歴の管理画面 Controller。
 *
 * - 管理者向けのお知らせ配信履歴一覧・詳細表示
 * - お知らせ作成画面の表示・配信処理
 */
class AnnouncementController extends Controller
{
    public function index(IndexAction $action): View
    {
        return view('announcement.management.index', ['announcements' => $action()]);
    }

    public function show(Announcement $announcement, ShowAction $action): View
    {
        $this->authorize('view', $announcement);

        return view('announcement.management.show', ['announcement' => $action($announcement)]);
    }

    public function create(): View
    {
        $this->authorize('create', Announcement::class);

        return view('announcement.management.create', [
            'certifications' => Certification::query()
                ->published()
                ->orderBy('name')
                ->get(),
            'students' => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $announcement = $action($request->user(), $request->validated());

        $announcement->refresh();

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success', "お知らせを配信しました ({$announcement->dispatched_count}件)。");
    }
}
