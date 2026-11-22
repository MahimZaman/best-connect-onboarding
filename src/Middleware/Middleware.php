<?php

namespace BestConnectOnboarding\Middleware;

use BestConnectOnboarding\Leads\Lead;
use BestConnectOnboarding\Helpers\Logger;

defined('ABSPATH') || exit;

class Middleware
{
    private const RETRY_HOOK = 'bcob_middleware_retry';
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY = 15 * MINUTE_IN_SECONDS;

    public function __construct()
    {
        add_action(
            self::RETRY_HOOK,
            [self::class, 'retry'],
            10,
            1
        );
    }

    public static function post(
        array $payload,
        int $lead_id = 0
    ): array {
        $previous_attempts =
            $lead_id
            ? self::get_attempt_count(
                $lead_id
            )
            : 0;

        if (
            !Payload::has_valid_total_gross_amount(
                $payload
            )
        ) {
            $message =
                __(
                    'DATEV payload was not sent because totalGrossAmount is missing or invalid.',
                    'best-connect-onboarding'
                );

            if ($lead_id) {
                wp_clear_scheduled_hook(
                    self::RETRY_HOOK,
                    [$lead_id]
                );

                self::save_state(
                    $lead_id,
                    [
                        'status' =>
                        'failed',

                        'attempts' =>
                        $previous_attempts,

                        'max_attempts' =>
                        self::MAX_ATTEMPTS,

                        'http_status' =>
                        0,

                        'last_error' =>
                        $message,

                        'last_attempt_at' =>
                        '',

                        'next_retry_at' =>
                        '',

                        'completed_at' =>
                        '',
                    ]
                );
            }

            Logger::error(
                'middleware',
                $message,
                [
                    'operation' =>
                    'datev_delivery',

                    'lead_id' =>
                    $lead_id,

                    'reason' =>
                    'invalid_total_gross_amount',
                ]
            );

            $result =
                self::error_response(
                    $message
                );

            $result['attempts'] =
                $previous_attempts;

            $result['retry_scheduled'] =
                false;

            return $result;
        }

        $attempt =
            $lead_id
            ? $previous_attempts + 1
            : 1;

        $result =
            self::send_request(
                $payload
            );

        if (!$lead_id) {
            return $result;
        }

        if (!empty($result['success'])) {
            wp_clear_scheduled_hook(
                self::RETRY_HOOK,
                [$lead_id]
            );

            self::save_state(
                $lead_id,
                [
                    'status' =>
                    'success',

                    'attempts' =>
                    $attempt,

                    'max_attempts' =>
                    self::MAX_ATTEMPTS,

                    'http_status' =>
                    absint(
                        $result['status'] ?? 0
                    ),

                    'last_error' =>
                    '',

                    'last_attempt_at' =>
                    current_time('mysql'),

                    'next_retry_at' =>
                    '',

                    'completed_at' =>
                    current_time('mysql'),
                ]
            );

            $result['attempts'] =
                $attempt;

            $result['retry_scheduled'] =
                false;

            Logger::info(
                'middleware',
                'Middleware delivery completed successfully.',
                [
                    'operation' =>
                    'datev_delivery',

                    'lead_id' =>
                    $lead_id,

                    'attempt' =>
                    $attempt,

                    'max_attempts' =>
                    self::MAX_ATTEMPTS,

                    'http_status' =>
                    absint(
                        $result['status']
                            ?? 0
                    ),
                ]
            );

            return $result;
        }

        $message =
            sanitize_text_field(
                $result['message'] ??
                    __(
                        'Middleware request failed.',
                        'best-connect-onboarding'
                    )
            );

        $diagnostic =
            sanitize_text_field(
                $result['diagnostic']
                    ?? ''
            );

        if ($diagnostic !== '') {
            $message =
                trim(
                    $message .
                        ' ' .
                        $diagnostic
                );
        }

        if ($attempt < self::MAX_ATTEMPTS) {
            $next =
                wp_next_scheduled(
                    self::RETRY_HOOK,
                    [$lead_id]
                );

            if (!$next) {
                $next =
                    time() +
                    self::RETRY_DELAY;

                $scheduled =
                    wp_schedule_single_event(
                        $next,
                        self::RETRY_HOOK,
                        [$lead_id],
                        true
                    );

                if (
                    is_wp_error($scheduled) ||
                    !$scheduled
                ) {
                    self::save_state(
                        $lead_id,
                        [
                            'status' =>
                            'failed',

                            'attempts' =>
                            $attempt,

                            'max_attempts' =>
                            self::MAX_ATTEMPTS,

                            'http_status' =>
                            absint(
                                $result['status'] ?? 0
                            ),

                            'last_error' =>
                            $message,

                            'last_attempt_at' =>
                            current_time('mysql'),

                            'next_retry_at' =>
                            '',
                        ]
                    );

                    Logger::error(
                        'middleware',
                        'Middleware retry could not be scheduled.',
                        [
                            'operation' =>
                            'datev_delivery',

                            'lead_id' =>
                            $lead_id,

                            'attempt' =>
                            $attempt,

                            'max_attempts' =>
                            self::MAX_ATTEMPTS,

                            'http_status' =>
                            absint(
                                $result['status']
                                    ?? 0
                            ),
                        ]
                    );

                    self::notify_final_failure(
                        $lead_id
                    );

                    $result['attempts'] =
                        $attempt;

                    $result['retry_scheduled'] =
                        false;

                    return $result;
                }
            }

            self::save_state(
                $lead_id,
                [
                    'status' =>
                    'retry_scheduled',

                    'attempts' =>
                    $attempt,

                    'max_attempts' =>
                    self::MAX_ATTEMPTS,

                    'http_status' =>
                    absint(
                        $result['status'] ?? 0
                    ),

                    'last_error' =>
                    $message,

                    'last_attempt_at' =>
                    current_time('mysql'),

                    'next_retry_at' =>
                    wp_date(
                        'Y-m-d H:i:s',
                        $next,
                        wp_timezone()
                    ),
                ]
            );

            $result['attempts'] =
                $attempt;

            $result['retry_scheduled'] =
                true;

            Logger::warning(
                'middleware',
                'Middleware delivery failed and a retry was scheduled.',
                [
                    'operation' =>
                    'datev_delivery',

                    'lead_id' =>
                    $lead_id,

                    'attempt' =>
                    $attempt,

                    'max_attempts' =>
                    self::MAX_ATTEMPTS,

                    'http_status' =>
                    absint(
                        $result['status']
                            ?? 0
                    ),

                    'retry_scheduled' =>
                    true,
                ]
            );

            return $result;
        }

        self::save_state(
            $lead_id,
            [
                'status' =>
                'failed',

                'attempts' =>
                $attempt,

                'max_attempts' =>
                self::MAX_ATTEMPTS,

                'http_status' =>
                absint(
                    $result['status'] ?? 0
                ),

                'last_error' =>
                $message,

                'last_attempt_at' =>
                current_time('mysql'),

                'next_retry_at' =>
                '',
            ]
        );

        Logger::error(
            'middleware',
            'Middleware delivery failed after all attempts.',
            [
                'operation' =>
                'datev_delivery',

                'lead_id' =>
                $lead_id,

                'attempt' =>
                $attempt,

                'max_attempts' =>
                self::MAX_ATTEMPTS,

                'http_status' =>
                absint(
                    $result['status']
                        ?? 0
                ),
            ]
        );

        self::notify_final_failure(
            $lead_id
        );

        $result['attempts'] =
            $attempt;

        $result['retry_scheduled'] =
            false;

        return $result;
    }

    public static function retry(
        int $lead_id
    ): void {
        $lead =
            Lead::get(
                absint($lead_id)
            );

        if (!$lead) {
            return;
        }

        $submission =
            json_decode(
                $lead->submission ?? '',
                true
            );

        if (!is_array($submission)) {
            return;
        }

        $state =
            $submission['middleware']
            ?? [];

        if (
            ($state['status'] ?? '') ===
            'success'
        ) {
            return;
        }

        if (
            absint(
                $state['attempts'] ?? 0
            ) >= self::MAX_ATTEMPTS
        ) {
            return;
        }

        self::post(
            Payload::build(
                $submission
            ),
            (int) $lead->id
        );
    }

    private static function send_request(
        array $payload
    ): array {
        $settings =
            get_option(
                'bcob_settings',
                []
            );

        if (!is_array($settings)) {
            return self::error_response(
                __(
                    'Middleware settings are invalid.',
                    'best-connect-onboarding'
                )
            );
        }

        $api_key =
            isset(
                $settings['bcob_datev_api_key']
            )
            ? trim(
                (string)
                $settings['bcob_datev_api_key']
            )
            : '';

        $url =
            isset(
                $settings['bcob_datev_url']
            )
            ? trim(
                (string)
                $settings['bcob_datev_url']
            )
            : '';

        if (
            $api_key === '' ||
            $url === ''
        ) {
            return self::error_response(
                __(
                    'Middleware API settings are incomplete.',
                    'best-connect-onboarding'
                )
            );
        }

        $parsed_url =
            wp_parse_url(
                $url
            );

        if (
            !is_array($parsed_url) ||
            empty($parsed_url['host'])
        ) {
            return self::error_response(
                __(
                    'Invalid middleware API URL.',
                    'best-connect-onboarding'
                )
            );
        }

        $scheme =
            strtolower(
                (string) (
                    $parsed_url['scheme']
                    ?? ''
                )
            );

        $host =
            strtolower(
                rtrim(
                    (string) $parsed_url['host'],
                    '.'
                )
            );

        $is_localhost =
            in_array(
                $host,
                [
                    'localhost',
                    '127.0.0.1',
                    '::1',
                ],
                true
            );

        if ($is_localhost) {
            if (
                !in_array(
                    $scheme,
                    [
                        'http',
                        'https',
                    ],
                    true
                )
            ) {
                return self::error_response(
                    __(
                        'Invalid middleware API URL.',
                        'best-connect-onboarding'
                    )
                );
            }
        } else {
            if ($scheme !== 'https') {
                return self::error_response(
                    __(
                        'Middleware API URL must use HTTPS.',
                        'best-connect-onboarding'
                    )
                );
            }

            if (
                wp_http_validate_url(
                    $url
                ) === false
            ) {
                return self::error_response(
                    __(
                        'Invalid or unsafe middleware API URL.',
                        'best-connect-onboarding'
                    )
                );
            }
        }

        $url =
            untrailingslashit(
                $url
            ) .
            '/adhocInvoiceRuns';

        $response =
            wp_remote_post(
                $url,
                [
                    'timeout' =>
                    30,

                    'redirection' =>
                    0,

                    'headers' => [
                        'Content-Type' =>
                        'application/json',

                        'X-API-Key' =>
                        $api_key,

                        'Accept' =>
                        'application/json',
                    ],

                    'body' =>
                    wp_json_encode(
                        $payload
                    ),
                ]
            );

        if (
            is_wp_error(
                $response
            )
        ) {
            return self::error_response(
                __(
                    'Unable to connect to the middleware API.',
                    'best-connect-onboarding'
                ),
                $response->get_error_message()
            );
        }

        $status_code =
            wp_remote_retrieve_response_code(
                $response
            );

        $body =
            wp_remote_retrieve_body(
                $response
            );

        $decoded =
            json_decode(
                $body,
                true
            );

        if (
            $status_code < 200 ||
            $status_code >= 300
        ) {
            $result =
                self::error_response(
                    __(
                        'The middleware API returned an error.',
                        'best-connect-onboarding'
                    ),
                    is_array($decoded)
                        ? $decoded
                        : $body,
                    $status_code
                );

            $result['diagnostic'] =
                self::build_error_diagnostic(
                    $decoded,
                    $status_code,
                    $payload
                );

            return $result;
        }

        return [
            'success' =>
            true,

            'message' =>
            __(
                'Middleware request completed successfully.',
                'best-connect-onboarding'
            ),

            'data' =>
            is_array($decoded)
                ? $decoded
                : [],

            'status' =>
            $status_code,
        ];
    }

    private static function build_error_diagnostic(
        mixed $decoded,
        int $status_code,
        array $payload
    ): string {
        $parts = [];

        if ($status_code > 0) {
            $parts[] =
                'HTTP ' .
                absint($status_code);
        }

        if (!is_array($decoded)) {
            return implode(
                ' · ',
                $parts
            );
        }

        $code =
            self::find_error_metadata(
                $decoded,
                [
                    'code',
                    'errorcode',
                ]
            );

        if ($code !== '') {
            $parts[] =
                'code: ' .
                $code;
        }

        $field =
            self::find_error_metadata(
                $decoded,
                [
                    'field',
                    'fieldname',
                    'parameter',
                    'param',
                    'path',
                    'property',
                ]
            );

        if (
            $field !== '' &&
            self::is_payload_field_reference(
                $field,
                $payload
            )
        ) {
            $parts[] =
                'field: ' .
                $field;
        }

        return implode(
            ' · ',
            $parts
        );
    }

    private static function find_error_metadata(
        array $data,
        array $wanted_keys
    ): string {
        foreach ($data as $key => $value) {
            $normalized_key =
                strtolower(
                    preg_replace(
                        '/[^a-z0-9]/i',
                        '',
                        (string) $key
                    )
                );

            if (
                in_array(
                    $normalized_key,
                    $wanted_keys,
                    true
                ) &&
                is_scalar($value)
            ) {
                $value =
                    self::sanitize_error_metadata(
                        (string) $value
                    );

                if ($value !== '') {
                    return $value;
                }
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $found =
                self::find_error_metadata(
                    $value,
                    $wanted_keys
                );

            if ($found !== '') {
                return $found;
            }
        }

        return '';
    }

    private static function sanitize_error_metadata(
        string $value
    ): string {
        $value =
            trim(
                wp_strip_all_tags(
                    $value
                )
            );

        if (
            $value === '' ||
            strlen($value) > 120
        ) {
            return '';
        }

        if (
            !preg_match(
                '/^[A-Za-z0-9_.:\/\-\[\]\$]+$/',
                $value
            )
        ) {
            return '';
        }

        return $value;
    }

    private static function is_payload_field_reference(
        string $field,
        array $payload
    ): bool {
        $allowed = [];

        self::collect_payload_fields(
            $payload,
            '',
            $allowed
        );

        $field_normalized =
            strtolower(
                trim(
                    $field,
                    '$./'
                )
            );

        if (
            isset(
                $allowed[$field_normalized]
            )
        ) {
            return true;
        }

        $segments =
            preg_split(
                '/[.\/\[\]]+/',
                $field_normalized,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

        if (!$segments) {
            return false;
        }

        $last =
            end($segments);

        return isset(
            $allowed[strtolower(
                    (string) $last
                )]
        );
    }

    private static function collect_payload_fields(
        array $data,
        string $prefix,
        array &$allowed
    ): void {
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                if (is_array($value)) {
                    self::collect_payload_fields(
                        $value,
                        $prefix,
                        $allowed
                    );
                }

                continue;
            }

            $key_normalized =
                strtolower($key);

            $path =
                $prefix !== ''
                ? $prefix . '.' . $key
                : $key;

            $allowed[$key_normalized] =
                true;

            $allowed[strtolower($path)] = true;

            if (is_array($value)) {
                self::collect_payload_fields(
                    $value,
                    $path,
                    $allowed
                );
            }
        }
    }

    private static function get_attempt_count(
        int $lead_id
    ): int {
        $lead =
            Lead::get(
                $lead_id
            );

        if (!$lead) {
            return 0;
        }

        $submission =
            json_decode(
                $lead->submission ?? '',
                true
            );

        if (!is_array($submission)) {
            return 0;
        }

        return absint(
            $submission['middleware']['attempts']
                ?? 0
        );
    }

    private static function save_state(
        int $lead_id,
        array $state
    ): bool {
        $lead =
            Lead::get(
                $lead_id
            );

        if (!$lead) {
            return false;
        }

        $submission =
            json_decode(
                $lead->submission ?? '',
                true
            );

        if (!is_array($submission)) {
            return false;
        }

        $current =
            is_array(
                $submission['middleware']
                    ?? null
            )
            ? $submission['middleware']
            : [];

        $submission['middleware'] =
            array_merge(
                $current,
                $state
            );

        return Lead::update_submission(
            $lead_id,
            $submission
        );
    }

    private static function notify_final_failure(
        int $lead_id
    ): void {
        $lead =
            Lead::get(
                $lead_id
            );

        if (!$lead) {
            return;
        }

        $submission =
            json_decode(
                $lead->submission ?? '',
                true
            );

        if (!is_array($submission)) {
            return;
        }

        $state =
            is_array(
                $submission['middleware']
                    ?? null
            )
            ? $submission['middleware']
            : [];

        if (
            !empty($state['notified_at'])
        ) {
            return;
        }

        $settings =
            get_option(
                'bcob_settings',
                []
            );

        $email =
            sanitize_email(
                $settings['admin_debug_email']
                    ?? get_option('admin_email')
            );

        if (!is_email($email)) {
            return;
        }

        $subject =
            sprintf(
                '[BCOB] DATEV middleware failed for Lead #%d',
                $lead_id
            );

        $body =
            sprintf(
                "DATEV middleware delivery failed.\n\nLead ID: %d\nAttempts: %d/%d\nHTTP Status: %d\nError: %s",
                $lead_id,
                absint(
                    $state['attempts'] ?? 0
                ),
                self::MAX_ATTEMPTS,
                absint(
                    $state['http_status'] ?? 0
                ),
                sanitize_text_field(
                    $state['last_error']
                        ?? ''
                )
            );

        $sent =
            wp_mail(
                $email,
                $subject,
                $body
            );

        self::save_state(
            $lead_id,
            [
                'notified_at' =>
                $sent
                    ? current_time('mysql')
                    : '',

                'notification_status' =>
                $sent
                    ? 'sent'
                    : 'failed',
            ]
        );

        if (!$sent) {
            Logger::error(
                'middleware',
                'Middleware final failure notification could not be sent.',
                [
                    'operation' =>
                    'final_failure_notification',

                    'lead_id' =>
                    $lead_id,
                ]
            );
        }
    }

    private static function error_response(
        string $message,
        mixed $error = null,
        int $status = 0
    ): array {
        $response = [
            'success' =>
            false,

            'message' =>
            $message,

            'errors' =>
            [],

            'status' =>
            $status,
        ];

        if ($error !== null) {
            $response['errors'][] =
                $error;
        }

        return $response;
    }
}
