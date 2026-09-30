<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QaThreadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 質問掲示板スレッドを表す Model。
 *
 * 関連: Certification(親・資格マスタ) / QaReply(子・回答) / User(投稿者)
 * scope: forCertification(string) / byStatus(?string) / keyword(?string)(title / body 部分一致)
 */
class QaThread extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'certification_id',
        'title',
        'body',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'status' => QaThreadStatus::class,
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<QaReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class);
    }

    public function scopeForCertification(Builder $query, string $certificationId): Builder
    {
        return $query->where('certification_id', $certificationId);
    }

    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'unresolved' => $query->where('status', QaThreadStatus::Open->value),
            'resolved' => $query->where('status', QaThreadStatus::Resolved->value),
            default => $query,
        };
    }

    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || $keyword === '') {
            return $query;
        }

        $like = '%'.$keyword.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'LIKE', $like)
                ->orWhere('body', 'LIKE', $like)
                ->orWhereHas('replies', function (Builder $replyQuery) use ($like) {
                    $replyQuery->where('body', 'LIKE', $like);
                });
        });
    }
}
