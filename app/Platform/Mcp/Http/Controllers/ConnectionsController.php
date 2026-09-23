<?php

namespace App\Platform\Mcp\Http\Controllers;

use App\Platform\Http\Controller;
use App\Platform\Mcp\Application\ConnectionService;
use App\Platform\Mcp\Application\McpSettings;
use App\Platform\Mcp\Http\Requests\UpdateConnectionRequest;
use App\Platform\Mcp\Http\Resources\McpConnectionResource;
use App\Platform\Mcp\Models\McpConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in user's connected AI apps: list, lower to read only, revoke.
 */
class ConnectionsController extends Controller
{
    public function __construct(
        private readonly ConnectionService $connections,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return McpConnectionResource::collection($this->connections->forUser($request->user()));
    }

    public function update(UpdateConnectionRequest $request, McpConnection $connection): McpConnectionResource
    {
        $this->authorize('update', $connection);

        return new McpConnectionResource($this->connections->downgrade($connection)->load('company'));
    }

    public function destroy(McpConnection $connection): JsonResponse
    {
        $this->authorize('delete', $connection);

        $this->connections->revoke($connection);

        return response()->json(['success' => true]);
    }

    /**
     * Where clients connect, for the setup instructions on the page.
     */
    public function server(McpSettings $settings): JsonResponse
    {
        return response()->json([
            'data' => [
                'enabled' => $settings->enabled(),
                'server_url' => url('/mcp'),
            ],
        ]);
    }
}
