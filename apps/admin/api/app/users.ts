import instance from "../axios";
import CommonUtils from "../utils/common";

const APP_PATH = "/users";

interface UserIndex {
    page?: number;   // 当前页  默认1
    list_rows?: number;  // 每页显示条数 默认10  最大100
    search_value?: string;
    search_keys?: string[];
    order_key?: string;
    order_desc?: boolean;
}
const getUserIndex = (params: UserIndex) => {
    //参数处理
    if (params.search_keys != undefined && params.search_keys.length > 0) {
        params.search_keys = JSON.stringify(params.search_keys) as any;
    }
    params.search_value ? params.search_value : delete params.search_value;
    params.order_key ? params.order_key : delete params.order_key;
    //params.order_desc ? params.order_desc : delete params.order_desc;
    return instance.get(APP_PATH + "/index", {
        params: params,
    });
};

interface PatchUser {
    id: number;
    number: string;
    avatar: string;
    email: string;
    phone: string;
    username: string;
    password: string;
    status: string;
    roles_id: string;
}
const patchUser = (params: PatchUser) => {
    return instance.patch(APP_PATH + "/patch", params);
};

const deleteUser = (id: number | number[]) => {
    const idArray = Array.isArray(id) ? JSON.stringify(id) : id;
    return instance.delete(APP_PATH + "/delete", {
        params: {
            id: idArray
        }
    });
};

const UserApi = {
    getUserIndex,
    patchUser,
    deleteUser
};

export default UserApi;