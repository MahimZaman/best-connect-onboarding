<?php

namespace BestConnectOnboarding\Membership;

use BestConnectOnboarding\Leads\Lead;

defined('ABSPATH') || exit;

class MembershipNumber
{
    public static function allocate(int $lead_id): string
    {
        global $wpdb;

        $lead = Lead::get($lead_id);

        if (!$lead) {
            throw new \RuntimeException('Lead not found.');
        }

        if (!empty($lead->membership_number)) {
            return (string) $lead->membership_number;
        }

        $settings = get_option('bcob_settings', []);

        $prefix = strtoupper(
            sanitize_text_field(
                $settings['bcob_membership_prefix'] ?? 'BC'
            )
        );

        $starting_serial = max(
            1,
            absint(
                $settings['bcob_membership_starting_serial'] ?? 1000
            )
        );

        if ($prefix === '') {
            $prefix = 'BC';
        }

        $year = (int) wp_date('Y');

        $table = $wpdb->prefix . 'bcob_membership_counters';

        $wpdb->query('START TRANSACTION');

        try {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$table}
                    (year, next_serial)
                    VALUES (%d, %d)",
                    $year,
                    $starting_serial
                )
            );

            $counter = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, next_serial
                     FROM {$table}
                     WHERE year = %d
                     FOR UPDATE",
                    $year
                )
            );

            if (!$counter) {
                throw new \RuntimeException(
                    'Unable to allocate membership serial.'
                );
            }

            $serial = absint($counter->next_serial);

            $updated = $wpdb->update(
                $table,
                [
                    'next_serial' => $serial + 1,
                ],
                [
                    'id' => (int) $counter->id,
                ],
                [
                    '%d',
                ],
                [
                    '%d',
                ]
            );

            if ($updated === false) {
                throw new \RuntimeException(
                    'Unable to update membership counter.'
                );
            }

            $number = sprintf(
                '%s-%d-%d',
                $prefix,
                $year,
                $serial
            );

            $lead_updated = Lead::update_membership_number(
                $lead_id,
                $number
            );

            if (!$lead_updated) {
                throw new \RuntimeException(
                    'Unable to save membership number.'
                );
            }

            $wpdb->query('COMMIT');

            return $number;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');

            throw $e;
        }
    }
}
