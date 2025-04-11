import instance from "../axios";

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
    return instance.get(APP_PATH + "/index", {
        params: {
            page: params.page,
            list_rows: params.list_rows,
            search_value: params.search_value,
            search_keys: params.search_keys,
            order_key: params.order_key,
            order_desc: params.order_desc,
        },
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
}
const patchUser = (params: PatchUser) => {
    return instance.patch(APP_PATH + "/patch", {
        id: params.id,
        number: params.number,
        avatar: params.avatar,
        email: params.email,
        phone: params.phone,
        username: params.username,
        password: params.password,
        status: params.status
    });
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