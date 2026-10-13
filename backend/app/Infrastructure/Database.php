<?php
declare(strict_types=1);
namespace App\Infrastructure;
use PDO;
final class Database
{
 public static function connect(): PDO {
  $driver=getenv('DB_DRIVER')?:'pgsql';
  if ($driver==='sqlite') {
   $path=getenv('SQLITE_PATH')?:BASE_PATH.'/runtime/minevision.sqlite';
   $pdo=new PDO('sqlite:'.$path);
   $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
  } else {
   $dsn=sprintf('pgsql:host=%s;port=%s;dbname=%s',getenv('DB_HOST')?:'database',getenv('DB_PORT')?:'5432',getenv('DB_DATABASE')?:'minevision');
   $pdo=new PDO($dsn,getenv('DB_USERNAME')?:'minevision',getenv('DB_PASSWORD')?:'');
  }
  $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
  return $pdo;
 }
}
