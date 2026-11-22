<?php

namespace BestConnectOnboarding\MagicLinks;

defined('ABSPATH') || exit;

class MagicLinkRepository
{
    private static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'bcob_magic_links';
    }

    public static function create(
        int $lead_id,
        string $token,
        string $expires_at
    ): int {
        global $wpdb;

        $result = $wpdb->insert(
            self::table(),
            [
                'lead_id'            => $lead_id,
                'token_hash'         => hash('sha256', $token),
                'expires_at'         => $expires_at,
                'is_used'            => 0,
                'used_at'            => null,
                'signature_ip'       => null,
                'signature_datetime' => null,
                'created_at'         => current_time('mysql'),
                'updated_at'         => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($result === false) {
            throw new \RuntimeException(
                $wpdb->last_error ?: 'Unable to create Magic Link.'
            );
        }

        return (int) $wpdb->insert_id;
    }

    public static function get_by_token(string $token): ?object
    {
        global $wpdb;

        if ($token === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() . " WHERE token_hash = %s LIMIT 1",
                hash('sha256', $token)
            )
        );

        return $row ?: null;
    }

    public static function mark_used(
        int $id,
        string $ip,
        string $datetime
    ): bool {
        global $wpdb;

        $updated =
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE " . self::table() . "
                SET
                    is_used = 1,
                    used_at = %s,
                    signature_ip = %s,
                    signature_datetime = %s,
                    updated_at = %s
                WHERE id = %d
                AND is_used = 0",
                    current_time('mysql'),
                    $ip,
                    $datetime,
                    current_time('mysql'),
                    $id
                )
            );

        return $updated === 1;
    }

    public static function get_active_for_lead(int $lead_id): ?object
    {
        global $wpdb;

        if (!$lead_id) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() . "
                 WHERE lead_id = %d
                 AND is_used = 0
                 ORDER BY id DESC
                 LIMIT 1",
                $lead_id
            )
        );

        return $row ?: null;
    }
}
