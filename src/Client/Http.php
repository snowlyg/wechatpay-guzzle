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
use Snowlyg\WechatPay\NoopValidator;
use Snowlyg\WechatPay\Util\AesUtil;
use Snowlyg\WechatPay\WechatPayMiddleware;
use Snowlyg\WechatPay\Util\PemUtil;
use Snowlyg\WechatPay\WechatPayMiddlewareBuilder;

/**
 * Class Http
 *
 * @package   Snowlyg\WechatPay\Client
 * @author    snowlyg
 * @datetime  2020/4/11 21:31
 */
class Http
{
    /**
     * @var string
     */
    protected $mch_id;//商户号id
    /**
     * @var string
     */
    protected $mch_key;//商户号支付key
    protected $ssl_key_path; //服务商的 api key路径
    protected $merchantSerialNumber; //商户API证书序列号

    // 平台证书 用于加密数据
    protected $wechat_public_cert_path; //平台证书存放目录
    protected $wechat_public_cert_fullpath; //平台证书文件路径
    protected $serial_no; //平台证书序列号

    //API v3密钥 微信平台证书解密, 32 字节
    protected $apiv3_key;

    // 图片上传使用
    const BOUNDARY = "sfdkewrdsfkd";
    // 请求基准路由
    const WechatPayURl = "https://api.mch.weixin.qq.com/v3/";

    /**
     * Client constructor.
     *
     * @param  string  $mch_id
     * @param  string  $mch_key
     * @param $merchantSerialNumber
     * @param  string  $ssl_key_path
     * @param  string  $wechat_public_cert
     * @param  string  $apiv3_key
     * @param  string  $boundary
     */
    public function __construct(
        $mch_id,
        $mch_key,
        $merchantSerialNumber,
        $ssl_key_path,
        $wechat_public_cert,
        $apiv3_key,
        $boundary
    ) {
        $this->mch_id = $mch_id;
        $this->mch_key = $mch_key;
        $this->merchantSerialNumber = $merchantSerialNumber;
        $this->ssl_key_path = $ssl_key_path;
        $this->wechat_public_cert = $wechat_public_cert;
        $this->apiv3_key = $apiv3_key;
        $this->boundary = $boundary;
    }


    /**
     * getWechatPayMiddlewareBuilder 微信应答签名的验证中间件构建器
     *
     * @return WechatPayMiddlewareBuilder
     * @author          snowlyg
     * @datetime        2020/4/4 13:01
     */
    public function getWechatPayMiddlewareBuilder()
    {
        $merchantId = $this->mch_id;
        $merchantPrivateKey = PemUtil::loadPrivateKey($this->ssl_key_path);

        return WechatPayMiddleware::builder()->withMerchant($merchantId, $this->merchantSerialNumber,
            $merchantPrivateKey);
    }

    /**
     * getWechatpayMiddleware 微信应答签名的验证中间件
     *
     * @return WechatPayMiddleware
     * @author    snowlyg
     * @datetime  2020/4/11 21:32
     */
    public function getWechatpayMiddleware()
    {
        $wechatpayCertificate = PemUtil::loadCertificate($this->wechat_public_cert_fullpath);
        return $this->getWechatPayMiddlewareBuilder()->withWechatPay([$wechatpayCertificate])->build();
    }


    /**
     * getWechatpayMiddlewareWithNoAuth
     *
     * 使用 WechatPayMiddlewareBuilder 需要调用 withWechatpay 设置微信支付平台证书，
     * 而平台证书又只能通过调用获取平台证书接口下载。为了解开"死循环"，你可以在第一次下载平台证书时，
     * 按照下述方法临时"跳过”应答签名的验证。
     *
     * @return WechatPayMiddleware
     * @author    snowlyg
     * @datetime  2020/4/11 21:31
     */
    public function getWechatpayMiddlewareWithNoAuth()
    {
        return $this->getWechatPayMiddlewareBuilder()->withValidator(new NoopValidator)->build();
    }

    /**
     * @param $wechatpayMiddleware
     *
     * @return Client
     * @author    snowlyg
     * @datetime  2020/4/11 21:32
     */
    public function getWechatPayClient($wechatpayMiddleware)
    {
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
        $client = $this->getWechatPayClient($this->getWechatpayMiddleware());
        $options = [
            'debug' => false,
            'json' => $data,
            'headers' => [
                'Accept' => 'application/json',
                'Wechatpay-Serial' => $this->serial_no,
            ],
        ];

        try {
            return $client->request("POST", $uri, $options);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw $e;
        }

    }

    /** getWechatPayImghasd  上传图片
     *
     * @param $uri
     * @param $data
     *
     * @return mixed|ResponseInterface
     * @throws GuzzleException
     * @author    snowlyg
     * @datetime  2020/4/8 0:09
     */
    public function getWechatPayImghasd($filepath)
    {
        $imginfo     = pathinfo($filepath);
        $picturedata = file_get_contents($filepath);
        $sign        = hash('sha256', $picturedata);
        $meta        = [
            "filename" => $imginfo['basename'],
            "sha256"   => $sign,
        ];

        $filestr = json_encode($meta);
        $output  = $this->getBody($filestr, $imginfo, $picturedata);

        $body    = \GuzzleHttp\Psr7\stream_for($output);
        $options = [
            'http_errors' => false,
            'debug'       => false,
            "body"        => $body,
            "metaJson"     => $filestr,
            'headers'     => [
                'Accept'       => 'application/json',
                "Content-Type" => " multipart/form-data;boundary=".$this->boundary,
                'Wechatpay-Serial' => $this->serial_no,
            ],
        ];

        $client = $this->getWechatPayClient();


        $response = $client->request("POST", "merchant/media/upload", $options);
        $response = $this->decodeJson($response);
        if (!empty($response["media_id"])) {
            return $response["media_id"];
        }

        return "";
    }

    /**
     * getWechatPay 进件申请单提交
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
        $wechatpayMiddleware = $this->getWechatpayMiddleware();
        if ($uri == "certificates") {
            $wechatpayMiddleware = $this->getWechatpayMiddlewareWithNoAuth();
        }
        $client = $this->getWechatPayClient($wechatpayMiddleware);
        $options = [
            'debug' => true,
            'headers' => ['Accept' => 'application/json',],
            'Wechatpay-Serial' => $this->serial_no,
        ];

        try {
            return $client->request("GET", $uri, $options);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw $e;
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
            $this->serial_no = $resp['data'][0]['serial_no'];
            $encrypt_certificate = $resp['data'][0]['encrypt_certificate'];

            $aes_util = new AesUtil($this->apiv3_key);
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
        }
    }

    /**
     * getEncrypt 进件敏感数据加密
     *
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
     * @return string
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

            return $msg;
        }

        return $resp;
    }

    /**
     * getBody 获取图片上传 body
     *
     * @param $filestr
     *
     * @param  array  $imginfo
     * @param $picturedata
     *
     * @return string
     * @author  snowlyg
     * @datetime  2020/4/11 21:40
     */
    public function getBody($filestr, array $imginfo, $picturedata)
    {
        $boundarystr = "--{$this->boundary}\r\n";
        $out         = $boundarystr;
        $out         .= 'Content-Disposition: form-data; name="meta";'."\r\n";
        $out         .= 'Content-Type: application/json; charset=UTF-8'."\r\n";
        $out         .= "\r\n";
        $out         .= "".$filestr."\r\n";
        $out         .= $boundarystr;
        $out         .= 'Content-Disposition: form-data; name="file"; filename="'.$imginfo['basename'].'";'."\r\n";
        $out         .= 'Content-Type: image/'.$imginfo['extension'].';'."\r\n";
        $out         .= "\r\n";
        $out         .= $picturedata."\r\n";
        $out         .= "--{$this->boundary}--\r\n";
        return $out;
    }
}
