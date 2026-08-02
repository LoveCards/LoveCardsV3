<?php

namespace app\modules\Platform\Files\Transport\Api;

use app\shared\Transport\Http\Resource;

final class DirectUploadResource extends Resource
{
    public static function make($value): array
    {
        $data = self::normalize($value);
        $credential = is_array($data) ? $data : (array) $data;

        $fields = ['record_id', 'upload_url', 'method', 'headers', 'form_data', 'expire'];
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $credential)) {
                $result[$field] = $credential[$field];
            }
        }

        return $result;
    }
}
