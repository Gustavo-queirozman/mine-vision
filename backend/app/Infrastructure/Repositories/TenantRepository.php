<?php
declare(strict_types=1);
namespace App\Infrastructure\Repositories;
use App\Domain\{ApiException,Tenant,Schema,GeoJson};
use PDO;
final class TenantRepository
{
 public function __construct(public readonly PDO $db) {}
 public function pg(): bool{return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql';}
 private function scope(string $table): string{return $table==='companies'?'id':'company_id';}
 private function select(string $table): string {return 't.*'.($this->pg()&&isset(Schema::RESOURCES[$table]['geometry'])?', ST_AsGeoJSON(t.geometry) AS geometry_json':'');}
 public function normalize(array $record): array {
  if(array_key_exists('geometry_json',$record)){$record['geometry']=$record['geometry_json'];unset($record['geometry_json']);}
  foreach($record as $field=>$value) {
   if(in_array($field,['geometry','metadata_json','content_json','result_json','payload_json'],true)&&is_string($value))$record[$field]=json_decode($value,true,512,JSON_THROW_ON_ERROR);
   elseif($value!==null&&($field==='id'||str_ends_with($field,'_id')||$field==='attempts'))$record[$field]=(int)$value;
   elseif($value!==null&&in_array($field,['latitude','longitude','area_hectares','affected_area_hectares','confidence','cloud_percentage','min_value','max_value','mean_value','threshold'],true))$record[$field]=(float)$value;
   elseif(in_array($field,['active','monitoring_enabled','email_enabled','webhook_enabled'],true))$record[$field]=in_array($value,[true,1,'1','t','true'],true);
  }
  unset($record['password_hash']);return $record;
 }
 public function find(string $table,int $id,Tenant $tenant): array {
  Schema::table($table);$q=$this->db->prepare('SELECT '.$this->select($table).' FROM '.$table.' t WHERE t.id=? AND t.'.$this->scope($table).'=?');$q->execute([$id,$tenant->companyId]);
  $row=$q->fetch();if(!$row)throw new ApiException(404,'Registro não encontrado.');return $this->normalize($row);
 }
 public function findPublicReport(string $token): array {
  $q=$this->db->prepare('SELECT '.$this->select('reports').' FROM reports t WHERE t.public_token=?');$q->execute([$token]);
  $row=$q->fetch();if(!$row)throw new ApiException(404,'Relatório não encontrado.');return $this->normalize($row);
 }
 public function list(string $table,Tenant $tenant,array $filters=[]): array {
  Schema::table($table);$where=['t.'.$this->scope($table).'=?'];$params=[$tenant->companyId];$schema=Schema::RESOURCES[$table];
  foreach(['mine_id','monitoring_area_id','satellite_image_id','inspection_id','inspector_user_id','severity','status','type','role','processing_status'] as $field)if(isset($filters[$field])&&array_key_exists($field,$schema)){$where[]='t.'.$field.'=?';$params[]=$filters[$field];}
  if(!empty($filters['search'])) {
   $searchField=array_key_exists('name',$schema)?'name':(array_key_exists('title',$schema)?'title':null);
   if($searchField){$where[]='LOWER(t.'.$searchField.') LIKE ?';$params[]='%'.strtolower(substr((string)$filters['search'],0,200)).'%';}
  }
  foreach(['date_from'=>'>=','date_to'=>'<='] as $key=>$operator)if(!empty($filters[$key])) {
   if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$filters[$key]))throw new ApiException(422,'Filtro de data inválido.');
   $field=$table==='satellite_images'?'acquisition_date':($table==='alerts'?'detected_at':'created_at');$where[]='t.'.$field.$operator.'?';$params[]=$filters[$key].($key==='date_to'?'T23:59:59Z':'');
  }
  $where=implode(' AND ',$where);$count=$this->db->prepare("SELECT COUNT(*) FROM {$table} t WHERE {$where}");$count->execute($params);$total=(int)$count->fetchColumn();
  $page=max(1,(int)($filters['page']??1));$limit=min(100,max(1,(int)($filters['per_page']??25)));$sort=$filters['sort']??'created_at';
  if(!in_array($sort,['id','created_at','updated_at',...array_keys($schema)],true)||in_array($sort,['geometry','password','metadata_json'],true))$sort='created_at';
  $direction=strtolower((string)($filters['direction']??'desc'))==='asc'?'ASC':'DESC';$offset=($page-1)*$limit;
  $q=$this->db->prepare('SELECT '.$this->select($table)." FROM {$table} t WHERE {$where} ORDER BY t.{$sort} {$direction}, t.id {$direction} LIMIT {$limit} OFFSET {$offset}");$q->execute($params);
  return ['data'=>array_map($this->normalize(...),$q->fetchAll()),'meta'=>['page'=>$page,'per_page'=>$limit,'total'=>$total,'last_page'=>max(1,(int)ceil($total/$limit))]];
 }
 public function all(string $table,Tenant $tenant,array $filters=[]): array {
  $all=[];$page=1;do{$result=$this->list($table,$tenant,[...$filters,'per_page'=>100,'page'=>$page++]);$all=[...$all,...$result['data']];}while($page<=$result['meta']['last_page']);return $all;
 }
 private function binding(string $field,mixed $value,string $table): array {
  if($field==='geometry'&&$value!==null&&$this->pg())return [$table==='mines'?'ST_SetSRID(ST_GeomFromGeoJSON(?),4326)':'ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?),4326))',json_encode($value,JSON_THROW_ON_ERROR)];
  if(is_array($value))$value=json_encode($value,JSON_THROW_ON_ERROR);
  if(is_bool($value))$value=$this->pg()?($value?'true':'false'):(int)$value;
  return ['?',$value];
 }
 public function create(string $table,array $values,Tenant $tenant): array {
  Schema::table($table);if($table!=='companies')$values['company_id']=$tenant->companyId;
  $values['created_at']=gmdate('c');$values['updated_at']=gmdate('c');$fields=array_keys($values);$marks=[];$params=[];
  foreach($values as $field=>$value){[$sql,$parameter]=$this->binding($field,$value,$table);$marks[]=$sql;$params[]=$parameter;}
  $sql='INSERT INTO '.$table.' ('.implode(',',$fields).') VALUES ('.implode(',',$marks).')'.($this->pg()?' RETURNING id':'');$q=$this->db->prepare($sql);$q->execute($params);
  $id=$this->pg()?(int)$q->fetchColumn():(int)$this->db->lastInsertId();return $this->find($table,$id,$table==='companies'?new Tenant($id,$tenant->userId,$tenant->role):$tenant);
 }
 public function update(string $table,int $id,array $values,Tenant $tenant): array {
  $this->find($table,$id,$tenant);$values['updated_at']=gmdate('c');$sets=[];$params=[];
  foreach($values as $field=>$value){[$sql,$parameter]=$this->binding($field,$value,$table);$sets[]=$field.'='.$sql;$params[]=$parameter;}
  $q=$this->db->prepare('UPDATE '.$table.' SET '.implode(',',$sets).' WHERE id=? AND '.$this->scope($table).'=?');$q->execute([...$params,$id,$tenant->companyId]);return $this->find($table,$id,$tenant);
 }
 public function delete(string $table,int $id,Tenant $tenant): void {
  $this->find($table,$id,$tenant);$q=$this->db->prepare('DELETE FROM '.$table.' WHERE id=? AND '.$this->scope($table).'=?');$q->execute([$id,$tenant->companyId]);
 }
 public function area(array $geometry): float {
  if(!$this->pg())return GeoJson::area($geometry);
  $q=$this->db->prepare('SELECT ST_IsValid(g) AS valid, ST_Area(g::geography)/10000 AS hectares FROM (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?),4326) g) x');$q->execute([json_encode($geometry)]);$result=$q->fetch();
  if(!in_array($result['valid'],[true,1,'t'],true))throw new ApiException(422,'Geometria inválida segundo PostGIS.');return (float)$result['hectares'];
 }
 public function transaction(callable $fn): mixed {
  $this->db->beginTransaction();try{$result=$fn();$this->db->commit();return $result;}catch(\Throwable $e){$this->db->rollBack();throw $e;}
 }
}
