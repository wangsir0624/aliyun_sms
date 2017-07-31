<?php
namespace Wangjian\Alisms\Signature;

use Wangjian\Alisms\AliyunSmsRequest;
use Wangjian\Alisms\AliyunSms;

abstract class Signature {
    /**
     * sign the request
     * @param AliyunSmsRequest $request
     * @param string $secret  the api secret
     * @return void
     */
    abstract public function sign(AliyunSmsRequest $request, $secret);
}