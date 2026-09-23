<?php

namespace App\Domains\Accounts\Http\Controllers\Auth;

use App\Domains\Accounts\Application\AccessTokenService;
use App\Domains\Accounts\Http\Resources\PersonalAccessTokenResource;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The caller's own signed-in devices.
 *
 * Tokens issued by AuthController have no expiry, so this is how a lost phone
 * is cut off. Nothing here takes an account: the caller is always the owner of
 * what is listed and revoked, which is the whole authorization story.
 */
class TokenController extends Controller
{
    public function __construct(private readonly AccessTokenService $tokens) {}

    /**
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        return PersonalAccessTokenResource::collection(
            $this->tokens->listFor($request->user())
        );
    }

    /**
     * A token id that belongs to somebody else is answered 404, exactly as an
     * id nobody holds would be.
     *
     * @return Response
     */
    public function destroy(Request $request, string $id)
    {
        $this->tokens->revokeFor($request->user(), $id);

        return response()->noContent();
    }
}
