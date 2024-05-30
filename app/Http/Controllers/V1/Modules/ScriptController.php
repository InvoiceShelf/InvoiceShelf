<?php

namespace App\Http\Controllers\V1\Modules;

use App\Http\Controllers\Controller;
use App\Services\Module\ModuleFacade;
use DateTime;
use Illuminate\Support\Arr;
use Request;

class ScriptController extends Controller
{
    /**
     * Serve the requested script.
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function __invoke(Request $request, string $script)
    {
        $path = Arr::get(ModuleFacade::allScripts(), $script);

        abort_if(is_null($path), 404);

        return response(
            file_get_contents($path),
            200,
            [
                'Content-Type' => 'application/javascript',
            ]
        )->setLastModified(DateTime::createFromFormat('U', filemtime($path)));
    }
}
