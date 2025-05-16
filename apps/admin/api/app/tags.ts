import instance from "../axios";
const APP_PATH = "/admin/tags";

//获取列表
const getIndex = () => {
    return instance.get(APP_PATH);
};

const TagsApi = {
    getIndex,
};

export default TagsApi;