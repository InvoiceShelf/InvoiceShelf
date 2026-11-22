<?php

namespace App\Platform\Announcements\Http\Controllers;

use App\Platform\Announcements\Application\AnnouncementService;
use App\Platform\Announcements\Models\Announcement;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Put an announcement away for the caller alone.
 */
class DismissAnnouncementController extends Controller
{
    public function __invoke(Request $request, Announcement $announcement, AnnouncementService $announcements): JsonResponse
    {
        $announcements->dismiss($request->user(), $announcement);

        return response()->json(['success' => true]);
    }
}
