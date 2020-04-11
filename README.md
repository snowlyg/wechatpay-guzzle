# wechatpay-guzzle

#### 微信支付v3 接口-特约商户进件提交，状态查询，以及图片上传 ,  使用 [https://github.com/snowlyg/wechatpay-guzzle-middleware](https://github.com/snowlyg/wechatpay-guzzle-middleware) 实现微信签名认证。

## 环境要求

+ PHP 5.5+ / PHP 7.0+
+ guzzlehttp/guzzle 6.0+

> 注意：PHP < 7.2 需要安装 bcmath 扩展

#### 使用方法
```php
use GuzzleHttp\Exception\RequestException;use Snowlyg\WechatPay\Client\Http;

// 商户相关配置
$merchantId = '1000100'; // 商户号
$merchantPrivateKey = '/path/to/mch/private/key.pem'; // 商户私钥
$merchantSerialNumber = 'XXXXXXXXXX'; // 商户API证书序列号
// 微信支付平台配置
$wechatpayCertificate = '/path/to/wechatpay/cert.pem'; // 微信支付平台证书
$apiv3_key = '/path/to/wechatpay/cert.pem'; // API v3密钥 32 字节

$http = new Http($merchantId,$merchantPrivateKey,$merchantPrivateKey,$wechatpayCertificate,$apiv3_key);    
try {
    // applyment_id 微信支付分的申请单号,提交申请单后返回
    $http->getWechatPay("applyment4sub/applyment/applyment_id/{applyment_id}"); 
    
    // 完整参数查看微信文档  [特约商户进件](https://pay.weixin.qq.com/wiki/doc/apiv3/wxpay/tool/applyment4sub/chapter3_2.shtml)
    $data =[
               //业务申请编号
               "business_code"     => "....",
               //超级管理员信息
               "contact_info"      => [
                   "contact_name"      => $http->getEncrypt("contact_name"), // 敏感数据
                  // .... 其他参数
               ],
               //主体资料
               "subject_info"      => [
                   "subject_type"          => "....",
   
                   //营业执照
                   "business_license_info" => [
                       "license_copy"   => $http->getWechatPayImghasd('license_copy'), //图片
                           // .... 其他参数
                   ],
                    // .... 其他参数
                ],
      ];
    //提交申请单
    $http->postWechatPay("applyment4sub/applyment",$data); 
} catch (RequestException $e) {
    // 进行错误处理
    echo $e->getMessage()."\n";
    if ($e->hasResponse()) {
        echo $e->getResponse()->getStatusCode().' '.$e->getResponse()->getReasonPhrase()."\n";
        echo $e->getResponse()->getBody();
    }
    return;
}
```

#### 开发插曲

> 写此功能的时候，在微信开放社区找到一个写过次功能的哥们联系方式。本来想向他请教一下，以免自己少走弯路。没想到这哥们直接开口要 300 大洋....
> 看来没法偷懒了，只能自己写了。
> 在微信开放社区找到很多朋友分享的代码，非常感谢他们。
> 还有微信官方文档的微信签名 sdk , [https://github.com/snowlyg/wechatpay-guzzle-middleware](https://github.com/snowlyg/wechatpay-guzzle-middleware) 。


#### 联系交流
如果你发现了BUG或者有任何疑问、建议，请通过issue进行反馈。
也可以加 QQ 群交流：676717248
<a target="_blank" href="//shang.qq.com/wpa/qunwpa?idkey=cc99ccf86be594e790eacc91193789746af7df4a88e84fe949e61e5c6d63537c"><img border="0" src="http://pub.idqqimg.com/wpa/images/group.png" alt="Iris-go" title="Iris-go"></a>




