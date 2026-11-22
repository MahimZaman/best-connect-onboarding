<?php

namespace BestConnectOnboarding\Payments;

use BestConnectOnboarding\Leads\Lead;
use BestConnectOnboarding\Coupon\Coupon;
use BestConnectOnboarding\Helpers\Logger;

defined('ABSPATH') || exit;

class Stripe
{
    public function __construct()
    {
        add_action(
            'admin_enqueue_scripts',
            [$this, 'admin_script']
        );

        add_action(
            'wp_ajax_bcob_connect_stripe',
            [$this, 'ajax_connect']
        );

        add_action(
            'wp_enqueue_scripts',
            [$this, 'frontend_script']
        );

        add_action(
            'wp_ajax_bcob_payment_config',
            [self::class, 'payment_config']
        );

        add_action(
            'wp_ajax_nopriv_bcob_payment_config',
            [self::class, 'payment_config']
        );

        add_action(
            'wp_ajax_bcob_prepare_payment',
            [self::class, 'prepare_payment']
        );

        add_action(
            'wp_ajax_nopriv_bcob_prepare_payment',
            [self::class, 'prepare_payment']
        );

        add_action(
            'wp_ajax_bcob_payment_return',
            [self::class, 'payment_return']
        );

        add_action(
            'wp_ajax_nopriv_bcob_payment_return',
            [self::class, 'payment_return']
        );

        add_action(
            'wp_ajax_bcob_payment_failed',
            [self::class, 'payment_failed']
        );

        add_action(
            'wp_ajax_nopriv_bcob_payment_failed',
            [self::class, 'payment_failed']
        );

        add_action(
            'wp_ajax_bcob_sync_payment',
            [self::class, 'sync_payment']
        );

        add_action(
            'wp_ajax_nopriv_bcob_sync_payment',
            [self::class, 'sync_payment']
        );

        new Webhooks();
    }

    public static function create_offer_signature(
        int $membership_id,
        int $portal_index,
        int $form_id
    ): string {
        return hash_hmac(
            'sha256',
            $membership_id .
                '|' .
                $portal_index .
                '|' .
                $form_id,
            wp_salt('auth')
        );
    }

    public static function verify_offer_signature(
        int $membership_id,
        int $portal_index,
        int $form_id,
        string $signature
    ): bool {
        if ($signature === '') {
            return false;
        }

        return hash_equals(
            self::create_offer_signature(
                $membership_id,
                $portal_index,
                $form_id
            ),
            $signature
        );
    }

    public static function sync_payment(): void
    {
        check_ajax_referer(
            'bcob_frontend',
            'nonce'
        );

        try {
            $payment_intent_id =
                sanitize_text_field(
                    wp_unslash(
                        $_POST['payment_intent'] ?? ''
                    )
                );

            $lead_token =
                sanitize_text_field(
                    wp_unslash(
                        $_POST['lead_token'] ?? ''
                    )
                );

            if (!$lead_token) {
                throw new \Exception(
                    __(
                        'Lead authorization is required.',
                        'best-connect-onboarding'
                    )
                );
            }

            if (!$payment_intent_id) {
                throw new \Exception(
                    __(
                        'Payment could not be identified.',
                        'best-connect-onboarding'
                    )
                );
            }

            $settings =
                get_option(
                    'bcob_settings',
                    []
                );

            $mode =
                $settings['stripe_mode'] ?? 'test';

            $secret_key =
                $mode === 'live'
                ? (
                    $settings['stripe_live_secret_key'] ?? ''
                )
                : (
                    $settings['stripe_test_secret_key'] ?? ''
                );

            if (!$secret_key) {
                throw new \Exception(
                    __(
                        'Stripe Secret Key is missing.',
                        'best-connect-onboarding'
                    )
                );
            }

            $stripe =
                new \Stripe\StripeClient(
                    $secret_key
                );

            $intent =
                $stripe->paymentIntents->retrieve(
                    $payment_intent_id
                );

            self::sync_payment_intent(
                $intent
            );

            $payment =
                Payment::get_by_payment_intent(
                    $payment_intent_id
                );

            $lead =
                Lead::get_by_token(
                    $lead_token
                );

            if (
                !$lead ||
                !$payment ||
                (int) $payment->lead_id !== (int) $lead->id
            ) {
                throw new \Exception(
                    __(
                        'Invalid payment authorization.',
                        'best-connect-onboarding'
                    )
                );
            }

            wp_send_json_success([
                'status' =>
                $intent->status,

                'payment_intent_id' =>
                $payment_intent_id,

                'lead_id' =>
                (int) $payment->lead_id,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' =>
                $e->getMessage(),
            ]);
        }
    }

    public function frontend_script(): void
    {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'bcob-payment',
            BCOB_URL . 'assets/css/frontend/payment.css',
            [],
            BCOB_VER
        );
    }

    public static function payment_config(): void
    {
        check_ajax_referer(
            'bcob_frontend',
            'nonce'
        );

        $settings = get_option(
            'bcob_settings',
            []
        );

        $mode =
            $settings['stripe_mode'] ?? 'test';

        $publishable_key =
            $mode === 'live'
            ? ($settings['stripe_live_publishable_key'] ?? '')
            : ($settings['stripe_test_publishable_key'] ?? '');

        if (!$publishable_key) {
            wp_send_json_error([
                'message' => __(
                    'Stripe is not configured.',
                    'best-connect-onboarding'
                ),
            ]);
        }

        wp_send_json_success([
            'publishable_key' => $publishable_key,
            'mode' => $mode,
        ]);
    }

    public static function prepare_payment(): void
    {
        check_ajax_referer(
            'bcob_frontend',
            'nonce'
        );

        try {
            $submission =
                json_decode(
                    wp_unslash(
                        $_POST['submission'] ?? ''
                    ),
                    true
                );

            if (!is_array($submission)) {
                throw new \Exception(
                    __(
                        'Invalid payment data.',
                        'best-connect-onboarding'
                    )
                );
            }

            $page_url =
                esc_url_raw(
                    wp_unslash(
                        $_POST['page_url'] ?? ''
                    )
                );

            if (!$page_url) {
                $page_url = home_url('/');
            }

            $membership =
                is_array(
                    $submission['membership']
                        ?? null
                )
                ? $submission['membership']
                : [];

            $membership_id =
                absint(
                    $membership['id'] ?? 0
                );

            $portal_index =
                array_key_exists(
                    'portal_index',
                    $membership
                )
                ? absint(
                    $membership['portal_index']
                )
                : -1;

            $submitted_form_id =
                absint(
                    $submission['form']['id']
                        ?? 0
                );

            $context_signature =
                sanitize_text_field(
                    $membership['context_signature']
                        ?? ''
                );

            if (
                !$membership_id ||
                $portal_index < 0 ||
                !$submitted_form_id ||
                !self::verify_offer_signature(
                    $membership_id,
                    $portal_index,
                    $submitted_form_id,
                    $context_signature
                )
            ) {
                throw new \Exception(
                    __(
                        'Invalid membership selection.',
                        'best-connect-onboarding'
                    )
                );
            }

            if (
                get_post_type(
                    $membership_id
                ) !== 'bcob_membership' ||
                get_post_status(
                    $membership_id
                ) !== 'publish'
            ) {
                throw new \Exception(
                    __(
                        'Invalid membership selection.',
                        'best-connect-onboarding'
                    )
                );
            }

            $versions =
                get_post_meta(
                    $membership_id,
                    '_bcob_versions',
                    true
                );

            if (
                !is_array($versions) ||
                !array_key_exists(
                    $portal_index,
                    $versions
                ) ||
                !is_array(
                    $versions[$portal_index]
                )
            ) {
                throw new \Exception(
                    __(
                        'Invalid membership selection.',
                        'best-connect-onboarding'
                    )
                );
            }

            $portal =
                $versions[$portal_index];

            if (
                absint(
                    $portal['form'] ?? 0
                ) !== $submitted_form_id
            ) {
                throw new \Exception(
                    __(
                        'Invalid membership selection.',
                        'best-connect-onboarding'
                    )
                );
            }

            $submission['form'] =
                is_array(
                    $submission['form'] ?? null
                )
                ? $submission['form']
                : [];

            $submission['form']['id'] =
                $submitted_form_id;

            $submission['membership'] = [
                'id' =>
                $membership_id,

                'portal_index' =>
                $portal_index,

                'form' =>
                $submitted_form_id,

                'context_signature' =>
                $context_signature,
            ];

            $lead_id =
                absint(
                    $submission['lead_id'] ?? 0
                );

            $lead_token =
                sanitize_text_field(
                    $submission['lead_token'] ?? ''
                );

            if ($lead_id) {
                if (!$lead_token) {
                    throw new \Exception(
                        __(
                            'Lead authorization is required.',
                            'best-connect-onboarding'
                        )
                    );
                }

                $lead =
                    Lead::get_by_token(
                        $lead_token
                    );

                if (
                    !$lead ||
                    (int) $lead->id !==
                    $lead_id
                ) {
                    throw new \Exception(
                        __(
                            'Invalid lead authorization.',
                            'best-connect-onboarding'
                        )
                    );
                }
            } else {
                $created =
                    Lead::create(
                        $submission
                    );

                $lead_id =
                    (int) $created['id'];

                $lead_token =
                    $created['token'];

                $lead =
                    Lead::get(
                        $lead_id
                    );
            }

            if (!$lead) {
                throw new \Exception(
                    __(
                        'Lead could not be identified.',
                        'best-connect-onboarding'
                    )
                );
            }

            $form_id =
                (int) $lead->form_id;

            if (
                !$form_id ||
                $form_id !==
                $submitted_form_id
            ) {
                throw new \Exception(
                    __(
                        'Invalid payment context.',
                        'best-connect-onboarding'
                    )
                );
            }

            $stored_submission =
                json_decode(
                    $lead->submission ?? '',
                    true
                );

            if (
                is_array(
                    $stored_submission
                ) &&
                !empty($stored_submission['payment_context']) &&
                is_array(
                    $stored_submission['payment_context']
                )
            ) {
                $stored_context =
                    $stored_submission['payment_context'];

                if (
                    absint(
                        $stored_context['membership_id'] ?? 0
                    ) !== $membership_id ||
                    absint(
                        $stored_context['portal_index'] ?? -1
                    ) !== $portal_index ||
                    absint(
                        $stored_context['form_id'] ?? 0
                    ) !== $form_id
                ) {
                    throw new \Exception(
                        __(
                            'Payment context does not match.',
                            'best-connect-onboarding'
                        )
                    );
                }
            }

            $existing_payment =
                Payment::get_latest_for_lead(
                    $lead_id
                );

            if ($existing_payment) {
                if (
                    !empty($existing_payment
                        ->membership_id) &&
                    (int) $existing_payment
                        ->membership_id !==
                    $membership_id
                ) {
                    throw new \Exception(
                        __(
                            'Payment context does not match.',
                            'best-connect-onboarding'
                        )
                    );
                }

                if (
                    $existing_payment
                    ->portal_index !== null &&
                    (int) $existing_payment
                        ->portal_index !==
                    $portal_index
                ) {
                    throw new \Exception(
                        __(
                            'Payment context does not match.',
                            'best-connect-onboarding'
                        )
                    );
                }

                if (
                    (int) $existing_payment
                        ->form_id !==
                    $form_id
                ) {
                    throw new \Exception(
                        __(
                            'Payment context does not match.',
                            'best-connect-onboarding'
                        )
                    );
                }
            }

            $submission['lead_id'] =
                $lead_id;

            $submission['lead_token'] =
                $lead_token;

            $submission['page_url'] =
                $page_url;

            $payment =
                $submission['payment']
                ?? [];

            $type =
                ($payment['type']
                    ?? 'one_time') ===
                'recurring'
                ? 'recurring'
                : 'one_time';

            $subtotal = 0;
            $snapshot_items = [];

            foreach (
                ($portal['items'] ?? [])
                as $item
            ) {
                $name =
                    sanitize_text_field(
                        $item['name'] ?? ''
                    );

                $price =
                    max(
                        0,
                        (float) (
                            $item['price'] ?? 0
                        )
                    );

                $price =
                    round(
                        $price,
                        2
                    );

                $subtotal +=
                    $price;

                $snapshot_items[] = [
                    'name' =>
                    $name,

                    'price' =>
                    $price,
                ];
            }

            $subtotal =
                round(
                    $subtotal,
                    2
                );

            $discount = 0;
            $coupon_code = '';

            if (
                !empty($submission['discount']) &&
                is_array(
                    $submission['discount']
                )
            ) {
                $coupon_code =
                    sanitize_text_field(
                        $submission['discount'][0]['code']
                            ?? ''
                    );
            }

            if ($coupon_code) {
                $discount =
                    Coupon::calculate_discount(
                        $coupon_code,
                        $subtotal,
                        $form_id
                    );

                $discount =
                    max(
                        0,
                        round(
                            (float) $discount,
                            2
                        )
                    );

                if ($discount > 0) {
                    $submission['discount'] = [
                        [
                            'code' =>
                            $coupon_code,

                            'amount' =>
                            $discount,
                        ],
                    ];
                } else {
                    $coupon_code = '';
                    $submission['discount'] = [];
                }
            } else {
                $submission['discount'] = [];
            }

            $total =
                max(
                    0,
                    round(
                        $subtotal -
                            $discount,
                        2
                    )
                );

            $amount =
                (int) round(
                    $total * 100
                );

            $settings =
                get_option(
                    'bcob_settings',
                    []
                );

            $currency =
                strtolower(
                    sanitize_text_field(
                        $settings['currency_code'] ?? 'eur'
                    )
                );

            if ($amount <= 0) {
                throw new \Exception(
                    __(
                        'Invalid payment amount.',
                        'best-connect-onboarding'
                    )
                );
            }

            $price_snapshot = [
                'items' =>
                $snapshot_items,

                'subtotal' =>
                $subtotal,

                'discount' =>
                $discount,

                'coupon_code' =>
                $coupon_code,

                'total' =>
                $total,

                'currency' =>
                strtoupper(
                    $currency
                ),
            ];

            $offer_context = [
                'membership_id' =>
                $membership_id,

                'portal_index' =>
                $portal_index,

                'form_id' =>
                $form_id,

                'price_snapshot' =>
                $price_snapshot,
            ];

            $submission['payment_context'] =
                $offer_context;

            $submission['products'] =
                $snapshot_items;

            $submission['subtotal'] =
                $subtotal;

            $submission['discount_total'] =
                $discount;

            $submission['total'] =
                $total;

            $submission['amount'] =
                $total;

            $submission['currency'] =
                strtoupper(
                    $currency
                );

            if (
                !Lead::update_submission_by_token(
                    $lead_token,
                    $submission
                )
            ) {
                throw new \Exception(
                    __(
                        'Lead could not be updated.',
                        'best-connect-onboarding'
                    )
                );
            }

            Lead::update_status(
                $lead_id,
                'pending_payment'
            );

            $mode =
                $settings['stripe_mode']
                ?? 'test';

            $secret_key =
                $mode === 'live'
                ? (
                    $settings['stripe_live_secret_key'] ?? ''
                )
                : (
                    $settings['stripe_test_secret_key'] ?? ''
                );

            if (!$secret_key) {
                throw new \Exception(
                    __(
                        'Stripe Secret Key is missing.',
                        'best-connect-onboarding'
                    )
                );
            }

            $stripe =
                new \Stripe\StripeClient(
                    $secret_key
                );

            if ($type === 'recurring') {
                $result =
                    self::prepare_subscription(
                        $stripe,
                        $lead_id,
                        $submission,
                        $amount,
                        $currency,
                        $page_url,
                        $offer_context
                    );
            } else {
                $result =
                    self::prepare_one_time(
                        $stripe,
                        $lead_id,
                        $submission,
                        $amount,
                        $currency,
                        $page_url,
                        $offer_context
                    );
            }

            $submission['payment'] =
                array_merge(
                    $submission['payment']
                        ?? [],
                    $result['payment']
                );

            Lead::update_submission(
                $lead_id,
                $submission
            );

            wp_send_json_success([
                'lead_id' =>
                $lead_id,

                'lead_token' =>
                $lead_token,

                'payment' =>
                $result['payment'],

                'client_secret' =>
                $result['client_secret'],

                'payment_return_signature' =>
                self::get_payment_return_signature(
                    (string) (
                        $result['payment']['payment_intent_id'] ?? ''
                    ),
                    $lead_id
                ),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' =>
                $e->getMessage(),
            ]);
        }
    }

    private static function prepare_one_time(
        \Stripe\StripeClient $stripe,
        int $lead_id,
        array $submission,
        int $amount,
        string $currency,
        string $page_url,
        array $offer_context
    ): array {
        $payment =
            $submission['payment'] ?? [];

        $existing_id =
            sanitize_text_field(
                $payment['payment_intent_id'] ?? ''
            );

        if ($existing_id) {
            try {
                $intent =
                    $stripe->paymentIntents->retrieve(
                        $existing_id
                    );

                if (
                    $intent->amount === $amount &&
                    $intent->currency === $currency &&
                    in_array(
                        $intent->status,
                        [
                            'requires_payment_method',
                            'requires_confirmation',
                            'requires_action',
                            'processing',
                        ],
                        true
                    )
                ) {
                    $intent =
                        $stripe->paymentIntents->update(
                            $intent->id,
                            [
                                'metadata' => [
                                    'bcob_lead_id' =>
                                    (string) $lead_id,

                                    'bcob_form_id' =>
                                    (string) absint(
                                        $submission['form']['id'] ?? 0
                                    ),
                                ],
                            ]
                        );

                    Payment::create(
                        $lead_id,
                        [
                            ...$submission,
                            'payment' => [
                                ...$payment,
                                'payment_intent_id' =>
                                $intent->id,
                                'status' =>
                                $intent->status,
                                'response' =>
                                self::to_array($intent),
                            ],
                        ],
                        $amount / 100,
                        $currency,
                        $offer_context
                    );

                    return [
                        'client_secret' =>
                        $intent->client_secret,

                        'payment' => [
                            'provider' =>
                            'stripe',

                            'type' =>
                            'one_time',

                            'payment_intent_id' =>
                            $intent->id,

                            'status' =>
                            $intent->status,
                        ],
                    ];
                }
            } catch (\Throwable $e) {
                // Create a fresh PaymentIntent below.
            }
        }

        $idempotency_key =
            'bcob_pi_' .
            $lead_id .
            '_' .
            wp_generate_uuid4();

        $intent =
            $stripe->paymentIntents->create(
                [
                    'amount' =>
                    $amount,

                    'currency' =>
                    $currency,

                    'automatic_payment_methods' => [
                        'enabled' => true,
                    ],

                    'metadata' => [
                        'bcob_lead_id' =>
                        (string) $lead_id,

                        'bcob_form_id' =>
                        (string) absint(
                            $submission['form']['id'] ?? 0
                        ),

                        'bcob_payment_type' =>
                        'one_time',
                    ],
                ],
                [
                    'idempotency_key' =>
                    $idempotency_key,
                ]
            );

        Payment::create(
            $lead_id,
            [
                ...$submission,
                'payment' => [
                    ...$payment,
                    'provider' =>
                    'stripe',

                    'type' =>
                    'one_time',

                    'payment_intent_id' =>
                    $intent->id,

                    'status' =>
                    $intent->status,

                    'response' =>
                    self::to_array($intent),
                ],
            ],
            $amount / 100,
            $currency,
            $offer_context
        );

        return [
            'client_secret' =>
            $intent->client_secret,

            'payment' => [
                'provider' =>
                'stripe',

                'type' =>
                'one_time',

                'payment_intent_id' =>
                $intent->id,

                'status' =>
                $intent->status,
            ],
        ];
    }

    private static function prepare_subscription(
        \Stripe\StripeClient $stripe,
        int $lead_id,
        array $submission,
        int $amount,
        string $currency,
        string $page_url,
        array $offer_context
    ): array {
        $payment =
            $submission['payment'] ?? [];

        $interval =
            sanitize_key(
                $payment['interval'] ?? 'month'
            );

        $interval_count =
            max(
                1,
                absint(
                    $payment['interval_count'] ?? 1
                )
            );

        $allowed_intervals = [
            'day',
            'week',
            'month',
            'year',
        ];

        if (
            !in_array(
                $interval,
                $allowed_intervals,
                true
            )
        ) {
            throw new \Exception(
                __(
                    'Invalid subscription interval.',
                    'best-connect-onboarding'
                )
            );
        }

        $existing_subscription_id = '';

        if ($existing_subscription_id) {
            try {
                $subscription =
                    $stripe->subscriptions->retrieve(
                        $existing_subscription_id,
                        [
                            'expand' => [
                                'latest_invoice.confirmation_secret',
                            ],
                        ]
                    );

                if (
                    in_array(
                        $subscription->status,
                        [
                            'incomplete',
                            'trialing',
                        ],
                        true
                    )
                ) {
                    $client_secret =
                        self::get_invoice_client_secret(
                            $subscription->latest_invoice
                        );

                    if ($client_secret) {
                        return [
                            'client_secret' =>
                            $client_secret,

                            'payment' => [
                                'provider' =>
                                'stripe',

                                'type' =>
                                'recurring',

                                'subscription_id' =>
                                $subscription->id,

                                'customer_id' =>
                                (string) $subscription->customer,

                                'price_id' =>
                                (string) (
                                    $subscription
                                        ->items
                                        ->data[0]
                                    ->price
                                    ->id
                                    ?? ''
                                ),

                                'payment_intent_id' =>
                                self::get_invoice_payment_intent_id(
                                    $subscription->latest_invoice
                                ),

                                'status' =>
                                $subscription->status,
                            ],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Create a fresh subscription below.
            }
        }

        $customer =
            self::create_customer(
                $stripe,
                $lead_id,
                $submission
            );

        $price_id =
            self::get_price_id(
                $stripe,
                $submission,
                $amount,
                $currency,
                $interval,
                $interval_count
            );

        $idempotency_key =
            'bcob_sub_' .
            $lead_id .
            '_' .
            md5(
                implode(
                    '|',
                    [
                        $price_id,
                        $customer->id,
                    ]
                )
            );

        $subscription =
            $stripe->subscriptions->create(
                [
                    'customer' =>
                    $customer->id,

                    'items' => [
                        [
                            'price' =>
                            $price_id,

                            'quantity' =>
                            1,
                        ],
                    ],

                    'payment_behavior' =>
                    'default_incomplete',

                    'payment_settings' => [
                        'save_default_payment_method' =>
                        'on_subscription',
                    ],

                    'metadata' => [
                        'bcob_lead_id' =>
                        (string) $lead_id,

                        'bcob_form_id' =>
                        (string) absint(
                            $submission['form']['id'] ?? 0
                        ),

                        'bcob_payment_type' =>
                        'recurring',
                    ],

                    'expand' => [
                        'latest_invoice.confirmation_secret',
                    ],
                ],
                [
                    'idempotency_key' =>
                    $idempotency_key,
                ]
            );

        $invoice =
            self::retrieve_invoice(
                $stripe,
                $subscription->latest_invoice
            );

        if (!$invoice) {
            throw new \Exception(
                __(
                    'Unable to retrieve the subscription invoice.',
                    'best-connect-onboarding'
                )
            );
        }

        $client_secret =
            self::get_invoice_client_secret(
                $invoice
            );

        if (!$client_secret) {
            throw new \Exception(
                __(
                    'Unable to initialize the subscription payment.',
                    'best-connect-onboarding'
                )
            );
        }

        $payment_intent_id =
            self::get_invoice_payment_intent_id(
                $invoice
            );

        if (!$payment_intent_id) {
            throw new \Exception(
                __(
                    'Unable to identify the subscription PaymentIntent.',
                    'best-connect-onboarding'
                )
            );
        }

        $invoice_id =
            (string) $invoice->id;

        Payment::create(
            $lead_id,
            [
                ...$submission,
                'payment' => [
                    ...$payment,
                    'provider' =>
                    'stripe',

                    'type' =>
                    'recurring',

                    'customer_id' =>
                    $customer->id,

                    'subscription_id' =>
                    $subscription->id,

                    'price_id' =>
                    $price_id,

                    'product_id' =>
                    self::get_price_product_id(
                        $stripe,
                        $price_id
                    ),

                    'payment_intent_id' =>
                    $payment_intent_id,

                    'invoice_id' =>
                    $invoice_id,

                    'status' =>
                    $subscription->status,

                    'response' =>
                    self::to_array($subscription),
                ],
            ],
            $amount / 100,
            $currency,
            $offer_context
        );

        return [
            'client_secret' =>
            $client_secret,

            'payment' => [
                'provider' =>
                'stripe',

                'type' =>
                'recurring',

                'customer_id' =>
                $customer->id,

                'subscription_id' =>
                $subscription->id,

                'price_id' =>
                $price_id,

                'payment_intent_id' =>
                $payment_intent_id,

                'invoice_id' =>
                $invoice_id,

                'status' =>
                $subscription->status,

                'interval' =>
                $interval,

                'interval_count' =>
                $interval_count,
            ],
        ];
    }

    private static function create_customer(
        \Stripe\StripeClient $stripe,
        int $lead_id,
        array $submission
    ): \Stripe\Customer {
        $email = '';
        $name = '';
        $phone = '';

        foreach (
            ($submission['values'] ?? [])
            as $field
        ) {
            $type =
                $field['type'] ?? '';

            $label =
                strtolower(
                    $field['label'] ?? ''
                );

            $value =
                is_scalar(
                    $field['value'] ?? null
                )
                ? trim(
                    (string) $field['value']
                )
                : '';

            if (
                $type === 'email' &&
                !$email
            ) {
                $email = sanitize_email($value);
            }

            if (
                $type === 'phone' &&
                !$phone
            ) {
                $phone = sanitize_text_field($value);
            }

            if (
                $type === 'text' &&
                !$name &&
                (
                    str_contains(
                        $label,
                        'first'
                    ) ||
                    str_contains(
                        $label,
                        'vorname'
                    )
                )
            ) {
                $name =
                    sanitize_text_field($value);
            }
        }

        $params = [
            'metadata' => [
                'bcob_lead_id' =>
                (string) $lead_id,
            ],
        ];

        if ($email) {
            $params['email'] = $email;
        }

        if ($name) {
            $params['name'] = $name;
        }

        if ($phone) {
            $params['phone'] = $phone;
        }

        return $stripe->customers->create(
            $params,
            [
                'idempotency_key' =>
                'bcob_customer_' .
                    $lead_id,
            ]
        );
    }

    private static function get_price_id(
        \Stripe\StripeClient $stripe,
        array $submission,
        int $amount,
        string $currency,
        string $interval,
        int $interval_count
    ): string {
        $form_id =
            absint(
                $submission['form']['id'] ?? 0
            );

        $hash =
            hash(
                'sha256',
                implode(
                    '|',
                    [
                        $form_id,
                        $amount,
                        $currency,
                        $interval,
                        $interval_count,
                    ]
                )
            );

        $products =
            $stripe->products->all([
                'active' => true,
                'limit' => 100,
            ]);

        $product = null;

        foreach ($products->data as $item) {
            $metadata =
                $item->metadata ?? null;

            if (
                $metadata &&
                ($metadata->bcob_hash ?? '') ===
                $hash
            ) {
                $product = $item;
                break;
            }
        }

        if (!$product) {
            $product =
                $stripe->products->create([
                    'name' =>
                    sprintf(
                        'BCOB Membership #%d',
                        $form_id
                    ),

                    'metadata' => [
                        'bcob_form_id' =>
                        (string) $form_id,

                        'bcob_hash' =>
                        $hash,
                    ],
                ]);
        }

        $prices =
            $stripe->prices->all([
                'product' =>
                $product->id,

                'active' =>
                true,

                'limit' =>
                100,
            ]);

        foreach ($prices->data as $price) {
            if (
                $price->currency === $currency &&
                (int) $price->unit_amount === $amount &&
                isset($price->recurring) &&
                $price->recurring->interval ===
                $interval &&
                (int) $price->recurring->interval_count ===
                $interval_count
            ) {
                return $price->id;
            }
        }

        $price =
            $stripe->prices->create([
                'product' =>
                $product->id,

                'currency' =>
                $currency,

                'unit_amount' =>
                $amount,

                'recurring' => [
                    'interval' =>
                    $interval,

                    'interval_count' =>
                    $interval_count,
                ],

                'metadata' => [
                    'bcob_form_id' =>
                    (string) $form_id,

                    'bcob_hash' =>
                    $hash,
                ],
            ]);

        return $price->id;
    }

    private static function get_price_product_id(
        \Stripe\StripeClient $stripe,
        string $price_id
    ): string {
        if (!$price_id) {
            return '';
        }

        try {
            $price =
                $stripe->prices->retrieve(
                    $price_id
                );

            return is_string($price->product)
                ? $price->product
                : (string) (
                    $price->product->id ?? ''
                );
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function get_invoice_client_secret(
        $invoice
    ): string {
        if (!$invoice || !is_object($invoice)) {
            return '';
        }

        if (
            isset($invoice->confirmation_secret) &&
            is_object($invoice->confirmation_secret) &&
            !empty($invoice->confirmation_secret->client_secret)
        ) {
            return (string) $invoice
                ->confirmation_secret
                ->client_secret;
        }

        return '';
    }

    private static function get_invoice_payment_intent_id(
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

    private static function get_invoice_subscription_id(
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

    private static function retrieve_invoice(
        \Stripe\StripeClient $stripe,
        $invoice
    ) {
        $invoice_id = '';

        if (is_string($invoice)) {
            $invoice_id = $invoice;
        } elseif (
            is_object($invoice) &&
            !empty($invoice->id)
        ) {
            $invoice_id = (string) $invoice->id;
        }

        if (!$invoice_id) {
            return null;
        }

        return $stripe->invoices->retrieve(
            $invoice_id,
            [
                'expand' => [
                    'confirmation_secret',
                    'payments.data.payment.payment_intent',
                    'parent.subscription_details.subscription',
                ],
            ]
        );
    }

    public static function get_subscription_period_end(
        string $subscription_id
    ): int {
        $subscription_id =
            sanitize_text_field(
                $subscription_id
            );

        if ($subscription_id === '') {
            return 0;
        }

        $settings =
            get_option(
                'bcob_settings',
                []
            );

        $mode =
            $settings['stripe_mode']
            ?? 'test';

        $secret_key =
            $mode === 'live'
            ? (
                $settings['stripe_live_secret_key']
                ?? ''
            )
            : (
                $settings['stripe_test_secret_key']
                ?? ''
            );

        if ($secret_key === '') {
            return 0;
        }

        try {
            $stripe =
                new \Stripe\StripeClient(
                    $secret_key
                );

            $subscription =
                $stripe
                ->subscriptions
                ->retrieve(
                    $subscription_id
                );

            $period_ends = [];

            foreach (
                ($subscription->items->data ?? [])
                as $item
            ) {
                $period_end =
                    absint(
                        $item->current_period_end
                            ?? 0
                    );

                if ($period_end) {
                    $period_ends[] =
                        $period_end;
                }
            }

            if (empty($period_ends)) {
                return 0;
            }

            return min(
                $period_ends
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function to_array($object): array
    {
        if (is_array($object)) {
            return $object;
        }

        if (
            is_object($object) &&
            method_exists(
                $object,
                'toArray'
            )
        ) {
            return $object->toArray();
        }

        return [];
    }

    private static function get_payment_return_signature(
        string $payment_intent_id,
        int $lead_id
    ): string {
        return hash_hmac(
            'sha256',
            $payment_intent_id . '|' . $lead_id,
            wp_salt('auth')
        );
    }

    public static function payment_return(): void
    {
        check_ajax_referer(
            'bcob_frontend',
            'nonce'
        );

        try {
            $payment_intent_id =
                sanitize_text_field(
                    wp_unslash(
                        $_POST['payment_intent'] ?? ''
                    )
                );

            $payment_return_signature =
                sanitize_text_field(
                    wp_unslash(
                        $_POST['payment_return_signature'] ?? ''
                    )
                );

            if (
                !$payment_intent_id ||
                !$payment_return_signature
            ) {
                throw new \Exception(
                    __(
                        'Payment could not be identified.',
                        'best-connect-onboarding'
                    )
                );
            }

            $payment =
                Payment::get_by_payment_intent(
                    $payment_intent_id
                );

            if (!$payment) {
                throw new \Exception(
                    __(
                        'Payment record could not be found.',
                        'best-connect-onboarding'
                    )
                );
            }

            $expected_signature =
                self::get_payment_return_signature(
                    $payment_intent_id,
                    (int) $payment->lead_id
                );

            if (
                !hash_equals(
                    $expected_signature,
                    $payment_return_signature
                )
            ) {
                throw new \Exception(
                    __(
                        'Invalid payment authorization.',
                        'best-connect-onboarding'
                    )
                );
            }

            $settings =
                get_option(
                    'bcob_settings',
                    []
                );

            $mode =
                $settings['stripe_mode'] ?? 'test';

            $secret_key =
                $mode === 'live'
                ? ($settings['stripe_live_secret_key'] ?? '')
                : ($settings['stripe_test_secret_key'] ?? '');

            $stripe =
                new \Stripe\StripeClient(
                    $secret_key
                );

            $intent =
                $stripe->paymentIntents->retrieve(
                    $payment_intent_id
                );

            self::sync_payment_intent(
                $intent
            );

            if ($intent->status === 'succeeded') {
                $result =
                    \BestConnectOnboarding\Frontend\Ajax::complete_paid_lead(
                        (int) $payment->lead_id
                    );

                wp_send_json_success(
                    $result
                );
            }

            if ($intent->status === 'processing') {
                wp_send_json_success([
                    'processing' => true,
                    'message' => __(
                        'Your payment is being processed.',
                        'best-connect-onboarding'
                    ),
                ]);
            }

            wp_send_json_error([
                'message' => __(
                    'The payment was not completed.',
                    'best-connect-onboarding'
                ),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function payment_failed(): void
    {
        check_ajax_referer(
            'bcob_frontend',
            'nonce'
        );

        $payment_intent_id =
            sanitize_text_field(
                wp_unslash(
                    $_POST['payment_intent'] ?? ''
                )
            );

        $lead_token =
            sanitize_text_field(
                wp_unslash(
                    $_POST['lead_token'] ?? ''
                )
            );

        if (
            !$payment_intent_id ||
            !$lead_token
        ) {
            wp_send_json_error([
                'message' => __(
                    'Payment could not be identified.',
                    'best-connect-onboarding'
                ),
            ]);
        }

        $payment =
            Payment::get_by_payment_intent(
                $payment_intent_id
            );

        $lead =
            Lead::get_by_token(
                $lead_token
            );

        if (
            !$lead ||
            !$payment ||
            (int) $payment->lead_id !== (int) $lead->id
        ) {
            wp_send_json_error([
                'message' => __(
                    'Invalid payment authorization.',
                    'best-connect-onboarding'
                ),
            ]);
        }

        if ($payment) {
            Payment::update_status(
                (int) $payment->id,
                'failed'
            );

            Lead::update_status(
                (int) $payment->lead_id,
                'payment_failed'
            );

            Logger::warning(
                'payment',
                'Payment was marked as failed.',
                [
                    'operation' =>
                    'payment_failed',

                    'payment_id' =>
                    (int) $payment->id,

                    'lead_id' =>
                    (int) $payment->lead_id,

                    'form_id' =>
                    (int) $payment->form_id,

                    'status' =>
                    'failed',
                ]
            );
        }

        wp_send_json_success();
    }

    public static function sync_payment_intent(
        $intent
    ): void {
        if (!is_object($intent)) {
            return;
        }

        $payment =
            Payment::get_by_payment_intent(
                (string) $intent->id
            );

        if (!$payment) {
            return;
        }

        $was_succeeded =
            $payment->status === 'succeeded';

        $status =
            $intent->status;

        $bcob_status = match ($status) {
            'succeeded' => 'succeeded',
            'processing' => 'processing',
            'requires_payment_method',
            'payment_failed' => 'failed',
            default => $status,
        };

        $updated =
            Payment::update(
                (int) $payment->id,
                [
                    'status' =>
                    $bcob_status,

                    'response' =>
                    wp_json_encode(
                        self::to_array($intent)
                    ),
                ]
            );

        if (!$updated) {
            Logger::error(
                'payment',
                'Payment status could not be synchronized.',
                [
                    'operation' =>
                    'sync_payment_intent',

                    'payment_id' =>
                    (int) $payment->id,

                    'lead_id' =>
                    (int) $payment->lead_id,

                    'form_id' =>
                    (int) $payment->form_id,
                ]
            );

            return;
        }

        if ($bcob_status === 'failed') {
            Logger::warning(
                'payment',
                'Stripe payment status changed to failed.',
                [
                    'operation' =>
                    'sync_payment_intent',

                    'payment_id' =>
                    (int) $payment->id,

                    'lead_id' =>
                    (int) $payment->lead_id,

                    'form_id' =>
                    (int) $payment->form_id,

                    'status' =>
                    $bcob_status,
                ]
            );
        } else {
            Logger::info(
                'payment',
                'Stripe payment status synchronized.',
                [
                    'operation' =>
                    'sync_payment_intent',

                    'payment_id' =>
                    (int) $payment->id,

                    'lead_id' =>
                    (int) $payment->lead_id,

                    'form_id' =>
                    (int) $payment->form_id,

                    'status' =>
                    $bcob_status,
                ]
            );
        }

        if (
            $bcob_status === 'succeeded' &&
            !$was_succeeded
        ) {
            $lead =
                Lead::get(
                    (int) $payment->lead_id
                );

            if ($lead) {
                $submission =
                    json_decode(
                        $lead->submission ?? '',
                        true
                    );

                $coupon_code =
                    is_array($submission) &&
                    !empty($submission['discount'][0]['code'])
                    ? sanitize_text_field(
                        $submission['discount'][0]['code']
                    )
                    : '';

                if ($coupon_code) {
                    Coupon::record_usage(
                        $coupon_code,
                        (int) $payment->lead_id
                    );
                }
            }
        }

        if ($bcob_status === 'failed') {
            Lead::update_status(
                (int) $payment->lead_id,
                'payment_failed'
            );
        }

        if ($bcob_status === 'processing') {
            Lead::update_status(
                (int) $payment->lead_id,
                'payment_processing'
            );
        }
    }

    public function admin_script(): void
    {
        wp_enqueue_style(
            'bcob-stripe-admin',
            BCOB_URL . 'assets/css/stripe-admin.css',
            [],
            BCOB_VER
        );

        wp_enqueue_script(
            'bcob-stripe-admin',
            BCOB_URL . 'assets/js/admin/stripe-admin.js',
            [],
            BCOB_VER,
            true
        );

        wp_localize_script(
            'bcob-stripe-admin',
            'BCOBStripe',
            [
                'ajax_url' =>
                admin_url(
                    'admin-ajax.php'
                ),

                'nonce' =>
                wp_create_nonce(
                    'bcob_admin'
                ),
            ]
        );
    }

    public static function ajax_connect(): void
    {
        check_ajax_referer(
            'bcob_admin',
            'nonce'
        );

        if (
            !current_user_can(
                'manage_options'
            )
        ) {
            wp_send_json_error([
                'message' => __(
                    'Permission denied.',
                    'best-connect-onboarding'
                ),
            ]);
        }

        $mode =
            sanitize_text_field(
                $_POST['stripe_mode'] ?? 'test'
            );

        $secret =
            $mode === 'live'
            ? trim(
                wp_unslash(
                    $_POST['stripe_live_secret_key'] ?? ''
                )
            )
            : trim(
                wp_unslash(
                    $_POST['stripe_test_secret_key'] ?? ''
                )
            );

        if (!$secret) {
            wp_send_json_error([
                'message' => __(
                    'Please enter your Stripe Secret Key.',
                    'best-connect-onboarding'
                ),
            ]);
        }

        try {
            $stripe =
                new \Stripe\StripeClient(
                    $secret
                );

            $account =
                $stripe->accounts->retrieve();

            $settings =
                get_option(
                    'bcob_settings',
                    []
                );

            $settings['stripe_account_id'] =
                $account->id ?? '';

            $settings['stripe_business_name'] =
                $account
                ->business_profile
                ->name ?? '';

            $settings['stripe_country'] =
                $account->country ?? '';

            $settings['stripe_email'] =
                $account->email ?? '';

            $settings['stripe_connected_at'] =
                current_time('mysql');

            update_option(
                'bcob_settings',
                $settings
            );

            wp_send_json_success([
                'message' => __(
                    'Stripe connected successfully.',
                    'best-connect-onboarding'
                ),

                'account' => [
                    'id' =>
                    $account->id,

                    'country' =>
                    $account->country,

                    'email' =>
                    $account->email,

                    'business_name' =>
                    $account
                        ->business_profile
                        ->name ?? '',
                ],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' =>
                $e->getMessage(),
            ]);
        }
    }
}
