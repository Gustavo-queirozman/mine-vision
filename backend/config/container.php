<?php
declare(strict_types=1);
$container = new \Hyperf\Di\Container((new \Hyperf\Di\Definition\DefinitionSourceFactory())());
return \Hyperf\Context\ApplicationContext::setContainer($container);
