/**
 * Shapes returned by the backend API.
 *
 * Kept in step with the App\Http\Resources classes by hand. Anything the API
 * can return as null is typed as nullable here, because "unknown" is a real
 * and meaningful state throughout this product — an unsynchronised MFA report
 * is not the same as a user without MFA, and the UI must not conflate them.
 */

export type Permission =
    | 'tenant.read'
    | 'tenant.manage'
    | 'members.manage'
    | 'users.read'
    | 'users.write'
    | 'users.disable'
    | 'users.revoke_sessions'
    | 'groups.read'
    | 'groups.write'
    | 'devices.read'
    | 'devices.write'
    | 'devices.sync'
    | 'devices.retire'
    | 'devices.wipe'
    | 'applications.read'
    | 'policies.read'
    | 'policies.write'
    | 'automation.read'
    | 'automation.write'
    | 'audit.read'
    | 'reports.read';

export type TenantStatus = 'pending' | 'active' | 'degraded' | 'disabled';

export interface Tenant {
    id: string;
    name: string;
    slug: string;
    default_domain: string | null;
    status: TenantStatus;
    status_label: string;
    is_connected: boolean;
    connection_error: string | null;
    consented_at: string | null;
    role: string | null;
    permissions: Permission[];
}

export interface CurrentUser {
    id: string;
    name: string;
    email: string;
    tenants: Tenant[];
}

export interface EntraUser {
    id: string;
    microsoft_id: string;
    display_name: string | null;
    user_principal_name: string | null;
    mail: string | null;
    job_title: string | null;
    department: string | null;
    office_location: string | null;
    user_type: string | null;
    account_enabled: boolean | null;
    /** Null means the report has not been synchronised, not "not registered". */
    mfa_registered: boolean | null;
    license_count: number;
    device_count: number;
    last_sign_in_at: string | null;
    created_at: string | null;
    is_directory_synced: boolean;
    synced_at: string | null;
}

export interface EntraUserDetail extends EntraUser {
    groups: EntraGroup[];
    devices: ManagedDevice[];
    licenses: { count: number; sku_ids: string[] };
    sign_in: {
        last_interactive_at: string | null;
        last_non_interactive_at: string | null;
    };
}

export type ComplianceState =
    | 'unknown'
    | 'compliant'
    | 'noncompliant'
    | 'conflict'
    | 'error'
    | 'inGracePeriod'
    | 'configManager';

export interface ManagedDevice {
    id: string;
    microsoft_id: string;
    device_name: string | null;
    manufacturer: string | null;
    model: string | null;
    serial_number: string | null;
    operating_system: string | null;
    os_version: string | null;
    compliance_state: ComplianceState | null;
    compliance_state_label: string | null;
    owner_type: string | null;
    is_encrypted: boolean | null;
    primary_user: {
        entra_user_id: string | null;
        user_principal_name: string | null;
    };
    last_check_in_at: string | null;
    enrolled_at: string | null;
    /** Has not checked in recently, so its state is last-known rather than current. */
    is_stale: boolean;
    storage: { total_bytes: number | null; free_bytes: number | null };
    synced_at: string | null;
}

export interface EntraGroup {
    id: string;
    microsoft_id: string;
    display_name: string | null;
    description: string | null;
    mail: string | null;
    security_enabled: boolean | null;
    mail_enabled: boolean | null;
    group_types: string[];
    member_count: number;
    has_dynamic_membership: boolean;
    is_directory_synced: boolean;
    is_membership_editable: boolean;
    synced_at: string | null;
}

export interface AuditLogEntry {
    id: string;
    action: string;
    action_label: string;
    result: 'success' | 'failure' | 'pending' | 'denied';
    result_label: string;
    actor: { id: string | null; name: string | null; email: string | null };
    resource: { type: string | null; id: string | null; label: string | null };
    previous_state: Record<string, unknown> | null;
    new_state: Record<string, unknown> | null;
    error_code: string | null;
    channel: string;
    correlation_id: string;
    ip_address: string | null;
    created_at: string | null;
}

export interface SyncState {
    resource: string;
    resource_label: string;
    status: 'idle' | 'running' | 'failed';
    status_label: string;
    last_successful_at: string | null;
    is_stale: boolean;
    records: { created: number; updated: number; deleted: number };
    consecutive_failures: number;
    error_code: string | null;
}

export interface Dashboard {
    users: {
        total: number;
        enabled: number;
        disabled: number;
        mfa_registration_unknown: number;
        mfa_not_registered: number;
    };
    devices: {
        total: number;
        compliant: number;
        non_compliant: number;
        unknown: number;
        needs_attention: number;
        not_checked_in_14_days: number;
        unencrypted: number;
    };
    recent_activity: AuditLogEntry[];
    sync: SyncState[];
    tenant: { status: TenantStatus; connection_error: string | null };
}

export interface SearchResults {
    query: string;
    users: EntraUser[];
    devices: ManagedDevice[];
    groups: EntraGroup[];
}

/** Laravel's paginated resource collection envelope. */
export interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
}

/** Single-resource envelope. */
export interface Envelope<T> {
    data: T;
    message?: string;
}

/**
 * The error shape the API returns for Graph failures. `detail` is written for
 * an administrator to act on, and `required_permission` names the consent to
 * grant when one is missing.
 */
export interface ApiError {
    message: string;
    error?: {
        key: string;
        graph_error_code?: string;
        graph_request_id?: string;
        required_permission?: string;
        retryable?: boolean;
    };
}
