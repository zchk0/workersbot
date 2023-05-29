<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
    '/6128457743:AAH0lLM0B7CEuuS2zWE-nyDIaXYl5t34N-w/webhook'
    ];
    
    
}
