<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvoiceShelf\Modules\Registry as ModuleRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ScriptController extends Controller
{
    /**
     * Serve the requested module-registered script.
     *
     * Modules call \InvoiceShelf\Modules\Registry::registerScript($name, $path)
     * from their ServiceProvider::boot() to inject custom JS into the host app.
     *
     * @throws NotFoundHttpException
     */
    public function __invoke(Request $request, string $script): Response
    {
        $path = ModuleRegistry::scriptFor($script);

        abort_if($path === null, 404);

        return response(
            file_get_contents($path),
            200,
            [
                'Content-Type' => 'application/javascript',
            ]
        )->setLastModified(DateTime::createFromFormat('U', (string) filemtime($path)));
    }
}
