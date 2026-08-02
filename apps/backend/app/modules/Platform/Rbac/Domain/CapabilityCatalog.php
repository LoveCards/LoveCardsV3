<?php

namespace app\modules\Platform\Rbac\Domain;

final class CapabilityCatalog
{
    /**
     * 全量能力列表。
     *
     * @return array capability => description
     */
    public static function all(): array
    {
        return [
            // Cards
            'cards.read'         => '查看卡片',
            'cards.read.all'     => '查看全部卡片',
            'cards.create'       => '创建卡片',
            'cards.update'       => '编辑自己的卡片',
            'cards.update.all'   => '编辑任意卡片',
            'cards.delete'       => '删除自己的卡片',
            'cards.delete.all'   => '删除任意卡片',
            'cards.approve'      => '审核卡片',
            'cards.approve.all'  => '审核全部卡片',
            'cards.pin'          => '置顶自己的卡片',
            'cards.pin.all'      => '置顶任意卡片',
            // Comments
            'comments.read'         => '查看评论',
            'comments.read.all'     => '查看全部评论',
            'comments.create'       => '创建评论',
            'comments.update'       => '编辑自己的评论',
            'comments.update.all'   => '编辑任意评论',
            'comments.delete'       => '删除自己的评论',
            'comments.delete.all'   => '删除任意评论',
            // Tags
            'tags.read'         => '查看标签',
            'tags.read.all'     => '查看全部标签',
            'tags.create'       => '创建标签',
            'tags.update'       => '编辑自己的标签',
            'tags.update.all'   => '编辑任意标签',
            'tags.delete'       => '删除自己的标签',
            'tags.delete.all'   => '删除任意标签',
            // Users
            'users.read'         => '查看用户',
            'users.read.all'     => '查看全部用户',
            'users.update'       => '编辑自己',
            'users.update.all'   => '编辑任意用户',
            'users.delete'       => '注销自己',
            'users.delete.all'   => '删除任意用户',
            // Files
            'files.upload'      => '上传文件',
            'files.read'        => '查看文件',
            'files.read.all'    => '查看全部文件',
            'files.update'      => '编辑自己的文件',
            'files.update.all'  => '编辑任意文件',
            'files.delete'      => '删除自己的文件',
            'files.delete.all'  => '删除任意文件',
            // Likes
            'likes.create'      => '点赞',
            'likes.read'        => '查看点赞',
            'likes.delete'      => '取消点赞',
            // Roles
            'roles.read'        => '查看角色',
            'roles.create'      => '创建角色',
            'roles.update'      => '编辑角色',
            'roles.delete'      => '删除角色',
            'roles.assign'      => '分配角色能力',
            // Permissions
            'permissions.read'  => '查看权限列表',
            // Config
            'config.read'       => '查看配置',
            'config.update'     => '保存配置',
            'config.init'       => '初始化配置',
            'config.reload'     => '重载配置',
            'config.register'   => '注册配置',
            'config.deleteKey'  => '删除配置键',
            // Storage
            'storage.read'      => '查看存储',
            'storage.install'   => '安装存储驱动',
            'storage.test'      => '测试存储渠道',
            // Sender
            'sender.read'       => '查看消息',
            'sender.install'    => '安装消息驱动',
            'sender.test'       => '测试消息渠道',
            // Captcha
            'captcha.read'      => '查看验证驱动',
            'captcha.install'   => '安装验证驱动',
            // Theme
            'theme.read'        => '查看主题',
            'theme.update'      => '更新主题配置',
            'theme.upload'      => '上传主题',
            'theme.delete'      => '删除主题',
            'theme.freeze'      => '固化主题配置',
            'theme.activate'    => '切换主题',
            // Dashboard
            'dashboard.read'    => '查看控制台',
            // System
            'system.update'     => '系统更新',
            // Session（公开路由，通常不需要分配）
            'session.login'     => '登录',
            'session.register'  => '注册',
            'session.guest'     => '访客登录',
            'session.logout'    => '登出',
            'session.check'     => 'Token校验',
            'session.captcha'   => '获取验证码',
        ];
    }
}
