<?php namespace App\Http\Middleware;
use App\Models\User; use App\Services\JwtService; use Closure; use Illuminate\Http\Request;
class JwtAuthenticate { public function handle(Request $request,Closure $next){$token=$request->bearerToken();$p=$token?app(JwtService::class)->verify($token):null;$user=$p?User::with('role','employee')->find($p['sub']):null;if(!$user||!$user->active)return response()->json(['message'=>'No autenticado'],401);auth()->setUser($user);return $next($request);} }
