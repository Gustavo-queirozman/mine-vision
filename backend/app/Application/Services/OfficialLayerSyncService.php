<?php
declare(strict_types=1);
namespace App\Application\Services;

use App\Domain\{GeoJson,Tenant};
use App\Infrastructure\Repositories\TenantRepository;
use GuzzleHttp\Client;

/** Imports only public, official geoservices and keeps each imported feature traceable. */
final class OfficialLayerSyncService
{
 public function __construct(private TenantRepository $repo,private ?Client $http=null) {}
 public function sync(int $areaId,Tenant $tenant): array {
  $tenant->allow(['ADMIN','ANALYST']);$area=$this->repo->find('monitoring_areas',$areaId,$tenant);$bbox=$this->bbox($area['geometry']);$client=$this->http??new Client(['timeout'=>30,'connect_timeout'=>8,'headers'=>['User-Agent'=>'MineVision/1.0 official-geodata-sync']]);
  $sources=[
   ['source'=>'FUNAI','category'=>'INDIGENOUS_LAND','url'=>'https://geoserver.funai.gov.br/geoserver/Funai/ows','type'=>'Funai:tis_poligonais','home'=>'https://www.gov.br/funai/pt-br/atuacao/terras-indigenas/geoprocessamento-e-mapas'],
   ['source'=>'ICMBIO','category'=>'PROTECTED_AREA','url'=>'https://geoservicos.inde.gov.br/geoserver/ICMBio/ows','type'=>'ICMBio:limiteucsfederais_a','home'=>'https://www.gov.br/icmbio/pt-br/dados-icmbio/dados_geoespaciais'],
  ];$result=[];
  foreach($sources as $source)try{$features=$this->wfs($client,$source,$bbox);$result[]=$this->store($features,$source,$tenant);}catch(\Throwable $e){$result[]=['source'=>$source['source'],'imported'=>0,'updated'=>0,'error'=>'Serviço oficial indisponível ou respondeu em formato inesperado.'];}
  try{$features=$this->anm($client,$bbox);$result[]=$this->store($features,['source'=>'ANM','category'=>'MINING_RIGHT','home'=>'https://geo.anm.gov.br/arcgis/rest/services/SIGMINE/dados_anm/FeatureServer/0'],$tenant);}catch(\Throwable $e){$result[]=['source'=>'ANM','imported'=>0,'updated'=>0,'error'=>'SIGMINE indisponível ou respondeu em formato inesperado.'];}
  $result[]=['source'=>'IBAMA','imported'=>0,'updated'=>0,'status'=>'CATALOG_SYNC_PENDING','message'=>'O catálogo oficial foi identificado, mas a base nacional de embargos é publicada para download. A sincronização espacial será executada pelo job de importação de arquivo oficial, sem enviar a área monitorada ao IBAMA.'];
  $out=['monitoring_area_id'=>$areaId,'synchronized_at'=>gmdate('c'),'providers'=>$result];$this->record($areaId,$tenant,$out);return $out;
 }
 public function syncDue(): int {
  $cutoff=gmdate('c',time()-86400);$active=$this->repo->pg()?'u.active=true':'u.active=1';$enabled=$this->repo->pg()?'a.monitoring_enabled=true':'a.monitoring_enabled=1';$q=$this->repo->db->prepare("SELECT a.id,a.company_id,(SELECT u.id FROM users u WHERE u.company_id=a.company_id AND u.role IN ('ADMIN','ANALYST') AND $active ORDER BY u.id LIMIT 1) AS user_id FROM monitoring_areas a LEFT JOIN source_sync_runs s ON s.company_id=a.company_id AND s.monitoring_area_id=a.id WHERE $enabled AND (s.completed_at IS NULL OR s.completed_at<?) ORDER BY a.id LIMIT 10");$q->execute([$cutoff]);$count=0;foreach($q->fetchAll() as $row){if(!$row['user_id'])continue;try{$this->sync((int)$row['id'],new Tenant((int)$row['company_id'],(int)$row['user_id'],'ADMIN'));$count++;}catch(\Throwable $e){error_log('Official layer sync failed for area '.$row['id']);}}return $count;
 }
 private function wfs(Client $client,array $source,array $bbox): array {
  $response=$client->get($source['url'],['query'=>['service'=>'WFS','version'=>'2.0.0','request'=>'GetFeature','typeNames'=>$source['type'],'outputFormat'=>'application/json','srsName'=>'EPSG:4326','bbox'=>implode(',',$bbox).',EPSG:4326','count'=>1000]]);
  $data=json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR);return $data['features']??[];
 }
 private function anm(Client $client,array $bbox): array {
  [$west,$south,$east,$north]=$bbox;$response=$client->get('https://geo.anm.gov.br/arcgis/rest/services/SIGMINE/dados_anm/FeatureServer/0/query',['query'=>['f'=>'geojson','where'=>'1=1','geometry'=>"$west,$south,$east,$north",'geometryType'=>'esriGeometryEnvelope','spatialRel'=>'esriSpatialRelIntersects','inSR'=>4326,'outSR'=>4326,'outFields'=>'*','returnGeometry'=>'true','resultRecordCount'=>1000]]);
  $data=json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR);return $data['features']??[];
 }
 private function store(array $features,array $source,Tenant $tenant): array {
  $existing=[];foreach($this->repo->all('compliance_layers',$tenant) as $row)$existing[$row['source'].'|'.($row['external_id']??'')]=$row;
  $imported=0;$updated=0;foreach($features as $feature){$geometry=$feature['geometry']??null;if(!is_array($geometry)||!in_array($geometry['type']??'', ['Polygon','MultiPolygon'],true))continue;try{$geometry=GeoJson::validate($geometry);}catch(\Throwable){continue;}$props=is_array($feature['properties']??null)?$feature['properties']:[];$id=(string)$this->first($props,['processo','PROCESSO','codigo','codigo_uc','cd_uc','id','objectid','OBJECTID'],sha1(json_encode($geometry)));$name=(string)$this->first($props,['nome','NOME','nome_uc','NM_UC','titulo','PROCESSO'],($source['source'].' '.$id));$status=(string)$this->first($props,['fase','FASE','situacao','SITUACAO','status','STATUS'],'Conferir na fonte oficial');$values=['name'=>mb_substr($name,0,10000),'source'=>$source['source'],'category'=>$source['category'],'external_id'=>mb_substr($id,0,10000),'legal_status'=>mb_substr($status,0,10000),'source_url'=>$source['home'],'source_updated_at'=>gmdate('Y-m-d'),'geometry'=>$geometry];$key=$source['source'].'|'.$id;if(isset($existing[$key])){$this->repo->update('compliance_layers',(int)$existing[$key]['id'],$values,$tenant);$updated++;}else{$this->repo->create('compliance_layers',$values,$tenant);$imported++;}}
  return ['source'=>$source['source'],'imported'=>$imported,'updated'=>$updated];
 }
 private function first(array $properties,array $keys,mixed $fallback): mixed {foreach($keys as $key)if(isset($properties[$key])&&$properties[$key]!=='')return $properties[$key];return $fallback;}
 private function record(int $areaId,Tenant $tenant,array $result): void {$now=gmdate('c');$json=json_encode($result,JSON_THROW_ON_ERROR);$q=$this->repo->db->prepare('SELECT id FROM source_sync_runs WHERE company_id=? AND monitoring_area_id=?');$q->execute([$tenant->companyId,$areaId]);if($q->fetchColumn()){$q=$this->repo->db->prepare('UPDATE source_sync_runs SET completed_at=?,result_json=? WHERE company_id=? AND monitoring_area_id=?');$q->execute([$now,$json,$tenant->companyId,$areaId]);}else{$q=$this->repo->db->prepare('INSERT INTO source_sync_runs(company_id,monitoring_area_id,completed_at,result_json) VALUES(?,?,?,?)');$q->execute([$tenant->companyId,$areaId,$now,$json]);}}
 private function bbox(array $geometry): array {$points=[];$walk=function($value)use(&$walk,&$points){if(is_array($value)&&count($value)===2&&is_numeric($value[0])&&is_numeric($value[1]))$points[]=$value;elseif(is_array($value))foreach($value as $child)$walk($child);};$walk($geometry['coordinates']);$x=array_column($points,0);$y=array_column($points,1);return [min($x),min($y),max($x),max($y)];}
}
