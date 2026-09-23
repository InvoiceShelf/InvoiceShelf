<?php

namespace App\Platform\Mcp\Support;

use App\Platform\Mcp\McpContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;

/**
 * Runs one of the app's own form requests on input a tool composed, as if it
 * had arrived over the REST API from the connection's user in its company.
 *
 * The same rules, the same `after` hooks (unique numbers, template names,
 * custom field answers) and the same payload builders apply, so a record
 * written through MCP is validated and stored exactly like one written by the
 * SPA.
 */
class DomainRequestValidator
{
    /**
     * @template T of FormRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $input
     * @param  array<string, Model>  $route  route parameters an update request reads, such as ['invoice' => $invoice]
     * @return T
     *
     * @throws ValidationException
     */
    public function validate(string $class, array $input, McpContext $context, string $method = 'POST', array $route = []): FormRequest
    {
        $request = $class::create('/mcp', $method, $input);
        $request->headers->set('company', (string) $context->company->id);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $context->user);
        $request->setContainer(app())->setRedirector(app(Redirector::class));

        $binding = (new Route($method, 'mcp', []))->bind($request);

        foreach ($route as $name => $model) {
            $binding->setParameter($name, $model);
        }

        $request->setRouteResolver(fn () => $binding);
        $request->validateResolved();

        return $request;
    }
}
