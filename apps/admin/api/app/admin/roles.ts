import instance from "../../axios";

import type { Params as PublicParams } from "../../types/public";
import type { Params } from "../../types/roles";

const APP_PATH = "/admin/roles";
const APP_PATH_ID = "/admin/role";

const getRoleIndex = (params: PublicParams.Index) => {
    if (params.search_keys != undefined && params.search_keys.length > 0) {
        params.search_keys = JSON.stringify(params.search_keys) as any;
    }
    params.search_value ? params.search_value : delete params.search_value;
    params.order_key ? params.order_key : delete params.order_key;
    return instance.get(APP_PATH, { params });
};

const getRole = (params: Params.GetRole) => {
    return instance.get(APP_PATH_ID, { params });
};

const createRole = (params: Params.CreateRole) => {
    return instance.post(APP_PATH_ID, params);
};

const patchRole = (params: Params.PatchRole) => {
    return instance.patch(APP_PATH_ID, params);
};

const deleteRole = (params: Params.DeleteRole) => {
    return instance.delete(APP_PATH_ID, { params });
};

const assignPermissions = (params: Params.AssignPermissions) => {
    return instance.post(APP_PATH_ID + '/assign-permissions', params);
};

const getRolePermissions = (params: Params.GetRolePermissions) => {
    return instance.get(APP_PATH_ID + '/permissions', { params });
};

const RolesApi = {
    getRoleIndex,
    getRole,
    createRole,
    patchRole,
    deleteRole,
    assignPermissions,
    getRolePermissions,
};

export default RolesApi;
