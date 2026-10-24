<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;

class PlanController extends Controller
{
    public function __construct()
    {
        // Set Stripe secret key from environment
        Stripe::setApiKey(getenv('STRIPE_SECRET'));
    }

    /**
     * Display a listing of the plans.
     */
    public function index(Request $request)
    {
        $plans = Plan::orderBy('sort_order')->get();
        return view('admin.plans.index', compact('plans'));
    }

    /**
     * Show the form for creating a new plan.
     */
    public function create()
    {
        return view('admin.plans.create');
    }

    /**
     * Store a newly created plan in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|integer|min:0', // amount in cents
            'currency' => 'required|string|size:3', // e.g., usd
            'interval' => 'required|in:day,week,month,year', // for recurring price
            'interval_count' => 'required|integer|min:1',
        ]);

        try {
            // Create a Product in Stripe
            $product = Product::create([
                'name' => $request->name,
                'description' => $request->description,
                'active' => true,
            ]);

            // Create a Price in Stripe
            $price = Price::create([
                'product' => $product->id,
                'unit_amount' => $request->amount,
                'currency' => $request->currency,
                'recurring' => [
                    'interval' => $request->interval,
                    'interval_count' => $request->interval_count,
                ],
                'active' => true,
            ]);

            // Save to local plan model (amount kept locally so MRR reporting
            // has a source of truth without calling the Stripe API)
            $plan = Plan::create([
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
                'name' => $request->name,
                'description' => $request->description,
                'active' => true,
                'sort_order' => 0, // we'll manage order later
                'amount' => $request->amount,
                'currency' => strtolower($request->currency),
                'interval' => $request->interval,
                'interval_count' => $request->interval_count,
            ]);

            Log::info('Plan created via Stripe', [
                'plan_id' => $plan->id,
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
            ]);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan created successfully.');
        } catch (\Exception $e) {
            Log::error('Error creating plan: '.$e->getMessage());
            return back()->withInput()
                ->with('error', 'Failed to create plan: '.$e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified plan.
     */
    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    /**
     * Update the specified plan in storage.
     */
    public function update(Request $request, Plan $plan)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
            'interval' => 'required|in:day,week,month,year',
            'interval_count' => 'required|integer|min:1',
            'active' => 'sometimes|boolean',
        ]);

        try {
            // Retrieve the Stripe product and price
            $product = Product::retrieve($plan->stripe_product_id);
            $price = Price::retrieve($plan->stripe_price_id);

            // Update the Product
            $product->name = $request->name;
            $product->description = $request->description;
            $product->active = $request->boolean('active');
            $product->save();

            // Update the Price
            $price->unit_amount = $request->amount;
            $price->currency = $request->currency;
            $price->recurring = [
                'interval' => $request->interval,
                'interval_count' => $request->interval_count,
            ];
            $price->active = $request->boolean('active');
            $price->save();

            // Update the local plan model
            // Note: we don't update the stripe IDs because they are immutable
            $plan->update([
                'name' => $request->name,
                'description' => $request->description,
                'active' => $request->boolean('active'),
                'amount' => $request->amount,
                'currency' => strtolower($request->currency),
                'interval' => $request->interval,
                'interval_count' => $request->interval_count,
            ]);

            Log::info('Plan updated via Stripe', [
                'plan_id' => $plan->id,
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
            ]);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating plan: '.$e->getMessage());
            return back()->withInput()
                ->with('error', 'Failed to update plan: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified plan from storage.
     */
    public function destroy(Plan $plan)
    {
        try {
            // Retrieve the Stripe product and price
            $product = Product::retrieve($plan->stripe_product_id);
            $price = Price::retrieve($plan->stripe_price_id);

            // Deactivate the Product and Price in Stripe (cannot delete)
            $product->active = false;
            $product->save();

            $price->active = false;
            $price->save();

            // Optionally, we can delete the local plan record or set active to false.
            // We'll set active to false and keep the record for history.
            $plan->update(['active' => false]);

            Log::info('Plan deactivated via Stripe', [
                'plan_id' => $plan->id,
                'stripe_product_id' => $plan->stripe_product_id,
                'stripe_price_id' => $plan->stripe_price_id,
            ]);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan deactivated successfully.');
        } catch (\Exception $e) {
            Log::error('Error deactivating plan: '.$e->getMessage());
            return back()->withError('Failed to deactivate plan: '.$e->getMessage());
        }
    }
}
