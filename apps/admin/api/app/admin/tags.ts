import instance from "../../axios";

import type { Params as PublicParams } from "../../types/public";
import type { Params as CardsParams } from "../../types/cards";

const APP_PATH = "/admin/tags";
const APP_PATH_ID = "/admin/tag";

//获取列表
const getTagIndex = (params: PublicParams.Index) => {
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
//创建标签
const createTag = (params: any) => {
    return instance.post(APP_PATH_ID, params);
};
//删除标签
const deleteTag = (params: PublicParams.IDOperate) => {
    return instance.delete(APP_PATH_ID, { params: params });
};
//编辑标签
const patchTag = (params: any) => {
    return instance.patch(APP_PATH_ID, params);
};
//批量操作
const batchOperate = (params: PublicParams.BatchOperate) => {
    const data = {
        ids: JSON.stringify(params.ids),
        method: params.method,
    };
    return instance.post(APP_PATH + '/batch-operate', data);
};

const TagsApi = {
    getTagIndex,
    deleteTag,
    patchTag,
    batchOperate,
    createTag
};

export default TagsApi;