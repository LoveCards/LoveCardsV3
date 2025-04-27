import instance from "../axios";
import type { Params } from "../types/upload";

const APP_PATH = "/upload";

const postUserImages = (data: Params.UserImages) => {
    const formData = new FormData();

    formData.append('file', data.file);
    formData.append('uid', data.uid.toString());
    formData.append('aid', data.aid.toString());
    formData.append('pid', data.pid.toString());

    return instance.post(APP_PATH + "/user-images", formData);
};

const UploadApi = {
    postUserImages
};

export default UploadApi;
