import type { User } from '@/types/auth';

export interface Notification {
    id: number;
    unread?: boolean;
    sender: User;
    body: string;
    date: string;
}

/** A tenant API token, as returned by `ApiTokenResource`. The plain token is never sent. */
export type ApiTokenData = {
    id: number;
    name: string;
    abilities: Array<{ value: string; label: string }>;
    /** The name of the user who created it. */
    owner: string | null;
    last_used_at: string | null;
    expires_at: string | null;
    is_expired: boolean;
    created_at: string;
    actions?: any[];
};

/** One MCP tool call, as returned by `McpAuditLogResource`. */
export type McpAuditLogData = {
    id: number;
    /** The token's name, noting when it's been revoked. */
    token: string;
    user: string | null;
    tool: string;
    ability: string;
    /** e.g. "page #12" */
    target: string | null;
    outcome: { value: string; label: string };
    message: string | null;
    arguments: Record<string, unknown>;
    changes: Record<string, { before: unknown; after: unknown }> | null;
    ip_address: string | null;
    created_at: string;
};

/** A newly created API token, flashed once with its plain text. */
export type NewApiToken = {
    name: string;
    token: string;
};
