import instance from "../../axios";

import type { Params as PublicParams } from "../../types/public";
import type { Params as UsersParams } from "../../types/users";

const APP_PATH = "/all/users";

const getUserIndex = (params: PublicParams.Index) => {
    if (params.search_keys != undefined && params.search_keys.length > 0) {
        params.search_keys = JSON.stringify(params.search_keys) as any;
    }
    params.search_value ? params.search_value : delete params.search_value;
    params.order_key ? params.order_key : delete params.order_key;
    return instance.get(APP_PATH, {
        params: params,
    });
};

const patchUser = (id: number, params: UsersParams.Patch) => {
    return instance.patch(`${APP_PATH}/${id}`, params);
};

const deleteUser = (id: number) => {
    return instance.delete(`${APP_PATH}/${id}`);
};

const batchOperate = (params: PublicParams.BatchOperate) => {
    const data = {
        ids: JSON.stringify(params.ids),
        method: params.method,
    };
    return instance.post(APP_PATH + '/batch', data);
};

const UsersApi = {
    getUserIndex,
    patchUser,
    deleteUser,
    batchOperate
};

export default UsersApi;
