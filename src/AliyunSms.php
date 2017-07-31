<?php
namespace Wangjian\Alisms;

use Wangjian\Alisms\Signature\Signature;
use Wangjian\Alisms\Signature\HmacSignature;
use Wangjian\Alisms\Signature\Md5Signature;

class AliyunSms {
    /**
     * API host
     * @const string
     */
    const API_HOST = 'http://gw.api.taobao.com/router/rest';

    /**
     * sign method constants
     * @const string
     */
    const SIGN_METHOD_HMAC = 'hmac';
    const SIGN_METHOD_MD5 = 'md5';

    /**
     * the api return type, xml or json
     * @var string
     */
    protected $format = 'json';

    /**
     * whether return the simplified json
     * @var string
     */
    protected $simplify = 'false';

    /**
     * api version
     * @var string
     */
    protected $version = '2.0';

    /**
     * signature algorithm
     * @var string
     */
    protected $signatureMethod = 'hmac';

    /**
     * the signature handler
     * @var Signature
     */
    protected $signature;

    /**
     * Access Key ID
     * @var string
     */
    protected $accessKeyId;

    /**
     * Access Key Secret
     * @var string
     */
    protected $accessKeySecret;

    /**
     * lastest error
     * @var array
     */
    protected $error = [];

    /**
     * AliyunSms constructor.
     * @param string $accessKeyId
     * @param string $accessKeySecret
     * @param array $options
     * @return void
     * @throw \RuntimeException
     */
    public function __construct($accessKeyId, $accessKeySecret, $options = []) {
        $this->accessKeyId = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->format = isset($options['format']) ? $options['format'] : $this->format;
        $this->simplify = isset($options['simplify']) ? $options['simplify'] : $this->simplify;
        $this->version = isset($options['version']) ? $options['version'] : $this->version;
        $this->signatureMethod = isset($options['signatureMethod']) ? $options['signatureMethod'] : $this->signatureMethod;
        if($this->signatureMethod == self::SIGN_METHOD_HMAC) {
            $this->signature = new HmacSignature();
        } else if($this->signatureMethod == self::SIGN_METHOD_MD5) {
            $this->signature = new Md5Signature();
        } else {
            throw new \RuntimeException('unsupported sign method');
        }
    }

    /**
     * send message
     * @param string|array $numbers  the cellphone numbers
     * @param string $signName  the message signature
     * @param string $template  the message template id
     * @param array $parameters  the parameters
     * @return bool  return true on success, and false on failure
     */
    public function sendMessage($numbers, $signName, $template, $parameters= []) {
        $numbers = is_string($numbers) ? $numbers : implode(',', $numbers);

        $request = $this->createRequest([
            'method' => 'alibaba.aliqin.fc.sms.num.send',
            'sms_type' => 'normal',
            'sms_free_sign_name' => $signName,
            'rec_num' => $numbers,
            'sms_template_code' => $template
        ]);

        if(!empty($parameters)) {
            $request->sms_param = json_encode($parameters);
        }

        //send the request
        $result = json_decode($this->sendRequest($request), true);
        if(isset($result['error_response'])) {
            $this->error = $result['error_response'];
            return false;
        }

        return true;
    }

    /**
     * query the send messages
     * @param string $number
     * @param string $date  the format is yyyyMMdd
     * @param int $page
     * @param int $pageSize
     * @param string $bizId
     * @return bool  return true on success, and false on failure
     */
    public function queryMessage($number, $date, $page = 1, $pageSize = 10, $bizId = '') {
        $request = $this->createRequest([
            'method' => 'alibaba.aliqin.fc.sms.num.query',
            'rec_num' => $number,
            'query_date' => $date,
            'current_page' => $page,
            'page_size' => $pageSize,
        ]);
        if(!empty($bizId)) {
            $request->biz_id = $bizId;
        }

        //send the request
        $result = json_decode($this->sendRequest($request), true);
        if(isset($result['error_response'])) {
            $this->error = $result['error_response'];
            return false;
        }

        return true;
    }

    /**
     * get the latest error info
     * @return array
     */
    public function getError() {
        return $this->error;
    }

    /**
     * create an aliyun_sms request
     * @param array $parameters
     * @param string $method
     * @return AliyunSmsRequest
     */
    protected function createRequest($parameters = [], $method = 'GET') {
        //common parameters
        $commonParameters = [
            'app_key' => $this->accessKeyId,
            'sign_method' => $this->signatureMethod,
            'format' => $this->format,
            'v' => $this->version,
        ];

        //simplify parameter
        if($this->format == 'json') {
            $commonParameters['simplify'] = $this->simplify;
        }

        //timestamp parameter
        $timezone = new \DateTimeZone('GMT+8');
        $time = new \DateTime('now', $timezone);
        $commonParameters['timestamp'] = $time->format('Y-m-d H:i:s');

        $parameters = array_merge($commonParameters, $parameters);
        return new AliyunSmsRequest($parameters, $method);
    }

    /**
     * sign the request
     * @param AliyunSmsRequest $request
     * @return void
     */
    protected function signRequest(AliyunSmsRequest $request) {
        $request->sign = $this->signature->sign($request, $this->accessKeySecret);
    }

    /**
     * send the request
     * @param AliyunSmsRequest $request
     * @return string
     */
    protected function sendRequest(AliyunSmsRequest $request) {
        //sign the request
        $this->signRequest($request);

        //send the request
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if(strtoupper($request->method()) == 'POST') {
            curl_setopt($curl, CURLOPT_URL, self::API_HOST);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $request->serialize());
        } else if(strtoupper($request->method()) == 'GET') {
            curl_setopt($curl, CURLOPT_URL, self::API_HOST . '?' . $request->serialize());
        }
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);

        //SSL setting
        $ssl = parse_url(self::API_HOST, PHP_URL_SCHEME) == 'https';
        if($ssl) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        }

        $result =  curl_exec($curl);
        curl_close($curl);

        return $result;
    }
}