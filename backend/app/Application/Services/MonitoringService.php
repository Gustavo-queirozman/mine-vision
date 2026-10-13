<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Domain\{ApiException,Tenant};
use App\Infrastructure\Repositories\TenantRepository;
use App\Domain\SatelliteProviderInterface;
final class MonitoringService
{
 public function __construct(private TenantRepository $repo,private SatelliteProviderInterface $provider) {}
 public function acquire(int $areaId,array $data,Tenant $tenant): array {
  $tenant->allow(['ADMIN','ANALYST']);$area=$this->repo->find('monitoring_areas',$areaId,$tenant);
  foreach(['start','end'] as $key){$value=$data[$key]??'';$date=is_string($value)?\DateTimeImmutable::createFromFormat('!Y-m-d',$value):false;if(!$date||$date->format('Y-m-d')!==$value)throw new ApiException(422,'Informe datas válidas.');}
  if($data['start']>=$data['end'])throw new ApiException(422,'A data final deve ser posterior à inicial.');
  $sources=$data['sources']??['SENTINEL-1','SENTINEL-2'];
  if(!is_array($sources)||$sources===[]||count($sources)>2||array_diff($sources,['SENTINEL-1','SENTINEL-2']))throw new ApiException(422,'Selecione Sentinel-1, Sentinel-2, ou ambos.');
  return $this->repo->transaction(fn()=>array_map(fn($image)=>$this->repo->create('satellite_images',$image,$tenant),$this->provider->searchImages($area,$data['start'],$data['end'],['sources'=>array_values(array_unique($sources))])));
 }
 public function map(Tenant $tenant): array {
  $tables=['mines','monitoring_areas','compliance_layers','change_detections','alerts','inspections','operational_assets','exploration_records'];
  $records=[];$index=[];
  foreach($tables as $table){
   try{$records[$table]=$this->repo->all($table,$tenant);}catch(\PDOException $e){if(in_array($table,['operational_assets','exploration_records'],true)){$records[$table]=[];continue;}throw $e;}
   $index[$table]=[];foreach($records[$table] as $record)$index[$table][(int)$record['id']]=$record;
  }
  $result=[];
  foreach($tables as $table){
   $features=[];foreach($records[$table] as $record){
    $geometry=$this->mapGeometry($table,$record,$index);
    if($geometry){unset($record['geometry']);$features[]=['type'=>'Feature','id'=>$record['id'],'geometry'=>$geometry,'properties'=>['resource'=>$table,...$record]];}
   }$result[$table]=['type'=>'FeatureCollection','features'=>$features];
  }return $result;
 }
 private function mapGeometry(string $table,array $record,array $index): ?array {
  if(isset($record['geometry'])&&is_array($record['geometry'])&&isset($record['geometry']['type']))return $record['geometry'];
  if(isset($record['longitude'],$record['latitude'])&&$record['longitude']!==null&&$record['latitude']!==null)return ['type'=>'Point','coordinates'=>[$record['longitude'],$record['latitude']]];
  // Eventos sem coordenadas próprias permanecem visíveis no contexto espacial
  // mais específico ao qual estão vinculados.
  if($table==='inspections'&&isset($record['alert_id'])){
   $alert=$index['alerts'][(int)$record['alert_id']]??null;
   if($alert&&($geometry=$this->mapGeometry('alerts',$alert,$index)))return $geometry;
  }
  if(in_array($table,['alerts','inspections'],true)&&isset($record['change_detection_id'])){
   $detection=$index['change_detections'][(int)$record['change_detection_id']]??null;
   if($detection&&($geometry=$this->mapGeometry('change_detections',$detection,$index)))return $geometry;
  }
  if(in_array($table,['alerts','inspections'],true)&&isset($record['monitoring_area_id'])){
   $area=$index['monitoring_areas'][(int)$record['monitoring_area_id']]??null;
   if($area&&($geometry=$this->mapGeometry('monitoring_areas',$area,$index)))return $geometry;
  }
  if(in_array($table,['alerts','inspections'],true)&&isset($record['mine_id'])){
   $mine=$index['mines'][(int)$record['mine_id']]??null;
   if($mine)return $this->mapGeometry('mines',$mine,$index);
  }
  return null;
 }
 public function dashboard(Tenant $tenant): array {
  $mines=$this->repo->all('mines',$tenant);$areas=$this->repo->all('monitoring_areas',$tenant);$alerts=$this->repo->all('alerts',$tenant);$images=$this->repo->all('satellite_images',$tenant);$inspections=$this->repo->all('inspections',$tenant);
  $open=array_values(array_filter($alerts,fn($a)=>in_array($a['status'],['OPEN','INVESTIGATING'],true)));$distribution=array_fill_keys(['LOW','MEDIUM','HIGH','CRITICAL'],0);$period=[];
  foreach($alerts as $alert){$distribution[$alert['severity']]++;$day=substr($alert['detected_at']??$alert['created_at'],0,10);$period[$day]=($period[$day]??0)+1;}
  ksort($period);$activities=[];foreach([['alerts',$alerts],['inspections',$inspections],['satellite_images',$images]] as [$type,$records])foreach($records as $record)$activities[]=['type'=>$type,'id'=>$record['id'],'title'=>$record['title']??($type==='inspections'?'Inspeção #'.$record['id']:'Imagem '.$record['satellite']),'at'=>$record['updated_at'],'status'=>$record['status']??$record['processing_status']];
  usort($activities,fn($a,$b)=>strcmp($b['at'],$a['at']));
  return ['counts'=>['mines'=>count($mines),'monitored_hectares'=>array_sum(array_column(array_filter($areas,fn($a)=>$a['monitoring_enabled']),'area_hectares')),'open_alerts'=>count($open),'critical_alerts'=>count(array_filter($open,fn($a)=>$a['severity']==='CRITICAL')),'processed_images'=>count(array_filter($images,fn($i)=>$i['processing_status']==='COMPLETED')),'pending_inspections'=>count(array_filter($inspections,fn($i)=>in_array($i['status'],['SCHEDULED','IN_PROGRESS'],true)))], 'recent_alerts'=>array_slice($alerts,0,6),'recent_images'=>array_slice($images,0,5),'severity_distribution'=>$distribution,'alerts_by_period'=>array_map(fn($date,$count)=>['date'=>$date,'count'=>$count],array_keys($period),array_values($period)),'activities'=>array_slice($activities,0,10),'simulated'=>true];
 }
}
