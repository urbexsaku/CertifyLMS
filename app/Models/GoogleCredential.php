<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoogleCredentialFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * コーチの Google Calendar 連携情報を表す Model。
 *
 * access_token には Google OAuth token response を JSON 形式で保存する。
 * access_token / refresh_token は機密情報のためシリアライズ時は hidden とする。
 *
 * アプリケーションの認可ではコーチのみが作成・更新・削除できる。
 *
 * 関連: User(coach)
 */
class GoogleCredential extends Model
{
    /** @use HasFactory<GoogleCredentialFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'google_user_id',
        'calendar_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'connected_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
