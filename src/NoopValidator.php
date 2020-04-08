<?php

namespace Snowlyg\WechatPay;

use Psr\Http\Message\ResponseInterface;
use WechatPay\GuzzleMiddleware\Validator;

/**
 * Class NoopValidator 为了解开"死循环"，你可以在第一次下载平台证书时，按照下述方法临时"跳过”应答签名的验证。
 *
 * @package   app\common\service
 * @author    snowlyg
 * @datetime  2020/4/4 15:03
 */
class NoopValidator implements Validator
{
    public function validate(ResponseInterface $response)
    {
        return true;
    }
}