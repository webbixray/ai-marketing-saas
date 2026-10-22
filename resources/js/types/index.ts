// Core types for the application

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at?: string;
    tenant_id: number;
    roles: string[];
    permissions: string[];
    created_at: string;
    updated_at: string;
}

export interface Tenant {
    id: number;
    name: string;
    domain: string;
    plan: string;
    plan_expires_at?: string;
    settings: TenantSettings;
    white_label_config?: WhiteLabelConfig;
    sso_config?: SSOConfig;
    created_at: string;
    updated_at: string;
}

export interface TenantSettings {
    timezone: string;
    locale: string;
    currency: string;
    date_format: string;
    features: Record<string, boolean>;
}

export interface WhiteLabelConfig {
    enabled: boolean;
    custom_domain?: string;
    domain_verified: boolean;
    ssl_status: string;
    theme: 'light' | 'dark' | 'auto';
    brand_name: string;
    logo_url?: string;
    favicon_url?: string;
    colors: Record<string, string>;
    fonts: Record<string, string>;
    custom_css?: string;
    custom_js?: string;
    assets: Asset[];
    email_templates: Record<string, EmailTemplate>;
    login_page: LoginPageConfig;
}

export interface Asset {
    type: 'logo' | 'favicon' | 'background' | 'illustration' | 'icon';
    url: string;
    name: string;
    width?: number;
    height?: number;
}

export interface EmailTemplate {
    subject: string;
    html: string;
    text: string;
    variables: string[];
}

export interface LoginPageConfig {
    show_powered_by: boolean;
    background_image?: string;
    custom_message?: string;
    show_remember_me: boolean;
    show_forgot_password: boolean;
    show_sso_buttons: boolean;
}

export interface SSOConfig {
    saml?: SAMLConfig;
    oidc?: OIDCConfig;
    google?: GoogleConfig;
    microsoft?: MicrosoftConfig;
    okta?: OktaConfig;
    auto_provision: boolean;
    default_role: string;
    just_in_time_provisioning: boolean;
}

export interface SAMLConfig {
    enabled: boolean;
    sp_entity_id: string;
    sp_acs_url: string;
    sp_sls_url?: string;
    idp_entity_id: string;
    idp_sso_url: string;
    idp_slo_url?: string;
    idp_x509_cert: string;
}

export interface OIDCConfig {
    enabled: boolean;
    client_id: string;
    client_secret: string;
    issuer: string;
    discovery_url?: string;
    redirect_uri: string;
    scopes: string[];
    pkce_enabled: boolean;
}

export interface GoogleConfig {
    enabled: boolean;
    client_id: string;
    client_secret: string;
    redirect_uri: string;
    scopes: string[];
    hosted_domain?: string;
}

export interface MicrosoftConfig {
    enabled: boolean;
    client_id: string;
    client_secret: string;
    tenant_id: string;
    redirect_uri: string;
    scopes: string[];
}

export interface OktaConfig {
    enabled: boolean;
    client_id: string;
    client_secret: string;
    issuer: string;
    redirect_uri: string;
    scopes: string[];
}

// Campaign types
export interface Campaign {
    id: number;
    tenant_id: number;
    client_id?: number;
    name: string;
    description?: string;
    platform: 'facebook' | 'instagram' | 'google' | 'linkedin' | 'twitter' | 'tiktok' | 'youtube';
    objective: 'awareness' | 'traffic' | 'engagement' | 'leads' | 'sales' | 'app_installs';
    status: 'draft' | 'pending_approval' | 'active' | 'paused' | 'completed' | 'archived';
    budget_type: 'daily' | 'lifetime';
    budget: number;
    spend: number;
    currency: string;
    targeting: TargetingConfig;
    creative: CreativeConfig;
    schedule: ScheduleConfig;
    utm: UTMConfig;
    metrics: CampaignMetrics;
    ai_generated: boolean;
    created_by: number;
    approved_by?: number;
    approved_at?: string;
    started_at?: string;
    ended_at?: string;
    created_at: string;
    updated_at: string;
}

export interface TargetingConfig {
    locations?: string[];
    age_min?: number;
    age_max?: number;
    genders?: string[];
    interests?: string[];
    behaviors?: string[];
    custom_audiences?: string[];
    lookalike_audiences?: string[];
    exclusions?: string[];
}

export interface CreativeConfig {
    format: 'single_image' | 'carousel' | 'video' | 'collection' | 'story';
    primary_text?: string;
    headline?: string;
    description?: string;
    call_to_action?: string;
    images: CreativeAsset[];
    videos: CreativeAsset[];
}

export interface CreativeAsset {
    id: string;
    url: string;
    type: 'image' | 'video';
    width?: number;
    height?: number;
    size?: number;
    alt_text?: string;
}

export interface ScheduleConfig {
    start_date: string;
    end_date?: string;
    timezone: string;
    day_parting?: DayParting[];
}

export interface DayParting {
    days: number[];
    start_time: string;
    end_time: string;
}

export interface UTMConfig {
    source: string;
    medium: string;
    campaign: string;
    term?: string;
    content?: string;
}

export interface CampaignMetrics {
    impressions: number;
    clicks: number;
    ctr: number;
    cpc: number;
    cpm: number;
    spend: number;
    conversions: number;
    conversion_rate: number;
    cpa: number;
    roas: number;
    revenue: number;
}

// Client types
export interface Client {
    id: number;
    tenant_id: number;
    name: string;
    email: string;
    phone?: string;
    website?: string;
    industry?: string;
    company_size?: string;
    status: 'active' | 'inactive' | 'prospect' | 'churned';
    assigned_to?: number;
    tags: string[];
    notes?: string;
    billing_info?: BillingInfo;
    created_at: string;
    updated_at: string;
}

export interface BillingInfo {
    company_name: string;
    tax_id?: string;
    address: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    payment_method?: string;
}

// Analytics types
export interface AnalyticsEvent {
    event_id: string;
    event_name: string;
    tenant_id: number;
    user_id?: number;
    session_id?: string;
    properties: Record<string, unknown>;
    context: Record<string, unknown>;
    timestamp: string;
}

export interface MetricDataPoint {
    timestamp: string;
    value: number;
    label?: string;
}

export interface TimeSeriesData {
    metric: string;
    period: string;
    interval: string;
    data: MetricDataPoint[];
}

export interface DashboardWidget {
    id: string;
    type: 'metric' | 'line_chart' | 'bar_chart' | 'pie_chart' | 'funnel' | 'table';
    metric: string;
    title: string;
    period?: string;
    interval?: string;
    limit?: number;
    config?: Record<string, unknown>;
}

export interface Dashboard {
    id: string;
    name: string;
    description?: string;
    widgets: DashboardWidget[];
    layout: Record<string, unknown>;
    is_default: boolean;
}

// Enterprise types
export interface SSOProvider {
    id: string;
    name: string;
    enabled: boolean;
    configured: boolean;
    login_url: string;
    config_fields: ConfigField[];
}

export interface ConfigField {
    key: string;
    label: string;
    type: 'text' | 'password' | 'url' | 'textarea' | 'array' | 'boolean';
    required: boolean;
    default?: string;
}

export interface SCIMUser {
    id: string;
    externalId?: string;
    userName: string;
    name: {
        givenName: string;
        familyName: string;
        formatted: string;
    };
    displayName?: string;
    emails: SCIMUserEmail[];
    phoneNumbers?: SCIMUserPhoneNumber[];
    addresses?: SCIMUserAddress[];
    active: boolean;
    groups?: SCIMUserGroup[];
    roles?: SCIMUserRole[];
}

export interface SCIMUserEmail {
    value: string;
    type?: 'work' | 'home' | 'other';
    primary?: boolean;
}

export interface SCIMUserPhoneNumber {
    value: string;
    type?: 'work' | 'home' | 'mobile' | 'fax' | 'pager' | 'other';
    primary?: boolean;
}

export interface SCIMUserAddress {
    formatted?: string;
    streetAddress?: string;
    locality?: string;
    region?: string;
    postalCode?: string;
    country?: string;
    type?: 'work' | 'home' | 'other';
    primary?: boolean;
}

export interface SCIMUserGroup {
    value: string;
    display?: string;
    type?: 'direct' | 'indirect';
}

export interface SCIMUserRole {
    value: string;
    display?: string;
    type?: string;
}

export interface SCIMGroup {
    id: string;
    displayName: string;
    members: SCIMGroupMember[];
}

export interface SCIMGroupMember {
    value: string;
    display?: string;
    type?: 'User' | 'Group';
}

// Marketplace types
export interface MarketplaceListing {
    id: number;
    title: string;
    slug: string;
    description: string;
    short_description: string;
    category: string;
    type: 'agent' | 'template' | 'workflow' | 'integration' | 'report' | 'creative' | 'script' | 'connector';
    version: string;
    price_type: 'free' | 'one_time' | 'subscription' | 'usage';
    price: number;
    currency: string;
    billing_cycle?: 'monthly' | 'yearly';
    tags: string[];
    requirements: string[];
    compatibility: string[];
    screenshots: string[];
    demo_url?: string;
    documentation_url?: string;
    repository_url?: string;
    support_email: string;
    support_url?: string;
    license_type: string;
    rating_average: number;
    rating_count: number;
    download_count: number;
    featured: boolean;
    verified: boolean;
    status: 'draft' | 'pending_review' | 'approved' | 'rejected';
    created_at: string;
    updated_at: string;
}

export interface MarketplaceReview {
    id: number;
    listing_id: number;
    user_id: number;
    rating: number;
    title?: string;
    comment?: string;
    pros: string[];
    cons: string[];
    verified_purchase: boolean;
    created_at: string;
}

// Global types
export interface Region {
    code: string;
    name: string;
    endpoint: string;
    database: string;
    storage: string;
    cdn: string;
    active: boolean;
    priority: number;
    data_residency: string[];
    compliance: string[];
    health?: RegionHealth;
}

export interface RegionHealth {
    region: string;
    status: 'healthy' | 'unhealthy' | 'unreachable';
    latency_ms: number;
    last_check: string;
    details?: Record<string, unknown>;
}

export interface CDNConfig {
    provider: string;
    cache_rules: CacheRule[];
    waf_rules: WAFRule[];
    rate_limits: RateLimitRule[];
    analytics: CDNAnalytics;
}

export interface CacheRule {
    pattern: string;
    ttl: number;
    browser_ttl: number;
    stale_while_revalidate: number;
}

export interface WAFRule {
    id: string;
    expression: string;
    action: 'block' | 'challenge' | 'js_challenge' | 'managed_challenge' | 'log' | 'allow';
    description: string;
    priority: number;
    paused: boolean;
}

export interface RateLimitRule {
    url: string;
    threshold: number;
    period: number;
    action: 'block' | 'challenge' | 'js_challenge';
    timeout: number;
}

export interface CDNAnalytics {
    requests: number;
    bandwidth: number;
    cached_requests: number;
    threats: number;
    cache_hit_rate: number;
}

// Data Governance types
export interface DataAsset {
    id: string;
    name: string;
    description?: string;
    type: 'table' | 'view' | 'file' | 'api' | 'stream';
    source: string;
    schema: DataSchemaField[];
    classification: 'public' | 'internal' | 'confidential' | 'restricted';
    owner: number;
    steward?: number;
    tags: string[];
    lineage: DataLineage;
    quality_score?: number;
    pii_fields: PIIField[];
}

export interface DataSchemaField {
    name: string;
    type: string;
    nullable: boolean;
    description?: string;
}

export interface DataLineage {
    upstream: DataLineageNode[];
    downstream: DataLineageNode[];
    transformations: DataTransformation[];
}

export interface DataLineageNode {
    asset_id: string;
    asset_name: string;
    relationship: string;
}

export interface DataTransformation {
    id: string;
    name: string;
    type: string;
    description: string;
}

export interface PIIField {
    field: string;
    type: string;
    confidence: number;
}

export interface DataQualityRule {
    id: string;
    name: string;
    description?: string;
    asset_id?: string;
    type: 'completeness' | 'uniqueness' | 'validity' | 'accuracy' | 'consistency' | 'timeliness';
    dimension: string;
    condition: Record<string, unknown>;
    threshold: number;
    severity: 'info' | 'warning' | 'critical';
    enabled: boolean;
    schedule: 'hourly' | 'daily' | 'weekly' | 'manual';
    last_run?: string;
    last_result?: DataQualityResult;
}

export interface DataQualityResult {
    rule_id: string;
    rule_name: string;
    type: string;
    passed: boolean;
    score: number;
    threshold: number;
    records_checked: number;
    records_failed: number;
    executed_at: string;
    details: string;
}

// Disaster Recovery types
export interface BackupPlan {
    id: string;
    name: string;
    description?: string;
    databases: string[];
    tables: string[];
    exclude_tables: string[];
    storage: string;
    retention: RetentionPolicy;
    compression: boolean;
    encryption: boolean;
    schedule: string;
    verify: boolean;
    enabled: boolean;
    last_run?: string;
    last_status?: string;
    next_run?: string;
}

export interface RetentionPolicy {
    hourly: number;
    daily: number;
    weekly: number;
    monthly: number;
}

export interface Backup {
    id: string;
    plan_id: string;
    type: 'scheduled' | 'manual' | 'verification';
    status: 'pending' | 'running' | 'completed' | 'failed';
    started_at: string;
    completed_at?: string;
    size_bytes: number;
    tables_count: number;
    duration_seconds?: number;
    error?: string;
    verified: boolean;
    verified_at?: string;
    checksum_verified: boolean;
    restore_tested: boolean;
}

// Cost Optimization types
export interface CostOverview {
    period: string;
    period_start: string;
    period_end: string;
    total_cost: number;
    currency: string;
    by_provider: Record<string, ProviderCost>;
    by_service: Record<string, number>;
    by_region: Record<string, number>;
    daily_trend: Record<string, number>;
    change_from_previous: {
        percentage: number;
        amount: number;
    };
}

export interface ProviderCost {
    total: number;
    by_service: Record<string, number>;
    by_region: Record<string, number>;
    daily: Record<string, number>;
}

export interface CostAnomaly {
    type: 'service_cost' | 'daily_spike';
    service?: string;
    date?: string;
    current_cost: number;
    previous_cost?: number;
    expected?: number;
    change_percentage: number;
    deviation?: number;
    severity: 'high' | 'medium';
    direction?: 'increase' | 'decrease';
    detected_at: string;
}

export interface Recommendation {
    type: 'rightsizing' | 'reserved_instances' | 'savings_plans' | 'spot_instances';
    resource_id: string;
    current_type: string;
    recommended_type: string;
    current_cost: number;
    estimated_cost: number;
    monthly_savings: number;
    annual_savings: number;
    cpu_utilization?: number;
    memory_utilization?: number;
    confidence: number;
    risk: 'low' | 'medium' | 'high';
    action: string;
}

export interface Budget {
    id: string;
    name: string;
    amount: number;
    currency: string;
    period: 'daily' | 'weekly' | 'monthly' | 'quarterly' | 'yearly';
    start_date: string;
    end_date?: string;
    filters: Record<string, unknown>;
    alert_thresholds: number[];
    alert_channels: string[];
    enabled: boolean;
}

// API Response types
export interface ApiResponse<T> {
    data: T;
    message?: string;
    errors?: Record<string, string[]>;
}

export interface PaginatedResponse<T> {
    data: T[];
    pagination: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
    status: number;
}

// Form types
export interface FormField {
    name: string;
    label: string;
    type: 'text' | 'email' | 'password' | 'number' | 'select' | 'multiselect' | 'textarea' | 'checkbox' | 'radio' | 'date' | 'datetime' | 'file' | 'switch';
    required?: boolean;
    placeholder?: string;
    options?: SelectOption[];
    validation?: ValidationRule[];
    dependsOn?: string;
    showWhen?: string;
}

export interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

export interface ValidationRule {
    type: 'required' | 'email' | 'min' | 'max' | 'pattern' | 'custom';
    value?: string | number;
    message: string;
    validator?: (value: unknown) => boolean;
}

// Navigation types
export interface NavItem {
    label: string;
    href: string;
    icon?: React.ComponentType<{ className?: string }>;
    badge?: string | number;
    children?: NavItem[];
    permission?: string;
    roles?: string[];
}

// Theme types
export type Theme = 'light' | 'dark' | 'system';

export interface ThemeConfig {
    theme: Theme;
    colors: {
        primary: string;
        secondary: string;
        success: string;
        warning: string;
        danger: string;
    };
    fonts: {
        heading: string;
        body: string;
        mono: string;
    };
}

// WebSocket types
export interface WebSocketMessage {
    event: string;
    data: unknown;
    timestamp: string;
}

export interface RealtimeEvent {
    type: 'campaign_update' | 'metric_update' | 'alert' | 'notification' | 'task_update';
    payload: unknown;
}

// Component prop types
export interface BaseComponentProps {
    className?: string;
    children?: React.ReactNode;
    'data-testid'?: string;
}

export interface ButtonProps extends BaseComponentProps {
    variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger' | 'success';
    size?: 'sm' | 'md' | 'lg' | 'xl';
    disabled?: boolean;
    loading?: boolean;
    onClick?: () => void;
    type?: 'button' | 'submit' | 'reset';
    fullWidth?: boolean;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
}

export interface InputProps extends BaseComponentProps {
    type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'url' | 'search';
    value?: string;
    onChange?: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    error?: string;
    label?: string;
    helperText?: string;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
}

export interface SelectProps extends BaseComponentProps {
    options: SelectOption[];
    value?: string | number;
    onChange?: (value: string | number) => void;
    placeholder?: string;
    disabled?: boolean;
    error?: string;
    label?: string;
    helperText?: string;
    multiple?: boolean;
    searchable?: boolean;
}

export interface ModalProps extends BaseComponentProps {
    isOpen: boolean;
    onClose: () => void;
    title?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
    closeOnOverlayClick?: boolean;
    closeOnEscape?: boolean;
}

export interface TableColumn<T> {
    key: string;
    header: string;
    accessor?: (row: T) => React.ReactNode;
    cell?: (row: T) => React.ReactNode;
    sortable?: boolean;
    filterable?: boolean;
    width?: string;
    align?: 'left' | 'center' | 'right';
}

export interface TableProps<T> extends BaseComponentProps {
    columns: TableColumn<T>[];
    data: T[];
    keyExtractor: (row: T) => string;
    sortable?: boolean;
    filterable?: boolean;
    pagination?: boolean;
    pageSize?: number;
    onRowClick?: (row: T) => void;
    selection?: boolean;
    selectedRows?: string[];
    onSelectionChange?: (rows: string[]) => void;
    loading?: boolean;
    emptyMessage?: string;
}
