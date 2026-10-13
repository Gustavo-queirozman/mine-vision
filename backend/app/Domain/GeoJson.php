<?php
declare(strict_types=1);
namespace App\Domain;
final class GeoJson
{
 public static function validate(mixed $geometry, bool $point=false): array {
  if (!is_array($geometry) || !isset($geometry['type'],$geometry['coordinates'])) throw new ApiException(422,'Geometria GeoJSON obrigatória.');
  $type=$geometry['type'];$coordinates=$geometry['coordinates'];
  if ($point) {
   if ($type!=='Point') throw new ApiException(422,'A mina requer geometria Point.');
   self::position($coordinates);return ['type'=>'Point','coordinates'=>$coordinates];
  }
  if (!in_array($type,['Polygon','MultiPolygon'],true) || !is_array($coordinates) || !$coordinates) throw new ApiException(422,'Use Polygon ou MultiPolygon não vazio.');
  $polygons=$type==='Polygon'?[$coordinates]:$coordinates;$vertices=0;
  foreach($polygons as $polygon) {
   if (!is_array($polygon)||!$polygon) throw new ApiException(422,'Polígono vazio.');
   foreach($polygon as $ring) {
    if (!is_array($ring)||count($ring)<4||$ring[0]!==$ring[count($ring)-1]) throw new ApiException(422,'Os anéis devem estar fechados e possuir pelo menos quatro posições.');
    $vertices+=count($ring);if ($vertices>1000) throw new ApiException(422,'Limite de 1000 vértices excedido.');
    foreach($ring as $position) self::position($position);
    if (count(array_unique(array_map(fn($p)=>json_encode($p),$ring)))<3) throw new ApiException(422,'Polígono degenerado.');
    $xs=array_column($ring,0);if(max($xs)-min($xs)>180) throw new ApiException(422,'Áreas que atravessam o antimeridiano não são suportadas nesta versão.');
    $count=count($ring)-1;
    for($i=0;$i<$count;$i++)for($j=$i+2;$j<$count;$j++) {
     if($i===0&&$j===$count-1)continue;
     if(self::cross($ring[$i],$ring[$i+1],$ring[$j],$ring[$j+1]))throw new ApiException(422,'O polígono possui auto-interseção.');
    }
   }
   foreach(array_slice($polygon,1) as $hole)if(!self::inside($hole[0],$polygon[0]))throw new ApiException(422,'Anel interno fora do polígono.');
  }
  if(self::area($geometry)<=0)throw new ApiException(422,'A área do polígono deve ser positiva.');
  return ['type'=>$type,'coordinates'=>$coordinates];
 }
 private static function position(mixed $point): void {
  if(!is_array($point)||count($point)!==2||!is_int($point[0])&&!is_float($point[0])||!is_int($point[1])&&!is_float($point[1])||!is_finite((float)$point[0])||!is_finite((float)$point[1])||abs($point[0])>180||abs($point[1])>90)throw new ApiException(422,'Coordenada WGS84 inválida; use [longitude, latitude].');
 }
 private static function cross(array $a,array $b,array $c,array $d): bool {
  $f=fn($p,$q,$r)=>($q[0]-$p[0])*($r[1]-$p[1])-($q[1]-$p[1])*($r[0]-$p[0]);
  return $f($a,$b,$c)*$f($a,$b,$d)<0&&$f($c,$d,$a)*$f($c,$d,$b)<0;
 }
 public static function inside(array $point,array $ring): bool {
  $inside=false;$j=count($ring)-1;
  for($i=0;$i<count($ring);$j=$i++)if(($ring[$i][1]>$point[1])!==($ring[$j][1]>$point[1])&&$point[0]<($ring[$j][0]-$ring[$i][0])*($point[1]-$ring[$i][1])/($ring[$j][1]-$ring[$i][1])+$ring[$i][0])$inside=!$inside;
  return $inside;
 }
 public static function area(array $geometry): float {
  $polygons=$geometry['type']==='Polygon'?[$geometry['coordinates']]:$geometry['coordinates'];$total=0;
  foreach($polygons as $polygon)foreach($polygon as $index=>$ring) {
   $sum=0;for($i=0;$i<count($ring)-1;$i++)$sum+=deg2rad($ring[$i+1][0]-$ring[$i][0])*(2+sin(deg2rad($ring[$i][1]))+sin(deg2rad($ring[$i+1][1])));
   $total+=($index===0?1:-1)*abs($sum)*6371008.8**2/2;
  }
  return max(0,$total/10000);
 }
}
