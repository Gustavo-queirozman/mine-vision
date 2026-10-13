<?php
declare(strict_types=1);
namespace App\Application\Services;

use App\Domain\{ApiException,Tenant};
use App\Infrastructure\Repositories\TenantRepository;

/** Produz contexto para priorização; não substitui parecer, licença ou validação jurídica. */
final class ComplianceService
{
 private const WEIGHTS=['PROTECTED_AREA'=>100,'INDIGENOUS_LAND'=>100,'EMBARGO'=>90,'TAILINGS'=>80,'WATER_BODY'=>60,'MINING_RIGHT'=>45,'LICENSE_BOUNDARY'=>35,'OTHER'=>20];
 public function __construct(private TenantRepository $repo) {}
 public function contextForArea(int $areaId,Tenant $tenant): array {
  $area=$this->repo->find('monitoring_areas',$areaId,$tenant);$layers=$this->repo->all('compliance_layers',$tenant);
  $matches=[];$score=0;$exact=$this->repo->pg();
  foreach($layers as $layer){
   $hectares=$this->intersectionHectares($area['geometry'],$layer['geometry']);
   if($hectares<=0)continue;
   $weight=self::WEIGHTS[$layer['category']]??20;$score=max($score,$weight);
   $matches[]=['id'=>$layer['id'],'name'=>$layer['name'],'source'=>$layer['source'],'category'=>$layer['category'],'external_id'=>$layer['external_id'],'legal_status'=>$layer['legal_status'],'source_url'=>$layer['source_url'],'source_updated_at'=>$layer['source_updated_at'],'overlap_hectares'=>round($hectares,2),'priority'=>$this->priority($weight)];
  }
  usort($matches,fn($a,$b)=>$b['overlap_hectares']<=>$a['overlap_hectares']);
  $notice=$layers===[]?'Nenhuma camada regulatória foi cadastrada para esta empresa. Cadastre ou importe uma camada oficial antes de executar o cruzamento.':($exact?'Sobreposições calculadas geometricamente no PostGIS. Confirme a vigência e o enquadramento da fonte oficial antes de agir.':'Ambiente SQLite: resultado aproximado por caixa envolvente, apenas para desenvolvimento. Use PostGIS para decisão operacional.');
  return ['monitoring_area_id'=>$areaId,'registered_layers'=>count($layers),'risk_score'=>$score,'priority'=>$this->priority($score),'method'=>$exact?'POSTGIS_EXACT':'BBOX_DEVELOPMENT_ONLY','notice'=>$notice,'matches'=>$matches];
 }
 private function priority(int $score): string{return $score>=90?'CRITICAL':($score>=60?'HIGH':($score>=35?'MEDIUM':'LOW'));}
 private function intersectionHectares(array $left,array $right): float {
  if($this->repo->pg()){
   $q=$this->repo->db->prepare('SELECT ST_Area(ST_Intersection(ST_SetSRID(ST_GeomFromGeoJSON(?),4326),ST_SetSRID(ST_GeomFromGeoJSON(?),4326))::geography)/10000');
   $q->execute([json_encode($left,JSON_THROW_ON_ERROR),json_encode($right,JSON_THROW_ON_ERROR)]);return (float)$q->fetchColumn();
  }
  [$a0,$a1,$a2,$a3]=$this->bounds($left);[$b0,$b1,$b2,$b3]=$this->bounds($right);$west=max($a0,$b0);$east=min($a1,$b1);$south=max($a2,$b2);$north=min($a3,$b3);
  if($west>=$east||$south>=$north)return 0.;return (($east-$west)*111320)*(($north-$south)*111320)*cos(deg2rad(($south+$north)/2))/10000;
 }
 private function bounds(array $geometry): array {
  $coordinates=$geometry['type']==='Polygon'?$geometry['coordinates']:$geometry['coordinates'];$points=[];$walk=function($value)use(&$walk,&$points){if(is_array($value)&&count($value)===2&&is_numeric($value[0])&&is_numeric($value[1]))$points[]=$value;elseif(is_array($value))foreach($value as $child)$walk($child);};$walk($coordinates);
  if(!$points)throw new ApiException(422,'Camada sem geometria válida.');$xs=array_column($points,0);$ys=array_column($points,1);return [min($xs),max($xs),min($ys),max($ys)];
 }
}
