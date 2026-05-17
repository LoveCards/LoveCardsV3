import instance from "../../axios";

const getConfig = (group?: string) => {
    const params = group ? { group } : {};
    return instance.get('/system/config', { params });
};

const postConfig = (params: any) => {
    return instance.post('/system/config', params);
};

const getThemes = () => {
    return instance.get('/system/themes');
};

const postThemeConfig = (params: any) => {
    return instance.post('/system/theme-config', params);
};

const postSetTheme = (params: any) => {
    return instance.post('/system/set-theme', params);
};

const getUpdata = () => {
    return instance.get('/system/updata');
};

const SystemApi = {
    getConfig,
    postConfig,
    getThemes,
    postThemeConfig,
    postSetTheme,
    getUpdata
};

export default SystemApi;
