import instance from "../../axios";

const getTypes = () => {
    return instance.get("/all/captcha/types");
};

const getDrivers = () => {
    return instance.get("/all/captcha/drivers");
};

const getMeta = (slug: string) => {
    return instance.get(`/all/captcha/${slug}/meta`);
};

const install = () => {
    return instance.post("/all/captcha/install");
};

const getConfig = () => {
    return instance.get("/captcha/config");
};

const CaptchaApi = {
    getTypes,
    getDrivers,
    getMeta,
    install,
    getConfig,
};

export default CaptchaApi;
