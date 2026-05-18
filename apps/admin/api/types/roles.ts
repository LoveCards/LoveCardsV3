import type { Params as PublicParams } from "./public";

export namespace Params {
    export interface RoleIndex extends PublicParams.Index {}

    export interface GetRole {
        id: number;
    }

    export interface CreateRole {
        name: string;
        slug: string;
        description?: string;
    }

    export interface PatchRole {
        id: number;
        name?: string;
        slug?: string;
        description?: string;
    }

    export interface DeleteRole {
        id: number;
    }

    export interface AssignPermissions {
        id: number;
        permission_hashes: string;
    }

    export interface GetRolePermissions {
        id: number;
    }
}

export interface RoleItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    created_at: string;
    updated_at: string;
}
