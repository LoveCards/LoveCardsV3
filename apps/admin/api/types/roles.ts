import type { Params as PublicParams } from "./public";

export namespace Params {
    export interface RoleIndex extends PublicParams.Index {}

    export interface CreateRole {
        name: string;
        slug: string;
        description?: string;
    }

    export interface PatchRole {
        name?: string;
        slug?: string;
        description?: string;
    }

    export interface AssignPermissions {
        permission_hashes: string;
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
