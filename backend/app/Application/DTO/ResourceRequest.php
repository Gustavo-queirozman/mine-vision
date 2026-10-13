<?php
declare(strict_types=1);
namespace App\Application\DTO;
use App\Domain\{ApiException,Schema,GeoJson};
final readonly class ResourceRequest
{
 public function __construct(public array $values) {}
 public static function from(string $table,array $input,bool $create=true): self {
  $schema=Schema::RESOURCES[$table];$values=[];$errors=[];
  foreach($input as $field=>$value)if(!array_key_exists($field,$schema))$errors[$field]='Campo não permitido.';
  foreach($schema as $field=>$rule) {
   $required=str_ends_with($rule,'!');$rule=rtrim($rule,'!');
   if(!array_key_exists($field,$input)) {if($required&&$create)$errors[$field]='Campo obrigatório.';continue;}
   $value=$input[$field];
   if($value===null&& !$required){$values[$field]=null;continue;}
   if(str_starts_with($rule,'enum:'))$valid=is_string($value)&&in_array($value,explode(',',substr($rule,5)),true);
   elseif(str_starts_with($rule,'id:'))$valid=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])!==false;
   else $valid=match($rule) {
    'text'=>is_string($value)&&strlen($value)<=10000&&(!$required||trim($value)!==''),
    'email'=>is_string($value)&&strlen($value)<=254&&filter_var($value,FILTER_VALIDATE_EMAIL)!==false,
    'password'=>is_string($value)&&strlen($value)>=12&&strlen($value)<=128,
    'bool'=>is_bool($value),
    'latitude','longitude','number','positive','percentage','threshold'=>is_numeric($value)&&is_finite((float)$value)&&match($rule){'latitude'=>abs((float)$value)<=90,'longitude'=>abs((float)$value)<=180,'positive'=>(float)$value>=0,'percentage'=>(float)$value>=0&&(float)$value<=100,'threshold'=>(float)$value>0&&(float)$value<=2,default=>true},
    'date'=>is_string($value)&&($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value))&&$d->format('Y-m-d')===$value,
    'datetime'=>is_string($value)&&preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/',$value)&&strtotime($value)!==false,
    'url'=>is_string($value)&&strlen($value)<=2048&&filter_var($value,FILTER_VALIDATE_URL)!==false&&parse_url($value,PHP_URL_SCHEME)==='https',
    'json'=>is_array($value), 'polygon','point'=>is_array($value),default=>false,
   };
   if(!$valid){$errors[$field]='Valor inválido.';continue;}
   if(in_array($rule,['polygon','point'],true))$value=GeoJson::validate($value,$rule==='point');
   $values[$field]=is_string($value)&&$rule==='text'?trim($value):$value;
  }
  if($errors)throw new ApiException(422,'Confira os campos informados.',$errors);
  return new self($values);
 }
}
