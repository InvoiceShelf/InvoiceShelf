<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SearchController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $customers = Customer::applyFilters($request->only(['search']))
            ->whereCompany()
            ->latest()
            ->paginate(10);

        if ($user->isOwner()) {
            $users = User::whereCompany()
                ->applyFilters($request->only(['search']))
                ->latest()
                ->paginate(10);
        }

        return response()->json([
            'customers' => $customers,
            'users' => $users ?? [],
        ]);
    }
}
