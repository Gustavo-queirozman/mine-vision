<?php
declare(strict_types=1);
namespace App\Domain;
abstract readonly class Entity implements \JsonSerializable
{
 public function __construct(public array $attributes) {}
 public function jsonSerialize(): array { return $this->attributes; }
}
