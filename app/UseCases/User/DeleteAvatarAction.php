<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * アバター画像の削除ユースケース。
 *
 * ユーザーの avatar_url を NULL に更新し、
 * DB のコミット後にアプリ管理下のアバター画像を削除する。
 */
final class DeleteAvatarAction
{
    public function __invoke(User $user): void
    {
        $disk = Storage::disk('public');
        $oldPath = $this->resolveAvatarPath(
            $user->avatar_url,
            $disk->url('avatars'),
        );

        DB::transaction(function () use ($user, $oldPath, $disk): void {
            $user->update([
                'avatar_url' => null,
            ]);

            if ($oldPath !== null) {
                return;
            }

            DB::afterCommit(function () use ($disk, $oldPath, $user): void {
                if (! $disk->delete($oldPath)) {
                    Log::warning('アバター画像の削除に失敗しました。', [
                        'user_id' => $user->id,
                        'path' => $oldPath,
                    ]);
                }
            });
        });
    }

    /**
     * アプリ管理下の avatars/ 直下にある画像 URL から相対パスを取得する。
     * 外部 URL や想定外のパスは削除対象にしない。
     */
    private function resolveAvatarPath(?string $url, string $avatarBaseUrl): ?string
    {
        if ($url === null) {
            return null;
        }

        $prefix = rtrim($avatarBaseUrl, '/').'/';

        if (! str_starts_with($url, $prefix)) {
            return null;
        }

        $filename = substr($url, strlen($prefix));

        if (
            $filename === ''
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_contains($filename, '..')
            || str_contains($filename, '?')
            || str_contains($filename, '#')
        ) {
            return null;
        }

        return 'avatars/'.$filename;
    }
}
