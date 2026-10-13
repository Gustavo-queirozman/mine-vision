<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Domain\{ApiException,Tenant,GeoJson};
use App\Application\DTO\ResourceRequest;
use App\Infrastructure\Repositories\TenantRepository;
use GuzzleHttp\Client;
final class JobService
{
 public function __construct(private TenantRepository $repo) {}
 public function enqueue(array $data,Tenant $tenant): array {
  $tenant->allow(['ADMIN','ANALYST']);$data=ResourceRequest::from('analysis_jobs',$data)->values;
  $area=$this->repo->find('monitoring_areas',(int)$data['monitoring_area_id'],$tenant);
  if((int)$area['mine_id']!==(int)$data['mine_id'])throw new ApiException(422,'A área deve pertencer à mina.');
  $reference=$this->repo->find('satellite_images',(int)$data['reference_image_id'],$tenant);$current=$this->repo->find('satellite_images',(int)$data['current_image_id'],$tenant);
  foreach([$reference,$current] as $image)if((int)$image['monitoring_area_id']!==$area['id']||$image['processing_status']!=='COMPLETED')throw new ApiException(422,'Selecione imagens disponíveis da mesma área.');
  if($reference['id']===$current['id']||$reference['acquisition_date']>=$current['acquisition_date'])throw new ApiException(422,'Selecione duas imagens em ordem temporal.');
  if(($reference['satellite']??'')!==($current['satellite']??''))throw new ApiException(422,'Selecione imagens do mesmo Sentinel; dados ópticos e radar não devem ser comparados diretamente.');
  $job=$this->repo->create('analysis_jobs',[...$data,'threshold'=>$data['threshold']??0.18,'status'=>'PENDING','requested_by'=>$tenant->userId],$tenant);
  if(extension_loaded('redis')&&getenv('REDIS_HOST'))try{$redis=new \Redis();$redis->connect(getenv('REDIS_HOST'),6379,2);$redis->lPush('mv:jobs',(string)$job['id']);}catch(\Throwable $e){error_log('Fila Redis indisponível; job persistido no banco.');}
  return $job;
 }
 public function processOne(): bool {
  $db=$this->repo->db;$db->beginTransaction();
  try {
   $stale=gmdate('c',time()-600);$q=$db->prepare("UPDATE analysis_jobs SET status='PENDING',started_at=NULL WHERE status='PROCESSING' AND started_at<? AND attempts<3");$q->execute([$stale]);
   $q=$db->prepare("UPDATE analysis_jobs SET status='FAILED',error='Limite de tentativas após interrupções.' WHERE status='PROCESSING' AND started_at<? AND attempts>=3");$q->execute([$stale]);
   $q=$db->query("SELECT * FROM analysis_jobs WHERE status='PENDING' ORDER BY id LIMIT 1".($this->repo->pg()?' FOR UPDATE SKIP LOCKED':''));$row=$q->fetch();
   if(!$row){$db->commit();return false;}
   $q=$db->prepare("UPDATE analysis_jobs SET status='PROCESSING',started_at=?,updated_at=?,attempts=attempts+1 WHERE id=? AND status='PENDING'");$q->execute([gmdate('c'),gmdate('c'),$row['id']]);$db->commit();
  }catch(\Throwable $e){$db->rollBack();throw $e;}
  $tenant=new Tenant((int)$row['company_id'],(int)$row['requested_by'],'ANALYST');$jobId=(int)$row['id'];
  try {
   $area=$this->repo->find('monitoring_areas',(int)$row['monitoring_area_id'],$tenant);$reference=$this->repo->find('satellite_images',(int)$row['reference_image_id'],$tenant);$current=$this->repo->find('satellite_images',(int)$row['current_image_id'],$tenant);
   $client=new Client(['timeout'=>180,'connect_timeout'=>5]);
   $response=$client->post((getenv('GEO_URL')?:'http://127.0.0.1:8000').'/analyze',['headers'=>['X-Internal-Token'=>getenv('INTERNAL_TOKEN')],'json'=>['job_id'=>$jobId,'company_id'=>$tenant->companyId,'monitoring_area_id'=>$area['id'],'geometry'=>$area['geometry'],'reference_image'=>$reference,'current_image'=>$current,'analysis_type'=>$row['analysis_type']??'CHANGE_DETECTION','threshold'=>(float)$row['threshold']]]);
   $result=json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR);
   if(!isset($result['affected_area_hectares'],$result['severity'],$result['confidence'],$result['metadata']))throw new \RuntimeException('Resultado incompleto.');
   $this->repo->transaction(function()use($result,$row,$tenant,$jobId,$reference,$current){
    $detection=null;$alert=null;
    if($result['affected_area_hectares']>0){
     $geometry=GeoJson::validate($result['geometry']);
     $isProspection=($row['analysis_type']??'CHANGE_DETECTION')==='GOLD_POTENTIAL';$type=$isProspection?'GOLD_POTENTIAL':($result['metadata']['activity_class']??'SURFACE_CHANGE');
     $detection=$this->repo->create('change_detections',['mine_id'=>(int)$row['mine_id'],'monitoring_area_id'=>(int)$row['monitoring_area_id'],'reference_image_id'=>$reference['id'],'current_image_id'=>$current['id'],'type'=>$type,'severity'=>$result['severity'],'affected_area_hectares'=>(float)$result['affected_area_hectares'],'geometry'=>$geometry,'confidence'=>(float)$result['confidence'],'metadata_json'=>$result['metadata']],$tenant);
     $title=$isProspection?'Alvo espectral com potencial para ouro':'Alteração de superfície detectada';
     $description=$isProspection?sprintf('%.2f ha apresentam assinatura espectral compatível com minerais associados a ouro. É uma triagem remota e requer validação geológica e de campo.',$result['affected_area_hectares']):sprintf('%.2f ha de mudança entre %s e %s. Resultado automatizado; requer análise humana.',$result['affected_area_hectares'],$reference['acquisition_date'],$current['acquisition_date']);
     $alert=$this->repo->create('alerts',['mine_id'=>(int)$row['mine_id'],'monitoring_area_id'=>(int)$row['monitoring_area_id'],'change_detection_id'=>$detection['id'],'title'=>$title,'description'=>$description,'type'=>$type,'severity'=>$result['severity'],'status'=>'OPEN','detected_at'=>gmdate('c')],$tenant);
    }
    foreach($result['images']??[] as $image)$this->repo->update('satellite_images',(int)$image['id'],['storage_path'=>$image['storage_path'],'thumbnail_path'=>$image['thumbnail_path']],$tenant);
    foreach($result['indices']??[] as $index)$this->repo->create('spectral_indices',$index,$tenant);
    $this->repo->update('analysis_jobs',$jobId,['status'=>'COMPLETED','completed_at'=>gmdate('c'),'result_json'=>[...$result,'detection_id'=>$detection['id']??null,'alert_id'=>$alert['id']??null]],$tenant);
   });
  }catch(\Throwable $e){error_log(json_encode(['event'=>'job_failed','job_id'=>$jobId,'error'=>$e->getMessage()]));$this->repo->update('analysis_jobs',$jobId,['status'=>'FAILED','completed_at'=>gmdate('c'),'error'=>'Falha no processamento. Verifique os logs do worker e tente uma nova análise.'],$tenant);}
  return true;
 }
}
