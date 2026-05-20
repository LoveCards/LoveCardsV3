import instance from "../axios";

const postUpload = (data: { file: File; scene?: string; ref_type?: string; ref_id?: number; is_public?: number }) => {
    const formData = new FormData();

    formData.append('file', data.file);
    if (data.scene) formData.append('scene', data.scene);
    if (data.ref_type) formData.append('ref_type', data.ref_type);
    if (data.ref_id !== undefined) formData.append('ref_id', data.ref_id.toString());
    if (data.is_public !== undefined) formData.append('is_public', data.is_public.toString());

    return instance.post("/files", formData);
};

const UploadApi = {
    postUpload
};

export default UploadApi;
