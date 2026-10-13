#!/usr/bin/env php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
define('SWOOLE_HOOK_FLAGS', \Hyperf\Engine\DefaultOption::hookFlags());
\Hyperf\Di\ClassLoader::init();
$container = require BASE_PATH . '/config/container.php';
$container->get(\Hyperf\Contract\ApplicationInterface::class)->run();
