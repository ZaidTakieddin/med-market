<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Notifications', description: 'In-app notifications (order status changes, low-stock alerts). Notifications belong to the authenticated user; mail copies are sent automatically.')]
class NotificationController extends BaseApiController
{
    /**
     * List my notifications.
     */
    #[OA\Get(
        path: '/notifications',
        summary: 'List my notifications',
        description: 'Paginated list of the authenticated user\'s notifications, newest first. Filter with `?unread=true` for unread only.',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'unread', in: 'query', schema: new OA\Schema(type: 'boolean'), description: 'Only unread notifications'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notifications retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser();

        $query = DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        [$notifications, $pagination] = $this->paginateOrAll($query->latest(), $request);

        $additional = [
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'unreadCount' => $user->unreadNotifications()->count(),
        ];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return NotificationResource::collection($notifications)
            ->additional($additional)
            ->response();
    }

    /**
     * Count my unread notifications.
     */
    #[OA\Get(
        path: '/notifications/unread-count',
        summary: 'Unread notification count',
        description: 'Cheap badge endpoint for polling.',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Unread count returned'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function unreadCount(): JsonResponse
    {
        return $this->success(
            ['unreadCount' => $this->currentUser()->unreadNotifications()->count()],
            'Unread count retrieved successfully'
        );
    }

    /**
     * Mark a notification as read.
     */
    #[OA\Post(
        path: '/notifications/{id}/mark-read',
        summary: 'Mark notification as read',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification marked as read'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function markRead(string $id): JsonResponse
    {
        $notification = $this->currentUser()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notification->markAsRead();

        return $this->success(
            new NotificationResource($notification),
            'Notification marked as read'
        );
    }

    /**
     * Mark all my notifications as read.
     */
    #[OA\Post(
        path: '/notifications/mark-all-read',
        summary: 'Mark all notifications as read',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'All notifications marked as read'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function markAllRead(): JsonResponse
    {
        $this->currentUser()->unreadNotifications->markAsRead();

        return $this->success(null, 'All notifications marked as read');
    }
}
