<?php
declare(strict_types=1);

namespace app\frontend\controller;

use app\frontend\response\LocalFileResponse;
use app\modules\Platform\Files\Public\Files;
use think\Response;

final class Media
{
    public function card(int $cardId, int $fileId): Response
    {
        try {
            $media = \app\modules\Content\Cards\Service\CardMedia::read($cardId, $fileId, (string) request()->get('grant', ''));
        } catch (\app\api\ApiException) {
            return Response::create('', 'html', 404)->header(['Cache-Control' => 'private, no-store']);
        }
        $headers = ['Cache-Control' => 'private, no-store', 'ETag' => $media['etag'], 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff'];
        if (trim((string) request()->header('if-none-match', '')) === $media['etag']) return Response::create('', 'html', 304)->header($headers);
        $range = (string) request()->header('range', '');
        $ifRange = (string) request()->header('if-range', '');
        if ($ifRange !== '' && !hash_equals($media['etag'], $ifRange)) $range = '';
        return (new LocalFileResponse($media['local_path'], $media['mime_type'], $media['etag'], request()->method() === 'HEAD', $range))->header($headers);
    }

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
                'Cache-Control' => 'no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        if (is_string($media['local_path']) && $media['local_path'] !== '') {
            $range = trim((string) request()->header('range', ''));
            $ifRange = trim((string) request()->header('if-range', ''));
            if ($ifRange !== '' && !hash_equals($media['etag'], $ifRange)) $range = '';
            return (new LocalFileResponse(
                $media['local_path'],
                $media['mime_type'],
                $media['etag'],
                $method === 'HEAD',
                $range
            ))->header(['Cache-Control' => 'no-cache, must-revalidate']);
        }
        $response = Response::create('', 'html', 302)->header([
            'Location' => $media['url'],
            'Cache-Control' => 'no-cache, must-revalidate',
            'ETag' => $media['etag'],
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
        return $response;
    }
}
