import instance from "../axios";

const APP_PATH = "/user";

const getUserInfo = () => {
    return instance.get(APP_PATH + "/info");
};

export { getUserInfo };
