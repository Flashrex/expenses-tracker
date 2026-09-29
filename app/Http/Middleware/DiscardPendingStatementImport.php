<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DiscardPendingStatementImport
{
    public const SESSION_KEY = 'statement_import';

    /**
     * Drop a parsed but unconfirmed statement as soon as the user leaves the review.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('upload.review', 'upload.confirm', 'upload.discard', 'upload.assign', 'upload.always')) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return $next($request);
    }
}
