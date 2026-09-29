<?php

namespace App\Platform\Announcements\Http\Controllers;

use App\Platform\Announcements\Application\AnnouncementService;
use App\Platform\Announcements\Http\Requests\AnnouncementRequest;
use App\Platform\Announcements\Http\Requests\AnnouncementVisibilityRequest;
use App\Platform\Announcements\Http\Resources\AnnouncementResource;
use App\Platform\Announcements\Models\Announcement;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Announcements for the whole install, for the super admin; a hosting
 * provider can script the same endpoints with a super admin's token.
 * Announcements from the InvoiceShelf feed can only be hidden here.
 */
class AnnouncementsController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(): AnonymousResourceCollection
    {
        return AnnouncementResource::collection(
            Announcement::query()->orderByRaw("case source when 'local' then 0 else 1 end")->latest('id')->get(),
        );
    }

    public function store(AnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->announcements->save(null, $request->validated(), $request->user());

        return (new AnnouncementResource($announcement))->response()->setStatusCode(201);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): AnnouncementResource
    {
        return new AnnouncementResource($this->announcements->save($announcement, $request->validated(), $request->user()));
    }

    public function visibility(AnnouncementVisibilityRequest $request, Announcement $announcement): AnnouncementResource
    {
        return new AnnouncementResource($this->announcements->setHidden($announcement, $request->boolean('hidden')));
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->announcements->delete($announcement);

        return response()->json(['success' => true]);
    }
}
