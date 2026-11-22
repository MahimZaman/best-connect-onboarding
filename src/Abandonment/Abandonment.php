<?php

namespace BestConnectOnboarding\Abandonment;

use BestConnectOnboarding\Emails\Mailer;

defined('ABSPATH') || exit;

class Abandonment
{
    private const CRON_HOOK = 'bcob_abandonment_cron';
    private const TABLE = 'bcob_abandoned_sessions';
    private const CONSENT_KEY =
    '__bcob_abandonment_consent';

    private const SUPPRESSION_OPTION =
    'bcob_abandonment_suppressed_emails';

    public function __construct()
    {
        add_action(
            'wp_ajax_bcob_abandonment_save',
            [$this, 'save']
        );

        add_action(
            'wp_ajax_nopriv_bcob_abandonment_save',
            [$this, 'save']
        );

        add_action(
            'wp_ajax_bcob_abandonment_resume',
            [$this, 'resume']
        );

        add_action(
            'wp_ajax_nopriv_bcob_abandonment_resume',
            [$this, 'resume']
        );

        add_action(
            'bcob_submission_completed',
            [$this, 'complete'],
            10,
            2
        );

        add_action(
            self::CRON_HOOK,
            [$this, 'process_reminders']
        );

        add_filter(
            'cron_schedules',
            [$this, 'cron_schedule']
        );

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(
                time() + 300,
                'bcob_15_minutes',
                self::CRON_HOOK
            );
        }

        add_action(
            'wp_enqueue_scripts',
            [$this, 'enqueue']
        );

        add_action(
            'wp_ajax_bcob_abandonment_unsubscribe',
            [$this, 'unsubscribe']
        );

        add_action(
            'wp_ajax_nopriv_bcob_abandonment_unsubscribe',
            [$this, 'unsubscribe']
        );
    }

    public function cron_schedule(array $schedules): array
    {
        $schedules['bcob_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => __('Every 15 Minutes', 'best-connect-onboarding'),
        ];

        return $schedules;
    }

    private function should_load_frontend_assets(): bool
    {
        $post =
            is_singular()
            ? get_queried_object()
            : null;

        $should_load = false;

        if ($post instanceof \WP_Post) {
            $should_load =
                $this->contains_bcob_shortcode(
                    $post->post_content
                ) ||
                $this->contains_bcob_shortcode(
                    get_post_meta(
                        $post->ID
                    )
                );
        }

        return (bool) apply_filters(
            'bcob_should_load_frontend_assets',
            $should_load,
            $post
        );
    }

    private function contains_bcob_shortcode(
        mixed $value
    ): bool {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (
                    $this->contains_bcob_shortcode(
                        $item
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        if (is_object($value)) {
            return $this->contains_bcob_shortcode(
                get_object_vars(
                    $value
                )
            );
        }

        if (!is_string($value)) {
            return false;
        }

        $unserialized =
            maybe_unserialize(
                $value
            );

        if ($unserialized !== $value) {
            return $this->contains_bcob_shortcode(
                $unserialized
            );
        }

        return
            has_shortcode(
                $value,
                'bcob_form'
            ) ||
            has_shortcode(
                $value,
                'onboarding_portal'
            );
    }

    public function enqueue(): void
    {
        if (
            is_admin() ||
            !$this->should_load_frontend_assets()
        ) {
            return;
        }

        wp_enqueue_script(
            'bcob-abandonment',
            BCOB_URL . 'assets/js/frontend/abandonment.js',
            ['jquery'],
            BCOB_VER,
            true
        );

        $settings =
            get_option(
                'bcob_settings',
                []
            );

        wp_localize_script(
            'bcob-abandonment',
            'BCOBAbandonment',
            [
                'ajax_url' =>
                admin_url('admin-ajax.php'),

                'nonce' =>
                wp_create_nonce(
                    'bcob_abandonment'
                ),

                'enabled' => (
                    $settings['bcob_abandonment_enabled']
                    ?? '0'
                ) === '1',

                'consent_text' =>
                sanitize_text_field(
                    $settings['bcob_abandonment_consent_text']
                        ??
                        __(
                            'I agree to receive reminder emails about this unfinished onboarding process.',
                            'best-connect-onboarding'
                        )
                ),

                'consent_version' =>
                sanitize_text_field(
                    $settings['bcob_abandonment_consent_version']
                        ?? '1.0'
                ),
            ]
        );
    }

    public function save(): void
    {
        check_ajax_referer(
            'bcob_abandonment',
            'nonce'
        );

        $settings =
            get_option(
                'bcob_settings',
                []
            );

        if (
            (
                $settings['bcob_abandonment_enabled']
                ?? '0'
            ) !== '1'
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'Abandonment reminders are disabled.',
                        'best-connect-onboarding'
                    ),
                ],
                403
            );
        }

        $consent_given =
            isset($_POST['consent']) &&
            sanitize_text_field(
                wp_unslash(
                    $_POST['consent']
                )
            ) === '1';

        $consent_text =
            sanitize_text_field(
                $settings['bcob_abandonment_consent_text']
                    ?? __(
                        'I agree to receive reminder emails about this unfinished onboarding process.',
                        'best-connect-onboarding'
                    )
            );

        $consent_version =
            sanitize_text_field(
                $settings['bcob_abandonment_consent_version']
                    ?? '1.0'
            );

        if (
            !$consent_given ||
            $consent_text === '' ||
            $consent_version === ''
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'Reminder consent is required.',
                        'best-connect-onboarding'
                    ),
                ],
                400
            );
        }

        $email = sanitize_email(
            wp_unslash($_POST['email'] ?? '')
        );

        $form_id = absint(
            $_POST['form_id'] ?? 0
        );

        $step = max(
            1,
            absint($_POST['step'] ?? 1)
        );

        $page_url = esc_url_raw(
            wp_unslash($_POST['page_url'] ?? '')
        );

        $values = json_decode(
            wp_unslash($_POST['values'] ?? '{}'),
            true
        );

        if (
            !$email ||
            !is_email($email) ||
            !$form_id ||
            !is_array($values)
        ) {
            wp_send_json_error(
                [
                    'message' => __(
                        'Invalid abandonment data.',
                        'best-connect-onboarding'
                    ),
                ],
                400
            );
        }

        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        $token = sanitize_text_field(
            wp_unslash($_POST['token'] ?? '')
        );

        if ($token) {

            $session = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                    FROM {$table}
                    WHERE token = %s
                    LIMIT 1",
                    $token
                )
            );

            if ($session) {

                $existing_data =
                    json_decode(
                        $session->form_data,
                        true
                    );

                $existing_consent =
                    is_array($existing_data) &&
                    is_array(
                        $existing_data[self::CONSENT_KEY] ?? null
                    )
                    ? $existing_data[self::CONSENT_KEY]
                    : [];

                $consent_timestamp =
                    (
                        (
                            $existing_consent['version']
                            ?? ''
                        ) === $consent_version &&
                        !empty($existing_consent['timestamp'])
                    )
                    ? $existing_consent['timestamp']
                    : current_time('mysql');

                $values[self::CONSENT_KEY] = [
                    'granted' =>
                    true,

                    'timestamp' =>
                    $consent_timestamp,

                    'version' =>
                    $consent_version,

                    'text' =>
                    $consent_text,
                ];

                $session_status =
                    self::is_email_suppressed(
                        $email
                    )
                    ? 'unsubscribed'
                    : 'pending';

                if (
                    (int) $session->form_id !== $form_id
                ) {
                    wp_send_json_error(
                        [
                            'message' => __(
                                'Invalid resume session.',
                                'best-connect-onboarding'
                            ),
                        ],
                        403
                    );
                }

                $stored_host =
                    wp_parse_url(
                        $session->page_url,
                        PHP_URL_HOST
                    );

                $current_host =
                    wp_parse_url(
                        $page_url,
                        PHP_URL_HOST
                    );

                if (
                    $stored_host &&
                    $current_host &&
                    strtolower($stored_host) !==
                    strtolower($current_host)
                ) {
                    $page_url = $session->page_url;
                }

                if (
                    $session->status === 'completed'
                ) {
                    wp_send_json_error(
                        [
                            'message' => __(
                                'This session has already been completed.',
                                'best-connect-onboarding'
                            ),
                        ],
                        409
                    );
                }

                $wpdb->update(
                    $table,
                    [
                        'email'        => $email,
                        'form_data'    => wp_json_encode($values),
                        'current_step' => $step,
                        'page_url'     => $page_url,
                        'updated_at'   => current_time('mysql'),
                        'status' => $session_status,
                    ],
                    [
                        'id' => $session->id,
                    ],
                    [
                        '%s',
                        '%s',
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                    ],
                    [
                        '%d',
                    ]
                );

                wp_send_json_success(
                    [
                        'token' => $token,
                    ]
                );
            }
        }

        $token = bin2hex(
            random_bytes(32)
        );

        $now = current_time('mysql');

        $values[self::CONSENT_KEY] = [
            'granted' => true,
            'timestamp' => $now,
            'version' => $consent_version,
            'text' => $consent_text,
        ];

        $session_status = self::is_email_suppressed($email) ? 'unsubscribed' : 'pending';

        $inserted = $wpdb->insert(
            $table,
            [
                'form_id'             => $form_id,
                'email'               => $email,
                'token'               => $token,
                'form_data'           => wp_json_encode($values),
                'current_step'        => $step,
                'page_url'            => $page_url,
                'status'              => $session_status,
                'reminder_1_sent_at'  => null,
                'reminder_2_sent_at'  => null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            wp_send_json_error(
                [
                    'message' => __(
                        'Unable to save the onboarding session.',
                        'best-connect-onboarding'
                    ),
                ],
                500
            );
        }

        wp_send_json_success(
            [
                'token' => $token,
            ]
        );
    }

    public function resume(): void
    {
        check_ajax_referer(
            'bcob_abandonment',
            'nonce'
        );

        $token = sanitize_text_field(
            wp_unslash($_POST['token'] ?? '')
        );

        if (
            !preg_match('/^[a-f0-9]{64}$/', $token)
        ) {
            wp_send_json_error(
                [
                    'message' => __(
                        'Invalid resume link.',
                        'best-connect-onboarding'
                    ),
                ],
                400
            );
        }

        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        $session = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$table}
                WHERE token = %s
                LIMIT 1",
                $token
            )
        );

        if (!$session) {
            wp_send_json_error(
                [
                    'message' => __(
                        'Resume session not found.',
                        'best-connect-onboarding'
                    ),
                ],
                404
            );
        }

        if ($session->status === 'completed') {
            wp_send_json_error(
                [
                    'message' => __(
                        'This onboarding has already been completed.',
                        'best-connect-onboarding'
                    ),
                ],
                409
            );
        }

        if (
            empty($session->updated_at) ||
            current_time('timestamp') -
            strtotime($session->updated_at) >
            DAY_IN_SECONDS * 30
        ) {
            wp_send_json_error(
                [
                    'message' => __(
                        'This resume link has expired.',
                        'best-connect-onboarding'
                    ),
                ],
                410
            );
        }

        $values =
            json_decode(
                $session->form_data,
                true
            );

        $consent = [];

        if (is_array($values)) {
            $consent =
                is_array(
                    $values[self::CONSENT_KEY]
                        ?? null
                )
                ? $values[self::CONSENT_KEY]
                : [];

            unset(
                $values[self::CONSENT_KEY]
            );
        }

        wp_send_json_success(
            [
                'token'        => $session->token,
                'form_id'      => (int) $session->form_id,
                'email'        => $session->email,
                'values'       => is_array($values) ? $values : [],
                'current_step' => (int) $session->current_step,
                'consent_granted' => !empty($consent['granted']),
                'consent_version' => sanitize_text_field($consent['version'] ?? ''),
            ]
        );
    }

    public function complete(
        int $lead_id,
        array $submission
    ): void {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        $token = sanitize_text_field(
            $submission['abandonment_token'] ?? ''
        );

        if (!$token) {
            return;
        }

        $wpdb->update(
            $table,
            [
                'status'     => 'completed',
                'updated_at' => current_time('mysql'),
            ],
            [
                'token' => $token,
                'status' => 'pending',
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%s',
                '%s',
            ]
        );
    }

    public function process_reminders(): void
    {
        $settings = get_option(
            'bcob_settings',
            []
        );

        if (
            ($settings['bcob_abandonment_enabled'] ?? '0') !== '1'
        ) {
            return;
        }

        $delay_1 = max(
            1,
            absint(
                $settings['bcob_abandonment_delay_1'] ?? 2
            )
        );

        $delay_2 = max(
            1,
            absint(
                $settings['bcob_abandonment_delay_2'] ?? 2
            )
        );

        $max_reminders =
            max(
                1,
                min(
                    2,
                    absint(
                        $settings['bcob_abandonment_max_reminders']
                            ?? 2
                    )
                )
            );

        $template = $settings['bcob_abandonment_email'] ?? '';

        if (!$template) {
            return;
        }

        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        $sessions = $wpdb->get_results(
            "SELECT *
            FROM {$table}
            WHERE status = 'pending'
            ORDER BY updated_at ASC"
        );

        if (empty($sessions)) {
            return;
        }

        $now = current_time('timestamp');

        foreach ($sessions as $session) {

            if (
                self::is_email_suppressed(
                    $session->email
                )
            ) {
                $wpdb->update(
                    $table,
                    [
                        'status' =>
                        'unsubscribed',

                        'updated_at' =>
                        current_time('mysql'),
                    ],
                    [
                        'id' =>
                        $session->id,
                    ],
                    [
                        '%s',
                        '%s',
                    ],
                    [
                        '%d',
                    ]
                );

                continue;
            }

            if (
                !self::has_valid_consent(
                    $session
                )
            ) {
                continue;
            }

            if (
                (int) $session->current_step <= 1
            ) {
                continue;
            }

            $sent_for_email =
                self::get_email_reminder_count(
                    $session->email
                );

            if (
                $sent_for_email >=
                $max_reminders
            ) {
                continue;
            }

            $updated = strtotime(
                $session->updated_at
            );

            if (!$updated) {
                continue;
            }

            $age = $now - $updated;

            if (
                empty($session->reminder_1_sent_at) &&
                $age >= ($delay_1 * HOUR_IN_SECONDS)
            ) {

                if ($this->send_reminder($session, $template)) {

                    $wpdb->update(
                        $table,
                        [
                            'reminder_1_sent_at' => current_time('mysql'),
                        ],
                        [
                            'id' => $session->id,
                        ],
                        [
                            '%s',
                        ],
                        [
                            '%d',
                        ]
                    );
                }

                continue;
            }

            if ($max_reminders < 2) {
                continue;
            }

            if (
                self::get_email_reminder_count(
                    $session->email
                ) >= $max_reminders
            ) {
                continue;
            }

            if (
                !empty($session->reminder_1_sent_at) &&
                empty($session->reminder_2_sent_at)
            ) {

                $reminder_1 = strtotime(
                    $session->reminder_1_sent_at
                );

                if (
                    $reminder_1 &&
                    ($now - $reminder_1) >= ($delay_2 * DAY_IN_SECONDS)
                ) {

                    if ($this->send_reminder($session, $template)) {

                        $wpdb->update(
                            $table,
                            [
                                'reminder_2_sent_at' => current_time('mysql'),
                            ],
                            [
                                'id' => $session->id,
                            ],
                            [
                                '%s',
                            ],
                            [
                                '%d',
                            ]
                        );
                    }
                }
            }
        }
    }

    private function send_reminder(
        object $session,
        string $template
    ): bool {
        $magic_link = add_query_arg(
            'resume',
            $session->token,
            $session->page_url
        );

        $content = str_replace(
            '{{magic_link}}',
            esc_url($magic_link),
            $template
        );

        $unsubscribe_link =
            self::get_unsubscribe_url(
                $session
            );

        $content .=
            '<p><a href="' .
            esc_url(
                $unsubscribe_link
            ) .
            '">' .
            esc_html__(
                'Unsubscribe from onboarding reminders',
                'best-connect-onboarding'
            ) .
            '</a></p>';

        $headers =
            Mailer::get_headers(
                [
                    'form' => [
                        'id' =>
                        (int) $session->form_id,
                    ],
                ]
            );

        return wp_mail(
            $session->email,
            __('Continue your onboarding', 'best-connect-onboarding'),
            wpautop($content),
            $headers
        );
    }

    public function unsubscribe(): void
    {
        $token =
            sanitize_text_field(
                wp_unslash(
                    $_GET['token'] ?? ''
                )
            );

        $access =
            sanitize_text_field(
                wp_unslash(
                    $_GET['access'] ?? ''
                )
            );

        if (
            !preg_match(
                '/^[a-f0-9]{64}$/',
                $token
            ) ||
            !$access
        ) {
            wp_die(
                esc_html__(
                    'Invalid unsubscribe link.',
                    'best-connect-onboarding'
                ),
                '',
                [
                    'response' => 400,
                ]
            );
        }

        $expected =
            self::get_unsubscribe_signature(
                $token
            );

        if (
            !hash_equals(
                $expected,
                $access
            )
        ) {
            wp_die(
                esc_html__(
                    'Invalid unsubscribe link.',
                    'best-connect-onboarding'
                ),
                '',
                [
                    'response' => 403,
                ]
            );
        }

        global $wpdb;

        $table =
            $wpdb->prefix .
            self::TABLE;

        $session =
            $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                FROM {$table}
                WHERE token = %s
                LIMIT 1",
                    $token
                )
            );

        if (!$session) {
            wp_die(
                esc_html__(
                    'Unsubscribe request could not be found.',
                    'best-connect-onboarding'
                ),
                '',
                [
                    'response' => 404,
                ]
            );
        }

        self::suppress_email(
            $session->email
        );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
            SET status = 'unsubscribed',
                updated_at = %s
            WHERE email = %s
            AND status = 'pending'",
                current_time('mysql'),
                $session->email
            )
        );

        wp_die(
            esc_html__(
                'You have been unsubscribed from onboarding reminder emails.',
                'best-connect-onboarding'
            ),
            esc_html__(
                'Unsubscribed',
                'best-connect-onboarding'
            ),
            [
                'response' => 200,
            ]
        );
    }

    private static function get_unsubscribe_signature(
        string $token
    ): string {
        return hash_hmac(
            'sha256',
            'unsubscribe|' . $token,
            wp_salt('auth')
        );
    }

    private static function get_unsubscribe_url(
        object $session
    ): string {
        return add_query_arg(
            [
                'action' =>
                'bcob_abandonment_unsubscribe',

                'token' =>
                $session->token,

                'access' =>
                self::get_unsubscribe_signature(
                    $session->token
                ),
            ],
            admin_url(
                'admin-ajax.php'
            )
        );
    }

    private static function email_hash(
        string $email
    ): string {
        return hash_hmac(
            'sha256',
            strtolower(
                trim($email)
            ),
            wp_salt('auth')
        );
    }

    private static function suppress_email(
        string $email
    ): void {
        $hash =
            self::email_hash(
                $email
            );

        $suppressed =
            get_option(
                self::SUPPRESSION_OPTION,
                []
            );

        if (!is_array($suppressed)) {
            $suppressed = [];
        }

        $suppressed[$hash] =
            current_time('mysql');

        update_option(
            self::SUPPRESSION_OPTION,
            $suppressed,
            false
        );
    }

    private static function is_email_suppressed(
        string $email
    ): bool {
        $suppressed =
            get_option(
                self::SUPPRESSION_OPTION,
                []
            );

        if (!is_array($suppressed)) {
            return false;
        }

        return array_key_exists(
            self::email_hash($email),
            $suppressed
        );
    }

    private static function has_valid_consent(
        object $session
    ): bool {
        $data =
            json_decode(
                $session->form_data,
                true
            );

        if (!is_array($data)) {
            return false;
        }

        $consent =
            $data[self::CONSENT_KEY]
            ?? null;

        return (
            is_array($consent) &&
            !empty($consent['granted']) &&
            !empty($consent['timestamp']) &&
            !empty($consent['version']) &&
            !empty($consent['text'])
        );
    }

    private static function get_email_reminder_count(
        string $email
    ): int {
        global $wpdb;

        $table =
            $wpdb->prefix .
            self::TABLE;

        $rows =
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT
                    reminder_1_sent_at,
                    reminder_2_sent_at
                FROM {$table}
                WHERE email = %s",
                    $email
                )
            );

        $count = 0;

        foreach ($rows as $row) {
            if (
                !empty($row->reminder_1_sent_at)
            ) {
                $count++;
            }

            if (
                !empty($row->reminder_2_sent_at)
            ) {
                $count++;
            }
        }

        return $count;
    }

    private function find_email(
        array $submission
    ): string {
        foreach ($submission as $key => $field) {

            if (
                !is_array($field)
            ) {
                continue;
            }

            $type = $field['type'] ?? '';
            $value = $field['value'] ?? '';

            if (
                $type === 'email' &&
                is_scalar($value)
            ) {
                return sanitize_email(
                    (string) $value
                );
            }

            if (
                is_string($key) &&
                strpos(strtolower($key), 'email') !== false &&
                is_scalar($value)
            ) {
                $email = sanitize_email(
                    (string) $value
                );

                if (is_email($email)) {
                    return $email;
                }
            }
        }

        return '';
    }
}
