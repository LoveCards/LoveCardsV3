<?php

namespace app\modules\Platform\Sender\Infrastructure\Driver;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use app\modules\Platform\Sender\Contract\Message;
use app\modules\Platform\Sender\Contract\SendResult;

class AliyunSmsDriver extends AbstractDriver
{
    public static function supportedTypes(): array
    {
        return ['code'];
    }

    public static function meta(): array
    {
        return [
            'type'        => 'aliyun_sms',
            'channelType' => 'sms',
            'name'        => '阿里云短信',
            'icon'        => 'mdi-cellphone',
            'fields'      => [
                ['key' => 'access_key',    'label' => 'AccessKey',    'type' => 'text'],
                ['key' => 'secret_key',    'label' => 'SecretKey',    'type' => 'password'],
                ['key' => 'sign_name',     'label' => '短信签名',      'type' => 'text'],
                ['key' => 'template_code', 'label' => '模板编码',      'type' => 'text'],
            ],
        ];
    }

    public function isConfigured(): bool
    {
        foreach (['access_key', 'secret_key', 'sign_name', 'template_code'] as $key) {
            if (trim((string) ($this->config[$key] ?? '')) === '') return false;
        }
        return true;
    }

    public function send(Message $message): SendResult
    {
        if (!$this->isConfigured()) return SendResult::fail('sms', '短信配置不完整');
        if (!is_string($message->to) || preg_match('/^1[3-9][0-9]{9}$/D', $message->to) !== 1) {
            return SendResult::fail('sms', '请输入有效的中国大陆手机号');
        }

        try {
            $client = 'lovecards-sms';
            AlibabaCloud::accessKeyClient($this->config['access_key'], $this->config['secret_key'])
                ->regionId('cn-hangzhou')->name($client);
            $result = AlibabaCloud::rpcRequest()->client($client)
                ->product('Dysmsapi')->version('2017-05-25')->action('SendSms')
                ->host('dysmsapi.aliyuncs.com')->scheme('https')->method('POST')->connectTimeout(2)->timeout(5)
                ->options(['query' => [
                    'PhoneNumbers' => $message->to,
                    'SignName' => $this->config['sign_name'],
                    'TemplateCode' => $this->config['template_code'],
                    'TemplateParam' => json_encode(['code' => $message->code], JSON_THROW_ON_ERROR),
                ]])->request()->toArray();
        } catch (ClientException | ServerException) {
            return SendResult::fail('sms', '短信渠道请求失败，请检查配置与服务连通性');
        }

        if (($result['Code'] ?? null) !== 'OK') {
            return SendResult::fail('sms', '短信服务拒绝请求，请检查签名、模板或发送额度');
        }
        return SendResult::ok('sms', $result['BizId'] ?? null);
    }
}
