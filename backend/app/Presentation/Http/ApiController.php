<?php
declare(strict_types=1);
namespace App\Presentation\Http;
use Hyperf\HttpServer\Contract\{RequestInterface,ResponseInterface};
use Psr\Http\Message\ResponseInterface as PsrResponse;
final class ApiController
{
 public function __construct(private RequestInterface $request,private ResponseInterface $response) {}
 public function handle(): PsrResponse {
  $headers=[];foreach($this->request->getHeaders() as $key=>$value)$headers[strtolower($key)]=implode(',',$value);
  $body=(string)$this->request->getBody();$contentType=$headers['content-type']??'';
  if(strlen($body)>8*1024*1024)return $this->response->json(['error'=>['code'=>413,'message'=>'Corpo muito grande.']])->withStatus(413);
  if(str_contains($contentType,'application/json')){
   try{$input=$body===''?[]:json_decode($body,true,64,JSON_THROW_ON_ERROR);if(!is_array($input)||array_is_list($input)&&$input!==[])throw new \JsonException();}catch(\JsonException){return $this->response->json(['error'=>['code'=>400,'message'=>'JSON inválido.']])->withStatus(400);}
  }else $input=$this->request->all();
  $files=[];foreach($this->request->getUploadedFiles() as $key=>$file)if($file->getError()===UPLOAD_ERR_OK&&$file->getSize()<=5*1024*1024)$files[$key]=['bytes'=>(string)$file->getStream()];
  $result=(new Kernel())->handle($this->request->getMethod(),$this->request->getUri()->getPath(),$headers,$input,$this->request->getQueryParams(),(string)($this->request->getServerParams()['remote_addr']??'unknown'),$files);
  $response=is_array($result['body'])?$this->response->json($result['body']):$this->response->raw($result['body']);
  foreach($result['headers'] as $key=>$value)$response=$response->withHeader($key,$value);
  return $response->withStatus($result['status']);
 }
}
