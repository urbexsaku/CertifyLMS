<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\UseCases\GoogleCalendar\CallbackAction;
use App\UseCases\GoogleCalendar\DestroyAction;
use App\UseCases\GoogleCalendar\RedirectAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * コーチのGoogle Calendar連携に関するHTTPエントリポイント。
 *
 * OAuth認証開始・コールバック・連携解除のリクエストを受け付け、
 * 各業務処理をActionへ委譲する。
 */
class GoogleCalendarController extends Controller
{
    public function redirect(Request $request, RedirectAction $action): RedirectResponse
    {
        return $action($request->user(), $request->query('redirect_path'));
    }

    public function callback(Request $request, CallbackAction $action): RedirectResponse
    {
        return $action($request->user(), $request);
    }

    public function destroy(Request $request, DestroyAction $action): RedirectResponse
    {
        return $action($request->user());
    }
}
