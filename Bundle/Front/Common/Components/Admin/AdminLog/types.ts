// Generic types for Common AdminLog components.
// Mirrors the rows produced by Framework-level admin-log endpoints.

import type {GridConfig} from '../AdminGrid/types';

export type {GridConfig};

export interface ActionLog {
    id: number;
    actor_id: number;
    actor_login: string;
    actor_name: string;
    actor_type: string;
    target_id: number;
    target_login: string;
    target_name: string;
    target_type: string;
    action: string;
    old_value: string;
    new_value: string;
    created_at: number;
}

export interface CronLogEntry {
    id: number;
    task_name: string;
    started_at: number;
    finished_at: number;
    duration_ms: number;
    status: 'success' | 'error' | 'running';
    output: string | null;
    error_message: string | null;
    created_at: number;
}

export interface JsErrorEntry {
    id: number;
    hash: string;
    message: string;
    stack: string | null;
    file: string | null;
    line: number;
    col: number;
    url: string | null;
    user_agent: string | null;
    account_id: number | null;
    account_name?: string;
    count: number;
    first_seen_at: number;
    last_seen_at: number;
}

export interface MailLogEntry {
    id: number;
    account_id: number | null;
    account_name: string;
    account_login: string;
    recipient_email: string;
    mail_type: string;
    subject: string;
    /** Only present for admin role; stripped for moderators/owners */
    body_html?: string;
    /** Structured service data (auth codes etc.); only present for admin */
    meta?: string | null;
    status: string;
    error_log: string | null;
    created_at: number;
}
