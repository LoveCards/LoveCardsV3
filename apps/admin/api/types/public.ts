export namespace Params {
    // Index通用参数
    export interface Index {
        page?: number;   // 当前页  默认1
        list_rows?: number;  // 每页显示条数 默认10  最大100
        search_value?: string;
        search_keys?: string[];
        order_key?: string;
        order_desc?: boolean;
    }

    //GetAppID通用参数
    export interface GetAppID {
        card_id: number;
    }
    //GetID通用参数
    export interface GetID {
        id: number;
    }
    //Delete通用参数
    export interface Delete {
        id: number | number[];
    }
}