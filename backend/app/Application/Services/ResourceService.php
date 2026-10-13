<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Domain\{ApiException,Tenant,Schema};
use App\Application\DTO\ResourceRequest;
use App\Infrastructure\Repositories\TenantRepository;
final class ResourceService
{
 public function __construct(private TenantRepository $repo,private AuthService $auth) {}
 public function authorize(string $table,Tenant $tenant,string $method): void {
  if($method==='GET')return;
  if(in_array($table,['satellite_images','spectral_indices','change_detections','analysis_jobs','inspection_images','audit_events','mineral_occurrences'],true))throw new ApiException(405,'Use o fluxo específico deste recurso.');
  if(in_array($table,['companies','users','notification_rules','compliance_layers'],true))$tenant->allow(['ADMIN']);
  elseif($table==='inspections')$tenant->allow(['ADMIN','ANALYST','FIELD_TECHNICIAN']);
  else $tenant->allow(['ADMIN','ANALYST']);
  if($table==='companies'&&$method!=='PUT')throw new ApiException(405,'A empresa atual pode ser consultada ou editada.');
 }
 public function create(string $table,array $data,Tenant $tenant): array {
  $this->authorize($table,$tenant,'POST');$values=ResourceRequest::from($table,$data)->values;
  if($table==='users'&&!isset($values['password']))throw new ApiException(422,'Senha de pelo menos 12 caracteres obrigatória.');
  $values=[...Schema::defaults($table),...$values];$values=$this->prepare($table,$values,$tenant);
  $record=$this->repo->create($table,$values,$tenant);$this->audit('CREATED',$table,$record['id'],$tenant,['after'=>$record]);return $record;
 }
 public function update(string $table,int $id,array $data,Tenant $tenant): array {
  $this->authorize($table,$tenant,'PUT');$current=$this->repo->find($table,$id,$tenant);$this->ownership($table,$current,$tenant);
  $values=ResourceRequest::from($table,$data,false)->values;
  $merged=[...array_intersect_key($current,Schema::RESOURCES[$table]),...$values];$merged=$this->prepare($table,$merged,$tenant);
  if($table==='users'){
   if($id===$tenant->userId&&(($merged['active']??true)===false||($merged['role']??$tenant->role)!==$tenant->role))throw new ApiException(422,'Não remova seus próprios privilégios nesta sessão.');
   if(isset($values['password'])||isset($values['active'])||isset($values['role']))$this->auth->revokeUser($id);
  }
  $record=$this->repo->update($table,$id,$merged,$tenant);$this->audit('UPDATED',$table,$id,$tenant,['before'=>$current,'after'=>$record]);return $record;
 }
 public function delete(string $table,int $id,Tenant $tenant): void {
  $this->authorize($table,$tenant,'DELETE');$current=$this->repo->find($table,$id,$tenant);$this->ownership($table,$current,$tenant);
  if($table==='users'&&$id===$tenant->userId)throw new ApiException(422,'Você não pode excluir seu próprio usuário.');
  $this->repo->delete($table,$id,$tenant);$this->audit('DELETED',$table,$id,$tenant,['before'=>$current]);
 }
 public function ownership(string $table,array $record,Tenant $tenant): void {
  if($table==='inspections'&&$tenant->role==='FIELD_TECHNICIAN'&&(int)$record['inspector_user_id']!==$tenant->userId)throw new ApiException(403,'A inspeção pertence a outro responsável.');
 }
 private function prepare(string $table,array $values,Tenant $tenant): array {
  $linked=[];
  foreach(Schema::RESOURCES[$table] as $field=>$rule)if(str_starts_with($rule,'id:')&&isset($values[$field]))$linked[$field]=$this->repo->find(rtrim(substr($rule,3),'!'),(int)$values[$field],$tenant);
  if(isset($linked['monitoring_area_id'],$values['mine_id'])&&(int)$linked['monitoring_area_id']['mine_id']!==(int)$values['mine_id'])throw new ApiException(422,'A área não pertence à mina selecionada.');
  if(isset($linked['alert_id'])){
   $alert=$linked['alert_id'];
   if((int)$alert['mine_id']!==(int)$values['mine_id']||isset($values['monitoring_area_id'])&&(int)$alert['monitoring_area_id']!==(int)$values['monitoring_area_id'])throw new ApiException(422,'Alerta e inspeção devem referir-se à mesma mina e área.');
  }
  if(isset($linked['change_detection_id'])&&((int)$linked['change_detection_id']['mine_id']!==(int)$values['mine_id']||(int)$linked['change_detection_id']['monitoring_area_id']!==(int)($values['monitoring_area_id']??0)))throw new ApiException(422,'Detecção e alerta devem referir-se à mesma área.');
  if($table==='users'){
   $values['email']=strtolower($values['email']);
   if(isset($values['password'])){$values['password_hash']=password_hash($values['password'],PASSWORD_BCRYPT);unset($values['password']);}
  }
  if($table==='mines'){
   $center=$this->centroid($values['geometry']);
   $values['longitude']=$center[0];$values['latitude']=$center[1];
  }
  if($table==='monitoring_areas')$values['area_hectares']=$this->repo->area($values['geometry']);
  if($table==='inspections') {
   if($tenant->role==='FIELD_TECHNICIAN'&&(int)$values['inspector_user_id']!==$tenant->userId)throw new ApiException(403,'Você só pode assumir suas próprias inspeções.');
   if($values['status']==='COMPLETED'){
    if(trim($values['notes']??'')==='')throw new ApiException(422,'Registre o resultado para concluir a inspeção.');
    $values['performed_at']=$values['performed_at']??gmdate('c');
   }
  }
  if($table==='alerts'){
   if($values['status']==='INVESTIGATING')$values['acknowledged_at']=$values['acknowledged_at']??gmdate('c');
   $values['resolved_at']=in_array($values['status'],['RESOLVED','DISMISSED'],true)?($values['resolved_at']??gmdate('c')):null;
  }
  if($table==='reports'&&$values['period_start']>$values['period_end'])throw new ApiException(422,'Período invertido.');
  if($table==='notification_rules'&&($values['email_enabled']??false||$values['webhook_enabled']??false))throw new ApiException(422,'Envio de notificações é previsto para V1. Nesta versão, as regras podem ser cadastradas sem ativar canais de envio.');
  return $values;
 }
 private function centroid(array $geometry): array {
  $polygon=$geometry['type']==='Polygon'?$geometry['coordinates'][0]:$geometry['coordinates'][0][0];
  $points=array_slice($polygon,0,-1);$count=count($points);
  return [array_sum(array_column($points,0))/$count,array_sum(array_column($points,1))/$count];
 }
 private function audit(string $action,string $type,int $id,Tenant $tenant,array $payload): void {
  if($type==='audit_events')return;
  $q=$this->repo->db->prepare('INSERT INTO audit_events(company_id,actor_user_id,action,resource_type,resource_id,payload_json,created_at) VALUES(?,?,?,?,?,?,?)');
  $q->execute([$tenant->companyId,$tenant->userId,$action,$type,$id,json_encode($payload,JSON_THROW_ON_ERROR),gmdate('c')]);
 }
}
