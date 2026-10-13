<?php
declare(strict_types=1);
namespace App\Infrastructure;
use PDO;
final class Migrator
{
 public function __construct(private PDO $db) {}
 public function run(): void {
  $pg=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql';
  if ($pg) $this->db->exec('CREATE EXTENSION IF NOT EXISTS postgis');
  $this->db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at VARCHAR(40) NOT NULL)');
  $this->db->beginTransaction();
  try {
   if ($pg) $this->db->exec("SELECT pg_advisory_xact_lock(712807)");
   $files=glob(BASE_PATH.'/database/migrations/*_'.($pg?'postgis':'sqlite').'.sql');sort($files);
   foreach($files as $file) {
    $version=substr(basename($file),0,3);
    $q=$this->db->prepare('SELECT version FROM schema_migrations WHERE version=?');$q->execute([$version]);
    if (!$q->fetch()) {
     $this->db->exec(file_get_contents($file));
     $q=$this->db->prepare('INSERT INTO schema_migrations(version,applied_at) VALUES(?,?)');$q->execute([$version,gmdate('c')]);
    }
   }
   $this->backfillReportTokens();
   $this->db->commit();
  } catch (\Throwable $e) {$this->db->rollBack();throw $e;}
 }
 private function backfillReportTokens(): void {
  $rows=$this->db->query("SELECT id FROM reports WHERE public_token IS NULL OR public_token='' ")->fetchAll(PDO::FETCH_COLUMN);
  $update=$this->db->prepare('UPDATE reports SET public_token=? WHERE id=?');
  foreach($rows as $id)$update->execute([bin2hex(random_bytes(32)),(int)$id]);
 }
}
