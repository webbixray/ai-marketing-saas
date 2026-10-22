<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle a customer.subscription.deleted event.
     *
     * @param  array  $payload
     * @return void
     */
    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        // Let the parent handle the actual subscription deletion.
        return parent::handleCustomerSubscriptionDeleted($payload);
    }

    /**
     * Handle a customer.subscription.updated event.
     *
     * @param  array  $payload
     * @return void
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        // Let the parent handle the actual subscription update.
        return parent::handleCustomerSubscriptionUpdated($payload);
    }

    /**
     * Handle a Stripe webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);

        // Log the event for debugging.
        $eventType = $payload['type'] ?? 'unknown';
        Log::info('Stripe webhook received', ['event' => $eventType]);

        // Let the parent handle the webhook (signature verification, event dispatching, etc.)
        $response = parent::handleWebhook($request);

        // If the response is not successful (e.g., invalid signature), return early.
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        return $response;
    }
}
