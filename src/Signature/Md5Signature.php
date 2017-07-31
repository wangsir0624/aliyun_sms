<?php
namespace Wangjian\Alisms\Signature;

use Wangjian\Alisms\AliyunSmsRequest;

class Md5Signature extends Signature {
    /**
     * sign the request
     * @param AliyunSmsRequest $request
     * @param string $secret  the api secret
     * @return void
     */
    public function sign(AliyunSmsRequest $request, $secret) {
        $signature = '';
        $parameters = $request->parameters();

        //unset the sign parameter
        unset($parameters['sign']);

        //sort the parameters by dictionary ordering
        ksort($parameters);

        //join the parameters
        foreach($parameters as $key => $value) {
            $signature .= $key . $value;
        }

        $signature = md5("$secret$signature$secret");

        return strtoupper($signature);
    }
}