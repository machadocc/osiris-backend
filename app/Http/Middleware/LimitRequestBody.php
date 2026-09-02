<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recusa corpos de requisição acima de MAX_BYTES antes do Laravel montar a
 * request inteira (RNF-09). O teto cobre o upload de comprovante de 5 MB
 * (RF-TRX-04) com margem; qualquer coisa maior é abuso ou erro de cliente.
 */
class LimitRequestBody
{
    private const MAX_BYTES = 6 * 1024 * 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $length = (int) $request->server('CONTENT_LENGTH', 0);

        if ($length > self::MAX_BYTES) {
            abort(413, 'Request payload too large.');
        }

        return $next($request);
    }
}
