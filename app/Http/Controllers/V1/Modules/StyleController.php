<?php

namespace App\Http\Controllers\V1\Modules;

use App\Http\Controllers\Controller;
use App\Services\Module\ModuleFacade;
use DateTime;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StyleController extends Controller
{
    /**
     * Serve the requested stylesheet.
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     */
    public function __invoke(Request $request, string $style)
    {
        $path = Arr::get(ModuleFacade::allStyles(), $style);

        abort_if(is_null($path), 404);

        return response(
            file_get_contents($path),
            200,
            [
                'Content-Type' => 'text/css',
            ]
        )->setLastModified(DateTime::createFromFormat('U', filemtime($path)));
    }
}
