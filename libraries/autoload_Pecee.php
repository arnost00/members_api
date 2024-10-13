<?php

// Credits to @skipperbent, https://github.com/skipperbent/simple-php-router/

// New type is added for convience at SimpleRouter::form, derived from JS's fetch function name that makes preflight requests.
//
// /**
//  * This type will route the given url to your callback on the provided request methods.
//  * Route the given url to your callback on POST, GET and OPTIONS request method.
//  *
//  * @param string $url
//  * @param string|array|Closure $callback
//  * @param array|null $settings
//  * @return RouteUrl|IRoute
//  * @see SimpleRouter::form
//  */
// public static function fetch(string $url, $callback, array $settings = null): IRoute
// {
//     return static::match([
//         Request::REQUEST_TYPE_GET,
//         Request::REQUEST_TYPE_POST,
//         Request::REQUEST_TYPE_OPTIONS,
//     ], $url, function (...$arguments) use ($callback) {
//         if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
//             return;
//         }
        
//         // proceed normally
//         return $callback(...$arguments);
//     }, $settings);
// }

require_once __DIR__ . "/Pecee/Exceptions/InvalidArgumentException.php";

require_once __DIR__ . "/Pecee/SimpleRouter/SimpleRouter.php";
require_once __DIR__ . "/Pecee/SimpleRouter/IRouterBootManager.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Router.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Event/IEventArgument.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Event/EventArgument.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Exceptions/HttpException.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Exceptions/NotFoundHttpException.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Exceptions/ClassNotFoundHttpException.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/IRoute.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/Route.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/ILoadableRoute.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/LoadableRoute.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/RouteUrl.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/IControllerRoute.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/RouteController.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/IGroupRoute.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/RouteGroup.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/IPartialGroupRoute.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/RoutePartialGroup.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Route/RouteResource.php";
require_once __DIR__ . "/Pecee/SimpleRouter/ClassLoader/IClassLoader.php";
require_once __DIR__ . "/Pecee/SimpleRouter/ClassLoader/ClassLoader.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Handlers/IEventHandler.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Handlers/EventHandler.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Handlers/DebugEventHandler.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Handlers/IExceptionHandler.php";
require_once __DIR__ . "/Pecee/SimpleRouter/Handlers/CallbackExceptionHandler.php";

require_once __DIR__ . "/Pecee/Controllers/IResourceController.php";

require_once __DIR__ . "/Pecee/Http/Response.php";
require_once __DIR__ . "/Pecee/Http/Request.php";
require_once __DIR__ . "/Pecee/Http/Url.php";
require_once __DIR__ . "/Pecee/Http/Exceptions/MalformedUrlException.php";

require_once __DIR__ . "/Pecee/Http/Middleware/IMiddleware.php";
require_once __DIR__ . "/Pecee/Http/Middleware/IpRestrictAccess.php";
require_once __DIR__ . "/Pecee/Http/Middleware/BaseCsrfVerifier.php";
require_once __DIR__ . "/Pecee/Http/Middleware/Exceptions/TokenMismatchException.php";

require_once __DIR__ . "/Pecee/Http/Input/InputHandler.php";
require_once __DIR__ . "/Pecee/Http/Input/IInputItem.php";
require_once __DIR__ . "/Pecee/Http/Input/InputFile.php";
require_once __DIR__ . "/Pecee/Http/Input/InputItem.php";

require_once __DIR__ . "/Pecee/Http/Security/ITokenProvider.php";
require_once __DIR__ . "/Pecee/Http/Security/CookieTokenProvider.php";
require_once __DIR__ . "/Pecee/Http/Security/Exceptions/SecurityException.php";

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\Http\Url;
use Pecee\Http\Response;
use Pecee\Http\Request;

/**
 * Get url for a route by using either name/alias, class or method name.
 *
 * The name parameter supports the following values:
 * - Route name
 * - Controller/resource name (with or without method)
 * - Controller class name
 *
 * When searching for controller/resource by name, you can use this syntax "route.name@method".
 * You can also use the same syntax when searching for a specific controller-class "MyController@home".
 * If no arguments is specified, it will return the url for the current loaded route.
 *
 * @param string|null $name
 * @param string|array|null $parameters
 * @param array|null $getParams
 * @return \Pecee\Http\Url
 * @throws \InvalidArgumentException
 */
function url(?string $name = null, $parameters = null, ?array $getParams = null): Url
{
    return Router::getUrl($name, $parameters, $getParams);
}

/**
 * @return \Pecee\Http\Response
 */
function response(): Response
{
    return Router::response();
}

/**
 * @return \Pecee\Http\Request
 */
function request(): Request
{
    return Router::request();
}

// Do NOT uncomment this function, this is replaced by Input class in routes.php
/**
 * Get input class
 * @param string|null $index Parameter index name
 * @param string|mixed|null $defaultValue Default return value
 * @param array ...$methods Default methods
 * @return \Pecee\Http\Input\InputHandler|array|string|null
 */
// function input($index = null, $defaultValue = null, ...$methods)
// {
//     if ($index !== null) {
//         return request()->getInputHandler()->value($index, $defaultValue, ...$methods);
//     }

//     return request()->getInputHandler();
// }

/**
 * @param string $url
 * @param int|null $code
 */
function redirect(string $url, ?int $code = null): void
{
    if ($code !== null) {
        response()->httpCode($code);
    }

    response()->redirect($url);
}

/**
 * Get current csrf-token
 * @return string|null
 */
function csrf_token(): ?string
{
    $baseVerifier = Router::router()->getCsrfVerifier();
    if ($baseVerifier !== null) {
        return $baseVerifier->getTokenProvider()->getToken();
    }

    return null;
}
