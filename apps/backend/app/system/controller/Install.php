<?php

namespace app\system\controller;

use think\facade\Request;
use think\facade\Http;
use think\facade\Cache;

use app\system\utils\Export;

use app\api\service\System\VersionService;
use app\common\service\Config as ConfigService;
use app\api\service\Rbac\Roles;
use app\system\utils\Common;
use app\system\utils\Database;
use app\system\utils\Environment;
use app\system\utils\Rsa;

use app\common\infra\Jwt;

class Install
{

    private function getHttpData($key = '', $url = '', $heade = [], $time = 3600): string
    {
        $data = Cache::get($key);
        if (!$data) {
            // 设置HTTP头，模拟浏览器请求
            $options = [
                'http' => [
                    'header' => "User-Agent: PHP\r\n"
                ]
            ];
            $context = stream_context_create($options);
            // 发送请求并获取响应
            try {
                $data = file_get_contents($url, false, $context);
                Cache::set($key, $data, $time);
            } catch (\Throwable $th) {
                return false;
            }
        }
        return $data;
    }

    //获取系统信息
    public function GetVersionInfo()
    {
        $latestInfo = $this->getHttpData('GithubReleasesLatestInfo', 'https://api.github.com/repos/zhiguai/LoveCards/releases/latest');
        $verlogMd = $this->getHttpData('GithubVerlogMd', 'https://github.moeyy.xyz/https://raw.githubusercontent.com/zhiguai/LoveCards/main/VerLog.md');

        $data = VersionService::public();
        $info = VersionService::info();
        $data['php_min'] = $info['php_min'] ?? '8.1.0';
        $data['php_max'] = $info['php_max'] ?? '9.0.0';
        $data['mysql_min'] = $info['mysql_min'] ?? '5.7';
        $data['mysql_max'] = $info['mysql_max'] ?? '9999';
        $data['GithubInfo'] = json_decode($latestInfo, true);
        $data['GithubVerlogMd'] = $verlogMd;

        return Export::Create($data, 200);
    }

    //配置数据库
    public function PostDbConfig()
    {
        // 已安装则禁止再次执行
        if (Common::CheckInstallLock()) {
            return Export::Create(null, 500, '系统已安装，禁止重复配置数据库');
        }

        $hostname = Request::param('hostname');
        $database = Request::param('database');
        $username = Request::param('username');
        $password = Request::param('password');
        $hostport = Request::param('hostport');
        //pass优先级高于force
        $force = boolval(Request::param('force'));
        $pass = boolval(Request::param('pass'));

        //连接数据库验证
        try {
            Database::Connect($hostname, $database, $username, $password, $hostport);
        } catch (\Exception $e) {
            return Export::Create(null, 500, $e->getMessage());
        }

        //更新数据库配置
        $result = Database::UpdataConfig($hostname, $database, $username, $password, $hostport);
        if (!$result) {
            return Export::Create(null, 500, '配置写入失败，请检查权限');
        }

        //跳过导入
        if (!$pass) {
            //强制导入-清空数据库
            if ($force) {
                try {
                    $result = Database::Clear();
                    if (!$result) {
                        return Export::Create(null, 500, '清空数据库失败');
                    }
                } catch (\Throwable $e) {
                    return Export::Create(null, 500, $e->getMessage());
                }
            }
            //导入数据库文件
            try {
                Database::ImportSQLFile('../data.sql');
                return Export::Create(null, 200);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (strpos($msg, '1050') !== false) {
                    return Export::Create(['导入失败，数据库已存在相关数据'], 201);
                }
                return Export::Create(null, 500, $msg);
            }
        }

        //数据库导入成功
        return Export::Create(null, 200);
    }

    //生成安装锁
    public function PostInstallLock()
    {
        // 已安装则禁止再次执行
        if (Common::CheckInstallLock()) {
            return Export::Create(null, 500, '系统已安装，禁止重复执行安装锁');
        }

        // 先初始化配置项（从 config/apps/*.php 注册默认配置）
        // ConfigService::init() 内部会跳过已存在的配置键，因此可安全重复调用
        // 必须在创建安装锁之前执行，确保初始化失败时可重试
        try {
            ConfigService::init();
        } catch (\Throwable $e) {
            return Export::Create(null, 500, '配置初始化失败：' . $e->getMessage());
        }

        // 初始化系统角色能力（非破坏性，只补充缺失项，不覆盖自定义角色）
        try {
            Roles::seedSystemCapabilities();
        } catch (\Throwable $e) {
            return Export::Create(null, 500, '系统角色能力初始化失败：' . $e->getMessage());
        }

        // 配置初始化成功后再创建安装锁（标记安装完成）
        // InstallLock() 返回 bool，失败时抛出 RuntimeException
        try {
            Common::InstallLock();
        } catch (\RuntimeException $e) {
            return Export::Create(null, 500, $e->getMessage());
        }

        return Export::Create(null, 200);
    }

    //检查环境
    public function GetCheckEnvironment()
    {
        $data = Environment::Check();
        return Export::Create($data, 200);
    }

    //创建公私钥
    public function PostCreateRsa()
    {
        // 已安装则禁止重复执行，防止覆盖 JWT 密钥
        if (Common::CheckInstallLock()) {
            return Export::Create(null, 500, '系统已安装，禁止重复生成密钥');
        }

        $key = [
            'public' => Request::param('public'),
            'private' => Request::param('private'),
        ];

        //生成还是传入
        if (!$key['public'] && !$key['private']) {
            $key = Rsa::Generate();
            if (!$key) {
                return Export::Create(null, 500, '密钥对生成失败，请检查openssl扩展是否可用');
            }
        }

        //校验是否可用
        if (!Jwt::VerifyRsa($key['public'], $key['private'])) {
            return Export::Create(null, 500, '密钥对不可用');
        }

        //写入文件
        $result = Rsa::UpdataRsa($key['public'], $key['private']);
        if (!$result) {
            return Export::Create(null, 500, '密钥写入失败，请检查权限');
        }
        return Export::Create(null, 200);
    }
}
