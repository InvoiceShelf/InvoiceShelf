<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvoiceShelf\Modules\Registry as ModuleRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StyleController extends Controller
{
    /**
     * Serve the requested module-registered stylesheet.
     *
     * Modules call \InvoiceShelf\Modules\Registry::registerStyle($name, $path)
     * from their ServiceProvider::boot() to inject custom CSS into the host app.
     *
     * @throws NotFoundHttpException
     */
    public function __invoke(Request $request, string $style): Response
    {
        $path = ModuleRegistry::styleFor($style);

        abort_if($path === null, 404);

        return response(
            file_get_contents($path),
            200,
            [
                'Content-Type' => 'text/css',
            ]
        )->setLastModified(DateTime::createFromFormat('U', (string) filemtime($path)));
    }
}
