<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Domain\{ApiException,Tenant};
use Firebase\JWT\{JWT,Key};
use PDO;
final class AuthService
{
 private string $secret;
 public function __construct(private PDO $db) {
  $this->secret=getenv('JWT_SECRET')?:'';
  if(strlen($this->secret)<32)throw new \RuntimeException('JWT_SECRET precisa de ao menos 32 caracteres.');
  if(getenv('APP_ENV')==='production'&&str_starts_with($this->secret,'dev-only'))throw new \RuntimeException('Segredo de desenvolvimento não permitido em produção.');
 }
 public function login(array $data): array {
  $email=$data['email']??null;$password=$data['password']??null;
  if(!is_string($email)||!is_string($password)||strlen($email)>254||strlen($password)>128)throw new ApiException(422,'Informe email e senha.');
  $q=$this->db->prepare('SELECT * FROM users WHERE LOWER(email)=?');$q->execute([strtolower(trim($email))]);$user=$q->fetch();
  $hash=$user['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
  if(!password_verify($password,$hash)||!$user||!in_array($user['active'],[true,1,'1','t'],true))throw new ApiException(401,'Email ou senha inválidos.');
  $session=bin2hex(random_bytes(24));$refresh=bin2hex(random_bytes(48));$q=$this->db->prepare('INSERT INTO auth_sessions(id,user_id,refresh_hash,expires_at) VALUES(?,?,?,?)');$q->execute([$session,$user['id'],hash('sha256',$refresh),time()+86400*7]);
  return $this->tokens($user,$session,$refresh);
 }
 private function tokens(array $user,string $session,string $refresh): array {
  $ttl=max(60,min(3600,(int)(getenv('JWT_TTL')?:900)));
  $claims=['iss'=>'minevision','aud'=>'minevision-web','sub'=>(string)$user['id'],'sid'=>$session,'company_id'=>(int)$user['company_id'],'iat'=>time(),'nbf'=>time(),'exp'=>time()+$ttl];
  unset($user['password_hash']);$user['id']=(int)$user['id'];$user['company_id']=(int)$user['company_id'];
  return ['access_token'=>JWT::encode($claims,$this->secret,'HS256'),'refresh_token'=>$refresh,'expires_in'=>$ttl,'user'=>$user];
 }
 public function verify(string $token): array {
  try{$claims=(array)JWT::decode($token,new Key($this->secret,'HS256'));}catch(\Throwable){throw new ApiException(401,'Sessão expirada ou inválida.');}
  if(($claims['iss']??'')!=='minevision'||($claims['aud']??'')!=='minevision-web'||!isset($claims['sid'],$claims['sub']))throw new ApiException(401,'Token inválido.');
  $q=$this->db->prepare('SELECT u.*,s.id AS session_id FROM users u JOIN auth_sessions s ON s.user_id=u.id WHERE u.id=? AND s.id=? AND s.revoked=FALSE AND s.expires_at>?');$q->execute([(int)$claims['sub'],$claims['sid'],time()]);$user=$q->fetch();
  if(!$user||!in_array($user['active'],[true,1,'1','t'],true)||(int)$user['company_id']!==($claims['company_id']??0))throw new ApiException(401,'Sessão revogada.');
  return $user;
 }
 public function refresh(string $refresh): array {
  if(strlen($refresh)!==96)throw new ApiException(401,'Refresh token inválido.');
  $this->db->beginTransaction();try {
   $pg=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql';
   $q=$this->db->prepare('SELECT u.*,s.id AS session_id FROM auth_sessions s JOIN users u ON u.id=s.user_id WHERE refresh_hash=? AND s.revoked=FALSE AND s.expires_at>?'.($pg?' FOR UPDATE OF s':''));$q->execute([hash('sha256',$refresh),time()]);$user=$q->fetch();
   if(!$user||!in_array($user['active'],[true,1,'1','t'],true))throw new ApiException(401,'Refresh token expirado.');
   $next=bin2hex(random_bytes(48));$q=$this->db->prepare('UPDATE auth_sessions SET refresh_hash=? WHERE id=?');$q->execute([hash('sha256',$next),$user['session_id']]);
   $this->db->commit();return $this->tokens($user,$user['session_id'],$next);
  }catch(\Throwable $e){$this->db->rollBack();throw $e;}
 }
 public function logout(array $user): void {$q=$this->db->prepare('UPDATE auth_sessions SET revoked=TRUE WHERE id=?');$q->execute([$user['session_id']]);}
 public function revokeUser(int $userId): void {$q=$this->db->prepare('UPDATE auth_sessions SET revoked=TRUE WHERE user_id=?');$q->execute([$userId]);}
 public static function tenant(array $user): Tenant {return new Tenant((int)$user['company_id'],(int)$user['id'],$user['role']);}
}
