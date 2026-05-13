import instance from "../../axios";

import type { Params as PublicParams } from "../../types/public";
import type { Params } from "../../types/permissions";

const APP_PATH = "/admin/permissions";
const APP_PATH_ID = "/admin/permission";

const getPermissionIndex = (params: PublicParams.Index) => {
    if (params.search_keys != undefined && params.search_keys.length > 0) {
        params.search_keys = JSON.stringify(params.search_keys) as any;
    }
    params.search_value ? params.search_value : delete params.search_value;
    params.order_key ? params.order_key : delete params.order_key;
    return instance.get(APP_PATH, { params });
};

const getPermission = (params: Params.GetPermission) => {
    return instance.get(APP_PATH_ID, { params });
};

const createPermission = (params: Params.CreatePermission) => {
    return instance.post(APP_PATH_ID, params);
};

const patchPermission = (params: Params.PatchPermission) => {
    return instance.patch(APP_PATH_ID, params);
};

const deletePermission = (params: Params.DeletePermission) => {
    return instance.delete(APP_PATH_ID, { params });
};

const getAllPermissions = () => {
    return instance.get(APP_PATH + '/all', { params: { limit: 1000 } });
};

const PermissionsApi = {
    getPermissionIndex,
    getPermission,
    createPermission,
    patchPermission,
    deletePermission,
    getAllPermissions,
};

export default PermissionsApi;
