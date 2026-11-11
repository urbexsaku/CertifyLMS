<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stripeによる追加面談購入の決済情報を管理する Model。
 *
 * 購入者・購入対象の面談パック・決済金額・購入回数・決済状態を保持する。
 * Stripe Checkout Session と連携し、決済完了後の面談回数付与の起点となる。
 *
 * 関連: User / MeetingPack / MeetingQuotaTransaction
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'meeting_pack_id',
        'amount',
        'quantity',
        'status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'quantity' => 'integer',
        'status' => PaymentStatus::class,
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MeetingPack, $this>
     */
    public function meetingPack(): BelongsTo
    {
        return $this->belongsTo(MeetingPack::class);
    }

    /**
     * @return HasMany<MeetingQuotaTransaction, $this>
     */
    public function quotaTransactions(): HasMany
    {
        return $this->hasMany(MeetingQuotaTransaction::class, 'related_payment_id');
    }
}
