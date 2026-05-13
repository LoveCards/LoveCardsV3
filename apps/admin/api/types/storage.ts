import type { Params as PublicParams } from "./public";

export namespace Params {
    export interface FileIndex extends PublicParams.Index {
        scene?: string;
        status?: string | number;
        upload_status?: string | number;
        ref_type?: string;
        ref_id?: number;
        show_deleted?: number;
    }

    export interface BatchOperate {
        ids: number[];
        method: string;
    }
}

export interface FileItem {
    id: number;
    channel_slug: string;
    user_id: number | null;
    is_public: number;
    scene: string;
    ref_type: string | null;
    ref_id: number | null;
    original_name: string | null;
    file_path: string;
    file_url: string;
    file_size: number;
    file_ext: string;
    mime_type: string | null;
    driver_path: string | null;
    status: number;
    upload_status: number;
    expire_at: string | null;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}
