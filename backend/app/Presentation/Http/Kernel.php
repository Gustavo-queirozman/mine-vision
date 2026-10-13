<?php
declare(strict_types=1);
namespace App\Presentation\Http;
use App\Domain\{ApiException,Tenant,Schema};
use App\Application\Services\{AuthService,ResourceService,MonitoringService,JobService,ReportService,ComplianceService,OfficialLayerSyncService};
use App\Application\DTO\ResourceRequest;
use App\Infrastructure\{Database,RateLimiter};
use App\Infrastructure\Repositories\TenantRepository;
use App\Infrastructure\Satellite\{MockSatelliteProvider,CopernicusSatelliteProvider};
use App\Domain\SatelliteProviderInterface;
use App\Infrastructure\Storage\ObjectStorage;
use App\Presentation\Http\Middleware\TenantMiddleware;
use PDO;
final class Kernel
{
 private TenantRepository $repo;private AuthService $auth;private ResourceService $resources;private MonitoringService $monitoring;private SatelliteProviderInterface $provider;
 public function __construct(?PDO $db=null) {
  $this->repo=new TenantRepository($db??Database::connect());$this->auth=new AuthService($this->repo->db);$this->resources=new ResourceService($this->repo,$this->auth);$this->provider=getenv('COPERNICUS_CLIENT_ID')&&getenv('COPERNICUS_CLIENT_SECRET')?new CopernicusSatelliteProvider():new MockSatelliteProvider();$this->monitoring=new MonitoringService($this->repo,$this->provider);
 }
 private function ok(mixed $data,int $status=200,array $headers=[]): array{return ['status'=>$status,'body'=>['data'=>$data],'headers'=>$headers];}
 private function raw(string $body,string $mime): array{return ['status'=>200,'body'=>$body,'headers'=>['Content-Type'=>$mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']];}
 public function handle(string $method,string $path,array $headers=[],array $input=[],array $query=[],string $ip='local',array $files=[]): array {
  $headers=array_change_key_case($headers,CASE_LOWER);$responseHeaders=['Content-Type'=>'application/json; charset=utf-8','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'no-store'];$requestId=bin2hex(random_bytes(8));$responseHeaders['X-Request-ID']=$requestId;
  try {
   $origin=$headers['origin']??null;
   if($origin){$allowed=explode(',',getenv('CORS_ORIGINS')?:'http://localhost:3000,http://127.0.0.1:3000');if(!in_array($origin,$allowed,true))throw new ApiException(403,'Origem não permitida.');$responseHeaders['Access-Control-Allow-Origin']=$origin;$responseHeaders['Vary']='Origin';$responseHeaders['Access-Control-Allow-Headers']='Authorization,Content-Type';$responseHeaders['Access-Control-Allow-Methods']='GET,POST,PUT,PATCH,DELETE,OPTIONS';}
   if($method==='OPTIONS')return ['status'=>204,'body'=>'','headers'=>$responseHeaders];
   $result=$this->dispatch($method,rtrim($path,'/')?:'/',$headers,$input,$query,$ip,$files);
  }catch(ApiException $e){$result=['status'=>$e->status,'body'=>['error'=>['code'=>$e->status,'message'=>$e->getMessage(),'details'=>$e->details,'request_id'=>$requestId]],'headers'=>[]];}
  catch(\PDOException $e){$code=str_starts_with((string)$e->getCode(),'23')||str_contains($e->getMessage(),'UNIQUE constraint')||str_contains($e->getMessage(),'FOREIGN KEY constraint')?409:500;error_log(json_encode(['request_id'=>$requestId,'event'=>'database_error','message'=>$e->getMessage()]));$result=['status'=>$code,'body'=>['error'=>['code'=>$code,'message'=>$code===409?'O registro conflita com dados existentes.':'Falha ao acessar os dados.','request_id'=>$requestId]],'headers'=>[]];}
  catch(\Throwable $e){error_log(json_encode(['request_id'=>$requestId,'event'=>'request_failed','message'=>$e->getMessage()]));$result=['status'=>500,'body'=>['error'=>['code'=>500,'message'=>'Não foi possível concluir a operação.','request_id'=>$requestId]],'headers'=>[]];}
  $result['headers']=[...$responseHeaders,...($result['headers']??[])];return $result;
 }
 private function dispatch(string $method,string $path,array $headers,array $input,array $query,string $ip,array $files): array {
  if($path==='/health'&&$method==='GET'){$this->repo->db->query('SELECT 1');return $this->ok(['status'=>'ok','service'=>'MineVision API','database'=>$this->repo->pg()?'PostGIS':'SQLite development']);}
  if($path==='/api/v1/auth/login'&&$method==='POST'){(new RateLimiter($this->repo->db))->check('login:'.$ip,10);return $this->ok($this->auth->login($input));}
  if($path==='/api/v1/auth/refresh'&&$method==='POST'){(new RateLimiter($this->repo->db))->check('refresh:'.$ip,30);return $this->ok($this->auth->refresh(is_string($input['refresh_token']??null)?$input['refresh_token']:''));}
  if($path==='/api/v1/openapi.json'&&$method==='GET')return $this->raw(file_get_contents(BASE_PATH.'/docs/openapi.json'),'application/json');
  if($path==='/api/v1/docs'&&$method==='GET')return $this->raw('<!doctype html><html><meta charset="utf-8"><title>MineVision API</title><body><h1>MineVision API v1</h1><p>Baixe <a href="/api/v1/openapi.json">OpenAPI JSON</a> e importe no Swagger Editor ou em um cliente REST.</p></body></html>','text/html; charset=utf-8');
  if(preg_match('#^/api/v1/public/reports/([a-f0-9]{64})/html$#',$path,$matches)&&$method==='GET'){$record=$this->repo->findPublicReport($matches[1]);return $this->raw((new ReportService($this->repo,$this->resources,new ObjectStorage()))->html($record),'text/html; charset=utf-8');}
  $user=(new TenantMiddleware($this->auth))->authenticate($headers);$tenant=AuthService::tenant($user);(new RateLimiter($this->repo->db))->check('user:'.$tenant->userId,300);
  if($path==='/api/v1/auth/me'&&$method==='GET')return $this->ok($this->repo->find('users',$tenant->userId,$tenant));
  if($path==='/api/v1/auth/logout'&&$method==='POST'){$this->auth->logout($user);return $this->ok(['logged_out'=>true]);}
  if($path==='/api/v1/dashboard/summary'&&$method==='GET')return $this->ok($this->monitoring->dashboard($tenant));
  if($path==='/api/v1/map/features'&&$method==='GET')return $this->ok($this->monitoring->map($tenant));
  if(preg_match('#^/api/v1/monitoring-areas/(\d+)/risk-context$#',$path,$matches)&&$method==='GET')return $this->ok((new ComplianceService($this->repo))->contextForArea((int)$matches[1],$tenant));
  if(preg_match('#^/api/v1/monitoring-areas/(\d+)/official-layers/sync$#',$path,$matches)&&$method==='POST')return $this->ok((new OfficialLayerSyncService($this->repo))->sync((int)$matches[1],$tenant));
  if(preg_match('#^/api/v1/mines/(\d+)/monitoring-areas$#',$path,$matches)&&$method==='GET'){$this->repo->find('mines',(int)$matches[1],$tenant);return ['status'=>200,'body'=>$this->repo->list('monitoring_areas',$tenant,[...$query,'mine_id'=>(int)$matches[1]])];}
  if(preg_match('#^/api/v1/monitoring-areas/(\d+)/(?:satellite-images|mock-images)$#',$path,$matches)&&$method==='POST')return $this->ok($this->monitoring->acquire((int)$matches[1],$input,$tenant),201);
  if(preg_match('#^/api/v1/satellite-images/(\d+)/preview$#',$path,$matches)&&$method==='GET'){
   $image=$this->repo->find('satellite_images',(int)$matches[1],$tenant);
   if(!empty($image['thumbnail_path'])){$object=(new ObjectStorage())->get($image['thumbnail_path']);return $this->raw($object['body'],$object['mime']);}
   $mime=($image['source']??'')==='MOCK'?'image/svg+xml':'image/png';
   return $this->raw($this->provider->getPreview($image),$mime);
  }
  if($path==='/api/v1/change-detections/analyze'&&$method==='POST')return $this->ok((new JobService($this->repo))->enqueue($input,$tenant),202);
  if(preg_match('#^/api/v1/alerts/(\d+)/status$#',$path,$matches)&&$method==='PATCH'){
   foreach(array_keys($input) as $key)if($key!=='status')throw new ApiException(422,'Este endpoint aceita somente status.');
   return $this->ok($this->resources->update('alerts',(int)$matches[1],$input,$tenant));
  }
  if(preg_match('#^/api/v1/inspections/(\d+)/images$#',$path,$matches)&&$method==='POST')return $this->ok($this->upload((int)$matches[1],$input,$files,$tenant),201);
  if(preg_match('#^/api/v1/inspection-images/(\d+)/content$#',$path,$matches)&&$method==='GET'){$image=$this->repo->find('inspection_images',(int)$matches[1],$tenant);$object=(new ObjectStorage())->get($image['storage_path']);return $this->raw($object['body'],$object['mime']);}
  if(preg_match('#^/api/v1/reports/(\d+)/html$#',$path,$matches)&&$method==='GET'){$record=$this->repo->find('reports',(int)$matches[1],$tenant);return $this->raw((new ReportService($this->repo,$this->resources,new ObjectStorage()))->html($record),'text/html; charset=utf-8');}
  if(preg_match('#^/api/v1/reports/(\d+)/pdf$#',$path,$matches)&&$method==='GET'){$record=$this->repo->find('reports',(int)$matches[1],$tenant);$filename='relatorio-minevision-'.$record['id'].'.pdf';return ['status'=>200,'body'=>(new ReportService($this->repo,$this->resources,new ObjectStorage()))->pdf($record),'headers'=>['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$filename.'"','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']];}
  if(preg_match('#^/api/v1/([a-z-]+)(?:/(\d+))?$#',$path,$matches)){
   $table=Schema::table($matches[1]);$id=isset($matches[2])?(int)$matches[2]:null;
   if($table==='users')$tenant->allow(['ADMIN','ANALYST']);
   if($table==='notification_rules')$tenant->allow(['ADMIN']);
   if($method==='GET')return $id?$this->ok($this->repo->find($table,$id,$tenant)):['status'=>200,'body'=>$this->repo->list($table,$tenant,$query)];
   if($method==='POST'&&!$id){
    if($table==='reports')return $this->ok($this->repo->transaction(fn()=>(new ReportService($this->repo,$this->resources,new ObjectStorage()))->create($input,$tenant)),201);
    return $this->ok($this->resources->create($table,$input,$tenant),201);
   }
   if(in_array($method,['PUT','PATCH'],true)&&$id)return $this->ok($this->resources->update($table,$id,$input,$tenant));
   if($method==='DELETE'&&$id){$this->resources->delete($table,$id,$tenant);return $this->ok(['deleted'=>true]);}
   throw new ApiException(405,'Método não permitido.');
  }
  throw new ApiException(404,'Endpoint não encontrado.');
 }
 private function upload(int $id,array $input,array $files,Tenant $tenant): array {
  $tenant->allow(['ADMIN','ANALYST','FIELD_TECHNICIAN']);$inspection=$this->repo->find('inspections',$id,$tenant);$this->resources->ownership('inspections',$inspection,$tenant);
  $bytes=$files['file']['bytes']??null;
  if($bytes===null&&isset($input['content_base64'])&&is_string($input['content_base64']))$bytes=base64_decode($input['content_base64'],true);
  if(!is_string($bytes)||strlen($bytes)>5*1024*1024||strlen($bytes)===0)throw new ApiException(422,'Envie uma imagem de até 5 MiB.');
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
  if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)||@getimagesizefromstring($bytes)===false)throw new ApiException(422,'Apenas JPEG, PNG e WebP válidos são aceitos.');
  $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];$key='companies/'.$tenant->companyId.'/inspections/'.$id.'/'.bin2hex(random_bytes(16)).'.'.$ext;
  $metadata=array_intersect_key($input,array_flip(['latitude','longitude','captured_at','description']));
  $values=ResourceRequest::from('inspection_images',['inspection_id'=>$id,'storage_path'=>'pending',...$metadata])->values;
  $values['storage_path']=(new ObjectStorage())->put($key,$bytes,$mime);return $this->repo->create('inspection_images',$values,$tenant);
 }
}
