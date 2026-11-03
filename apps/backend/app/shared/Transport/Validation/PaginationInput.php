<?php

namespace app\shared\Transport\Validation;

use think\Validate;

//通用超级分页查询验证类
class PaginationInput extends Validate
{
    //参数过滤场景
    static $all_scene = [
        'AdminUsers' => [
            'normal' => ['page', 'list_rows', 'search_keys', 'search_like', 'order_desc', 'order_key', 'show_deleted', 'status', 'role_id'],
            'require' => false,
            'nonNull' => false,
            'toNull' => ['search_value'],
        ],
        'Defult' => [
            'normal' => [
                'page',
                'list_rows',
                'search_keys',
                'search_like',
                'order_desc',
                'order_key',
                'show_deleted',
                'tag_id',
            ],
            'require' => false,
            'nonNull' => false,
            'toNull' => [
                'search_value'
            ],
        ]
    ];

    //定义验证规则
    protected $rule =   [
        //页码
        'page'  => 'number',
        //列表长度
        'list_rows'   => 'number|between:1,1000',
        //搜索值
        'search_value' => 'max:255',
        //搜索字段
        'search_keys' => 'searchKey',
        'search_like' => 'number|between:0,2',
        //排序字段
        'order_desc' => 'stringBool',
        //排序字段
        'order_key' => 'alphaDash',
        // 0=active, 1=with trashed, 2=only trashed
        'show_deleted' => 'number|between:0,2',
        'tag_id' => 'number',
        'status' => 'integer|in:0,1,2,3',
        'role_id' => 'integer|gt:0',
    ];
    //定义错误信息
    protected $message  =   [
        'page.number' => '页码格式错误',

        'list_rows.number' => '列表长度格式错误',
        'list_rows.between' => '列表长度范围错误(1-1000)',

        'order_desc.stringBool' => '排序方式格式错误',

        'order_key.alphaDash' => '排序字段格式错误',
    ];

    protected function searchKey($value, $rule, $data = [])
    {
        // 指定字段时，搜索值不能为空（问题不能查询空值）
        // if (!isset($data['search_value'])) {
        //     return '搜索内容不能为空';
        // }
        if (!is_array($value)) {
            return '搜索字段格式错误';
        }
        // 字段名直达 whereLike 的 SQL 键位，必须限定为安全标识符；
        // 非法元素在此 422，避免穿透到查询层产生 500 噪音。
        foreach ($value as $key) {
            if (!is_string($key) || preg_match('/^[A-Za-z0-9_]{1,64}$/', $key) !== 1) {
                return '搜索字段格式错误';
            }
        }
        return true;
    }

    protected function stringBool($value, $rule, $data = [])
    {
        if ($value != null) {
            if ($value == 'true' || $value == 'false') {
                return true;
            } else {
                return false;
            }
        }
    }
}
