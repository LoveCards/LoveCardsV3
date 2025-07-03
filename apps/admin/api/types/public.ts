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

    //批量操作通用
    export interface BatchOperate {
        ids: number[];  // ID列表
        method: string;  // 操作方法
    }
    //GetAppID通用参数
    export interface GetAppID {
        card_id: number;
    }
    //ID操作通用参数
    export interface IDOperate {
        id: number;
    }
}