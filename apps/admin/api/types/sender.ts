export interface SenderChannelMeta {
    slug: string;
    channelType: string;
    name: string;
    icon: string;
    fields: SenderField[];
    supportedTypes: string[];
}

export interface SenderField {
    key: string;
    label: string;
    type: string;
    options?: Array<{ value: string; label: string }>;
}

export interface SenderType {
    type: string;
    channelType: string;
    name: string;
    icon: string;
    supportedTypes: string[];
}

export interface SenderMeta {
    type: string;
    channelType: string;
    name: string;
    icon: string;
    schema: Record<string, {
        type: string;
        default: string;
        description: string;
    }>;
    group: string;
    supportedTypes: string[];
}
