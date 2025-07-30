import instance from "../../axios";

// const APP_PATH = "/system/config";
// const APP_PATH_ID = "/admin/tag";

//获取列表
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

const SystemApi = {
    getConfig,
    postConfig,
    patchEmail,
    postSite
};

export default SystemApi;