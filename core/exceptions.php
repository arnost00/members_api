<?php

namespace Core;

use Pecee\Http\Request;
use Pecee\SimpleRouter\Handlers\IExceptionHandler;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;

use Core\Logging;

// $content = [
//     "code" => $error->getCode(),
//     "message" => $error->getMessage(),
//     // debug
//     "method" => $_SERVER["REQUEST_METHOD"],
//     // PATH_INFO sometimes fails
//     "path" => @$_SERVER["PATH_INFO"],
//     "input" => input()->all(),
//     "auth" => request()->getHeader("authorization"),
//     "line" => $error->getLine(),
//     "file" => $error->getFile(),
//     "stacktrace" => $error->getTrace(),
// ];

class ApiExceptionHandler implements IExceptionHandler {
    public function handleError(Request $request, \Exception $exception): void
    {
        $content = [
            "code" => $exception->getCode(),
            "message" => $exception->getMessage(),
        ] + (request()->debug ? [
            // debug
            "method" => $_SERVER["REQUEST_METHOD"],
            "path" => $_SERVER["PATH_INFO"],
            "input" => input()->all(),
            "line" => $exception->getLine(),
            "file" => $exception->getFile(),
            "stacktrace" => $exception->getTrace(),
        ] : []);

        Logging::exception($content);
        
        if ($request->getContentType() === "application/json") {
            response()->httpCode($exception->getCode());
            response()->json($content);
        } else {
            echo "<h1>API ERROR:<br /><i>" . $exception->getMessage() . "</i></h1>";
            echo "<pre>" . json_encode($content, JSON_PRETTY_PRINT) . "</pre>";
            
            if (!request()->debug) {
                echo "<p>For more debugging information, use test clubs or set <code>request()->debug = true;</code>.</p>";
            }
        }

        exit;
    }
}

class ApiException extends \Exception {
    public function __construct($message, $code = 500, \Throwable $previous = null)
    {
        if ($code < 400 || $code >= 600) {
            throw new \Exception("status code for exception must follow http error codes");
        }

        parent::__construct($message, $code, $previous);
    }
}