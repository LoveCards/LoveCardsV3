import instance from "../../axios";

// const APP_PATH = "/system/config";
// const APP_PATH_ID = "/admin/tag";

//获取列表
const getConfig = () => {
    return instance.get('/system/config');
};

const SystemApi = {
    getConfig,
};

export default SystemApi;