import instance from "../axios";

const APP_PATH = "/user";

const getUser = () => {
    return instance.get(APP_PATH);
};

export { getUser };
