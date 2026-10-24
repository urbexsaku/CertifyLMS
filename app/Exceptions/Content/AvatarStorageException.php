<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * アバター画像のアップロードまたは更新に失敗した場合に throw される。
 * 
 * 新規ファイルの保存後に DB更新が失敗した場合は、
 * 保存済みファイルを補償削除して orphan file を防止する。
 */
final class AvatarStorageException extends HttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(500, '画像の保存に失敗しました。時間をおいて再度お試しください。', $previous);
    }
}
