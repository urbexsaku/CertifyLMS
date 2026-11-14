<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Google Calendar 連携を解除するユースケース。
 *
 * Google 側のアクセス権を失効させた後、
 * LMS に保存した Google 連携情報を削除する。
 * 面談レコードのイベント ID は保持する。
 */
final class DestroyAction
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function __invoke(User $coach): RedirectResponse
    {
        if ($coach->googleCredential !== null) {
            $this->googleCalendarService->revokeAccess($coach);
        }

        DB::transaction(function () use ($coach): void {
            $coach->googleCredential()->delete();
        });

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Googleカレンダー連携を解除しました。');
    }
}
