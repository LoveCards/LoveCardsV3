import instance from "../../axios";

const APP_PATH = "/admin/dashboard";

const getDashboard = () => {
    return instance.get(APP_PATH);
};

export { getDashboard };
