<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
if (!is_dir(BASE_PATH . '/runtime')) mkdir(BASE_PATH . '/runtime', 0750, true);
date_default_timezone_set('UTC');
