<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Services\NotificationRouteResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知ポップオーバー表示用 Resource。
 *
 * 通知本文はプレビューとして返し、遷移先はルート名とパラメータで返す。
 *
 * @mixin DatabaseNotification
 */
class NotificationPopoverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        $data = is_array($this->data)
            ? $this->data
            : [];

        $route = app(NotificationRouteResolver::class)
            ->resolve($this->resource, $user);

        $preview = $data['message'] ?? ($data['body_preview'] ?? $data['body'] ?? '');

        return [
            'id' => $this->id,
            'notification_type' => $data['notification_type'] ?? null,
            'title' => $data['title'] ?? '通知',
            'preview' => (string) $preview,
            'created_at' => $this->created_at?->toISOString(),
            'relative_time' => $this->created_at?->diffForHumans(),
            'read_at' => $this->read_at?->toISOString(),
            'route' => $route['route'],
            'route_params' => $route['route_params'],
        ];
    }
}
