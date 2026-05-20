import instance from "../../axios";

const getConfig = (group?: string) => {
    const params = group ? { group } : {};
    return instance.get('/all/config', { params });
};

const postConfig = (params: any) => {
    return instance.post('/all/config', params);
};

const getThemes = () => {
    return instance.get('/all/system/themes');
};

const postThemeConfig = (params: any) => {
    return instance.post('/all/system/theme-config', params);
};

const postSetTheme = (params: any) => {
    return instance.post('/all/system/set-theme', params);
};

const getUpdata = () => {
    return instance.get('/all/system/update');
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
