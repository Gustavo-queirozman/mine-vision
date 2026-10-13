<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$db=\App\Infrastructure\Database::connect();
match($argv[1]??'') {
 'migrate'=>(new \App\Infrastructure\Migrator($db))->run(),
 'seed'=>(new \App\Infrastructure\Seeder($db))->run(),
 default=>throw new RuntimeException('Use migrate ou seed.'),
};
echo "Concluído.\n";
