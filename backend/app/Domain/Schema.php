<?php
declare(strict_types=1);
namespace App\Domain;
final class Schema
{
 public const ROLES=['ADMIN','ANALYST','FIELD_TECHNICIAN','VIEWER'];
 public const SEVERITIES=['LOW','MEDIUM','HIGH','CRITICAL'];
 public const RESOURCES=[
 'companies'=>['name'=>'text!','document'=>'text','email'=>'email!','phone'=>'text'],
 'users'=>['name'=>'text!','email'=>'email!','password'=>'password','role'=>'enum:ADMIN,ANALYST,FIELD_TECHNICIAN,VIEWER!','active'=>'bool'],
 'mines'=>['name'=>'text!','description'=>'text','status'=>'enum:ACTIVE,INACTIVE,PLANNED!','latitude'=>'latitude','longitude'=>'longitude','municipality'=>'text!','state'=>'text!','country'=>'text','mineral'=>'text','geometry'=>'polygon!'],
 'monitoring_areas'=>['mine_id'=>'id:mines!','name'=>'text!','description'=>'text','geometry'=>'polygon!','monitoring_enabled'=>'bool'],
 'compliance_layers'=>['name'=>'text!','source'=>'enum:ANM,IBAMA,FUNAI,ICMBIO,MAPBIOMAS,STATE,COMPANY,OTHER!','category'=>'enum:MINING_RIGHT,LICENSE_BOUNDARY,PROTECTED_AREA,INDIGENOUS_LAND,EMBARGO,WATER_BODY,TAILINGS,OTHER!','external_id'=>'text','legal_status'=>'text','source_url'=>'url','source_updated_at'=>'date','geometry'=>'polygon!'],
 'satellite_images'=>['mine_id'=>'id:mines!','monitoring_area_id'=>'id:monitoring_areas!','source'=>'text!','satellite'=>'text!','acquisition_date'=>'date!','cloud_percentage'=>'percentage','storage_path'=>'text','thumbnail_path'=>'text','metadata_json'=>'json','processing_status'=>'enum:PENDING,PROCESSING,COMPLETED,FAILED'],
 'spectral_indices'=>['satellite_image_id'=>'id:satellite_images!','type'=>'enum:NDVI,NDWI,NBR,CUSTOM!','storage_path'=>'text','min_value'=>'number','max_value'=>'number','mean_value'=>'number','metadata_json'=>'json'],
 'change_detections'=>['mine_id'=>'id:mines!','monitoring_area_id'=>'id:monitoring_areas!','reference_image_id'=>'id:satellite_images!','current_image_id'=>'id:satellite_images!','type'=>'text!','severity'=>'enum:LOW,MEDIUM,HIGH,CRITICAL!','affected_area_hectares'=>'positive!','geometry'=>'polygon!','confidence'=>'percentage!','metadata_json'=>'json'],
 'alerts'=>['mine_id'=>'id:mines!','monitoring_area_id'=>'id:monitoring_areas','change_detection_id'=>'id:change_detections','title'=>'text!','description'=>'text','type'=>'text!','severity'=>'enum:LOW,MEDIUM,HIGH,CRITICAL!','status'=>'enum:OPEN,INVESTIGATING,RESOLVED,DISMISSED!','detected_at'=>'datetime','acknowledged_at'=>'datetime','resolved_at'=>'datetime','assigned_user_id'=>'id:users'],
 'inspections'=>['mine_id'=>'id:mines!','monitoring_area_id'=>'id:monitoring_areas','alert_id'=>'id:alerts','inspector_user_id'=>'id:users!','status'=>'enum:SCHEDULED,IN_PROGRESS,COMPLETED,CANCELLED!','scheduled_at'=>'datetime','performed_at'=>'datetime','latitude'=>'latitude','longitude'=>'longitude','notes'=>'text'],
 'inspection_images'=>['inspection_id'=>'id:inspections!','storage_path'=>'text!','latitude'=>'latitude','longitude'=>'longitude','captured_at'=>'datetime','description'=>'text'],
 'reports'=>['mine_id'=>'id:mines!','title'=>'text!','type'=>'enum:MONITORING,CHANGES,INSPECTION,EXECUTIVE!','period_start'=>'date!','period_end'=>'date!'],
 'notification_rules'=>['name'=>'text!','event_type'=>'text!','minimum_severity'=>'enum:LOW,MEDIUM,HIGH,CRITICAL!','email_enabled'=>'bool','webhook_enabled'=>'bool','webhook_url'=>'url','active'=>'bool'],
 'analysis_jobs'=>['mine_id'=>'id:mines!','monitoring_area_id'=>'id:monitoring_areas!','reference_image_id'=>'id:satellite_images!','current_image_id'=>'id:satellite_images!','analysis_type'=>'enum:CHANGE_DETECTION,GOLD_POTENTIAL','threshold'=>'threshold'],
 'audit_events'=>['actor_user_id'=>'id:users','action'=>'text!','resource_type'=>'text!','resource_id'=>'positive','payload_json'=>'json'],
 'operational_assets'=>['mine_id'=>'id:mines!','kind'=>'enum:TAILINGS_DAM,LICENSE,DRONE_SURVEY!','name'=>'text!','status'=>'text!','due_at'=>'datetime','geometry'=>'polygon','metadata_json'=>'json'],
 'exploration_records'=>['mine_id'=>'id:mines!','monitoring_area_id'=>'id:monitoring_areas','sample_code'=>'text!','record_type'=>'enum:SAMPLE,DRILLHOLE!','lithology'=>'text','depth_m'=>'positive','grade_gpt'=>'positive','geometry'=>'point','metadata_json'=>'json'],
 'mineral_occurrences'=>['monitoring_area_id'=>'id:monitoring_areas!','name'=>'text!','source'=>'enum:SGB!','external_id'=>'text!','substance'=>'text!','occurrence_type'=>'text','economic_status'=>'text','importance'=>'text','description'=>'text','source_url'=>'url!','source_updated_at'=>'date','geometry'=>'point!','metadata_json'=>'json'],
 ];
 public const TABLES=['companies','users','mines','monitoring_areas','compliance_layers','satellite_images','spectral_indices','change_detections','alerts','inspections','inspection_images','reports','notification_rules','analysis_jobs','audit_events','operational_assets','exploration_records','mineral_occurrences'];
 public static function table(string $resource): string {
  $table=str_replace('-','_',$resource);
  if (!in_array($table,self::TABLES,true)) throw new ApiException(404,'Recurso não encontrado.');
  return $table;
 }
 public static function defaults(string $table): array {
  return match($table) {
   'users'=>['active'=>true], 'mines'=>['country'=>'Brasil','mineral'=>'ouro'], 'monitoring_areas'=>['monitoring_enabled'=>true],
   'satellite_images'=>['processing_status'=>'PENDING','metadata_json'=>[],'cloud_percentage'=>0],
   'alerts'=>['detected_at'=>gmdate('c')], 'reports'=>['status'=>'COMPLETED'],
   'notification_rules'=>['active'=>true,'email_enabled'=>false,'webhook_enabled'=>false], 'analysis_jobs'=>['status'=>'PENDING','analysis_type'=>'CHANGE_DETECTION','threshold'=>0.18], default=>[],
  };
 }
}
