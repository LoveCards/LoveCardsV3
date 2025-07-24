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

const SystemApi = {
    getConfig,
    postConfig
};

export default SystemApi;