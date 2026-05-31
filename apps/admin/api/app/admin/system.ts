import instance from "../../axios";

const getConfig = (group?: string) => {
    const params = group ? { group } : {};
    return instance.get('/all/config', { params });
};

const postConfig = (params: any) => {
    return instance.post('/all/config', params);
};

const getUpdata = () => {
    return instance.get('/all/system/update');
};

const SystemApi = {
    getConfig,
    postConfig,
    getUpdata
};

export default SystemApi;
