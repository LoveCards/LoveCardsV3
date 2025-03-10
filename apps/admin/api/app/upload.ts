import instance from "../axios";

const APP_PATH = "/upload";


interface PostUserImages {
    file: File;
    uid: number;
    aid: number;
    pid: number;
}

const postUserImages = (data: PostUserImages) => {
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
