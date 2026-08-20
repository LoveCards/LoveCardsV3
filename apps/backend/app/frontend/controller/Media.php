<?php
declare(strict_types=1);

namespace app\frontend\controller;

use app\frontend\response\LocalFileResponse;
use app\modules\Platform\Files\Public\Files;
use think\Response;

final class Media
{
    public function get(string $hash): Response
    {
        $method = strtoupper((string) request()->method());
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return Response::create('', 'html', 405)->header(['Allow' => 'GET, HEAD']);
        }
        $media = Files::publicMedia($hash);
        if ($media === null) {
            return Response::create('', 'html', 404)->header(['X-Content-Type-Options' => 'nosniff']);
        }
        if (trim((string) request()->header('if-none-match', '')) === $media['etag']) {
            return Response::create('', 'html', 304)->header([
                'ETag' => $media['etag'],
                'Cache-Control' => 'public, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        if (is_string($media['local_path']) && $media['local_path'] !== '') {
            $range = trim((string) request()->header('range', ''));
            $ifRange = trim((string) request()->header('if-range', ''));
            if ($ifRange !== '' && !hash_equals($media['etag'], $ifRange)) $range = '';
            return new LocalFileResponse(
                $media['local_path'],
                $media['mime_type'],
                $media['etag'],
                $method === 'HEAD',
                $range
            );
        }
        $response = Response::create('', 'html', 302)->header([
            'Location' => $media['url'],
            'Cache-Control' => 'public, max-age=300',
            'ETag' => $media['etag'],
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
        return $response;
    }
}
