<?php

namespace InvoiceShelf\Http\Controllers\V1\Admin\Update;

use Illuminate\Http\Request;
use InvoiceShelf\Http\Controllers\Controller;
use InvoiceShelf\Space\Updater;

class UnzipUpdateController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        if ((! $request->user()) || (! $request->user()->isOwner())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to update this app.',
            ], 401);
        }

        $request->validate([
            'path' => 'required',
        ]);

        try {
            $path = Updater::unzip($request->path);

            return response()->json([
                'success' => true,
                'path' => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
