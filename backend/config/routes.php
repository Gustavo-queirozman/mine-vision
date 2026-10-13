<?php
declare(strict_types=1);
use Hyperf\HttpServer\Router\Router;
use App\Presentation\Http\ApiController;
Router::addRoute(['GET','POST','PUT','PATCH','DELETE','OPTIONS'], '/{path:.*}', [ApiController::class, 'handle']);
