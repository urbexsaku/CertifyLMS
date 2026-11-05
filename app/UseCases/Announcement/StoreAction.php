<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementDispatchService;
use App\Services\AnnouncementRecipientResolverService;

/**
 * お知らせを登録し、対象受講生への通知配信を行うユースケース。
 */
final class StoreAction
{
    public function __construct(
        private readonly AnnouncementRecipientResolverService $recipientResolver,
        private readonly AnnouncementDispatchService $dispatchService,
    ) {}

    /**
     * @param array{title: string, body: string, target_type: string, target_certification_id?: ?string, target_user_id?: ?string} $validated
     */
    public function __invoke(User $admin, array $validated): Announcement
    {
        $recipients = $this->recipientResolver->resolve($validated);

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'target_type' => $validated['target_type'],
            'target_certification_id' => $validated['target_certification_id'] ?? null,
            'target_user_id' => $validated['target_user_id'] ?? null,
            'created_by_user_id' => $admin->id,
            'dispatched_at' => now(),
        ]);

        $this->dispatchService->dispatch($announcement, $recipients);

        return $announcement;
    }
}
