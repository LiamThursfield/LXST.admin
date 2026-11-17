<?php

namespace App\Mcp\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;

/**
 * Runs one of the admin's form requests on a tool's arguments, acting as the token's user, so MCP
 * writes get exactly the admin's authorisation, validation and saved shape. Throws an
 * AuthorizationException or ValidationException like the admin does.
 */
class AdminRequest
{
    /**
     * @template TRequest of FormRequest
     *
     * @param  class-string<TRequest>  $class
     * @param  array<string, mixed>  $input
     * @param  array<string, Model>  $routeParameters  e.g. ['page' => $page] for an update request
     * @return TRequest
     */
    public static function validate(string $class, array $input, User $user, array $routeParameters = []): FormRequest
    {
        $request = $class::create('/', 'POST', $input);
        $request->setContainer(app())->setRedirector(app(Redirector::class));
        $request->setUserResolver(fn () => $user);

        $route = (new Route(['POST'], '/', fn () => null))->bind($request);

        foreach ($routeParameters as $name => $model) {
            $route->setParameter($name, $model);
        }

        $request->setRouteResolver(fn () => $route);
        $request->validateResolved();

        return $request;
    }
}
