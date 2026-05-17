export const channelSlugs = ['local', 'oss', 'cos', 'qiniu'] as const;

export const channelName = (slug: string): string => {
    const map: Record<string, string> = {
        local: '本地存储',
        oss: '阿里云 OSS',
        cos: '腾讯云 COS',
        qiniu: '七牛云',
    };
    return map[slug] || slug;
};

export const channelIcon = (type: string): string => {
    const map: Record<string, string> = {
        local: 'mdi-harddisk',
        oss: 'mdi-cloud',
        cos: 'mdi-cloud',
        qiniu: 'mdi-cloud',
    };
    return map[type] || 'mdi-cloud-outline';
};

export const channelOptions = channelSlugs.map(slug => ({
    title: channelName(slug),
    value: slug,
}));

export const channelFieldDefs: Record<string, Array<{ key: string; label: string; type: string }>> = {
    local: [
        { key: 'root', label: '存储根目录', type: 'text' },
        { key: 'url_prefix', label: 'URL前缀', type: 'text' },
        { key: 'allow_mime_types', label: '允许的MIME类型', type: 'text' },
        { key: 'max_file_size', label: '最大文件大小(字节)', type: 'number' },
        { key: 'path_template', label: '路径模板', type: 'text' },
    ],
    oss: [
        { key: 'access_key', label: 'AccessKey', type: 'text' },
        { key: 'secret_key', label: 'SecretKey', type: 'password' },
        { key: 'bucket', label: 'Bucket', type: 'text' },
        { key: 'endpoint', label: 'Endpoint', type: 'text' },
        { key: 'url_prefix', label: 'URL前缀', type: 'text' },
        { key: 'allow_mime_types', label: '允许的MIME类型', type: 'text' },
        { key: 'max_file_size', label: '最大文件大小(字节)', type: 'number' },
        { key: 'path_template', label: '路径模板', type: 'text' },
    ],
    cos: [
        { key: 'secret_id', label: 'SecretId', type: 'text' },
        { key: 'secret_key', label: 'SecretKey', type: 'password' },
        { key: 'bucket', label: 'Bucket', type: 'text' },
        { key: 'region', label: 'Region', type: 'text' },
        { key: 'cdn_url', label: 'CDN域名', type: 'text' },
        { key: 'allow_mime_types', label: '允许的MIME类型', type: 'text' },
        { key: 'max_file_size', label: '最大文件大小(字节)', type: 'number' },
        { key: 'path_template', label: '路径模板', type: 'text' },
    ],
    qiniu: [
        { key: 'access_key', label: 'AccessKey', type: 'text' },
        { key: 'secret_key', label: 'SecretKey', type: 'password' },
        { key: 'bucket', label: 'Bucket', type: 'text' },
        { key: 'domain', label: '域名', type: 'text' },
        { key: 'allow_mime_types', label: '允许的MIME类型', type: 'text' },
        { key: 'max_file_size', label: '最大文件大小(字节)', type: 'number' },
        { key: 'path_template', label: '路径模板', type: 'text' },
    ],
};

export const formatSize = (bytes: number): string => {
    if (!bytes || bytes === 0) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};
