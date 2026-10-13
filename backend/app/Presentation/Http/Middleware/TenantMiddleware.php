<?php
declare(strict_types=1);
namespace App\Presentation\Http\Middleware;
use App\Application\Services\AuthService;
use App\Domain\ApiException;
final class TenantMiddleware
{
 public function __construct(private AuthService $auth) {}
 public function authenticate(array $headers): array {
  $authorization=$headers['authorization']??'';
  if(!preg_match('/^Bearer (\S+)$/',$authorization,$match))throw new ApiException(401,'Autenticação necessária.');
  return $this->auth->verify($match[1]);
 }
}
