<?php

namespace app\modules\Operations\Dashboard\Transport\Api;

use app\shared\Transport\Http\Resource;

final class DashboardResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'count' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                'cards' => ['type' => 'integer'],
                'comments' => ['type' => 'integer'],
                'good' => ['type' => 'integer'],
            ]],
            'chart' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                'label' => ['type' => 'string'],
                'data' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                    'x' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'y' => ['type' => 'array', 'items' => ['type' => 'integer']],
                ], 'required' => ['x', 'y']],
            ], 'required' => ['label', 'data']]],
            'ver' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                'app_name' => ['type' => 'string'],
                'homepage' => ['type' => 'string'],
                'version' => ['type' => 'string'],
                'build' => ['type' => 'integer'],
                'github' => ['type' => 'string'],
                'qgroup' => ['type' => 'string'],
            ], 'required' => ['app_name', 'homepage', 'version', 'build', 'github', 'qgroup']],
            'notice' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                'title' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'date' => ['type' => 'string'],
            ], 'required' => ['title', 'url', 'content', 'date']]],
            'workbench' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                'tasks' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                    'key' => ['type' => 'string'],
                    'label' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'count' => ['type' => 'integer'],
                    'action_url' => ['type' => 'string'],
                    'severity' => ['type' => 'string'],
                ], 'required' => ['key', 'label', 'description', 'count', 'action_url', 'severity']]],
            ], 'required' => ['tasks']],
        ], 'required' => ['count', 'chart', 'ver', 'notice', 'workbench']];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        $dashboard = is_array($data) ? $data : (array) $data;

        $fields = ['count', 'chart', 'ver', 'notice', 'workbench'];
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $dashboard)) {
                $result[$field] = $dashboard[$field];
            }
        }

        return $result;
    }
}
