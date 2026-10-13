<?php
declare(strict_types=1);
// Development adapter: same Kernel, services and repository; no business logic duplication.
require dirname(__DIR__).'/bin/bootstrap.php';
$headers=array_change_key_case(getallheaders(),CASE_LOWER);$body=file_get_contents('php://input');
if(strlen($body)>8*1024*1024){http_response_code(413);header('Content-Type: application/json');echo json_encode(['error'=>['code'=>413,'message'=>'Corpo muito grande.']]);exit;}
try{
 $input=str_contains($headers['content-type']??'','application/json')?($body===''?[]:json_decode($body,true,64,JSON_THROW_ON_ERROR)):$_POST;
 if(!is_array($input)||array_is_list($input)&&$input!==[])throw new JsonException();
}catch(JsonException){http_response_code(400);header('Content-Type: application/json');echo json_encode(['error'=>['code'=>400,'message'=>'JSON inválido.']]);exit;}
$files=[];foreach($_FILES as $key=>$file)if($file['error']===UPLOAD_ERR_OK&&$file['size']<=5*1024*1024)$files[$key]=['bytes'=>file_get_contents($file['tmp_name'])];
$result=(new \App\Presentation\Http\Kernel())->handle($_SERVER['REQUEST_METHOD'],parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),$headers,$input,$_GET,$_SERVER['REMOTE_ADDR']??'local',$files);
http_response_code($result['status']);foreach($result['headers'] as $key=>$value)header($key.': '.$value);
echo is_array($result['body'])?json_encode($result['body'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):$result['body'];
