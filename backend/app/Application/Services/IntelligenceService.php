<?php
declare(strict_types=1);
namespace App\Application\Services;

use App\Domain\{ApiException,Tenant};
use App\Infrastructure\Repositories\TenantRepository;

/** Scores are decision support, deliberately transparent and never estimates of mineral reserves. */
final class IntelligenceService
{
 public function __construct(private TenantRepository $repo) {}
 public function opportunity(int $areaId,Tenant $tenant): array {
  $area=$this->repo->find('monitoring_areas',$areaId,$tenant);
  $detections=array_values(array_filter($this->repo->all('change_detections',$tenant,['monitoring_area_id'=>$areaId]),fn($d)=>$d['type']==='GOLD_POTENTIAL'));
  $images=$this->repo->all('satellite_images',$tenant,['monitoring_area_id'=>$areaId]);
  $radar=count(array_filter($images,fn($i)=>$i['satellite']==='SENTINEL-1'&&$i['processing_status']==='COMPLETED'));
  $optical=count(array_filter($images,fn($i)=>$i['satellite']==='SENTINEL-2'&&$i['processing_status']==='COMPLETED'));
  $risk=(new ComplianceService($this->repo))->contextForArea($areaId,$tenant);
  $best=$detections?max(array_column($detections,'confidence')):0;
  $affected=array_sum(array_column($detections,'affected_area_hectares'));
  $evidence=min(40,round($best*.4))+min(20,count($detections)*10)+min(15,$optical*3)+min(10,$radar*2);
  $access=15; // kept explicit until roads/infrastructure data is connected.
  $regulatoryPenalty=round($risk['risk_score']*.15);
  $score=max(0,min(100,$evidence+$access-$regulatoryPenalty));
  $band=$score>=70?'PRIORITIZE_FIELD_VALIDATION':($score>=45?'REVIEW_GEOLOGY':($score>=20?'MONITOR':'INSUFFICIENT_EVIDENCE'));
  return ['monitoring_area_id'=>$areaId,'opportunity_score'=>$score,'recommendation'=>$band,'components'=>[
   ['name'=>'assinatura_espectral','points'=>min(40,round($best*.4)),'evidence'=>count($detections).' alvo(s), confiança máxima '.round($best,1).'%'],
   ['name'=>'cobertura_satelital','points'=>min(25,min(15,$optical*3)+min(10,$radar*2)),'evidence'=>"$optical óptica(s), $radar radar"],
   ['name'=>'acesso_base','points'=>$access,'evidence'=>'pontuação-base; conecte camada de vias para cálculo espacial'],
   ['name'=>'risco_regulatorio','points'=>-$regulatoryPenalty,'evidence'=>'prioridade '.$risk['priority']],
  ],'affected_hectares'=>round($affected,2),'disclaimer'=>'Score explicável de priorização. Não confirma ocorrência, teor, recurso ou reserva mineral; requer revisão geológica, direitos minerários e validação em campo.'];
 }
 public function copilot(array $input,Tenant $tenant): array {
  $question=trim((string)($input['question']??'')); if($question===''||mb_strlen($question)>1000)throw new ApiException(422,'Informe uma pergunta de até 1.000 caracteres.');
  $areaId=isset($input['monitoring_area_id'])?(int)$input['monitoring_area_id']:null;
  $alerts=$this->repo->all('alerts',$tenant); $open=array_values(array_filter($alerts,fn($a)=>in_array($a['status'],['OPEN','INVESTIGATING'],true)));
  $sources=[];$answer='';
  if($areaId){$score=$this->opportunity($areaId,$tenant);$context=(new ComplianceService($this->repo))->contextForArea($areaId,$tenant);$answer="A área #$areaId tem score de oportunidade {$score['opportunity_score']}/100 e recomendação {$score['recommendation']}. O contexto regulatório está em prioridade {$context['priority']}, com ".count($context['matches']).' sobreposição(ões) cadastrada(s).';$sources=[['type'=>'opportunity_score','monitoring_area_id'=>$areaId],['type'=>'risk_context','monitoring_area_id'=>$areaId]];}
  elseif(str_contains(mb_strtolower($question),'alert')){$answer='Há '.count($open).' alerta(s) aberto(s) ou em investigação. '.(count($open)?'Priorize os de maior severidade, atribua responsável e crie uma inspeção com prazo.':'Não há alertas pendentes no momento.');$sources=[['type'=>'alerts','count'=>count($open)]];}
  else {$answer='Posso resumir alertas pendentes ou explicar o score de uma área. Informe monitoring_area_id para obter uma resposta baseada em evidências territoriais e espectrais.';}
  return ['answer'=>$answer,'sources'=>$sources,'limitations'=>'O copiloto consulta apenas dados da sua empresa no MineVision. Não substitui profissional habilitado, validação de campo ou parecer jurídico.'];
 }
 public function createInspectionFromAlert(int $alertId,array $input,Tenant $tenant,ResourceService $resources): array {
  $tenant->allow(['ADMIN','ANALYST']);$alert=$this->repo->find('alerts',$alertId,$tenant);
  if(!in_array($alert['status'],['OPEN','INVESTIGATING'],true))throw new ApiException(422,'Só é possível encaminhar alertas abertos ou em investigação.');
  $data=['mine_id'=>$alert['mine_id'],'monitoring_area_id'=>$alert['monitoring_area_id'],'alert_id'=>$alert['id'],'inspector_user_id'=>$input['inspector_user_id']??null,'scheduled_at'=>$input['scheduled_at']??null,'status'=>'SCHEDULED','notes'=>$input['notes']??'Inspeção originada pelo alerta #'.$alert['id']];
  $inspection=$resources->create('inspections',$data,$tenant);$resources->update('alerts',$alertId,['status'=>'INVESTIGATING'],$tenant);return $inspection;
 }
}
