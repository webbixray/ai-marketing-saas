<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    //
    protected $fillable = [
        'stripe_product_id',
        'stripe_price_id',
        'name',
        'description',
        'active',
        'sort_order',
    ];

    // Optionally, we can disable timestamps if we don't need them, but we kept them in the migration.
    public $timestamps = true;
}