<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知種別に応じて遷移先ルートとパラメータを解決するサービス。
 *
 * Web通知画面と通知APIで共通利用する。
 */
class NotificationRouteResolver
{
    /**
     * 通知の遷移先ルートとパラメータを取得する。
     *
     * @return array{route: string, route_params: array<string, mixed>}
     */
    public function resolve(DatabaseNotification $notification, User $user): array
    {
        $data = is_array($notification->data)
            ? $notification->data
            : [];

        $type = $data['notification_type'] ?? null;
        $params = $data['route_params'] ?? null;

        return match ($type) {
            'chat_message_received' => $this->resolveParameterizedRoute(
                'chat.show',
                $params,
                'room'
            ),

            'qa_reply_received' => $this->resolveParameterizedRoute(
                'qa-board.show',
                $params,
                'thread'
            ),

            'meeting_reserved',
            'meeting_canceled' => [
                'route' => $user->role === UserRole::Coach
                    ? 'coach.meetings.index'
                    : 'meetings.index',
                'route_params' => [],
            ],

            'meeting_reminder' => $this->resolveParameterizedRoute(
                'meetings.show',
                $params,
                'meeting'
            ),

            'admin_announcement' => [
                'route' => 'notifications.show',
                'route_params' => [
                    'notification' => $notification->id,
                ],
            ],

            default => $this->fallback(),
        };
    }

    /**
     * 必須パラメータを確認して遷移先を返す。
     *
     * @return array{route: string, route_params: array<string, mixed>}
     */
    private function resolveParameterizedRoute(
        string $route,
        mixed $params,
        string $requiredParam,
    ): array {
        if (
            ! is_array($params)
            || ! isset($params[$requiredParam])
            || ! is_scalar($params[$requiredParam])
            || $params[$requiredParam] === ''
        ) {
            return $this->fallback();
        }

        return [
            'route' => $route,
            'route_params' => $params,
        ];
    }

    /**
     * 通知一覧への遷移先を返す。
     *
     * @return array{route: string, route_params: array<string, mixed>}
     */
    private function fallback(): array
    {
        return [
            'route' => 'notifications.index',
            'route_params' => [],
        ];
    }
}
