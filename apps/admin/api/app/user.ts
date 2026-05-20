import instance from "../axios";

const getUserInfo = () => {
    return instance.get("/users/me");
};

export { getUserInfo };
