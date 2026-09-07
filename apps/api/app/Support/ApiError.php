<?php
namespace App\Support;
class ApiError extends \RuntimeException {
    public function __construct(public string $errorCode,public int $status=422,public array $details=[]){parent::__construct(str_replace('_',' ',ucfirst(strtolower($errorCode))));}
    public function render($request){return response()->json(['error'=>['code'=>$this->errorCode,'message'=>$this->getMessage(),'details'=>$this->details,'request_id'=>$request->attributes->get('request_id')]],$this->status);}
}
