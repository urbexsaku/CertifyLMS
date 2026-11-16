<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::query()
            ->whereIn('email', [
                'student@certify-lms.test',
                'student-noquota@certify-lms.test',
            ])
            ->get();

        $meetingPacks = MeetingPack::query()
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get();

        if ($students->isEmpty() || $meetingPacks->isEmpty()) {
            $this->command?->warn(
                'PaymentSeeder: 受講生または公開中の面談パックがありません。',
            );

            return;
        }

        foreach ($students as $student) {
            foreach ([
                [
                    'key' => 'completed',
                    'status' => PaymentStatus::Succeeded,
                    'paid_at' => now()->subDays(10),
                    'pack_index' => 0,
                ],
                [
                    'key' => 'pending',
                    'status' => PaymentStatus::Pending,
                    'paid_at' => null,
                    'pack_index' => 1,
                ],
                [
                    'key' => 'failed',
                    'status' => PaymentStatus::Failed,
                    'paid_at' => null,
                    'pack_index' => 2,
                ],
            ] as $data) {
                $meetingPack = $meetingPacks->get($data['pack_index']);

                if ($meetingPack === null) {
                    continue;
                }

                $payment = Payment::query()->firstOrCreate(
                    [
                        'stripe_checkout_session_id' => 'seed_'.$student->id.'_'.$data['key'],
                    ],
                    [
                        'user_id' => $student->id,
                        'meeting_pack_id' => $meetingPack->id,
                        'amount' => $meetingPack->price,
                        'quantity' => $meetingPack->meeting_count,
                        'status' => $data['status'],
                        'paid_at' => $data['paid_at'],
                    ],
                );

                if ($payment->status === PaymentStatus::Succeeded) {
                    MeetingQuotaTransaction::query()->firstOrCreate(
                        [
                            'related_payment_id' => $payment->id,
                        ],
                        [
                            'user_id' => $payment->user_id,
                            'type' => MeetingQuotaTransactionType::Purchased,
                            'amount' => $payment->quantity,
                            'occurred_at' => $payment->paid_at ?? now(),
                        ],
                    );
                }
            }
        }
    }
}
