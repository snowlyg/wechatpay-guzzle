# wechatpay-guzzle

#### 微信支付v3 接口-特约商户进件提交，状态查询，以及图片上传 ,  使用 [https://github.com/snowlyg/wechatpay-guzzle-middleware](https://github.com/snowlyg/wechatpay-guzzle-middleware) 实现微信签名认证。

## 环境要求

+ PHP 5.5+ / PHP 7.0+
+ guzzlehttp/guzzle 6.0+

> 注意：PHP < 7.2 需要安装 bcmath 扩展
>

##### 安装  composer
```shell script
  composer require snowlyg/wechatpay-guzzle
```


#### 使用方法
参考：[https://github.com/wechatpay-apiv3/wechatpay-guzzle-middleware/blob/master/README.md](https://github.com/wechatpay-apiv3/wechatpay-guzzle-middleware/blob/master/README.md)

图片签名
```
 // 上传图片
    $resp = $client->request('POST', 'https://api.mch.weixin.qq.com/v3/...', [
        'body' =>\GuzzleHttp\Psr7\stream_for("body的内容"),
        // meta的json串 ,签名使用
       "metaJson"     => '{ "filename": "filea.jpg", "sha256": " hjkahkjsjkfsjk78687dhjahdajhk " }',
        'headers' => [ 
               'Accept'       => 'application/json',
               "Content-Type" => " multipart/form-data;boundary=boundary",
            ]
    ]);
```

#### 开发插曲

> 写此功能的时候，在微信开放社区找到一个写过次功能的哥们联系方式。本来想向他请教一下，以免自己少走弯路。没想到这哥们直接开口要 300 大洋....
>
> 看来没法偷懒了，只能自己写了。
>
> 在微信开放社区找到很多朋友分享的代码，非常感谢他们。
>
> 还有微信官方文档的微信签名 sdk , [https://github.com/snowlyg/wechatpay-guzzle-middleware](https://github.com/snowlyg/wechatpay-guzzle-middleware) 。


#### 联系交流
如果你发现了BUG或者有任何疑问、建议，请通过issue进行反馈。

也可以加 QQ 群交流：676717248

<a target="_blank" href="//shang.qq.com/wpa/qunwpa?idkey=cc99ccf86be594e790eacc91193789746af7df4a88e84fe949e61e5c6d63537c"><img border="0" src="http://pub.idqqimg.com/wpa/images/group.png" alt="Iris-go" title="Iris-go"></a>




