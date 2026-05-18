import instance from "../../axios";

const APP_PATH = "/admin/permissions";

const getAllPermissions = () => {
    return instance.get(APP_PATH + '/all');
};

const getPermissionIndex = (params: any) => {
    if (params.search_keys != undefined && params.search_keys.length > 0) {
        params.search_keys = JSON.stringify(params.search_keys) as any;
    }
    params.search_value ? params.search_value : delete params.search_value;
    return instance.get(APP_PATH, { params });
};

const PermissionsApi = {
    getAllPermissions,
    getPermissionIndex,
};

export default PermissionsApi;
