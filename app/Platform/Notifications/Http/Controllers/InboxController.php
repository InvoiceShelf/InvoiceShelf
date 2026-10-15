<?php

namespace App\Platform\Notifications\Http\Controllers;

use App\Platform\Http\Controller;
use App\Platform\Notifications\Application\Inbox;
use App\Platform\Notifications\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The caller's own notices, for the company in the header. Everyone signed
 * in has an inbox, so nothing here needs an ability; a notice that is not
 * the caller's is not found.
 */
class InboxController extends Controller
{
    public function __construct(private readonly Inbox $inbox) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $page = $this->inbox->list(
            $request->user(),
            $this->companyId($request),
            $request->boolean('unread'),
            min(max($request->integer('limit', 20), 1), 50),
        );

        return NotificationResource::collection($page)->additional([
            'meta' => ['unread_count' => $this->inbox->unreadCount($request->user(), $this->companyId($request))],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->inbox->unreadCount($request->user(), $this->companyId($request)),
        ]);
    }

    public function read(Request $request, string $notification): NotificationResource
    {
        $notice = $this->notice($request, $notification);
        $notice->markAsRead();

        return new NotificationResource($notice);
    }

    public function unread(Request $request, string $notification): NotificationResource
    {
        $notice = $this->notice($request, $notification);
        $notice->markAsUnread();

        return new NotificationResource($notice);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->inbox->markAllRead($request->user(), $this->companyId($request));

        return response()->json(['unread_count' => 0]);
    }

    public function destroy(Request $request, string $notification): Response
    {
        $this->notice($request, $notification)->delete();

        return response()->noContent();
    }

    private function notice(Request $request, string $id): DatabaseNotification
    {
        return $this->inbox->find($request->user(), $this->companyId($request), $id) ?? abort(404);
    }

    /**
     * The company in the header; a platform administrator in admin mode
     * sends none and sees platform notices only.
     */
    private function companyId(Request $request): ?int
    {
        $company = $request->header('company');

        return $company ? (int) $company : null;
    }
}
