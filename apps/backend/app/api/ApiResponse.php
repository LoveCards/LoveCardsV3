<?php

namespace app\api;

use think\Response;
use app\shared\Transport\Http\Resource;

class ApiResponse
{
    // 鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹?
    // HTTP 鐘舵€佺爜
    // 鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹?
    public const HTTP_OK           = 200;
    public const HTTP_CREATED      = 201;
    public const HTTP_ACCEPTED     = 202;
    public const HTTP_NO_CONTENT   = 204;
    public const HTTP_BAD_REQUEST  = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN   = 403;
    public const HTTP_NOT_FOUND   = 404;
    public const HTTP_CONFLICT    = 409;
    public const HTTP_TOO_MANY    = 429;
    public const HTTP_ERROR        = 500;
    public const HTTP_UNAVAILABLE = 503;

    // 鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹?
    // 鍝嶅簲鏋勫缓
    // 鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹?

    public static function setHeader($object)
    {
        $data = [
            'Content-Type'                 => 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With, X-Token, X-Request-Id, X-Correlation-Id',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
        ];
        return $object->header($data);
    }

    /**
     * 鍖呰鎴愬姛鍝嶅簲
     *
     * @param mixed $data 涓氬姟鏁版嵁锛堝垎椤垫暟鎹嚜鍔ㄦ彁鍙?pagination锛?
     * @param string $message 鎻愮ず娑堟伅
     * @return array
     */
    public static function wrap($data = null, string $message = '鎿嶄綔鎴愬姛'): array
    {
        $data = self::normalizeEncoding(Resource::normalize($data));
        $result = [
            'success' => true,
            'message' => self::normalizeEncoding($message),
            'timestamp' => date('c'),
        ];

        // 妫€娴嬪垎椤电粨鏋? {total, per_page, current_page, last_page, data}
        if (is_array($data) && isset($data['total'], $data['current_page'], $data['data']) && is_array($data['data'])) {
            $result['data'] = $data['data'];
            $result['pagination'] = [
                'currentPage' => (int) $data['current_page'],
                'totalPages'  => (int) ($data['last_page'] ?? 1),
                'totalItems'  => (int) $data['total'],
                'itemsPerPage' => (int) ($data['per_page'] ?? count($data['data'])),
            ];
        } else {
            $result['data'] = $data;
        }

        return $result;
    }

    /**
     * 鍖呰閿欒鍝嶅簲
     *
     * @param string $message 閿欒娑堟伅
     * @param mixed $detail 閿欒璇︽儏
     * @param int $code 涓氬姟鐮?
     * @return array
     */
    public static function error(string $message = '', $detail = null, int $code = 0): array
    {
        return [
            'success' => false,
            'error' => [
                'code'    => $code,
                'message' => self::normalizeEncoding($message),
                'details' => self::normalizeEncoding($detail),
            ],
            'timestamp' => date('c'),
        ];
    }

    /**
     * The legacy PHP sources contain UTF-8 Chinese text that was decoded and
     * re-encoded once during the migration. Convert response strings back to
     * their intended UTF-8 representation at the single transport boundary.
     */
    private static function normalizeEncoding($value)
    {
        if (is_string($value)) {
            // Already-correct UTF-8 text must not be converted a second time.
            // Only legacy mojibake strings (which contain the characteristic
            // GBK-decoding markers) should go through the repair path.
            if (!preg_match('/[鍊娓瀛閰鐭璇鐧缁濡鎴鐗榛鍓緤鈥闂]/u', $value)) {
                return $value;
            }
            $converted = @iconv('UTF-8', 'GBK//IGNORE', $value);
            // A converted value is safe only when the GBK byte sequence also
            // forms valid UTF-8. This distinguishes legacy mojibake from
            // already-correct Chinese strings and prevents malformed JSON.
            if ($converted !== false && $converted !== '' && @preg_match('//u', $converted) === 1) {
                return $converted;
            }
            return $value;
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalizeEncoding($item);
            }
        }
        return $value;
    }

    // 鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹?
    // 鎴愬姛鍝嶅簲锛?00锛?
    // 鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹?

    public static function createOk($data = null, string $message = '鎿嶄綔鎴愬姛'): Response
    {
        $result = Response::create(self::wrap($data, $message), 'json')->code(self::HTTP_OK);
        return self::setHeader($result);
    }

    public static function createCreated($data = null, string $message = '鍒涘缓鎴愬姛'): Response
    {
        $result = Response::create(self::wrap($data, $message), 'json')->code(self::HTTP_CREATED);
        return self::setHeader($result);
    }

    public static function createAccepted($data = null, string $message = ''): Response
    {
        $result = Response::create(self::wrap($data, $message), 'json')->code(self::HTTP_ACCEPTED);
        return self::setHeader($result);
    }

    public static function createNoContent(): Response
    {
        return self::setHeader(Response::create()->code(self::HTTP_NO_CONTENT));
    }

    // 鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹?
    // 閿欒鍝嶅簲
    // 鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹?

    public static function createBadRequest(string $message = '', $detail = null, int $code = 0): Response
    {
        return self::errorResponse(self::error($message, $detail, $code), self::HTTP_BAD_REQUEST);
    }

    public static function createUnauthorized(string $message = '', $detail = null, int $code = 0): Response
    {
        return self::errorResponse(self::error($message, $detail, $code), self::HTTP_UNAUTHORIZED);
    }

    public static function createForbidden(string $message = '', $detail = null, int $code = 0): Response
    {
        return self::errorResponse(self::error($message, $detail, $code), self::HTTP_FORBIDDEN);
    }

    public static function createNotFound(string $message = '', $detail = null, int $code = 0): Response
    {
        return self::errorResponse(self::error($message, $detail, $code), self::HTTP_NOT_FOUND);
    }

    public static function createConflict(string $message = '', $detail = null, int $code = 0): Response
    {
        return self::errorResponse(self::error($message, $detail, $code), self::HTTP_CONFLICT);
    }

    public static function createTooMany(string $message = '', $detail = null, int $code = 0): Response
    {
        return self::errorResponse(self::error($message, $detail, $code), self::HTTP_TOO_MANY);
    }

    public static function createError(string $message = '', $detail = null, int $code = 0): Response
    {
        return self::errorResponse(self::error($message, $detail, $code), self::HTTP_ERROR);
    }

    public static function createUnavailable(string $message = '', $detail = null, int $code = 0): Response
    {
        return self::errorResponse(self::error($message, $detail, $code), self::HTTP_UNAVAILABLE);
    }

    // 鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹?
    // 鍐呴儴鏂规硶
    // 鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹?

    private static function errorResponse(array $errorData, int $httpCode): Response
    {
        $result = Response::create($errorData, 'json')->code($httpCode);
        return self::setHeader($result);
    }
}
