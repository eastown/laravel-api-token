<?php

namespace Eastown\ApiToken\Middleware;

use Eastown\ApiToken\Token;

/**
 * Created by PhpStorm.
 * User: qi
 * Date: 2017/5/31
 * Time: 17:48
 */

class TokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     * @throws \Eastown\ApiToken\Exceptions\TokenAuthException
     */
    public function handle($request, \Closure $next, $guard)
    {
        $token = $request->input('token') or $token = $request->header('token') or $token = $request->bearerToken();
        Token::guard($guard)->auth($token);
        return $next($request);
    }
}