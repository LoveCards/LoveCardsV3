import type { Params as PublicParams } from "./public";

export namespace Params {
    export interface PermissionIndex extends PublicParams.Index {}

    export interface GetPermission {
        id: number;
    }

    export interface CreatePermission {
        name: string;
        slug: string;
        route_name: string;
        method: string;
        description?: string;
    }

    export interface PatchPermission {
        id: number;
        name?: string;
        slug?: string;
        route_name?: string;
        method?: string;
        description?: string;
    }

    export interface DeletePermission {
        id: number;
    }
}

export interface PermissionItem {
    id: number;
    name: string;
    slug: string;
    route_name: string;
    method: string;
    description: string | null;
    created_at: string;
    updated_at: string;
}
