<?php



namespace app\modules\Content\Cards\Transport\Api;



use think\Validate;

use app\modules\Platform\Config\Public\Config as ConfigService;



use app\shared\Transport\Validation\RuleRegistry;



class Cards extends Validate

{



    var $ruleRegistry;



    function __construct()

    {

        parent::__construct();

        $this->ruleRegistry = new RuleRegistry();

    }



    public static $all_scene = [

        'selfCreate' => [

            'normal' => [

                'pictures',
                'data',
                'media_file_ids',
                'picture_file_ids',
                'cover_file_id',
                'tags'
                ,'visibility'

            ],

            'require' => [

                'content'

            ],

            'nonNull' => false,

            'toNull' => [

                'cover',

                'tags'

            ],

        ],

        'selfUpdate' => [

            'normal' => [

                'pictures',
                'content',
                'data',
                'media_file_ids',
                'picture_file_ids',
                'cover_file_id',
                'tags'
                ,'visibility'

            ],

            'require' => [

                'id'

            ],

            'nonNull' => false,

            'toNull' => [

                'cover',

                'tags',

            ],

        ],
        'adminUpdate' => [
            // 置顶与审核状态仍可由管理端编辑，但 Service 会分别要求
            // cards.pin / cards.approve；作者、派生统计与互动计数不可直接写入。
            'normal' => ['pictures', 'content', 'data', 'media_file_ids', 'picture_file_ids', 'cover_file_id', 'tags', 'is_top', 'status'],
            'require' => ['id'], 'nonNull' => false, 'toNull' => ['cover', 'tags'],
        ],
        'create' => [
            'normal' => ['pictures', 'data', 'media_file_ids', 'picture_file_ids', 'cover_file_id', 'tags', 'visibility'],
            'require' => ['content'], 'nonNull' => false, 'toNull' => ['cover', 'tags'],
        ],
    ];

    public static $scene_message = [

        'id.require' => 'ID不能为空',

        'content.require' => '正文不能为空',
        'content.notBlank' => '正文不能为空',
        'content.max' => '正文最多 1000 字。',

    ];



    protected $rule =   [

        'id' => 'number',

        'is_top' => 'number|in:0,1',

        'status' => 'number|in:0,1,2,3',

        'user_id' => 'number',

        'data' => 'arrayJson',
        'media_file_ids' => 'arrayJson|picturesLength',
        'picture_file_ids' => 'arrayJson|picturesLength',
        'cover_file_id' => 'number',

        'cover' => 'url|max:2083',

        // ThinkPHP max uses mb_strlen (Unicode code points). A Chinese
        // character and a supplementary-plane emoji each count as one.
        'content' => 'notBlank|max:1000',

        'tags' => 'arrayJson|tagsLength',

        'visibility' => 'in:public,followers,private',

        'good' => 'number',

        'views' => 'number',

        'comments' => 'number',


        'post_ip' => 'ip|max:39',



        'pictures' => 'arrayJson|picturesLength',

    ];



    protected $message  =   [

        'id.number' => 'ID格式错误',



        'is_top.number' => '置顶状态格式错误',



        'status.number' => '状态格式错误',



        'user_id.number' => '用户ID格式错误',



        'data.arrayJson' => '自定义字段格式错误',

        'content.notBlank' => '正文不能为空',
        'content.max' => '正文最多 1000 字。',



        'cover.url' => '封面图片格式不正确',

        'cover.max' => '封面图片地址过长',



        'tags.arrayJson' => '标签格式错误',

        'visibility.in' => '可见范围必须是 public、followers 或 private。',

        'tags.tagsLength' => '标签个数超出上限',



        'good.number' => '喜欢数格式错误',



        'views.number' => '浏览量格式错误',



        'comments.number' => '评论数格式错误',




        'post_ip.ip' => 'IP地址格式不正确',

        'post_ip.max' => 'IP地址过长',



        'pictures.arrayJson' => '图集格式错误',

        'pictures.picturesLength' => '图片个数超出上限',

    ];



    protected function picturesLength($value)

    {

        $config = ConfigService::get('cards.picture_limit', 15);

        $decoded = is_array($value) ? $value : json_decode($value, true);

        return $this->ruleRegistry->checkArrayLength($decoded, $config);

    }

    protected function tagsLength($value)

    {

        $config = ConfigService::get('cards.tag_limit', 3);

        $decoded = is_array($value) ? $value : json_decode($value, true);

        return $this->ruleRegistry->checkArrayLength($decoded, $config);

    }
}
