<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SubscriptionCreated;
use App\Notifications\SubscriptionCancelled;
use App\Notifications\InvoicePaid;
use Exception;

/**
 * @group Billing & Subscriptions
 *
 * APIs for managing billing, subscriptions, and invoices via Stripe.
 */
class BillingController extends Controller
{
    /**
     * Create a new subscription for the authenticated user.
     */
    public function subscribe(Request $request)
    {
        $user = $request->user();

        // Validate plan
        $validator = Validator::make($request->all(), [
            'plan' => 'required|string|in:free,basic,pro',
            'payment_method' => 'required|string', // Stripe payment method ID
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $plan = $request->input('plan');
        $paymentMethod = $request->input('payment_method');

        try {
            // Attach payment method to customer
            $user->addPaymentMethod($paymentMethod);

            // Create subscription
            $subscription = $user->newSubscription('main', config("billing.plans.{$plan}.stripe_price"))
                                ->create($paymentMethod);

            // Send notification
            Notification::send($user, new SubscriptionCreated($subscription));

            Log::info('User subscribed to plan', [
                'user_id' => $user->id,
                'plan' => $plan,
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Subscription created successfully',
                'subscription' => $subscription,
            ]);
        } catch (Exception $e) {
            Log::error('Subscription creation failed', [
                'user_id' => $user->id,
                'plan' => $plan,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Subscription creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the current subscription for the authenticated user.
     */
    public function subscription(Request $request)
    {
        $user = $request->user();

        $subscription = $user->subscription('main');

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription',
                'subscription' => null,
            ], 404);
        }

        return response()->json([
            'subscription' => $subscription,
        ]);
    }

    /**
     * Swap subscription to a new plan.
     */
    public function swap(Request $request, $plan)
    {
        $user = $request->user();

        // Validate plan
        if (!array_key_exists($plan, config('billing.plans'))) {
            return response()->json([
                'message' => 'Invalid plan',
            ], 400);
        }

        $subscription = $user->subscription('main');

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription',
            ], 404);
        }

        try {
            // Swap plan
            $subscription->swap(config("billing.plans.{$plan}.stripe_price"));

            // TODO: Send notification for plan change (could create SubscriptionUpdated notification)
            // For now, we can send SubscriptionCreated again with updated plan or skip.
            // Notification::send($user, new SubscriptionCreated($subscription));

            Log::info('User swapped subscription plan', [
                'user_id' => $user->id,
                'new_plan' => $plan,
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Subscription plan swapped successfully',
                'subscription' => $subscription,
            ]);
        } catch (Exception $e) {
            Log::error('Subscription swap failed', [
                'user_id' => $user->id,
                'new_plan' => $plan,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Subscription swap failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel the user's subscription.
     */
    public function cancel(Request $request)
    {
        $user = $request->user();

        $subscription = $user->subscription('main');

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription',
            ], 404);
        }

        try {
            // Cancel subscription immediately
            $subscription->cancel();

            // Send notification
            Notification::send($user, new SubscriptionCancelled($subscription));

            Log::info('User cancelled subscription', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Subscription cancelled successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Subscription cancellation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Subscription cancellation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List invoices for the authenticated user.
     */
    public function invoices(Request $request)
    {
        $user = $request->user();

        $invoices = $user->invoices()->latest()->get();

        return response()->json([
            'invoices' => $invoices,
        ]);
    }

    /**
     * Get a specific invoice for the authenticated user.
     */
    public function invoice(Request $request, $invoiceId)
    {
        $user = $request->user();

        $invoice = $user->invoices()->find($invoiceId);

        if (!$invoice) {
            return response()->json([
                'message' => 'Invoice not found',
            ], 404);
        }

        return response()->json([
            'invoice' => $invoice,
        ]);
    }
}