<?php

namespace App\Http\Controllers;

class ErrorController extends Controller
{
    public function __invoke($code)
    {
        $errorViews = [
            404 => 'errors.404',
            500 => 'errors.500',
            403 => 'errors.403',
            429 => 'errors.429',
            // More if I need
        ];

        if (array_key_exists($code, $errorViews)) {
            return response()->view($errorViews[$code], [], $code);
        }

        return response()->view('errors.generic', ['code' => $code], $code);

    }
}
