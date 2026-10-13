<?php
declare(strict_types=1);
namespace App\Application\Services;
use App\Domain\Tenant;
use App\Infrastructure\Repositories\TenantRepository;
use App\Infrastructure\Storage\ObjectStorage;
final class ReportService
{
 public function __construct(private TenantRepository $repo,private ResourceService $resources,private ObjectStorage $storage) {}
 public function create(array $input,Tenant $tenant): array {
  $record=$this->resources->create('reports',$input,$tenant);
  $filters=['mine_id'=>$record['mine_id']];
  $content=['company'=>$this->repo->find('companies',$tenant->companyId,$tenant),'mine'=>$this->repo->find('mines',$record['mine_id'],$tenant),'period'=>[$record['period_start'],$record['period_end']],'monitoring_areas'=>$this->repo->all('monitoring_areas',$tenant,$filters)];
  $period=['date_from'=>$record['period_start'],'date_to'=>$record['period_end']];
  foreach(['change_detections','alerts','inspections'] as $table)$content[$table]=$this->repo->all($table,$tenant,[...$filters,...$period]);
  $content['gold_potential']=array_values(array_filter($content['change_detections'],fn($d)=>($d['type']??'')==='GOLD_POTENTIAL'));
  $compliance=new ComplianceService($this->repo);$content['regulatory_context']=array_map(fn($area)=>$compliance->contextForArea((int)$area['id'],$tenant),$content['monitoring_areas']);
  $intelligence=new IntelligenceService($this->repo);$content['opportunity_scores']=array_map(fn($area)=>$intelligence->opportunity((int)$area['id'],$tenant),$content['monitoring_areas']);
  $content['statistics']=['monitored_hectares'=>array_sum(array_column($content['monitoring_areas'],'area_hectares')),'affected_hectares'=>array_sum(array_column($content['change_detections'],'affected_area_hectares')),'gold_potential_hectares'=>array_sum(array_column($content['gold_potential'],'affected_area_hectares')),'gold_potential_targets'=>count($content['gold_potential']),'alerts'=>count($content['alerts']),'inspections'=>count($content['inspections'])];
  $content['generated_at']=gmdate('c');
  $record=$this->repo->update('reports',$record['id'],['created_by'=>$tenant->userId,'content_json'=>$content,'public_token'=>bin2hex(random_bytes(32))],$tenant);
  $path=$this->storage->put('companies/'.$tenant->companyId.'/reports/'.$record['id'].'.html',$this->html($record),'text/html; charset=utf-8');
  return $this->repo->update('reports',$record['id'],['storage_path'=>$path],$tenant);
 }
 public function html(array $report): string {
  $esc=fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$content=$report['content_json'];
  $html='<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>'.$esc($report['title']).'</title><style>body{font:15px system-ui;max-width:1000px;margin:40px auto;color:#17252b}h1,h2{color:#172e38}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccd6da;padding:8px;text-align:left}svg{width:100%;max-height:320px;background:#e8eeeb}.notice{padding:12px;background:#fff5d6;border-left:4px solid #b88628}small{color:#715919}@media print{body{margin:0}}</style><body><h1>MineVision · '.$esc($report['title']).'</h1><p>'.$esc($content['company']['name']).' · '.$esc($content['mine']['name']).'</p><p>Período: '.$esc($report['period_start']).' a '.$esc($report['period_end']).'</p><p class="notice">Dados de sensoriamento remoto. Assinaturas espectrais de potencial aurífero indicam alvos para investigação; não confirmam a presença de ouro, veio, teor ou reserva.</p>';
  $html.='<h2>Mapa esquemático das áreas</h2>'.$this->schematic($content['monitoring_areas']);
  foreach($content['statistics'] as $key=>$value)$html.='<p>'.$esc($key).': '.$esc(is_float($value)?round($value,2):$value).'</p>';
  $html.='<h2>Alvos espectrais de potencial aurífero</h2>';
  if(!$content['gold_potential'])$html.='<p>Nenhum alvo espectral de potencial aurífero foi identificado no período.</p>';
  else {$html.='<table><tr><th>Alvo</th><th>Área estimada</th><th>Confiança</th><th>Prioridade</th><th>Interpretação</th></tr>';foreach($content['gold_potential'] as $target){$interpretation=$target['metadata_json']['interpretation']??'Assinatura espectral; requer validação geológica e de campo.';$html.='<tr><td>#'.$esc($target['id']).'</td><td>'.$esc(round((float)$target['affected_area_hectares'],2)).' ha</td><td>'.$esc(round((float)$target['confidence'],1)).'%</td><td>'.$esc($target['severity']).'</td><td>'.$esc($interpretation).'</td></tr>';}$html.='</table>';}
  $html.='<h2>Contexto regulatório</h2><p class="notice">Sobreposições geográficas priorizam a revisão. Elas não confirmam impedimento legal, vigência de licença ou titularidade.</p>';
  foreach($content['regulatory_context'] as $context){$html.='<h3>Área #'.$esc($context['monitoring_area_id']).' · prioridade '.$esc($context['priority']).'</h3><p><small>'.$esc($context['notice']).'</small></p>';if(!$context['matches']){$html.='<p>Sem sobreposições nas camadas cadastradas.</p>';continue;}$html.='<table><tr><th>Camada</th><th>Fonte</th><th>Referência</th><th>Sobreposição</th></tr>';foreach($context['matches'] as $match)$html.='<tr><td>'.$esc($match['name']).'</td><td>'.$esc($match['source']).'</td><td>'.$esc($match['external_id']??'—').'</td><td>'.$esc($match['overlap_hectares']).' ha</td></tr>';$html.='</table>';}
  $html.='<h2>Priorização de oportunidade</h2><p class="notice">Score explicável de triagem; não representa recurso ou reserva mineral.</p><table><tr><th>Área</th><th>Score</th><th>Recomendação</th><th>Área de alvos</th></tr>';foreach($content['opportunity_scores'] as $score)$html.='<tr><td>#'.$esc($score['monitoring_area_id']).'</td><td>'.$esc($score['opportunity_score']).'/100</td><td>'.$esc($score['recommendation']).'</td><td>'.$esc($score['affected_hectares']).' ha</td></tr>';$html.='</table>';
  foreach(['monitoring_areas','change_detections','alerts','inspections'] as $table){$html.='<h2>'.$esc($table).'</h2><table><tr><th>Identificação</th><th>Situação / severidade</th><th>Detalhes</th></tr>';foreach($content[$table] as $row)$html.='<tr><td>'.$esc($row['name']??$row['title']??'#'.$row['id']).'</td><td>'.$esc($row['status']??$row['severity']??'monitorada').'</td><td>'.$esc($row['notes']??$row['description']??(isset($row['affected_area_hectares'])?round($row['affected_area_hectares'],2).' ha':'')).'</td></tr>';$html.='</table>';}
  return $html.'<p>Gerado em '.$esc($content['generated_at']).'</p></body></html>';
 }
 public function pdf(array $report): string {
  $content=$report['content_json'];$stats=$content['statistics'];$targets=$content['gold_potential'];$text=fn($value)=>str_replace(['\\','(',')'],['\\\\','\\(', '\\)'],iconv('UTF-8','Windows-1252//TRANSLIT',(string)$value)?:'');$line=fn($font,$size,$x,$y,$value)=>"BT /$font $size Tf $x $y Td (".$text($value).") Tj ET\n";$stream="q 0.06 0.15 0.15 rg 0 720 595 122 re f Q\n1 1 1 rg\n";
  $stream.=$line('F2',22,46,790,'RELATÓRIO DE INTELIGÊNCIA GEOESPACIAL').$line('F1',11,46,766,$report['title']).$line('F1',9,46,744,$content['mine']['name'].' · '.$content['mine']['municipality'].'/'.$content['mine']['state'])."0.06 0.15 0.15 rg\n";
  $cards=[['ÁREA MONITORADA',number_format((float)$stats['monitored_hectares'],1,',','.').' ha'],['ALVOS ESPECTRAIS',(string)$stats['gold_potential_targets']],['CONFIANÇA',$targets?number_format((float)$targets[0]['confidence'],0).'%':'—']];foreach($cards as $i=>$card){$x=46+$i*168;$stream.="q 0.94 0.96 0.94 rg $x 652 152 52 re f Q\n".$line('F2',8,$x+12,686,$card[0]).$line('F2',17,$x+12,664,$card[1]);}
  $stream.=$line('F2',13,46,623,'MAPA DE EVIDÊNCIAS').$line('F1',8,46,609,'Área monitorada e contorno dos alvos espectrais identificados no período');$stream.="q 0.90 0.93 0.91 rg 46 375 503 218 re f Q\n".$this->mapPdf(array_merge(array_map(fn($area)=>['geometry'=>$area['geometry'],'fill'=>[0.78,0.55,0.16]],$content['monitoring_areas']),array_map(fn($target)=>['geometry'=>$target['geometry'],'fill'=>[0.67,0.12,0.42],'strokeOnly'=>true],$targets)),46,375,503,218);
  $stream.="q 0.78 0.55 0.16 rg 48 359 8 8 re f Q\n".$line('F1',8,61,359,'Área monitorada')."q 0.67 0.12 0.42 rg 150 359 8 8 re f Q\n".$line('F1',8,163,359,'Alvo espectral');
  $stream.=$line('F2',13,46,329,'SÍNTESE TÉCNICA');$summary=$targets?('Alvo #'.$targets[0]['id'].' · '.number_format((float)$targets[0]['affected_area_hectares'],2,',','.').' ha · prioridade '.$targets[0]['severity'].'.'):'Nenhum alvo espectral de potencial aurífero foi identificado neste período.';$stream.=$line('F1',10,46,309,$summary).$line('F1',9,46,288,'Indicadores espectrais apontam áreas para investigação; não confirmam ouro, veio, teor ou reserva.').$line('F1',9,46,273,'Recomendação: validar em campo com geologia, amostragem e dados de sondagem.');
  $stream.="q 0.94 0.87 0.67 rg 46 205 503 46 re f Q\n".$line('F2',9,58,231,'NOTA DE USO RESPONSÁVEL').$line('F1',8,58,215,'Resultado automatizado de sensoriamento remoto. Decisões operacionais exigem avaliação de profissional habilitado.').$line('F1',8,46,42,'MineVision · Gerado em '.$content['generated_at']).$line('F1',8,452,42,'Página 1 de 1');return $this->pdfDocument($stream);
 }
 private function mapPdf(array $items,float $x,float $y,float $width,float $height): string {
  $rings=[];$xs=[];$ys=[];foreach($items as $item){$geometry=$item['geometry'];$polygons=$geometry['type']==='Polygon'?[$geometry['coordinates']]:$geometry['coordinates'];foreach($polygons as $polygon){$rings[]=[$polygon[0],$item['fill'],$item['strokeOnly']??false];foreach($polygon[0] as $point){$xs[]=$point[0];$ys[]=$point[1];}}}if(!$rings)return '';$west=min($xs);$east=max($xs);$south=min($ys);$north=max($ys);$out='';foreach($rings as [$ring,$color,$strokeOnly]){$points=[];foreach($ring as $point)$points[]=[round($x+($point[0]-$west)/max(.00001,$east-$west)*$width,2),round($y+($point[1]-$south)/max(.00001,$north-$south)*$height,2)];$out.="q {$color[0]} {$color[1]} {$color[2]} rg {$color[0]} {$color[1]} {$color[2]} RG ".($strokeOnly?'3':'1.4')." w ".$points[0][0].' '.$points[0][1]." m ";foreach(array_slice($points,1) as $point)$out.=$point[0].' '.$point[1]." l ";$out.='h '.($strokeOnly?'S':'B')." Q\n";}return $out;
 }
 private function pdfDocument(string $stream): string {
  $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>','<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $index=>$object){$offsets[]=strlen($pdf);$pdf.=($index+1)." 0 obj\n".$object."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 7\n0000000000 65535 f \n";for($i=1;$i<=6;$i++)$pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";$pdf.="trailer << /Size 7 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";return $pdf;
 }
 private function schematic(array $areas): string {
  $rings=[];$xs=[];$ys=[];foreach($areas as $area){$geometry=$area['geometry'];$polygons=$geometry['type']==='Polygon'?[$geometry['coordinates']]:$geometry['coordinates'];foreach($polygons as $polygon){$rings[]=$polygon[0];foreach($polygon[0] as $p){$xs[]=$p[0];$ys[]=$p[1];}}}
  if(!$rings)return '<p>Sem áreas cadastradas.</p>';
  $west=min($xs);$east=max($xs);$south=min($ys);$north=max($ys);$svg='<svg viewBox="0 0 900 320" xmlns="http://www.w3.org/2000/svg">';
  foreach($rings as $ring){$points=implode(' ',array_map(fn($p)=>(30+($p[0]-$west)/max(.00001,$east-$west)*840).','.(290-($p[1]-$south)/max(.00001,$north-$south)*260),$ring));$svg.='<polygon points="'.$points.'" fill="#dca64b" fill-opacity=".3" stroke="#8e6d35"/>';}
  return $svg.'</svg><p>WGS84; representação esquemática, sem base cartográfica.</p>';
 }
}
