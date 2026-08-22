<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap/app.php';
use App\Services\WhatsApp\WahaConfig;
use App\Services\WhatsApp\WahaException;
use App\Services\WhatsApp\WahaHttpClient;
use App\Services\WhatsApp\WahaService;
use App\Services\WhatsApp\WahaWebhookHandler;
use App\Services\WhatsApp\WahaWebhookStore;
final class FakeHttp implements WahaHttpClient {
    public array $requests = []; public array $responses = [];
    public function request(string $method,string $url,array $headers,?string $body,int $timeoutSeconds): array { $this->requests[] = compact('method','url','headers','body','timeoutSeconds'); $next = array_shift($this->responses); if ($next instanceof Throwable) throw $next; return $next; }
}
final class FakeStore implements WahaWebhookStore {
    public array $keys=[]; public array $events=[];
    public function enqueue(string $requestId,string $eventId,string $eventType,string $payloadJson,bool $fromMe): bool { if(isset($this->keys[$requestId]) || isset($this->keys[$eventId])) return false; $this->keys[$requestId]=true; $this->keys[$eventId]=true; $this->events[]=compact('requestId','eventId','eventType','payloadJson','fromMe'); return true; }
}
function check(bool $condition,string $message): void { if(!$condition) throw new RuntimeException($message); }
function throws(callable $callable,string $class): void { try{$callable();}catch(Throwable $e){check($e instanceof $class,"Excecao inesperada: ".get_class($e));return;}throw new RuntimeException('Excecao esperada nao ocorreu.'); }
$config = new WahaConfig('https://waha.example.test','test-api-key','klubecash','test-hmac');
check(WahaService::normalizePhone('(11) 99999-9999')==='5511999999999@c.us','Falha celular local.');
check(WahaService::normalizePhone('+55 11 99999-9999')==='5511999999999@c.us','Falha celular com DDI.');
check(WahaService::normalizePhone('(55) 3333-4444')==='555533334444@c.us','Falha DDD 55.');
throws(fn()=>WahaService::normalizePhone('10999999999'),InvalidArgumentException::class);
throws(fn()=>(new WahaService($config,new FakeHttp()))->sendText('11999999999','  '),InvalidArgumentException::class);
$http = new FakeHttp(); $http->responses[]=['status'=>200,'body'=>'{"id":"msg-1"}']; $service=new WahaService($config,$http); $sent=$service->sendText('11999999999','Oi');
check($sent['id']==='msg-1' && count($http->requests)===1,'Envio mock falhou.');
$request=$http->requests[0]; $body=json_decode($request['body'],true); check($body['chatId']==='5511999999999@c.us' && $body['session']==='klubecash','Payload WAHA incorreto.'); check($request['timeoutSeconds']===15,'Timeout incorreto.'); check(str_contains(implode('|',$request['headers']),'X-Api-Key: test-api-key'),'Header ausente.');
$http = new FakeHttp(); $http->responses[]=['status'=>401,'body'=>'{}']; throws(fn()=>(new WahaService($config,$http))->sendText('11999999999','Oi'),WahaException::class);
$http = new FakeHttp(); $http->responses[] = new WahaException('timeout',503,true); throws(fn()=>(new WahaService($config,$http))->sendText('11999999999','Oi'),WahaException::class); check(count($http->requests)===1,'Envio foi repetido e pode duplicar mensagem.');
$http = new FakeHttp(); $http->responses[]=['status'=>500,'body'=>'{}']; $http->responses[]=['status'=>200,'body'=>'{"status":"WORKING"}']; $status=(new WahaService($config,$http))->connectionStatus(); check($status['available']===true && count($http->requests)===2,'Status/backoff falhou.');
$http = new FakeHttp(); $http->responses[]=['status'=>200,'body'=>'{"status":"STOPPED"}']; check((new WahaService($config,$http))->connectionStatus()['available']===false,'Status aceitou sessao indisponivel.');
$store=new FakeStore(); $handler=new WahaWebhookHandler($config,$store); $event=['id'=>'evt-1','event'=>'message','session'=>'klubecash','payload'=>['id'=>'msg-1','from'=>'5511999999999@c.us','fromMe'=>false,'body'=>'segredo']]; $raw=json_encode($event); $sig=hash_hmac('sha512',$raw,'test-hmac');
check($handler->validSignature($raw,$sig) && !$handler->validSignature($raw,str_repeat('0',128)),'Validacao HMAC falhou.');
check($handler->handle($raw,$sig,'req-1')['status']===200,'Webhook valido rejeitado.'); check($handler->handle($raw,$sig,'req-1')['body']['duplicate']===true,'Idempotencia falhou.');
$other=$event; $other['id']='evt-2'; $other['session']='other'; $otherRaw=json_encode($other); check($handler->handle($otherRaw,hash_hmac('sha512',$otherRaw,'test-hmac'),'req-2')['status']===403,'Outra sessao aceita.');
$mine=$event; $mine['id']='evt-3'; $mine['payload']['id']='msg-3'; $mine['payload']['fromMe']=true; $mineRaw=json_encode($mine); $mineResult=$handler->handle($mineRaw,hash_hmac('sha512',$mineRaw,'test-hmac'),'req-3'); check($mineResult['body']['ignored']===true && end($store->events)['fromMe']===true,'Prevencao de loop falhou.');
check($handler->handle($raw,str_repeat('0',128),'req-4')['status']===401,'HMAC incorreto aceito.');
$sendRoute = null;
foreach (require dirname(__DIR__, 2) . '/routes/api.php' as $route) {
    if ($route['path'] === '/api/whatsapp/send-text') { $sendRoute = $route; break; }
}
check(is_array($sendRoute), 'Endpoint de envio administrativo ausente.');
check($sendRoute['methods'] === ['POST'], 'Endpoint de envio aceita metodo indevido.');
check(in_array('admin', $sendRoute['middleware'], true), 'Endpoint de envio nao exige administrador.');
echo "OK: testes WAHA concluidos sem chamadas externas.\n";
