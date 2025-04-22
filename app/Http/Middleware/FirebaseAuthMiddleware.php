<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Factory;
use Symfony\Component\HttpFoundation\Response;

class FirebaseAuthMiddleware
{
    protected $auth;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function __construct()
    {
        $this->auth = (new Factory) 
         ->withServiceAccount(config("firebase.credentials"))
         ->createAuth();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header("Authorization");

        if(!$token) {
            return response()->json(["error" => "Authorization token not found"],401);
        }

        try{
            $verifiedToken = $this->auth->verifyIdToken(str_replace("Bearer ", '', $token));
            $firebaseUserId = $verifiedToken->claims()->get('sub');
            $request->merge(['firebase_uid' => $firebaseUserId]);
        }catch(FailedToVerifyToken $e){
            return response()->json(["error" => "Invalid or expired token"],401);
        }
        return $next($request);
    }
}
