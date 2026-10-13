<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$once=in_array('--once',$argv,true);
do {
 try{
  $repo=new \App\Infrastructure\Repositories\TenantRepository(\App\Infrastructure\Database::connect());
  $service=new \App\Application\Services\JobService($repo);
  $processed=$service->processOne();
  if(!$processed)(new \App\Application\Services\OfficialLayerSyncService($repo))->syncDue();
 }catch(\Throwable $e){error_log('Worker: '.$e->getMessage());$processed=false;}
 if(!$processed&&!$once){
  if(extension_loaded('redis')&&getenv('REDIS_HOST'))try{$r=new Redis();$r->connect(getenv('REDIS_HOST'),6379,2);$r->brPop(['mv:jobs'],2);}catch(Throwable){sleep(2);}else sleep(2);
 }
}while(!$once);
