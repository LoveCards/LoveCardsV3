import instance from "../../axios";

const APP_PATH = "/all/dashboard";

const getDashboard = () => {
    return instance.get(APP_PATH);
};

export { getDashboard };
