<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Contracts\AbilityCatalog;
use App\Domains\Accounts\Http\Requests\RoleRequest;
use App\Domains\Accounts\Http\Resources\RoleResource;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

/**
 * The roles a company defines for its own members.
 *
 * Every action is owner-only by way of the role policy. None of the queries or
 * writes below name a company: roles sit inside the Bouncer scope that the
 * bouncer middleware derived from the `company` header, and reads and writes
 * alike inherit it. That is also why the listing's `company_id` filter can only
 * ever narrow the active company's roles -- pointing it at another company
 * intersects to nothing rather than crossing the tenant boundary.
 */
class RolesController extends Controller
{
    /**
     * Machine-readable key reported when a role is still held by someone.
     */
    private const IN_USE_ERROR = 'role_attached_to_users';

    /**
     * Human-readable counterpart of the in-use key.
     */
    private const IN_USE_MESSAGE = 'Roles Attached to user';

    public function __construct(private readonly AbilityCatalog $catalog) {}

    /**
     * Every role visible in the active scope.
     *
     * Kept as is: `orderByField` is handed to the database untouched, unlike
     * the allow-listed sorts elsewhere in the app.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::query();

        if ($request->has('orderByField')) {
            $query->orderBy($request['orderByField'], $request['orderBy']);
        }

        if ($request->company_id) {
            $query->where('scope', $request->company_id);
        }

        return RoleResource::collection($query->get());
    }

    /**
     * Define a role and settle its abilities in one go.
     */
    public function store(RoleRequest $request)
    {
        $this->authorize('create', Role::class);

        $role = Role::query()->create($request->getRolePayload());

        $this->writeCatalogGrants($role, $request->abilities);

        return RoleResource::make($role);
    }

    /**
     * One role with its current grants.
     */
    public function show(Role $role)
    {
        $this->authorize('view', $role);

        return RoleResource::make($role);
    }

    /**
     * Rename a role and rewrite its grants.
     */
    public function update(RoleRequest $request, Role $role)
    {
        $this->authorize('update', $role);

        $role->fill($request->getRolePayload())->save();

        $this->writeCatalogGrants($role, $request->abilities);

        return RoleResource::make($role);
    }

    /**
     * Drop a role, unless somebody in this company still holds it.
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        if (User::whereIs($role->name)->exists()) {
            return respondJson(self::IN_USE_ERROR, self::IN_USE_MESSAGE);
        }

        $role->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Walk the whole ability catalog and make the role match the submission.
     *
     * The submission is read as a set of names: a catalog entry named in it is
     * granted, every other entry is revoked, so a role never keeps a grant the
     * caller left out. Names that match no catalog entry are simply never
     * looked at -- including a module ability whose module has since been
     * disabled, whose grant therefore survives untouched.
     */
    private function writeCatalogGrants($role, $submitted): void
    {
        $wanted = array_column($submitted, 'ability');

        foreach ($this->catalog->all() as $entry) {
            if (in_array($entry['ability'], $wanted)) {
                BouncerFacade::allow($role)->to($entry['ability'], $entry['model']);

                continue;
            }

            BouncerFacade::disallow($role)->to($entry['ability'], $entry['model']);
        }
    }
}
