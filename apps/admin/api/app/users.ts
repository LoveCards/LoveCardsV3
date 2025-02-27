import instance from "../axios";

const APP_PATH = "/users";

const getUserIndex = (page: number = 1, listRows: number = 10) => {
    return instance.get(APP_PATH + "/index", {
        params: {
            page: page,
            list_rows: listRows,
        },
    });
};



export { getUserIndex };