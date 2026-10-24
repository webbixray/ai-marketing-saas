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
        'amount',
        'currency',
        'interval',
        'interval_count',
    ];

    protected $casts = [
        'active' => 'boolean',
        'amount' => 'integer',
        'interval_count' => 'integer',
    ];

    // Optionally, we can disable timestamps if we don't need them, but we kept them in the migration.
    public $timestamps = true;

    /**
     * Monthly recurring amount in major currency units (e.g. dollars),
     * normalized from the plan's billing interval.
     *
     * This is the local source of truth used by ReportingController for
     * MRR: subscriptions.stripe_price holds a Stripe price ID string, not
     * a numeric amount.
     */
    public function monthlyAmount(): float
    {
        $amount = (int) $this->amount;

        if ($amount <= 0) {
            return 0.0;
        }

        $count = max(1, (int) $this->interval_count);

        $monthlyCents = match ($this->interval ?: 'month') {
            'year' => $amount / 12 / $count,
            'week' => $amount * 52 / 12 / $count,
            'day' => $amount * 365 / 12 / $count,
            default => $amount / $count, // 'month'
        };

        return round($monthlyCents / 100, 2);
    }
}
