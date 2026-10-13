<?php
declare(strict_types=1);
namespace App\Infrastructure\Satellite;
use App\Domain\SatelliteProviderInterface;
final class MockSatelliteProvider implements SatelliteProviderInterface
{
 public function searchImages(array $area,string $start,string $end,array $options=[]): array {
  return array_map(fn($stage)=>[
   'mine_id'=>$area['mine_id'],'monitoring_area_id'=>$area['id'],'source'=>'MOCK','satellite'=>'SIMULATED-S2','acquisition_date'=>$stage===0?$start:$end,
   'cloud_percentage'=>0,'storage_path'=>'mock://areas/'.$area['id'].'/stage/'.$stage,'thumbnail_path'=>null,
   'metadata_json'=>['simulated'=>true,'stage'=>$stage,'seed'=>(int)$area['id'],'bands'=>$this->getBands([]),'description'=>'Raster sintético para demonstrar o fluxo operacional.'],
   'processing_status'=>'COMPLETED',
  ],[0,1]);
 }
 public function getMetadata(array $image): array{return $image['metadata_json'];}
 public function downloadImage(array $image): array{return ['mode'=>'generate_synthetic_raster','uri'=>$image['storage_path'],'parameters'=>$this->getMetadata($image)];}
 public function getBands(array $image): array{return ['RED'=>1,'GREEN'=>2,'NIR'=>3,'SWIR'=>4];}
 public function getPreview(array $image): string {
  $stage=(int)($image['metadata_json']['stage']??0);$seed=(int)($image['metadata_json']['seed']??$image['monitoring_area_id']);$date=htmlspecialchars((string)$image['acquisition_date'],ENT_XML1);
  $svg='<svg xmlns="http://www.w3.org/2000/svg" width="960" height="600" viewBox="0 0 960 600"><rect width="960" height="600" fill="#34443b"/><path d="M0 50Q220 250 400 80T960 180V600H0Z" fill="#495d3a"/><path d="M0 470Q260 250 510 470T960 370" fill="none" stroke="#213c4b" stroke-width="42"/>';
  for($i=0;$i<90;$i++){$x=($i*137+$seed*33)%960;$y=($i*79+$seed*61)%600;$svg.='<ellipse cx="'.$x.'" cy="'.$y.'" rx="42" ry="29" fill="'.($i%3===0?'#6c7050':'#354e38').'" opacity=".55"/>';}
  $svg.='<path d="M490 600L510 400L670 270L750 0" stroke="#a19673" stroke-width="13" fill="none"/><path d="M100 90L190 190L110 260L230 340" stroke="#8b8268" stroke-width="6" fill="none"/>';
  if($stage===1)$svg.='<path d="M310 180L570 175L585 355L315 360Z" fill="#bba176"/><path d="M345 210L530 205L545 320L350 330Z" fill="#9e835c"/><path d="M390 235L480 230L500 285L400 300Z" fill="#d1bd91"/>';
  return $svg.'<rect y="552" width="960" height="48" fill="#111a20"/><text x="24" y="583" fill="#e6bd6f" font-family="sans-serif" font-size="18">IMAGEM SIMULADA · '.$date.' · Área '.$image['monitoring_area_id'].'</text></svg>';
 }
}
