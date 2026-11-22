<?php

namespace BestConnectOnboarding\Payments;

use BestConnectOnboarding\Leads\Lead;
use BestConnectOnboarding\Frontend\Ajax;
use BestConnectOnboarding\Helpers\Logger;

defined('ABSPATH') || exit;

class Webhooks
{
    /**
     * Register Stripe webhook endpoint.
     */
    public function __construct()
    {
        add_action(
            'rest_api_init',
            [$this, 'register_routes']
        );
    }

    /**
     * Register REST route.
     */
    public function register_routes(): void
    {
        register_rest_route(
            'bcob/v1',
            '/stripe/webhook',
            [
                'methods' =>
                'POST',

                'callback' =>
                [$this, 'handle'],

                'permission_callback' =>
                '__return_true',
            ]
        );
    }

    /**
     * Handle Stripe webhook.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function handle(
        \WP_REST_Request $request
    ): \WP_REST_Response {
        $settings =
            get_option(
                'bcob_settings',
                []
            );

        $secret =
            sanitize_text_field(
                $settings['stripe_webhook_secret'] ?? ''
            );

        if (!$secret) {
            Logger::error(
                'payment',
                'Stripe webhook secret is not configured.',
                [
                    'operation' =>
                    'stripe_webhook',
                ]
            );
            return new \WP_REST_Response(
                [
                    'success' => false,
                ],
                500
            );
        }

        $payload =
            $request->get_body();

        $signature =
            $request->get_header(
                'stripe-signature'
            );

        try {
            $event =
                \Stripe\Webhook::constructEvent(
                    $payload,
                    $signature,
                    $secret
                );
        } catch (\Throwable $e) {
            Logger::warning(
                'payment',
                'Stripe webhook signature validation failed.',
                [
                    'operation' =>
                    'stripe_webhook',
                ]
            );
            return new \WP_REST_Response(
                [
                    'success' => false,
                ],
                400
            );
        }

        try {
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->payment_intent_succeeded(
                        $event->data->object
                    );
                    break;

                case 'payment_intent.processing':
                    $this->payment_intent_processing(
                        $event->data->object
                    );
                    break;

                case 'payment_intent.payment_failed':
                    $this->payment_intent_failed(
                        $event->data->object
                    );
                    break;

                case 'invoice.paid':
                    $this->invoice_paid(
                        $event->data->object
                    );
                    break;

                case 'invoice.payment_failed':
                    $this->invoice_failed(
                        $event->data->object
                    );
                    break;

                case 'customer.subscription.updated':
                    $this->subscription_updated(
                        $event->data->object
                    );
                    break;

                case 'customer.subscription.deleted':
                    $this->subscription_deleted(
                        $event->data->object
                    );
                    break;
            }
        } catch (\Throwable $e) {
            Logger::error(
                'payment',
                'Stripe webhook processing failed.',
                [
                    'operation' =>
                    'stripe_webhook',

                    'event_type' =>
                    (string) (
                        $event->type
                        ?? ''
                    ),
                ]
            );

            return new \WP_REST_Response(
                [
                    'success' => false,
                ],
                500
            );
        }

        return new \WP_REST_Response(
            [
                'success' => true,
            ],
            200
        );
    }

    private function payment_intent_succeeded(
        $intent
    ): void {
        $payment =
            Payment::get_by_payment_intent(
                (string) $intent->id
            );

        if (!$payment) {
            return;
        }

        $stripe_amount =
            (int) ($intent->amount_received ?? $intent->amount ?? 0);

        $stripe_currency =
            strtoupper(
                (string) ($intent->currency ?? '')
            );

        $payment_amount =
            (int) round(
                ((float) $payment->amount) * 100
            );

        $payment_currency =
            strtoupper(
                (string) $payment->currency
            );

        if (
            $stripe_amount !== $payment_amount ||
            $stripe_currency !== $payment_currency
        ) {
            Logger::error(
                'payment',
                'Stripe payment amount or currency validation failed.',
                [
                    'operation' =>
                    'payment_intent_succeeded',

                    'payment_id' =>
                    (int) $payment->id,

                    'lead_id' =>
                    (int) $payment->lead_id,

                    'form_id' =>
                    (int) $payment->form_id,
                ]
            );

            throw new \Exception(
                'Stripe payment amount or currency mismatch.'
            );
        }

        Stripe::sync_payment_intent(
            $intent
        );

        Ajax::complete_paid_lead(
            (int) $payment->lead_id
        );
    }

    private function payment_intent_processing(
        $intent
    ): void {
        Stripe::sync_payment_intent(
            $intent
        );
    }

    private function payment_intent_failed(
        $intent
    ): void {
        Stripe::sync_payment_intent(
            $intent
        );
    }

    private function invoice_paid(
        $invoice
    ): void {
        $subscription_id =
            $this->get_invoice_subscription_id(
                $invoice
            );

        if (!$subscription_id) {
            return;
        }

        $payment =
            Payment::get_by_subscription(
                $subscription_id
            );

        if (!$payment) {
            return;
        }

        $payment_intent_id =
            $this->get_invoice_payment_intent_id(
                $invoice
            );

        $invoice_id =
            (string) (
                $invoice->id ?? ''
            );

        Payment::create(
            (int) $payment->lead_id,
            [
                'form' => [
                    'id' =>
                    (int) $payment->form_id,
                ],

                'total' =>
                (float) $invoice->total / 100,

                'currency' =>
                strtoupper(
                    (string) $invoice->currency
                ),

                'payment' => [
                    'provider' =>
                    'stripe',

                    'type' =>
                    'recurring',

                    'subscription_id' =>
                    $subscription_id,

                    'payment_intent_id' =>
                    $payment_intent_id,

                    'invoice_id' =>
                    $invoice_id,

                    'customer_id' =>
                    (string) (
                        $invoice->customer ?? ''
                    ),

                    'status' =>
                    'succeeded',

                    'response' =>
                    $invoice->toArray(),
                ],
            ],
            (float) $invoice->total / 100,
            (string) $invoice->currency
        );

        /*
		 * Only the initial subscription payment
		 * completes the onboarding.
		 *
		 * Later recurring invoices only create
		 * payment history and do not change a
		 * completed lead back to failed.
		 */
        if (
            $payment->status !== 'succeeded'
        ) {
            Ajax::complete_paid_lead(
                (int) $payment->lead_id
            );
        }
    }

    private function invoice_failed(
        $invoice
    ): void {
        $subscription_id =
            $this->get_invoice_subscription_id(
                $invoice
            );

        if (!$subscription_id) {
            return;
        }

        $payment =
            Payment::get_by_subscription(
                $subscription_id
            );

        if (!$payment) {
            return;
        }

        $invoice_id =
            (string) (
                $invoice->id ?? ''
            );

        $payment_intent_id =
            $this->get_invoice_payment_intent_id(
                $invoice
            );

        Payment::create(
            (int) $payment->lead_id,
            [
                'form' => [
                    'id' =>
                    (int) $payment->form_id,
                ],

                'total' =>
                (float) $invoice->total / 100,

                'currency' =>
                strtoupper(
                    (string) $invoice->currency
                ),

                'payment' => [
                    'provider' =>
                    'stripe',

                    'type' =>
                    'recurring',

                    'subscription_id' =>
                    $subscription_id,

                    'payment_intent_id' =>
                    $payment_intent_id,

                    'invoice_id' =>
                    $invoice_id,

                    'status' =>
                    'failed',

                    'response' =>
                    $invoice->toArray(),
                ],
            ],
            (float) $invoice->total / 100,
            (string) $invoice->currency
        );

        /*
		 * Do not destroy an existing completed lead
		 * because a later renewal failed.
		 */
        if (
            $payment->status !== 'succeeded' &&
            $payment->status !== 'completed'
        ) {
            Lead::update_status(
                (int) $payment->lead_id,
                'payment_failed'
            );
        }
    }

    private function get_invoice_subscription_id(
        $invoice
    ): string {
        if (
            !$invoice ||
            !is_object($invoice)
        ) {
            return '';
        }

        if (
            isset($invoice->parent) &&
            is_object($invoice->parent) &&
            ($invoice->parent->type ?? '') === 'subscription_details'
        ) {
            $subscription =
                $invoice
                ->parent
                ->subscription_details
                ->subscription
                ?? null;

            if (is_string($subscription)) {
                return $subscription;
            }

            if (
                is_object($subscription) &&
                !empty($subscription->id)
            ) {
                return (string) $subscription->id;
            }
        }

        if (isset($invoice->subscription)) {
            if (is_string($invoice->subscription)) {
                return $invoice->subscription;
            }

            if (
                is_object($invoice->subscription) &&
                !empty($invoice->subscription->id)
            ) {
                return (string) $invoice->subscription->id;
            }
        }

        return '';
    }

    private function get_invoice_payment_intent_id(
        $invoice
    ): string {
        if (
            !$invoice ||
            !is_object($invoice)
        ) {
            return '';
        }

        $payments =
            $invoice->payments->data
            ?? [];

        if (
            is_array($payments) ||
            $payments instanceof \Traversable
        ) {
            foreach ($payments as $invoice_payment) {
                $payment =
                    is_object($invoice_payment)
                    ? ($invoice_payment->payment ?? null)
                    : (
                        is_array($invoice_payment)
                        ? ($invoice_payment['payment'] ?? null)
                        : null
                    );

                if (!$payment) {
                    continue;
                }

                $type =
                    is_object($payment)
                    ? ($payment->type ?? '')
                    : ($payment['type'] ?? '');

                if ($type !== 'payment_intent') {
                    continue;
                }

                $intent =
                    is_object($payment)
                    ? ($payment->payment_intent ?? null)
                    : ($payment['payment_intent'] ?? null);

                if (is_string($intent)) {
                    return $intent;
                }

                if (
                    is_object($intent) &&
                    !empty($intent->id)
                ) {
                    return (string) $intent->id;
                }

                if (
                    is_array($intent) &&
                    !empty($intent['id'])
                ) {
                    return (string) $intent['id'];
                }
            }
        }

        if (isset($invoice->payment_intent)) {
            if (is_string($invoice->payment_intent)) {
                return $invoice->payment_intent;
            }

            if (
                is_object($invoice->payment_intent) &&
                !empty($invoice->payment_intent->id)
            ) {
                return (string) $invoice->payment_intent->id;
            }
        }

        return '';
    }

    private function subscription_updated(
        $subscription
    ): void {
        $payment =
            Payment::get_by_subscription(
                (string) $subscription->id
            );

        if (!$payment) {
            return;
        }

        if (
            $subscription->status === 'past_due' &&
            $payment->status !== 'succeeded'
        ) {
            Lead::update_status(
                (int) $payment->lead_id,
                'payment_failed'
            );
        }
    }

    private function subscription_deleted(
        $subscription
    ): void {
        $payment =
            Payment::get_by_subscription(
                (string) $subscription->id
            );

        if (!$payment) {
            return;
        }

        /*
		 * Keep completed leads intact.
		 * Only record the subscription/payment state.
		 */
        if ($payment->status !== 'succeeded') {
            Payment::update_status(
                (int) $payment->id,
                'canceled'
            );
        }
    }
}
