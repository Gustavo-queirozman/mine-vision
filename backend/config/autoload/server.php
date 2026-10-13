<?php
declare(strict_types=1);
use Hyperf\Server\Event;
return [
 'mode'=>SWOOLE_BASE,
 'servers'=>[['name'=>'http','type'=>\Hyperf\Server\Server::SERVER_HTTP,'host'=>'0.0.0.0','port'=>9501,'sock_type'=>SWOOLE_SOCK_TCP,'callbacks'=>[Event::ON_REQUEST=>[\Hyperf\HttpServer\Server::class,'onRequest']]]],
 'settings'=>['enable_coroutine'=>true,'worker_num'=>2,'pid_file'=>BASE_PATH.'/runtime/hyperf.pid','max_request'=>10000,'package_max_length'=>12*1024*1024],
 'callbacks'=>[Event::ON_WORKER_START=>[\Hyperf\Framework\Bootstrap\WorkerStartCallback::class,'onWorkerStart'],Event::ON_PIPE_MESSAGE=>[\Hyperf\Framework\Bootstrap\PipeMessageCallback::class,'onPipeMessage'],Event::ON_WORKER_EXIT=>[\Hyperf\Framework\Bootstrap\WorkerExitCallback::class,'onWorkerExit']],
];
