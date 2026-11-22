<?php

namespace BestConnectOnboarding\Leads;

defined('ABSPATH') || exit;

class Lead
{
    private const STATUSES = [
        'pending',
        'pending_payment',
        'payment_processing',
        'payment_failed',
        'paid_pending_verification',
        'completed',
    ];

    public static function create(
        array $submission
    ) {
        global $wpdb;

        $table =
            $wpdb->prefix .
            'bcob_leads';

        $token =
            wp_generate_uuid4();

        $result =
            $wpdb->insert(
                $table,
                [
                    'form_id' =>
                    absint(
                        $submission['form']['id'] ?? 0
                    ),

                    'token' =>
                    $token,

                    'submission' =>
                    wp_json_encode(
                        $submission
                    ),

                    'ip_address' =>
                    self::get_ip(),

                    'user_agent' =>
                    sanitize_text_field(
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ),

                    'status' =>
                    'pending',

                    'created_at' =>
                    current_time(
                        'mysql'
                    ),

                    'updated_at' =>
                    current_time(
                        'mysql'
                    ),
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

        if ($result === false) {
            throw new \Exception(
                $wpdb->last_error
            );
        }

        return [
            'id' =>
            (int) $wpdb->insert_id,

            'token' =>
            $token,
        ];
    }

    public static function get(
        int $lead_id
    ): ?object {
        global $wpdb;

        if (!$lead_id) {
            return null;
        }

        $table =
            $wpdb->prefix .
            'bcob_leads';

        $lead =
            $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id=%d LIMIT 1",
                    $lead_id
                )
            );

        return $lead ?: null;
    }

    public static function update_submission(
        int $lead_id,
        array $submission
    ): bool {
        global $wpdb;

        if (!$lead_id) {
            return false;
        }

        return false !==
            $wpdb->update(
                $wpdb->prefix .
                    'bcob_leads',
                [
                    'submission' =>
                    wp_json_encode(
                        $submission
                    ),

                    'updated_at' =>
                    current_time(
                        'mysql'
                    ),
                ],
                [
                    'id' =>
                    $lead_id,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );
    }

    public static function get_by_token(
        string $token
    ): ?object {
        global $wpdb;

        $table =
            $wpdb->prefix .
            'bcob_leads';

        $lead =
            $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE token=%s LIMIT 1",
                    $token
                )
            );

        return $lead ?: null;
    }

    public static function update_submission_by_token(
        string $token,
        array $submission
    ): bool {
        global $wpdb;

        if ($token === '') {
            return false;
        }

        return false !==
            $wpdb->update(
                $wpdb->prefix . 'bcob_leads',
                [
                    'submission' =>
                    wp_json_encode(
                        $submission
                    ),

                    'updated_at' =>
                    current_time(
                        'mysql'
                    ),
                ],
                [
                    'token' =>
                    $token,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%s',
                ]
            );
    }

    public static function update_status(
        int $lead_id,
        string $status
    ): bool {
        global $wpdb;

        return (bool)
        $wpdb->update(
            $wpdb->prefix .
                'bcob_leads',
            [
                'status' =>
                $status,

                'updated_at' =>
                current_time(
                    'mysql'
                ),
            ],
            [
                'id' =>
                $lead_id,
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );
    }

    public static function update_membership_number(
        int $lead_id,
        string $membership_number
    ): bool {
        global $wpdb;

        if (!$lead_id || $membership_number === '') {
            return false;
        }

        return false !== $wpdb->update(
            $wpdb->prefix . 'bcob_leads',
            [
                'membership_number' => $membership_number,
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $lead_id,
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );
    }

    public static function update_payment_state(
        int $lead_id,
        string $status
    ): bool {
        if (
            !$lead_id ||
            !in_array($status, self::STATUSES, true)
        ) {
            return false;
        }

        return self::update_status(
            $lead_id,
            $status
        );
    }

    public static function get_by_payment_intent(
        string $payment_intent_id
    ): ?object {
        global $wpdb;

        if ($payment_intent_id === '') {
            return null;
        }

        $table = $wpdb->prefix . 'bcob_payments';

        $lead_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT lead_id
			FROM {$table}
			WHERE payment_intent_id = %s
			ORDER BY id DESC
			LIMIT 1",
                $payment_intent_id
            )
        );

        if (!$lead_id) {
            return null;
        }

        return self::get((int) $lead_id);
    }

    public static function get_by_subscription(
        string $subscription_id
    ): ?object {
        global $wpdb;

        if ($subscription_id === '') {
            return null;
        }

        $table = $wpdb->prefix . 'bcob_payments';

        $lead_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT lead_id
			FROM {$table}
			WHERE subscription_id = %s
			ORDER BY id DESC
			LIMIT 1",
                $subscription_id
            )
        );

        if (!$lead_id) {
            return null;
        }

        return self::get((int) $lead_id);
    }

    private static function get_ip(): string
    {
        return sanitize_text_field(
            $_SERVER['REMOTE_ADDR'] ?? ''
        );
    }
}
