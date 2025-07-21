import instance from "../../axios";

const APP_PATH = "/admin/dashboard";

//控制台
const getDashboard = () => {
    return instance.get(APP_PATH);
};

export { getDashboard };
