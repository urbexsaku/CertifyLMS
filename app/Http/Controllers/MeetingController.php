<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\Meeting\AvailabilityRequest;
use App\Http\Requests\Meeting\IndexAsCoachRequest;
use App\Http\Requests\Meeting\IndexRequest;
use App\Http\Requests\Meeting\StoreRequest;
use App\Http\Requests\Meeting\UpsertMemoRequest;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Services\MeetingQuotaService;
use App\UseCases\Meeting\CancelAction;
use App\UseCases\Meeting\FetchAvailabilityAction;
use App\UseCases\Meeting\IndexAction;
use App\UseCases\Meeting\IndexAsCoachAction;
use App\UseCases\Meeting\ShowAction;
use App\UseCases\Meeting\StoreAction;
use App\UseCases\Meeting\UpsertMemoAction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 1on1 面談予約 (Meeting) の HTTP エントリポイント。
 *
 * 受講生視点(index / show / create / store / cancel / fetchAvailability)とコーチ視点
 * (indexAsCoach / upsertMemo)を 1 Controller に集約する。
 * 面談一覧・詳細取得、予約・キャンセル・メモ保存、空き枠取得の業務処理は各 Action に委譲する。
 * Controller はリクエスト受付、認可の委譲、Action 呼び出し、レスポンス整形を担当する。
 */
class MeetingController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $result = $action(
            auth: $request->user(),
            filter: $request->validated('filter'),
        );

        return view('meeting.index', [
            'meetings' => $result['meetings'],
            'filter' => $request->validated('filter') ?? 'upcoming',
            'meetingsRemaining' => $result['meetingRemaining'],
        ]);
    }

    public function indexAsCoach(IndexAsCoachRequest $request, IndexAsCoachAction $action): View
    {
        $filters = $request->validated();
        $studentId = $filters['student'] ?? null;
        $enrollmentId = $filters['enrollment'] ?? null;

        $meetings = $action(
            auth: $request->user(),
            filter: $filters['filter'] ?? null,
            studentId: $studentId,
            enrollmentId: $enrollmentId,
        );

        return view('meeting.coach.index', [
            'meetings' => $meetings,
            'filter' => $filters['filter'] ?? 'upcoming',
            'studentFilter' => $studentId,
            'enrollmentFilter' => $enrollmentId,
        ]);
    }

    public function show(Meeting $meeting, ShowAction $action): View
    {
        $this->authorize('view', $meeting);

        return view('meeting.show', [
            'meeting' => $action($meeting),
        ]);
    }

    /**
     * 予約画面(受講生): URL に Enrollment を含む正規ルートで表示する。
     */
    public function create(Enrollment $enrollment, MeetingQuotaService $meetingQuota): View
    {
        $this->authorize('create', Meeting::class);

        abort_unless($enrollment->user_id === auth()->id(), 403);
        abort_unless($enrollment->status === EnrollmentStatus::Learning, 403);

        $enrollment->loadMissing('certification');

        return view('meeting.create', [
            'enrollment' => $enrollment,
            'meetingsRemaining' => $meetingQuota->remaining(auth()->user()),
        ]);
    }

    /**
     * 予約画面のエントリポイント(URL に Enrollment 無し)。
     * `resolve-default-enrollment` Middleware が default 資格に redirect するため、
     * 本 method に到達するのは default 未設定 + 残存 Enrollment が 0 件 or 2+ 件のケース。
     */
    public function createFallback(): View
    {
        $user = auth()->user();
        $enrollments = $user
            ?->enrollments()
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->with('certification')
            ->get();

        return view('meeting.empty-state', [
            'enrollments' => $enrollments ?? collect(),
        ]);
    }

    public function store(
        Enrollment $enrollment,
        StoreRequest $request,
        StoreAction $action,
    ): RedirectResponse {
        $meeting = $action(
            enrollment: $enrollment,
            validated: $request->validated(),
        );

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談を予約しました。');
    }

    public function cancel(
        Meeting $meeting,
        Request $request,
        CancelAction $action,
    ): RedirectResponse {
        $this->authorize('cancel', $meeting);

        $action($meeting, $request->user());

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談をキャンセルしました。面談回数を返却しました。');
    }

    public function upsertMemo(Meeting $meeting, UpsertMemoRequest $request, UpsertMemoAction $action): RedirectResponse
    {
        $body = $request->validated('body');

        $action($meeting, $body);

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談メモを保存しました。');
    }

    public function fetchAvailability(Enrollment $enrollment, AvailabilityRequest $request, FetchAvailabilityAction $action): JsonResponse
    {
        $date = Carbon::parse($request->validated('date'));

        $slots = $action($enrollment, $date);

        return response()->json([
            'date' => $date->toDateString(),
            'slots' => $slots->map(fn (array $slot) => [
                'slot_start' => $slot['slot_start']->toIso8601String(),
                'slot_end' => $slot['slot_end']->toIso8601String(),
                'available_coach_count' => $slot['available_coach_count'],
            ])->all(),
        ]);
    }
}
