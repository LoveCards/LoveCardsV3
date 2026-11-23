<?php

namespace app\modules\Platform\Storage\Contract;

class DirectUploadCredential
{
    public const DEFAULT_MAX_BYTES = 52_428_800;

    public string $url;
    public string $method;
    public array $headers;
    public array $formData;
    public int $expire;

    public function __construct(string $url, string $method, array $headers, array $formData, int $expire)
    {
        $this->url = $url;
        $this->method = $method;
        $this->headers = $headers;
        $this->formData = $formData;
        $this->expire = $expire;
    }
}
