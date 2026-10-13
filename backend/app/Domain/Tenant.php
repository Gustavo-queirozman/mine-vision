<?php
declare(strict_types=1);
namespace App\Domain;
final readonly class Tenant
{
 public function __construct(public int $companyId, public int $userId, public string $role) {}
 public function allow(array $roles): void { if (!in_array($this->role,$roles,true)) throw new ApiException(403,'Sem permissão para esta operação.'); }
}
