<?php
namespace App\Services;
use Illuminate\Support\Facades\Hash;
class JwtService {
 private function b64(string $v): string { return rtrim(strtr(base64_encode($v),'+/','-_'),'='); }
 public function issue($user): string { $now=time(); $payload=['sub'=>$user->id,'iat'=>$now,'exp'=>$now+(int)env('JWT_TTL',480)*60,'jti'=>bin2hex(random_bytes(8))]; $h=$this->b64(json_encode(['typ'=>'JWT','alg'=>'HS256'])); $p=$this->b64(json_encode($payload)); return "$h.$p.".$this->b64(hash_hmac('sha256',"$h.$p",env('JWT_SECRET',config('app.key')),true)); }
 public function verify(string $token): ?array { $parts=explode('.',$token); if(count($parts)!==3)return null; [$h,$p,$s]=$parts; $expected=$this->b64(hash_hmac('sha256',"$h.$p",env('JWT_SECRET',config('app.key')),true)); if(!hash_equals($expected,$s))return null; $data=json_decode(base64_decode(strtr($p,'-_','+/')),true); return ($data && ($data['exp']??0)>time())?$data:null; }
}
