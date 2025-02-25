import instance from "../axios";

const APP_PATH = "/users";

const userIndex = (page: number = 1, listRows: number = 12) => {
    return instance.get(APP_PATH + "/index", {
        params: {
            page: page,
            list_rows: listRows,
        },
    });
};



export { userIndex };