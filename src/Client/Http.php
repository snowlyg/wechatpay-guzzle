<?php
/**
 * Created by PhpStorm.
 * User: 影TXX
 * Date: 2018/3/14
 * Time: 9:24
 */

namespace Snowlyg\WechatPay\Client;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;
use WechatPay\GuzzleMiddleware\Util\AesUtil;
use WechatPay\GuzzleMiddleware\WechatPayMiddleware;
use WechatPay\GuzzleMiddleware\Util\PemUtil;

class Http
{
    protected $mch_id;//商户号id
    protected $mch_key;//商户号支付key
    protected $ssl_cert_path; //服务商的api证书路径
    protected $ssl_key_path; //服务商的 api key路径
    protected $merchantSerialNumber; //平台证书序列号

    // 平台证书 用于加密数据
    protected $wechat_public_cert_path; //平台证书存放目录
    protected $wechat_public_cert_fullpath; //平台证书路径
    protected $serial_no; //平台证书序列号

    //API v3密钥 微信平台证书解密, 32 字节
    protected $apiv3_key;
    // 图片上传使用
    protected $boundary;

    // 请求基准路由
    const WechatPayURl = "https://api.mch.weixin.qq.com/v3/";

    /**
     * Client constructor.
     *
     * @param  string  $mch_id
     * @param  string  $mch_key
     * @param  string  $ssl_cert_path
     * @param  string  $ssl_key_path
     * @param  string  $merchantSerialNumber
     * @param  string  $str
     * @param  string  $wechat_public_cert
     * @param  string  $serial_no
     * @param  string  $apiv3_key
     * @param  string  $boundary
     */
    public function __construct(
        $mch_id,
        $mch_key,
        $ssl_cert_path,
        $ssl_key_path,
        $merchantSerialNumber,
        $str,
        $wechat_public_cert,
        $serial_no,
        $apiv3_key,
        $boundary
    ) {
        $this->mch_id = $mch_id;
        $this->mch_key = $mch_key;
        $this->ssl_cert_path = $ssl_cert_path;
        $this->ssl_key_path = $ssl_key_path;
        $this->merchantSerialNumber = $merchantSerialNumber;
        $this->str = $str;
        $this->wechat_public_cert = $wechat_public_cert;
        $this->serial_no = $serial_no;
        $this->apiv3_key = $apiv3_key;
        $this->boundary = $boundary;
    }


    /**
     * 特约商户进件 微信支付 请求处理 v3
     *
     * @return Client
     * @author          snowlyg
     * @datetime        2020/4/4 13:01
     */
    public function getWechatPayClient($first = false)
    {

        $merchantId = $this->mch_id;
        $merchantPrivateKey = PemUtil::loadPrivateKey($this->ssl_key_path);
        $wechatpayCertificate = PemUtil::loadCertificate($this->ssl_cert_path);

        $wechatpayMiddlewareBuilder = WechatPayMiddleware::builder()
            ->withMerchant($merchantId, $this->merchantSerialNumber, $merchantPrivateKey);

        //设置一个空的应答签名验证器，**不要**用在业务请求
        //使用WechatPayMiddlewareBuilder需要调用withWechatpay设置微信支付平台证书，而平台证书又只能通过调用获取平台证书接口下载。
        //为了解开"死循环"，你可以在第一次下载平台证书时，按照下述方法临时"跳过”应答签名的验证。
        if ($first) {
            $wechatpayMiddleware = $wechatpayMiddlewareBuilder->withValidator(new NoopValidator)->build();
        } else {
            $wechatpayMiddleware = $wechatpayMiddlewareBuilder->withWechatPay([$wechatpayCertificate])->build();
        }

        $stack = HandlerStack::create();
        $stack->push($wechatpayMiddleware, 'wechatpay');

        return new Client(['handler' => $stack, "base_uri" => $this->str]);
    }

    /** postWechatPay  特约商户进件-提交申请单 v3
     *
     * @param $uri
     * @param $data
     *
     * @return mixed|ResponseInterface
     * @throws GuzzleException
     * @author    snowlyg
     * @datetime  2020/4/8 0:09
     */
    public function postWechatPay($uri, $data)
    {

        $client = $this->getWechatPayClient();

        $options = [
            'debug' => false,
            'json' => $data,
            'headers' => [
                'Accept' => 'application/json',
                'Wechatpay-Serial' => $this->serial_no,
            ],
        ];

        try {
            return $client->request("POST", $uri, $options, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $status_code = "";
            $contents = "";
            if ($e->hasResponse()) {
                $status_code = $e->getResponse()->getStatusCode();
                $contents = $e->getResponse()->getBody()->getContents();
            }

            apiSuc([], $contents, $status_code);
        }

    }

    /** postWechatPayImg  上传图片 v3
     *
     * @param $uri
     * @param $data
     *
     * @return mixed|ResponseInterface
     * @throws GuzzleException
     * @author    snowlyg
     * @datetime  2020/4/8 0:09
     */
    public function postWechatPayImg($uri, $data)
    {

        $client = $this->getWechatPayClient();
        $filestr = json_encode($data["meta"]);
        $boundarystr = "--{$this->boundary}\r\n";
        $out = $boundarystr;
        $out .= 'Content-Disposition: form-data; name="meta";'."\r\n";
        $out .= 'Content-Type: application/json; charset=UTF-8'."\r\n";
        $out .= "\r\n";
        $out .= "".$filestr."\r\n";
        $out .= $boundarystr;
        $out .= 'Content-Disposition: form-data; name="file"; filename="'.$data["meta"]["filename"].'";'."\r\n";
        $out .= 'Content-Type: image/'.$data['ext'].';'."\r\n";
        $out .= "\r\n";
        $out .= $data["file"]."\r\n";
        $out .= "--{$this->boundary}--\r\n";

        $body = \GuzzleHttp\Psr7\stream_for($out);
        $options = [
            'debug' => true,
            "body" => $body,
            'headers' => [
                'Accept' => 'application/json',
                "Content-Type" => " multipart/form-data;boundary=".$this->boundary,
                "metaJson" => $filestr,
            ],
        ];

        try {
            return $client->request("POST", $uri, $options, $body);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $status_code = "";
            $contents = "";
            if ($e->hasResponse()) {
                $status_code = $e->getResponse()->getStatusCode();
                $contents = $e->getResponse()->getBody()->getContents();
            }

            apiSuc([], $contents, $status_code);
        }
    }

    /**
     * getWechatPay 特约商户进件-提交申请单 v3
     *
     * @param $uri
     *
     * @return mixed|ResponseInterface
     * @throws GuzzleException
     * @author    snowlyg
     * @datetime  2020/4/8 0:09
     */
    public function getWechatPay($uri)
    {

        $client = $this->getWechatPayClient();

        $options = [
            'debug' => true,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        try {
            return $client->request("GET", $uri, $options);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $status_code = "";
            $contents = "";
            if ($e->hasResponse()) {
                $status_code = $e->getResponse()->getStatusCode();
                $contents = $e->getResponse()->getBody()->getContents();
            }

            apiSuc([], $contents, $status_code);
        }

    }

    /**
     * getWechatV3PublicCert 加载微信平台证书 每隔10小小时请求
     *
     * @throws \Exception
     * @throws GuzzleException
     * @author    snowlyg
     * @datetime  2020/4/7 22:00
     */
    public function getWechatV3PublicCert()
    {

        $resp = $this->getWechatPay("certificates");
        if ($resp) {
            $resp = $this->encodeJson($resp);
            $serial_no = $resp['data'][0]['serial_no'];
            $encrypt_certificate = $resp['data'][0]['encrypt_certificate'];

            $aes_util = new AesUtil($this->getApiv3Key());
            $associatedData = $encrypt_certificate["associated_data"];
            $nonce = $encrypt_certificate["nonce"];
            $ciphertext = $encrypt_certificate["ciphertext"];
            $public_key = $aes_util->decryptToString(
                $associatedData,
                $nonce,
                $ciphertext
            );

            if (!is_dir($this->wechat_public_cert_path)) {
                mkdir(pathinfo($this->wechat_public_cert_fullpath, PATHINFO_DIRNAME));
            }
            file_put_contents($this->wechat_public_cert_fullpath, $public_key);

        } else {
            $serial_no = "";
        }

        $this->serial_no = $serial_no;
    }

    /**
     * @param $str
     *
     * @return string
     * @throws Exception
     * @author    snowlyg
     * @datetime  2020/4/4 17:42
     */
    public function getEncrypt($str)
    {
        $public_key = file_get_contents($this->wechat_public_cert);
        $encrypted = '';
        if (openssl_public_encrypt($str, $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING)) {
            $sign = base64_encode($encrypted);
        } else {
            throw new Exception('encrypt failed');
        }

        return $sign;
    }

    /**
     * encodeJson 解析 json 为 array
     *
     * @param  ResponseInterface  $resp
     *
     * @return array
     */
    public function encodeJson(ResponseInterface $resp)
    {
        $resp = \GuzzleHttp\json_decode($resp->getBody()->getContents(), true);
        $error_codes = ["SYSTEM_ERROR", "PARAM_ERROR", "PROCESSING", "NO_AUTH", "RATE_LIMITED", "APPLYMENT_NOTEXIST"];
        if (!empty($resp["code"]) && in_array($resp["code"], $error_codes)) {
            $msg = "请检查商户证书是否正确";
            if (!empty($resp["message"])) {
                $msg = $resp["message"];
            }
            apiSuc([], $msg, 1);
        }

        return $resp;
    }
}
