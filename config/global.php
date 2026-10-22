<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global Scale Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for multi-region, CDN, analytics, enterprise SSO,
    | data governance, disaster recovery, and cost optimization.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Multi-Region Configuration
    |--------------------------------------------------------------------------
    */
    'multi_region' => [
        'enabled' => env('MULTI_REGION_ENABLED', true),
        'default_region' => env('MULTI_REGION_DEFAULT', 'us-east-1'),
        'regions' => [
            'us-east-1' => [
                'name' => 'US East (N. Virginia)',
                'endpoint' => env('REGION_US_EAST_1_ENDPOINT', 'https://us-east-1.api.webixray.com'),
                'database' => env('REGION_US_EAST_1_DB', 'mysql_us_east'),
                'storage' => env('REGION_US_EAST_1_STORAGE', 's3_us_east'),
                'cdn' => env('REGION_US_EAST_1_CDN', 'cloudfront_us_east'),
                'active' => env('REGION_US_EAST_1_ACTIVE', true),
                'priority' => 1,
                'data_residency' => ['US', 'CA'],
                'compliance' => ['SOC2', 'HIPAA'],
            ],
            'us-west-2' => [
                'name' => 'US West (Oregon)',
                'endpoint' => env('REGION_US_WEST_2_ENDPOINT', 'https://us-west-2.api.webixray.com'),
                'database' => env('REGION_US_WEST_2_DB', 'mysql_us_west'),
                'storage' => env('REGION_US_WEST_2_STORAGE', 's3_us_west'),
                'cdn' => env('REGION_US_WEST_2_CDN', 'cloudfront_us_west'),
                'active' => env('REGION_US_WEST_2_ACTIVE', true),
                'priority' => 2,
                'data_residency' => ['US'],
                'compliance' => ['SOC2'],
            ],
            'eu-west-1' => [
                'name' => 'EU West (Ireland)',
                'endpoint' => env('REGION_EU_WEST_1_ENDPOINT', 'https://eu-west-1.api.webixray.com'),
                'database' => env('REGION_EU_WEST_1_DB', 'mysql_eu_west'),
                'storage' => env('REGION_EU_WEST_1_STORAGE', 's3_eu_west'),
                'cdn' => env('REGION_EU_WEST_1_CDN', 'cloudfront_eu_west'),
                'active' => env('REGION_EU_WEST_1_ACTIVE', true),
                'priority' => 3,
                'data_residency' => ['EU', 'UK'],
                'compliance' => ['GDPR', 'SOC2'],
            ],
            'eu-central-1' => [
                'name' => 'EU Central (Frankfurt)',
                'endpoint' => env('REGION_EU_CENTRAL_1_ENDPOINT', 'https://eu-central-1.api.webixray.com'),
                'database' => env('REGION_EU_CENTRAL_1_DB', 'mysql_eu_central'),
                'storage' => env('REGION_EU_CENTRAL_1_STORAGE', 's3_eu_central'),
                'cdn' => env('REGION_EU_CENTRAL_1_CDN', 'cloudfront_eu_central'),
                'active' => env('REGION_EU_CENTRAL_1_ACTIVE', true),
                'priority' => 4,
                'data_residency' => ['EU', 'DE'],
                'compliance' => ['GDPR', 'SOC2'],
            ],
            'ap-southeast-1' => [
                'name' => 'Asia Pacific (Singapore)',
                'endpoint' => env('REGION_AP_SOUTHEAST_1_ENDPOINT', 'https://ap-southeast-1.api.webixray.com'),
                'database' => env('REGION_AP_SOUTHEAST_1_DB', 'mysql_ap_southeast'),
                'storage' => env('REGION_AP_SOUTHEAST_1_STORAGE', 's3_ap_southeast'),
                'cdn' => env('REGION_AP_SOUTHEAST_1_CDN', 'cloudfront_ap_southeast'),
                'active' => env('REGION_AP_SOUTHEAST_1_ACTIVE', true),
                'priority' => 5,
                'data_residency' => ['SG', 'AU', 'NZ'],
                'compliance' => ['SOC2'],
            ],
            'ap-northeast-1' => [
                'name' => 'Asia Pacific (Tokyo)',
                'endpoint' => env('REGION_AP_NORTHEAST_1_ENDPOINT', 'https://ap-northeast-1.api.webixray.com'),
                'database' => env('REGION_AP_NORTHEAST_1_DB', 'mysql_ap_northeast'),
                'storage' => env('REGION_AP_NORTHEAST_1_STORAGE', 's3_ap_northeast'),
                'cdn' => env('REGION_AP_NORTHEAST_1_CDN', 'cloudfront_ap_northeast'),
                'active' => env('REGION_AP_NORTHEAST_1_ACTIVE', true),
                'priority' => 6,
                'data_residency' => ['JP'],
                'compliance' => ['SOC2', 'APPI'],
            ],
        ],
        'failover' => [
            'enabled' => env('MULTI_REGION_FAILOVER_ENABLED', true),
            'health_check_interval' => env('MULTI_REGION_HEALTH_INTERVAL', 30),
            'failure_threshold' => env('MULTI_REGION_FAILURE_THRESHOLD', 3),
            'recovery_threshold' => env('MULTI_REGION_RECOVERY_THRESHOLD', 2),
            'auto_failover' => env('MULTI_REGION_AUTO_FAILOVER', true),
            'notify_on_failover' => env('MULTI_REGION_NOTIFY_FAILOVER', true),
        ],
        'routing' => [
            'strategy' => env('MULTI_REGION_ROUTING_STRATEGY', 'latency'), // latency, geo, weighted, failover
            'geo_mapping' => [],
            'weighted_routing' => [],
            'sticky_sessions' => env('MULTI_REGION_STICKY_SESSIONS', true),
            'session_ttl' => env('MULTI_REGION_SESSION_TTL', 3600),
        ],
        'replication' => [
            'mode' => env('MULTI_REGION_REPLICATION_MODE', 'async'), // sync, async, semi-sync
            'lag_threshold_ms' => env('MULTI_REGION_LAG_THRESHOLD', 1000),
            'conflict_resolution' => env('MULTI_REGION_CONFLICT_RESOLUTION', 'last_write_wins'),
            'sync_interval' => env('MULTI_REGION_SYNC_INTERVAL', 60),
        ],
        'disaster_recovery' => [
            'rpo_seconds' => env('MULTI_REGION_RPO', 300),
            'rto_seconds' => env('MULTI_REGION_RTO', 900),
            'backup_regions' => ['us-west-2', 'eu-central-1'],
            'automated_failover' => env('MULTI_REGION_AUTO_FAILOVER', true),
            'drill_schedule' => env('MULTI_REGION_DRILL_SCHEDULE', 'monthly'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    */
    'cdn' => [
        'enabled' => env('CDN_ENABLED', true),
        'default_provider' => env('CDN_DEFAULT_PROVIDER', 'cloudflare'),
        'providers' => [
            'cloudflare' => [
                'enabled' => env('CDN_CLOUDFLARE_ENABLED', true),
                'api_token' => env('CDN_CLOUDFLARE_API_TOKEN'),
                'account_id' => env('CDN_CLOUDFLARE_ACCOUNT_ID'),
                'zone_id' => env('CDN_CLOUDFLARE_ZONE_ID'),
                'email' => env('CDN_CLOUDFLARE_EMAIL'),
                'api_key' => env('CDN_CLOUDFLARE_API_KEY'),
                'base_url' => 'https://api.cloudflare.com/client/v4',
            ],
            'cloudfront' => [
                'enabled' => env('CDN_CLOUDFRONT_ENABLED', true),
                'access_key' => env('CDN_CLOUDFRONT_ACCESS_KEY'),
                'secret_key' => env('CDN_CLOUDFRONT_SECRET_KEY'),
                'region' => env('CDN_CLOUDFRONT_REGION', 'us-east-1'),
                'distribution_id' => env('CDN_CLOUDFRONT_DISTRIBUTION_ID'),
            ],
            'fastly' => [
                'enabled' => env('CDN_FASTLY_ENABLED', false),
                'api_token' => env('CDN_FASTLY_API_TOKEN'),
                'service_id' => env('CDN_FASTLY_SERVICE_ID'),
            ],
            'akamai' => [
                'enabled' => env('CDN_AKAMAI_ENABLED', false),
                'client_token' => env('CDN_AKAMAI_CLIENT_TOKEN'),
                'client_secret' => env('CDN_AKAMAI_CLIENT_SECRET'),
                'access_token' => env('CDN_AKAMAI_ACCESS_TOKEN'),
            ],
        ],
        'cache_rules' => [
            'static_assets' => [
                'pattern' => '/assets/*, /images/*, /fonts/*, /css/*, /js/*',
                'ttl' => 31536000,
                'browser_ttl' => 31536000,
                'stale_while_revalidate' => 86400,
            ],
            'api_responses' => [
                'pattern' => '/api/v1/reports/*, /api/v1/analytics/*',
                'ttl' => 300,
                'browser_ttl' => 60,
                'stale_while_revalidate' => 60,
            ],
            'user_content' => [
                'pattern' => '/uploads/*, /media/*',
                'ttl' => 86400,
                'browser_ttl' => 3600,
                'stale_while_revalidate' => 3600,
            ],
            'html_pages' => [
                'pattern' => '/*, /dashboard/*, /app/*',
                'ttl' => 0,
                'browser_ttl' => 0,
                'must_revalidate' => true,
            ],
        ],
        'waf_rules' => [
            'enabled' => env('CDN_WAF_ENABLED', true),
            'managed_rulesets' => ['owasp', 'cloudflare'],
            'custom_rules' => [],
            'rate_limiting' => [
                'enabled' => env('CDN_RATE_LIMIT_ENABLED', true),
                'requests_per_minute' => env('CDN_RATE_LIMIT_RPM', 1000),
                'action' => env('CDN_RATE_LIMIT_ACTION', 'challenge'),
            ],
            'bot_management' => [
                'enabled' => env('CDN_BOT_MANAGEMENT_ENABLED', true),
                'action' => env('CDN_BOT_ACTION', 'challenge'),
            ],
        ],
        'ddos_protection' => [
            'enabled' => env('CDN_DDOS_ENABLED', true),
            'network_layer' => env('CDN_DDOS_NETWORK', true),
            'application_layer' => env('CDN_DDOS_APPLICATION', true),
            'mitigation_threshold' => env('CDN_DDOS_THRESHOLD', 1000),
        ],
        'edge_functions' => [
            'enabled' => env('CDN_EDGE_FUNCTIONS_ENABLED', true),
            'runtime' => env('CDN_EDGE_RUNTIME', 'v8'),
            'max_execution_time' => env('CDN_EDGE_MAX_TIME', 50),
            'max_memory' => env('CDN_EDGE_MAX_MEMORY', 128),
        ],
        'image_optimization' => [
            'enabled' => env('CDN_IMAGE_OPT_ENABLED', true),
            'formats' => ['webp', 'avif', 'jpeg', 'png'],
            'quality' => env('CDN_IMAGE_QUALITY', 85),
            'widths' => [320, 640, 768, 1024, 1280, 1920],
            'fit' => 'cover',
        ],
        'compression' => [
            'brotli' => env('CDN_BROTLI', true),
            'gzip' => env('CDN_GZIP', true),
            'zstd' => env('CDN_ZSTD', true),
            'min_size' => env('CDN_MIN_COMPRESS_SIZE', 1024),
        ],
        'analytics' => [
            'enabled' => env('CDN_ANALYTICS_ENABLED', true),
            'sample_rate' => env('CDN_ANALYTICS_SAMPLE', 0.1),
            'retention_days' => env('CDN_ANALYTICS_RETENTION', 90),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Analytics Configuration
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'enabled' => env('ANALYTICS_ENABLED', true),
        'data_warehouse' => [
            'enabled' => env('ANALYTICS_WAREHOUSE_ENABLED', true),
            'connection' => env('ANALYTICS_WAREHOUSE_CONNECTION', 'analytics'),
            'sync_interval' => env('ANALYTICS_SYNC_INTERVAL', 300),
            'batch_size' => env('ANALYTICS_BATCH_SIZE', 10000),
        ],
        'real_time' => [
            'enabled' => env('ANALYTICS_REALTIME_ENABLED', true),
            'buffer_size' => env('ANALYTICS_BUFFER_SIZE', 1000),
            'flush_interval' => env('ANALYTICS_FLUSH_INTERVAL', 10),
        ],
        'aggregations' => [
            'hourly' => ['retention_days' => 90],
            'daily' => ['retention_days' => 365],
            'weekly' => ['retention_days' => 1095],
            'monthly' => ['retention_days' => 3650],
        ],
        'dashboards' => [
            'cache_ttl' => env('ANALYTICS_DASHBOARD_CACHE_TTL', 60),
            'max_widgets' => env('ANALYTICS_MAX_WIDGETS', 50),
        ],
        'alerts' => [
            'enabled' => env('ANALYTICS_ALERTS_ENABLED', true),
            'channels' => explode(',', env('ANALYTICS_ALERT_CHANNELS', 'email,slack,webhook,pagerduty')),
            'evaluation_interval' => env('ANALYTICS_ALERT_INTERVAL', 60),
        ],
        'ml_insights' => [
            'enabled' => env('ANALYTICS_ML_ENABLED', true),
            'anomaly_detection' => env('ANALYTICS_ANOMALY_DETECTION', true),
            'forecasting' => env('ANALYTICS_FORECASTING', true),
            'segmentation' => env('ANALYTICS_SEGMENTATION', true),
            'recommendations' => env('ANALYTICS_RECOMMENDATIONS', true),
            'model_refresh_interval' => env('ANALYTICS_MODEL_REFRESH', 86400),
        ],
        'export' => [
            'formats' => ['csv', 'json', 'xlsx', 'pdf'],
            'max_rows' => env('ANALYTICS_EXPORT_MAX_ROWS', 1000000),
            'async_threshold' => env('ANALYTICS_ASYNC_THRESHOLD', 10000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Enterprise SSO Configuration
    |--------------------------------------------------------------------------
    */
    'enterprise_sso' => [
        'enabled' => env('ENTERPRISE_SSO_ENABLED', true),
        'providers' => [
            'ldap' => [
                'enabled' => env('ENTERPRISE_SSO_LDAP_ENABLED', false),
                'host' => env('ENTERPRISE_SSO_LDAP_HOST'),
                'port' => env('ENTERPRISE_SSO_LDAP_PORT', 389),
                'use_ssl' => env('ENTERPRISE_SSO_LDAP_SSL', false),
                'use_tls' => env('ENTERPRISE_SSO_LDAP_TLS', true),
                'base_dn' => env('ENTERPRISE_SSO_LDAP_BASE_DN'),
                'bind_dn' => env('ENTERPRISE_SSO_LDAP_BIND_DN'),
                'bind_password' => env('ENTERPRISE_SSO_LDAP_BIND_PASSWORD'),
                'username_attribute' => env('ENTERPRISE_SSO_LDAP_USER_ATTR', 'uid'),
                'email_attribute' => env('ENTERPRISE_SSO_LDAP_EMAIL_ATTR', 'mail'),
                'first_name_attribute' => env('ENTERPRISE_SSO_LDAP_FIRST_NAME_ATTR', 'givenName'),
                'last_name_attribute' => env('ENTERPRISE_SSO_LDAP_LAST_NAME_ATTR', 'sn'),
                'display_name_attribute' => env('ENTERPRISE_SSO_LDAP_DISPLAY_NAME_ATTR', 'displayName'),
                'group_attribute' => env('ENTERPRISE_SSO_LDAP_GROUP_ATTR', 'memberOf'),
                'group_base_dn' => env('ENTERPRISE_SSO_LDAP_GROUP_BASE_DN'),
                'group_filter' => env('ENTERPRISE_SSO_LDAP_GROUP_FILTER', '(objectClass=group)'),
                'user_filter' => env('ENTERPRISE_SSO_LDAP_USER_FILTER', '(objectClass=person)'),
                'referrals' => env('ENTERPRISE_SSO_LDAP_REFERRALS', false),
                'timeout' => env('ENTERPRISE_SSO_LDAP_TIMEOUT', 10),
                'version' => env('ENTERPRISE_SSO_LDAP_VERSION', 3),
            ],
            'active_directory' => [
                'enabled' => env('ENTERPRISE_SSO_AD_ENABLED', false),
                'host' => env('ENTERPRISE_SSO_AD_HOST'),
                'port' => env('ENTERPRISE_SSO_AD_PORT', 389),
                'use_ssl' => env('ENTERPRISE_SSO_AD_SSL', false),
                'use_tls' => env('ENTERPRISE_SSO_AD_TLS', true),
                'domain' => env('ENTERPRISE_SSO_AD_DOMAIN'),
                'base_dn' => env('ENTERPRISE_SSO_AD_BASE_DN'),
                'bind_dn' => env('ENTERPRISE_SSO_AD_BIND_DN'),
                'bind_password' => env('ENTERPRISE_SSO_AD_BIND_PASSWORD'),
                'username_attribute' => env('ENTERPRISE_SSO_AD_USER_ATTR', 'sAMAccountName'),
                'email_attribute' => env('ENTERPRISE_SSO_AD_EMAIL_ATTR', 'userPrincipalName'),
                'first_name_attribute' => env('ENTERPRISE_SSO_AD_FIRST_NAME_ATTR', 'givenName'),
                'last_name_attribute' => env('ENTERPRISE_SSO_AD_LAST_NAME_ATTR', 'sn'),
                'display_name_attribute' => env('ENTERPRISE_SSO_AD_DISPLAY_NAME_ATTR', 'displayName'),
                'group_attribute' => env('ENTERPRISE_SSO_AD_GROUP_ATTR', 'memberOf'),
                'group_base_dn' => env('ENTERPRISE_SSO_AD_GROUP_BASE_DN'),
                'group_filter' => env('ENTERPRISE_SSO_AD_GROUP_FILTER', '(objectClass=group)'),
                'user_filter' => env('ENTERPRISE_SSO_AD_USER_FILTER', '(objectClass=user)'),
                'referrals' => env('ENTERPRISE_SSO_AD_REFERRALS', true),
                'timeout' => env('ENTERPRISE_SSO_AD_TIMEOUT', 10),
                'version' => env('ENTERPRISE_SSO_AD_VERSION', 3),
                'follow_referrals' => env('ENTERPRISE_SSO_AD_FOLLOW_REFERRALS', true),
            ],
            'kerberos' => [
                'enabled' => env('ENTERPRISE_SSO_KERBEROS_ENABLED', false),
                'realm' => env('ENTERPRISE_SSO_KERBEROS_REALM'),
                'kdc' => env('ENTERPRISE_SSO_KERBEROS_KDC'),
                'admin_server' => env('ENTERPRISE_SSO_KERBEROS_ADMIN_SERVER'),
                'keytab' => env('ENTERPRISE_SSO_KERBEROS_KEYTAB'),
                'service_principal' => env('ENTERPRISE_SSO_KERBEROS_SPN', 'HTTP/webixray.com@EXAMPLE.COM'),
                'spn_ego' => env('ENTERPRISE_SSO_KERBEROS_SPN_EGO', true),
                'delegate' => env('ENTERPRISE_SSO_KERBEROS_DELEGATE', true),
            ],
            'saml' => [
                'enabled' => env('ENTERPRISE_SSO_SAML_ENABLED', false),
                'sp_entity_id' => env('ENTERPRISE_SSO_SAML_SP_ENTITY_ID'),
                'sp_acs_url' => env('ENTERPRISE_SSO_SAML_SP_ACS_URL'),
                'sp_sls_url' => env('ENTERPRISE_SSO_SAML_SP_SLS_URL'),
                'idp_entity_id' => env('ENTERPRISE_SSO_SAML_IDP_ENTITY_ID'),
                'idp_sso_url' => env('ENTERPRISE_SSO_SAML_IDP_SSO_URL'),
                'idp_slo_url' => env('ENTERPRISE_SSO_SAML_IDP_SLO_URL'),
                'idp_x509_cert' => env('ENTERPRISE_SSO_SAML_IDP_X509_CERT'),
                'sp_x509_cert' => env('ENTERPRISE_SSO_SAML_SP_X509_CERT'),
                'sp_private_key' => env('ENTERPRISE_SSO_SAML_SP_PRIVATE_KEY'),
                'security' => [
                    'want_assertions_signed' => true,
                    'want_auth_requests_signed' => true,
                    'sign_metadata' => true,
                ],
            ],
        ],
        'auto_provision' => env('ENTERPRISE_SSO_AUTO_PROVISION', true),
        'default_role' => env('ENTERPRISE_SSO_DEFAULT_ROLE', 'user'),
        'sync_groups' => env('ENTERPRISE_SSO_SYNC_GROUPS', true),
        'sync_interval' => env('ENTERPRISE_SSO_SYNC_INTERVAL', 3600),
        'just_in_time_provisioning' => env('ENTERPRISE_SSO_JIT', true),
        'fallback_to_local' => env('ENTERPRISE_SSO_FALLBACK_LOCAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Governance Configuration
    |--------------------------------------------------------------------------
    */
    'data_governance' => [
        'enabled' => env('DATA_GOVERNANCE_ENABLED', true),
        'data_catalog' => [
            'enabled' => env('DATA_CATALOG_ENABLED', true),
            'auto_discovery' => env('DATA_CATALOG_AUTO_DISCOVERY', true),
            'scan_interval' => env('DATA_CATALOG_SCAN_INTERVAL', 3600),
            'classification_enabled' => env('DATA_CATALOG_CLASSIFICATION', true),
            'lineage_enabled' => env('DATA_CATALOG_LINEAGE', true),
        ],
        'privacy' => [
            'enabled' => env('DATA_PRIVACY_ENABLED', true),
            'gdpr' => env('DATA_PRIVACY_GDPR', true),
            'ccpa' => env('DATA_PRIVACY_CCPA', true),
            'hipaa' => env('DATA_PRIVACY_HIPAA', false),
            'pii_detection' => env('DATA_PRIVACY_PII_DETECTION', true),
            'data_subject_requests' => env('DATA_PRIVACY_DSR', true),
            'consent_management' => env('DATA_PRIVACY_CONSENT', true),
            'retention_policies' => env('DATA_PRIVACY_RETENTION', true),
            'right_to_be_forgotten' => env('DATA_PRIVACY_RTBF', true),
            'data_portability' => env('DATA_PRIVACY_PORTABILITY', true),
        ],
        'data_quality' => [
            'enabled' => env('DATA_QUALITY_ENABLED', true),
            'profiling' => env('DATA_QUALITY_PROFILING', true),
            'validation_rules' => env('DATA_QUALITY_VALIDATION', true),
            'anomaly_detection' => env('DATA_QUALITY_ANOMALY', true),
            'completeness_threshold' => env('DATA_QUALITY_COMPLETENESS', 0.95),
            'uniqueness_threshold' => env('DATA_QUALITY_UNIQUENESS', 0.99),
        ],
        'access_control' => [
            'enabled' => env('DATA_ACCESS_CONTROL_ENABLED', true),
            'data_masking' => env('DATA_MASKING_ENABLED', true),
            'row_level_security' => env('DATA_RLS_ENABLED', true),
            'column_level_security' => env('DATA_CLS_ENABLED', true),
            'dynamic_masking' => env('DATA_DYNAMIC_MASKING', true),
        ],
        'audit' => [
            'enabled' => env('DATA_AUDIT_ENABLED', true),
            'data_access_logging' => env('DATA_ACCESS_LOGGING', true),
            'data_modification_logging' => env('DATA_MOD_LOGGING', true),
            'schema_change_logging' => env('DATA_SCHEMA_LOGGING', true),
            'retention_days' => env('DATA_AUDIT_RETENTION', 2555),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Disaster Recovery Configuration
    |--------------------------------------------------------------------------
    */
    'disaster_recovery' => [
        'enabled' => env('DR_ENABLED', true),
        'rpo_seconds' => env('DR_RPO', 300),
        'rto_seconds' => env('DR_RTO', 900),
        'backup' => [
            'enabled' => env('DR_BACKUP_ENABLED', true),
            'schedule' => env('DR_BACKUP_SCHEDULE', '*/5 * * * *'),
            'retention' => [
                'hourly' => env('DR_RETENTION_HOURLY', 24),
                'daily' => env('DR_RETENTION_DAILY', 30),
                'weekly' => env('DR_RETENTION_WEEKLY', 12),
                'monthly' => env('DR_RETENTION_MONTHLY', 12),
            ],
            'compression' => env('DR_BACKUP_COMPRESSION', true),
            'encryption' => env('DR_BACKUP_ENCRYPTION', true),
            'encryption_key' => env('DR_BACKUP_ENCRYPTION_KEY'),
            'storage' => [
                'primary' => env('DR_STORAGE_PRIMARY', 's3_primary'),
                'secondary' => env('DR_STORAGE_SECONDARY', 's3_secondary'),
                'regions' => ['us-east-1', 'eu-west-1', 'ap-southeast-1'],
            ],
            'verify' => env('DR_BACKUP_VERIFY', true),
            'verify_schedule' => env('DR_VERIFY_SCHEDULE', '0 2 * * *'),
        ],
        'replication' => [
            'enabled' => env('DR_REPLICATION_ENABLED', true),
            'mode' => env('DR_REPLICATION_MODE', 'async'),
            'lag_threshold_ms' => env('DR_REPLICATION_LAG', 5000),
            'auto_failover' => env('DR_REPLICATION_AUTO_FAILOVER', true),
            'failover_timeout' => env('DR_FAILOVER_TIMEOUT', 300),
        ],
        'failover' => [
            'enabled' => env('DR_FAILOVER_ENABLED', true),
            'health_check_interval' => env('DR_HEALTH_INTERVAL', 30),
            'failure_threshold' => env('DR_FAILURE_THRESHOLD', 3),
            'recovery_threshold' => env('DR_RECOVERY_THRESHOLD', 2),
            'auto_failover' => env('DR_AUTO_FAILOVER', true),
            'notify_channels' => explode(',', env('DR_NOTIFY_CHANNELS', 'email,slack,pagerduty')),
            'runbook_url' => env('DR_RUNBOOK_URL', ''),
        ],
        'drill' => [
            'enabled' => env('DR_DRILL_ENABLED', true),
            'schedule' => env('DR_DRILL_SCHEDULE', '0 0 1 * *'),
            'scope' => env('DR_DRILL_SCOPE', 'full'),
            'notify_before_hours' => env('DR_DRILL_NOTIFY_HOURS', 24),
            'auto_approve' => env('DR_DRILL_AUTO_APPROVE', false),
        ],
        'restore' => [
            'point_in_time' => env('DR_RESTORE_PIT', true),
            'table_level' => env('DR_RESTORE_TABLE_LEVEL', true),
            'parallel_streams' => env('DR_RESTORE_STREAMS', 4),
            'throttle_mbps' => env('DR_RESTORE_THROTTLE', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Optimization Configuration
    |--------------------------------------------------------------------------
    */
    'cost_optimization' => [
        'enabled' => env('COST_OPT_ENABLED', true),
        'providers' => [
            'aws' => [
                'enabled' => env('COST_AWS_ENABLED', true),
                'access_key' => env('COST_AWS_ACCESS_KEY'),
                'secret_key' => env('COST_AWS_SECRET_KEY'),
                'region' => env('COST_AWS_REGION', 'us-east-1'),
                'account_id' => env('COST_AWS_ACCOUNT_ID'),
                'cur_bucket' => env('COST_AWS_CUR_BUCKET'),
                'cur_prefix' => env('COST_AWS_CUR_PREFIX'),
            ],
            'gcp' => [
                'enabled' => env('COST_GCP_ENABLED', false),
                'project_id' => env('COST_GCP_PROJECT_ID'),
                'credentials' => env('COST_GCP_CREDENTIALS'),
                'billing_export_dataset' => env('COST_GCP_BILLING_DATASET'),
            ],
            'azure' => [
                'enabled' => env('COST_AZURE_ENABLED', false),
                'subscription_id' => env('COST_AZURE_SUBSCRIPTION_ID'),
                'tenant_id' => env('COST_AZURE_TENANT_ID'),
                'client_id' => env('COST_AZURE_CLIENT_ID'),
                'client_secret' => env('COST_AZURE_CLIENT_SECRET'),
            ],
        ],
        'analysis' => [
            'enabled' => env('COST_ANALYSIS_ENABLED', true),
            'schedule' => env('COST_ANALYSIS_SCHEDULE', '0 6 * * *'),
            'lookback_days' => env('COST_LOOKBACK_DAYS', 30),
            'anomaly_threshold' => env('COST_ANOMALY_THRESHOLD', 0.2),
            'idle_threshold_days' => env('COST_IDLE_THRESHOLD_DAYS', 7),
            'rightsizing_enabled' => env('COST_RIGHTSIZING_ENABLED', true),
            'reserved_instances_enabled' => env('COST_RI_ENABLED', true),
            'savings_plans_enabled' => env('COST_SP_ENABLED', true),
            'spot_instances_enabled' => env('COST_SPOT_ENABLED', true),
        ],
        'budgets' => [
            'enabled' => env('COST_BUDGETS_ENABLED', true),
            'alert_thresholds' => [0.5, 0.75, 0.9, 1.0],
            'alert_channels' => explode(',', env('COST_BUDGET_ALERT_CHANNELS', 'email,slack')),
        ],
        'recommendations' => [
            'min_savings' => env('COST_MIN_SAVINGS', 10),
            'confidence_threshold' => env('COST_CONFIDENCE_THRESHOLD', 0.7),
            'auto_apply' => env('COST_AUTO_APPLY', false),
            'require_approval' => env('COST_REQUIRE_APPROVAL', true),
        ],
        'reporting' => [
            'enabled' => env('COST_REPORTING_ENABLED', true),
            'schedule' => env('COST_REPORT_SCHEDULE', '0 0 1 * *'),
            'formats' => ['pdf', 'csv', 'json'],
            'recipients' => explode(',', env('COST_REPORT_RECIPIENTS', '')),
        ],
    ],
];
