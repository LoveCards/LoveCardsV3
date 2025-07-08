import instance from "../axios";

import type { Params as PublicParams } from "../types/public";
import type { Params as UsersParams } from "../types/users";

const APP_PATH = "/admin/users";

//获取用户列表
const getUserIndex = (params: PublicParams.Index) => {
    //参数处理
    if (params.search_keys != undefined && params.search_keys.length > 0) {
        params.search_keys = JSON.stringify(params.search_keys) as any;
    }
    params.search_value ? params.search_value : delete params.search_value;
    params.order_key ? params.order_key : delete params.order_key;
    //params.order_desc ? params.order_desc : delete params.order_desc;
    return instance.get(APP_PATH, {
        params: params,
    });
};

//编辑用户
const patchUser = (params: UsersParams.Patch) => {
    return instance.patch(APP_PATH, params);
};

//删除用户
const deleteUser = (params: PublicParams.IDOperate) => {
    return instance.delete(APP_PATH, { params: params });
};

const UsersApi = {
    getUserIndex,
    patchUser,
    deleteUser
};

export default UsersApi;