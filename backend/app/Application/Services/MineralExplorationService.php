<?php
declare(strict_types=1);
namespace App\Application\Services;

use App\Domain\{ApiException,GeoJson,Tenant};
use App\Infrastructure\Repositories\TenantRepository;
use GuzzleHttp\Client;

/** Imports SGB/GeoSGB RECMIN occurrences and relates them to already processed Sentinel evidence. */
final class MineralExplorationService
{
 private const SGB_WFS='https://geoservicos.sgb.gov.br/geoserver/ows';
 private const SGB_HOME='https://opendata.sgb.gov.br/pages/sobre/';
 public function __construct(private TenantRepository $repo,private ?Client $http=null) {}

 public function sync(int $areaId,Tenant $tenant): array {
  $tenant->allow(['ADMIN','ANALYST']);$area=$this->repo->find('monitoring_areas',$areaId,$tenant);$client=$this->http??new Client(['timeout'=>30,'connect_timeout'=>8,'headers'=>['User-Agent'=>'MineVision/1.0 mineral-exploration','Accept-Encoding'=>'identity']]);
  try{$features=$this->recminWithRetry($client,$this->bbox($area['geometry']));}catch(\Throwable $e){error_log(json_encode(['event'=>'geosgb_request_failed','message'=>$e->getMessage()]));throw new ApiException(503,'A base pública GeoSGB está indisponível. Tente novamente mais tarde.');}
  $existing=[];foreach($this->repo->all('mineral_occurrences',$tenant,['monitoring_area_id'=>$areaId]) as $row)$existing[$row['source'].'|'.$row['external_id']]=$row;
  $imported=0;$updated=0;
  foreach($features as $feature){
   $geometry=$feature['geometry']??null;$properties=is_array($feature['properties']??null)?$feature['properties']:[];
   // `substancias` is the SGB's classification field.  Do not infer gold from
   // a locality name or the free-text geological description: that would turn
   // an incidental mention of gold into a purported official occurrence.
   if(!is_array($geometry)||($geometry['type']??'')!=='Point'||!$this->gold($properties))continue;
   try{$geometry=GeoJson::validate($geometry,true);}catch(\Throwable){continue;}
   $externalId=(string)$this->value($properties,['id_ocorrencia','ID_OCORRENCIA','id','ID','objectid','OBJECTID','codigo','CODIGO','numero','NUMERO'],sha1(json_encode($geometry)));
   $values=['monitoring_area_id'=>$areaId,'name'=>(string)$this->value($properties,['toponimia','TOPONIMIA','nome','NOME','name','NAME','ocorrencia','OCORRENCIA'],'Ocorrência de ouro '.$externalId),'source'=>'SGB','external_id'=>$externalId,'substance'=>(string)$this->value($properties,['substancias','SUBSTANCIAS'],'Ouro'),'occurrence_type'=>(string)$this->value($properties,['tipo_afloramento','TIPO_AFLORAMENTO','tipo','TIPO','classe','CLASSE','ocorrencia_tipo','OCORRENCIA_TIPO'],''),'economic_status'=>(string)$this->value($properties,['status_economico','STATUS_ECONOMICO','status','STATUS'],''),'importance'=>(string)$this->value($properties,['importancia','IMPORTANCIA'],''),'description'=>(string)$this->value($properties,['descricao','DESCRICAO','description','DESCRIPTION'],''),'source_url'=>$this->sourceUrl($externalId),'source_updated_at'=>$this->sourceDate($properties),'geometry'=>$geometry,'metadata_json'=>['provider'=>'SGB/GeoSGB','dataset'=>'RECMIN','source_feature_id'=>'ocorrencias_recursos_minerais.'.$externalId,'raw_properties'=>$properties]];
   $key='SGB|'.$externalId;if(isset($existing[$key])){$this->repo->update('mineral_occurrences',(int)$existing[$key]['id'],$values,$tenant);$updated++;}else{$this->repo->create('mineral_occurrences',$values,$tenant);$imported++;}
  }
  return ['monitoring_area_id'=>$areaId,'source'=>['name'=>'SGB/GeoSGB RECMIN','url'=>self::SGB_HOME,'description'=>'Ocorrências minerais públicas; podem representar ocorrência, depósito, mina ou garimpo e não constituem estimativa de recurso ou reserva.'],'imported'=>$imported,'updated'=>$updated,'spectral_crossing'=>$this->spectral($areaId,$tenant),'disclaimer'=>'Ouro não é detectado diretamente pelo Sentinel-2. O cruzamento reúne ocorrências oficiais e indicadores de alteração hidrotermal/óxidos de ferro para triagem técnica; exige geólogo habilitado, direitos minerários e validação de campo.'];
 }
 private function recmin(Client $client,array $bbox): array {
  $response=$client->get(self::SGB_WFS,['decode_content'=>false,'query'=>['service'=>'WFS','version'=>'2.0.0','request'=>'GetFeature','typeNames'=>'geosgb:ocorrencias_recursos_minerais','outputFormat'=>'application/json','srsName'=>'EPSG:4326','propertyName'=>'id_ocorrencia,toponimia,substancias,tipo_afloramento,status_economico,importancia,descricao,data_cadastro,geom','bbox'=>implode(',',$bbox).',EPSG:4326','count'=>1000]]);
  return $this->featuresFromGeoSgb((string)$response->getBody());
 }
 /** GeoSGB occasionally returns literal control characters in attribute values, which are invalid JSON. */
 private function featuresFromGeoSgb(string $body): array {
  // The WFS response is declared as UTF-8, but some records still contain
  // legacy bytes. Normalise those before handing the document to json_decode.
  if(!mb_check_encoding($body,'UTF-8'))$body=mb_convert_encoding($body,'UTF-8','UTF-8');
  try{$data=json_decode($body,true,512,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);}
  catch(\JsonException $error){
   if($error->getCode()!==JSON_ERROR_CTRL_CHAR)throw $error;
   // Do not use the UTF-8 modifier: the provider can combine a control byte
   // with legacy-encoded text in the same payload.
   $clean=preg_replace('/[\x00-\x1F\x7F]/','',$body);
   if($clean===null)throw $error;
   $data=json_decode($clean,true,512,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
  }
  return is_array($data['features']??null)?$data['features']:[];
 }
 private function recminWithRetry(Client $client,array $bbox): array {
  $last=null;
  for($attempt=0;$attempt<3;$attempt++){try{return $this->recmin($client,$bbox);}catch(\Throwable $e){$last=$e;if($attempt<2)usleep(200000*($attempt+1));}}
  throw $last??new \RuntimeException('Falha ao consultar GeoSGB.');
 }
 private function spectral(int $areaId,Tenant $tenant): array {
  $detections=array_values(array_filter($this->repo->all('change_detections',$tenant,['monitoring_area_id'=>$areaId]),fn(array $d)=>$d['type']==='GOLD_POTENTIAL'));
  $best=$detections?max(array_column($detections,'confidence')):0;
  return ['gold_potential_detections'=>count($detections),'highest_confidence'=>round((float)$best,2),'method'=>'Sentinel-2 proxies for hydrothermal alteration and iron oxides','interpretation'=>$detections?'Há indicadores espectrais para revisão conjunta com as ocorrências oficiais.':'Não há análise GOLD_POTENTIAL processada nesta área; adquira imagens Sentinel-2 e execute a análise antes de interpretar o cruzamento.'];
 }
 private function gold(array $properties): bool {
  $substances=(string)$this->value($properties,['substancias','SUBSTANCIAS'],'');
  return preg_match('/(^|[,;|\\s])(?:OURO|AU)(?=$|[,;|\\s])/u',mb_strtoupper($substances))===1;
 }
 private function sourceDate(array $properties): ?string {
  $value=(string)$this->value($properties,['data_cadastro','DATA_CADASTRO'],'');
  return preg_match('/^\\d{4}-\\d{2}-\\d{2}/',$value,$match)===1?$match[0]:null;
 }
 private function sourceUrl(string $externalId): string {
  return self::SGB_WFS.'?'.http_build_query(['service'=>'WFS','version'=>'2.0.0','request'=>'GetFeature','typeNames'=>'geosgb:ocorrencias_recursos_minerais','outputFormat'=>'application/json','featureID'=>'ocorrencias_recursos_minerais.'.$externalId]);
 }
 private function value(array $properties,array $keys,mixed $fallback): mixed {foreach($keys as $key)if(isset($properties[$key])&&$properties[$key]!=='')return $properties[$key];return $fallback;}
 private function bbox(array $geometry): array {$points=[];$walk=function($value)use(&$walk,&$points){if(is_array($value)&&count($value)===2&&is_numeric($value[0])&&is_numeric($value[1]))$points[]=$value;elseif(is_array($value))foreach($value as $child)$walk($child);};$walk($geometry['coordinates']);$x=array_column($points,0);$y=array_column($points,1);return [min($x),min($y),max($x),max($y)];}
}
