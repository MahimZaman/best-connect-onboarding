<?php

namespace BestConnectOnboarding\Helpers;

defined('ABSPATH') || exit;

class Logger
{
    private const TABLE = 'bcob_logs';

    private const LEVELS = [
        'error',
        'warning',
        'info',
    ];

    private static bool $table_ready = false;

    private static string $operation_id = '';

    public static function error(
        string $channel,
        string $message,
        array $context = []
    ): void {
        self::log(
            'error',
            $channel,
            $message,
            $context
        );
    }

    public static function warning(
        string $channel,
        string $message,
        array $context = []
    ): void {
        self::log(
            'warning',
            $channel,
            $message,
            $context
        );
    }

    public static function info(
        string $channel,
        string $message,
        array $context = []
    ): void {
        self::log(
            'info',
            $channel,
            $message,
            $context
        );
    }

    public static function enabled(): bool
    {
        $settings =
            get_option(
                'bcob_settings',
                []
            );

        if (!is_array($settings)) {
            return true;
        }

        return (
            $settings['bcob_logging_enabled']
            ?? '1'
        ) === '1';
    }

    public static function operation_id(): string
    {
        if (self::$operation_id === '') {
            self::$operation_id =
                wp_generate_uuid4();
        }

        return self::$operation_id;
    }

    public static function table_name(): string
    {
        global $wpdb;

        return
            $wpdb->prefix .
            self::TABLE;
    }

    public static function ensure_table(): bool
    {
        if (self::$table_ready) {
            return true;
        }

        global $wpdb;

        $table =
            self::table_name();

        $exists =
            $wpdb->get_var(
                $wpdb->prepare(
                    'SHOW TABLES LIKE %s',
                    $wpdb->esc_like(
                        $table
                    )
                )
            );

        if ($exists !== $table) {
            return false;
        }

        self::$table_ready = true;

        return true;
    }

    public static function log(
        string $level,
        string $channel,
        string $message,
        array $context = []
    ): void {
        if (!self::enabled()) {
            return;
        }

        if (
            !in_array(
                $level,
                self::LEVELS,
                true
            )
        ) {
            $level = 'info';
        }

        if (!self::ensure_table()) {
            return;
        }

        global $wpdb;

        $safe_context =
            self::sanitize_context(
                $context
            );

        $operation =
            isset(
                $safe_context['operation']
            )
            ? (string)
            $safe_context['operation']
            : '';

        $wpdb->insert(
            self::table_name(),
            [
                'level' =>
                $level,

                'channel' =>
                self::sanitize_identifier(
                    $channel,
                    50
                ),

                'message' =>
                self::sanitize_message(
                    $message
                ),

                'operation' =>
                $operation,

                'operation_id' =>
                self::operation_id(),

                'form_id' =>
                absint(
                    $safe_context['form_id']
                        ?? 0
                ) ?: null,

                'lead_id' =>
                absint(
                    $safe_context['lead_id']
                        ?? 0
                ) ?: null,

                'payment_id' =>
                absint(
                    $safe_context['payment_id']
                        ?? 0
                ) ?: null,

                'context' =>
                !empty($safe_context)
                    ? wp_json_encode(
                        $safe_context
                    )
                    : null,

                'created_at' =>
                current_time('mysql'),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
            ]
        );
    }

    public static function get_logs(
        array $args = []
    ): array {
        if (!self::ensure_table()) {
            return [];
        }

        global $wpdb;

        $level =
            sanitize_key(
                $args['level'] ?? ''
            );

        $channel =
            sanitize_key(
                $args['channel'] ?? ''
            );

        $limit =
            max(
                1,
                min(
                    100,
                    absint(
                        $args['limit'] ?? 50
                    )
                )
            );

        $offset =
            max(
                0,
                absint(
                    $args['offset'] ?? 0
                )
            );

        $where = [];
        $params = [];

        if (
            in_array(
                $level,
                self::LEVELS,
                true
            )
        ) {
            $where[] = 'level = %s';
            $params[] = $level;
        }

        if ($channel !== '') {
            $where[] = 'channel = %s';
            $params[] = $channel;
        }

        $where_sql =
            $where
            ? ' WHERE ' .
            implode(
                ' AND ',
                $where
            )
            : '';

        $sql =
            'SELECT *
            FROM ' .
            self::table_name() .
            $where_sql .
            ' ORDER BY id DESC
            LIMIT %d
            OFFSET %d';

        $params[] = $limit;
        $params[] = $offset;

        $rows =
            $wpdb->get_results(
                $wpdb->prepare(
                    $sql,
                    ...$params
                )
            );

        return is_array($rows)
            ? $rows
            : [];
    }

    public static function count_logs(
        array $args = []
    ): int {
        if (!self::ensure_table()) {
            return 0;
        }

        global $wpdb;

        $level =
            sanitize_key(
                $args['level'] ?? ''
            );

        $channel =
            sanitize_key(
                $args['channel'] ?? ''
            );

        $where = [];
        $params = [];

        if (
            in_array(
                $level,
                self::LEVELS,
                true
            )
        ) {
            $where[] = 'level = %s';
            $params[] = $level;
        }

        if ($channel !== '') {
            $where[] = 'channel = %s';
            $params[] = $channel;
        }

        $where_sql =
            $where
            ? ' WHERE ' .
            implode(
                ' AND ',
                $where
            )
            : '';

        $sql =
            'SELECT COUNT(*)
            FROM ' .
            self::table_name() .
            $where_sql;

        if ($params) {
            $sql =
                $wpdb->prepare(
                    $sql,
                    ...$params
                );
        }

        return (int)
        $wpdb->get_var(
            $sql
        );
    }

    public static function get_channels(): array
    {
        if (!self::ensure_table()) {
            return [];
        }

        global $wpdb;

        $channels =
            $wpdb->get_col(
                'SELECT DISTINCT channel
                FROM ' .
                    self::table_name() .
                    "
                WHERE channel <> ''
                ORDER BY channel ASC"
            );

        return is_array($channels)
            ? array_values($channels)
            : [];
    }

    public static function clear(): bool
    {
        if (!self::ensure_table()) {
            return false;
        }

        global $wpdb;

        return $wpdb->query(
            'TRUNCATE TABLE ' .
                self::table_name()
        ) !== false;
    }

    private static function sanitize_context(
        array $context
    ): array {
        $safe = [];

        $integer_keys = [
            'form_id',
            'lead_id',
            'payment_id',
            'attachment_id',
            'magic_link_id',
            'coupon_id',
            'attempt',
            'max_attempts',
            'http_status',
            'source_id',
            'normalized_id',
        ];

        foreach (
            $integer_keys
            as $key
        ) {
            if (
                !array_key_exists(
                    $key,
                    $context
                )
            ) {
                continue;
            }

            $safe[$key] =
                absint(
                    $context[$key]
                );
        }

        $string_keys = [
            'operation',
            'status',
            'event_type',
            'error_code',
            'payment_type',
            'provider',
            'mode',
        ];

        foreach (
            $string_keys
            as $key
        ) {
            if (
                !array_key_exists(
                    $key,
                    $context
                )
            ) {
                continue;
            }

            if (
                !is_scalar(
                    $context[$key]
                )
            ) {
                continue;
            }

            $safe[$key] =
                self::sanitize_identifier(
                    (string)
                    $context[$key],
                    100
                );
        }

        if (
            array_key_exists(
                'retry_scheduled',
                $context
            )
        ) {
            $safe['retry_scheduled'] =
                !empty($context['retry_scheduled'])
                ? 1
                : 0;
        }

        return $safe;
    }

    private static function sanitize_identifier(
        string $value,
        int $length
    ): string {
        $value =
            strtolower(
                trim($value)
            );

        $value =
            preg_replace(
                '/[^a-z0-9._-]/',
                '_',
                $value
            );

        return substr(
            (string) $value,
            0,
            $length
        );
    }

    private static function sanitize_message(
        string $message
    ): string {
        $message =
            wp_strip_all_tags(
                $message
            );

        $message =
            preg_replace(
                '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
                '[redacted-email]',
                $message
            );

        $message =
            preg_replace(
                '/https?:\/\/\S+/i',
                '[redacted-url]',
                $message
            );

        $message =
            preg_replace(
                '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
                '[redacted-ip]',
                $message
            );

        $message =
            preg_replace(
                '/\b[a-z0-9_-]{32,}\b/i',
                '[redacted-token]',
                $message
            );

        return substr(
            sanitize_text_field(
                (string) $message
            ),
            0,
            1000
        );
    }
}
