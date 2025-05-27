import instance from "../../axios";
//import CommonUtils from "../utils/common";
import type { Params as PublicParams } from "../../types/public";
import type { Params as UsersParams } from "../../types/users";

const APP_PATH = "/cards";
const APP_PATH_ID = "/card";

//获取卡片
const getCardImages = (params: PublicParams.GetAppID) => {
    return instance.get(APP_PATH_ID + '/images', {
        params: params,
    });
};


const CardsApi = {
    getCardImages,
};

export default CardsApi;