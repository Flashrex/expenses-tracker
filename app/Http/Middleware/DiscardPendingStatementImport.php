<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DiscardPendingStatementImport
{
    public const SESSION_KEY = 'statement_import';

    /**
     * Drop the unconfirmed months of a pending import as soon as the user leaves the review.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('upload.review', 'upload.confirm', 'upload.skip', 'upload.discard', 'upload.assign', 'upload.always', 'upload.override', 'upload.notice.dismiss')) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return $next($request);
    }
}
