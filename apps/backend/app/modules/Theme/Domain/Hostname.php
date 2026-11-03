<?php

namespace app\modules\Theme\Domain;

final class Hostname
{
    public static function normalize(string $host): string
    {
        $host = trim(strtolower($host));
        if ($host === '' || strpos($host, "\0") !== false || preg_match('/[\x00-\x20\x7f]/', $host)) {
            throw new \InvalidArgumentException('Host 不合法');
        }

        if (strpos($host, ':') !== false && $host[0] !== '[') {
            $host = (string)strtok($host, ':');
        }
        $host = rtrim($host, '.');
        if ($host === '' || strlen($host) > 253 || strpos($host, '/') !== false || strpos($host, '\\') !== false) {
            throw new \InvalidArgumentException('Host 不合法');
        }
        if ($host[0] === '[' && substr($host, -1) === ']') {
            $host = substr($host, 1, -1);
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $host;
        }
        if (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/', $host)) {
            throw new \InvalidArgumentException('Host 不合法');
        }
        return $host;
    }
}
