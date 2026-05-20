import instance from "../../axios";

import type { Params as PublicParams } from "../../types/public";
import type { Params as CardsParams } from "../../types/cards";

const APP_PATH = "/all/cards";

const getCard = (id: number) => {
    return instance.get(`${APP_PATH}/${id}`);
};

const getCardIndex = (params: PublicParams.Index) => {
    if (params.search_keys != undefined && params.search_keys.length > 0) {
        params.search_keys = JSON.stringify(params.search_keys) as any;
    }
    params.search_value ? params.search_value : delete params.search_value;
    params.order_key ? params.order_key : delete params.order_key;
    return instance.get(APP_PATH, {
        params: params,
    });
};

const deleteCard = (id: number) => {
    return instance.delete(`${APP_PATH}/${id}`);
};

const patchCard = (id: number, params: any) => {
    return instance.patch(`${APP_PATH}/${id}`, params);
};

const batchOperate = (params: PublicParams.BatchOperate) => {
    const data = {
        ids: JSON.stringify(params.ids),
        method: params.method,
    };
    return instance.post(APP_PATH + '/batch', data);
};

const CardsApi = {
    getCardIndex,
    deleteCard,
    getCard,
    patchCard,
    batchOperate
};

export default CardsApi;
