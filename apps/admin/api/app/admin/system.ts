import instance from "../../axios";

// const APP_PATH = "/system/config";
// const APP_PATH_ID = "/admin/tag";

const getThemes = () => {
    return instance.get('/system/themes');
};
const getConfig = () => {
    return instance.get('/system/config');
};
const postConfig = (params: any) => {
    return instance.post('/system/config', params);
};
const patchEmail = (params: any) => {
    return instance.patch('/system/email', params);
};
const postSite = (params: any) => {
    return instance.post('/system/site', params);
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
    patchEmail,
    postSite,
    getThemes,
    postThemeConfig,
    postSetTheme,
    getUpdata
};

export default SystemApi;