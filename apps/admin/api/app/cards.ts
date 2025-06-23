import instance from "../axios";
//import CommonUtils from "../utils/common";
import type { Params as PublicParams } from "../types/public";
import type { Params as CardsParams } from "../types/cards";

const APP_PATH = "/admin/cards";
const APP_PATH_ID = "/admin/card";

//获取卡片
const getCard = (params: PublicParams.GetID) => {
    return instance.get(APP_PATH_ID, {
        params: params,
    });
};

//获取卡片列表
const getCardIndex = (params: PublicParams.Index) => {
    //参数处理
    if (params.search_keys != undefined && params.search_keys.length > 0) {
        params.search_keys = JSON.stringify(params.search_keys) as any;
    }
    params.search_value ? params.search_value : delete params.search_value;
    params.order_key ? params.order_key : delete params.order_key;
    //params.order_desc ? params.order_desc : delete params.order_desc;
    return instance.get(APP_PATH, {
        params: params,
    });
};

//删除卡片
const deleteCard = (id: PublicParams.Delete) => {
    const idArray = Array.isArray(id) ? JSON.stringify(id) : id;
    return instance.delete(APP_PATH, {
        params: {
            id: idArray
        }
    });
};

//编辑卡片
const patchCard = (params: CardsParams.Patch) => {
    return instance.patch(APP_PATH_ID, params);
};

const CardsApi = {
    getCardIndex,
    deleteCard,
    getCard,
    patchCard
};

export default CardsApi;