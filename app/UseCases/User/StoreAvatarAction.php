<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Exceptions\Content\AvatarStorageException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * アバター画像のアップロードユースケース。
 *
 * 画像を public ディスクに保存し、ユーザーの avatar_url を更新する。
 * DB 更新に失敗した場合は、新しく保存した画像を削除する。
 * DB 更新成功後、アプリ管理下にある旧画像を削除する。
 */
final class StoreAvatarAction
{
    /**
     * @throws AvatarStorageException
     */
    public function __invoke(User $user, UploadedFile $file): User
    {
        $disk = Storage::disk('public');
        $ulid = (string) Str::ulid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = "{$ulid}.{$ext}";
        $path = "avatars/{$filename}";
        $oldUrl = $user->avatar_url;

        $storedPath = null;

        try {
            $storedPath = $disk->putFileAs('avatars', $file, $filename);

            if ($storedPath === false) {
                throw new \RuntimeException('アバター画像の保存に失敗しました。');
            }

            DB::transaction(function () use ($user, $disk, $storedPath): void {
                $user->avatar_url = $disk->url($storedPath);
                $user->save();
            });
        } catch (\Throwable $e) {
            if ($storedPath !== null) {
                $disk->delete($path);
            }            

            throw new AvatarStorageException($e);
        }

        $oldPath = $this->resolveAvatarPath($oldUrl, $disk->url('avatars'));

        if ($oldPath !== null && $oldPath !== $path) {
            $disk->delete($oldPath);
        }

        return $user;
    }

    /**
     * アプリ管理下の avatars/ 直下にある画像 URL から相対パスを取得する。
     * 外部 URL や想定外のパスは削除対象にしない。
     */
    private function resolveAvatarPath(
        ?string $url,
        string $avatarBaseUrl,
    ): ?string {
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
