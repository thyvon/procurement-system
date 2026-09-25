<?php

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Notifications\Http\Resources\NotificationResource;

/**
 * Self-scoped: every query is bound to the authenticated user's own
 * notifications, so no permission vocabulary is needed beyond authentication.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = $user->notifications()
            ->orderByRaw('read_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->paginate(
                (int) $request->integer('per_page', 20),
                ['*'],
                'page',
                (int) $request->integer('page', 1),
            );

        return ApiResponse::success(
            NotificationResource::collection($paginator->getCollection())->resolve($request),
            200,
            [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(['count' => $user->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $record = $user->notifications()->where('id', $notification)->first();

        if ($record === null) {
            abort(404, 'Notification not found.');
        }

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        return ApiResponse::success(new NotificationResource($record->refresh()));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(['read' => true]);
    }
}
