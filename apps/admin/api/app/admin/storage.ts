import instance from "../../axios";

import type { Params } from "../../types/storage";

const APP_PATH = "/upload";

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

const getFile = (params: { id: number }) => {
    return instance.get(APP_PATH + "/get-file", { params });
};

const batchOperate = (params: { ids: number[]; method: string }) => {
    return instance.post(APP_PATH + "/batch-operate", {
        ids: JSON.stringify(params.ids),
        method: params.method,
    });
};

const cleanup = () => {
    return instance.post(APP_PATH + "/cleanup");
};

const testChannel = (channel: string) => {
    return instance.post(APP_PATH + "/test-channel", { channel });
};

const getChannelStats = () => {
    return instance.get(APP_PATH + "/channel-stats");
};

const StorageApi = {
    getFileIndex,
    getFile,
    batchOperate,
    cleanup,
    testChannel,
    getChannelStats,
};

export default StorageApi;
