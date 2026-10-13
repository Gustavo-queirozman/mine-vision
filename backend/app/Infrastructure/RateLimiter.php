<?php
declare(strict_types=1);
namespace App\Infrastructure;
use PDO;
use App\Domain\ApiException;
final class RateLimiter
{
 public function __construct(private PDO $db) {}
 public function check(string $key,int $limit,int $window=60): void {
  $bucket='mv:rate:'.hash('sha256',$key).':'.(int)(time()/$window);
  if(extension_loaded('redis')&&getenv('REDIS_HOST')) {
   $redis=new \Redis();$redis->connect(getenv('REDIS_HOST'),6379,2);
   $count=$redis->incr($bucket);if($count===1)$redis->expire($bucket,$window+5);
  } else {
   $q=$this->db->prepare('INSERT INTO rate_limits(key,hits,expires_at) VALUES(?,1,?) ON CONFLICT(key) DO UPDATE SET hits=rate_limits.hits+1');$q->execute([$bucket,time()+$window+5]);
   $q=$this->db->prepare('SELECT hits FROM rate_limits WHERE key=?');$q->execute([$bucket]);$count=(int)$q->fetchColumn();
   $q=$this->db->prepare('DELETE FROM rate_limits WHERE expires_at<?');$q->execute([time()]);
  }
  if($count>$limit)throw new ApiException(429,'Muitas tentativas. Aguarde um minuto.');
 }
}
