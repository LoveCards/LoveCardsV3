import instance from "../../axios";

import type { Params as PublicParams } from "../../types/public";
import type { Params } from "../../types/roles";

const APP_PATH = "/all/roles";

const getRoleIndex = (params: PublicParams.Index) => {
    if (params.search_keys != undefined && params.search_keys.length > 0) {
        params.search_keys = JSON.stringify(params.search_keys) as any;
    }
    params.search_value ? params.search_value : delete params.search_value;
    params.order_key ? params.order_key : delete params.order_key;
    return instance.get(APP_PATH, { params });
};

const getRole = (id: number) => {
    return instance.get(`${APP_PATH}/${id}`);
};

const createRole = (params: Params.CreateRole) => {
    return instance.post(APP_PATH, params);
};

const patchRole = (id: number, params: Params.PatchRole) => {
    return instance.patch(`${APP_PATH}/${id}`, params);
};

const deleteRole = (id: number) => {
    return instance.delete(`${APP_PATH}/${id}`);
};

const assignPermissions = (id: number, params: Params.AssignPermissions) => {
    return instance.post(`${APP_PATH}/${id}/permissions`, params);
};

const getRolePermissionHashes = (id: number) => {
    return instance.get(`${APP_PATH}/${id}/permissions`);
};

const RolesApi = {
    getRoleIndex,
    getRole,
    createRole,
    patchRole,
    deleteRole,
    assignPermissions,
    getRolePermissionHashes,
};

export default RolesApi;
