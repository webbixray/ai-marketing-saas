<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant SCIM provisioning configuration.
 *
 * The bearer_token is the shared secret presented by the customer's
 * identity provider on every SCIM request (Authorization: Bearer <token>).
 */
class TenantScimConfig extends Model
{
    protected $table = 'tenant_scim_config';

    protected $fillable = [
        'tenant_id',
        'enabled',
        'bearer_token',
        'schemas',
        'attributes',
        'filter_attributes',
        'page_size',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'schemas' => 'array',
        'attributes' => 'array',
        'filter_attributes' => 'array',
        'page_size' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
