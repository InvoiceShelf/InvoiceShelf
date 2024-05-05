<?php

namespace InvoiceShelf\Http\Controllers\V1\Admin\Installer;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvoiceShelf\Http\Controllers\Controller;
use InvoiceShelf\Http\Requests;
use InvoiceShelf\Http\Requests\DeleteInstallersRequest;
use InvoiceShelf\Http\Resources\InstallerResource;
use InvoiceShelf\Models\Installer;

class InstallersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Installer::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $installers = Installer::with('creator')
            ->whereCompany()
            ->applyFilters($request->all())
            ->select(
                'installers.*',
                DB::raw('sum(invoices.base_due_amount) as base_due_amount'),
                DB::raw('sum(invoices.due_amount) as due_amount'),
            )
            ->groupBy('installers.id')
            ->leftJoin('invoices', 'installers.id', '=', 'invoices.installer_id')
            ->paginateData($limit);

        return InstallerResource::collection($installers)
            ->additional(['meta' => [
                'installer_total_count' => Installer::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Requests\InstallerRequest $request)
    {
        $this->authorize('create', Installer::class);

        $installer = Installer::createInstaller($request);

        return new InstallerResource($installer);
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Installer $installer)
    {
        $this->authorize('view', $installer);

        return new InstallerResource($installer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Requests\InstallerRequest $request, Installer $installer)
    {
        $this->authorize('update', $installer);

        $Installer = Installer::updateInstaller($request, $installer);

        if (is_string($installer)) {
            return respondJson('you_cannot_edit_currency', 'Cannot change currency once transactions created');
        }

        return new InstallerResource($installer);
    }

    /**
     * Remove a list of Installers along side all their resources (ie. Estimates, Invoices, Payments and Addresses)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(DeleteInstallersRequest $request)
    {
        $this->authorize('delete multiple installers');

        Installer::deleteInstallers($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
