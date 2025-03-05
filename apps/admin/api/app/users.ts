import instance from "../axios";

const APP_PATH = "/users";

interface UserIndex {
    page: number;   // 当前页  默认1
    list_rows: number;  // 每页显示条数 默认10  最大100
}
const getUserIndex = (page: number = 1, listRows: number = 10) => {
    return instance.get(APP_PATH + "/index", {
        params: {
            page: page,
            list_rows: listRows,
        },
    });
};

interface PatchUser {
    id: number
    number: string
    avatar: string
    email: string
    phone: string
    username: string
    password: string
    status: string
}
const patchUser = (data: PatchUser) => {
    return instance.patch(APP_PATH + "/patch", {
        id: data.id,
        number: data.number,
        avatar: data.avatar,
        email: data.email,
        phone: data.phone,
        username: data.username,
        password: data.password,
        status: data.status
    });
};

const UserApi = {
    getUserIndex,
    patchUser
};

export default UserApi;