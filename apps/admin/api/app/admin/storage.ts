import instance from "../../axios";

import type { Params } from "../../types/storage";

const APP_PATH = "/storage";

const getFileIndex = (params: Params.FileIndex) => {
    if (params.search_keys != undefined && params.search_keys.length > 0) {
        params.search_keys = JSON.stringify(params.search_keys) as any;
    }
    params.search_value ? params.search_value : delete params.search_value;
    params.order_key ? params.order_key : delete params.order_key;
    params.scene ? params.scene : delete params.scene;
    params.status !== undefined && params.status !== '' ? params.status : delete params.status;
    params.upload_status !== undefined && params.upload_status !== '' ? params.upload_status : delete params.upload_status;
    params.show_deleted ? params.show_deleted : delete params.show_deleted;
    return instance.get(APP_PATH + "/files", { params });
};

const getFile = (id: number) => {
    return instance.get(`${APP_PATH}/files/${id}`);
};

const batchOperate = (params: { ids: number[]; method: string }) => {
    return instance.post(`${APP_PATH}/files/batch`, {
        ids: JSON.stringify(params.ids),
        method: params.method,
    });
};

const cleanup = () => {
    return instance.delete(`${APP_PATH}/files/expired`);
};

const testChannel = (channel: string) => {
    return instance.post(`${APP_PATH}/channels/${channel}/test`);
};

const getChannelStats = () => {
    return instance.get(`${APP_PATH}/channels/stats`);
};

const getStorageChannels = () => {
    return instance.get(`${APP_PATH}/channels`);
};

const StorageApi = {
    getFileIndex,
    getFile,
    batchOperate,
    cleanup,
    testChannel,
    getChannelStats,
    getStorageChannels,
};

export default StorageApi;
