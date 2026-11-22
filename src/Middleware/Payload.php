<?php

namespace BestConnectOnboarding\Middleware;

use BestConnectOnboarding\Payments\Payment;

defined('ABSPATH') || exit;

class Payload
{
    private const FIELD_MAP = [
        'datev-customer-number' => ['customer', 'customerNumber'],
        'datev-name'            => ['customer', 'name'],
        'datev-email'           => ['customer', 'email'],
        'datev-street'          => ['customer', 'street'],
        'datev-zip'             => ['customer', 'zip'],
        'datev-city'            => ['customer', 'city'],
        'datev-country-code'    => ['customer', 'countryCode'],
        'datev-invoice-date'    => ['invoice', 'invoiceDate'],
        'datev-period-from'     => ['invoice', 'periodFrom'],
        'datev-period-to'       => ['invoice', 'periodTo'],
        'datev-due-date'        => ['invoice', 'dueDate'],
        'datev-total-net'       => ['invoice', 'totalNetAmount'],
        'datev-total-tax'       => ['invoice', 'totalTaxAmount'],
        'datev-text'            => ['invoice', 'text'],
    ];

    public static function build(array $submission): array
    {
        $payload = [
            'language' => 'DE',
            'customer' => [
                'customerNumber' => '',
                'name'            => '',
                'email'           => '',
                'street'          => '',
                'zip'             => '',
                'city'            => '',
                'countryCode'     => 'AT',
            ],
            'invoice' => [
                'invoiceDate'         => current_time('Y-m-d'),
                'periodFrom'          => wp_date('Y-m-01'),
                'periodTo'            => wp_date('Y-m-t'),
                'dueDate'             => '',
                'totalNetAmount'      => '',
                'totalTaxAmount'      => '',
                'totalGrossAmount'    => self::get_payment_amount($submission),
                'currencyCode'        => self::get_currency($submission),
                'usesSepaDirectDebit' => false,
                'text'                => 'Online Zahlung',
            ],
        ];

        $fields = self::get_form_fields($submission);

        foreach ($fields as $field) {
            $classes = self::get_field_classes($field);

            if (!$classes) {
                continue;
            }

            $value = self::get_submission_value(
                $submission,
                $field['id'] ?? $field['key'] ?? ''
            );

            foreach ($classes as $class) {
                if (!isset(self::FIELD_MAP[$class])) {
                    continue;
                }

                [$section, $key] = self::FIELD_MAP[$class];

                $payload[$section][$key] = self::normalize_value($value);
            }
        }

        return $payload;
    }

    private static function get_form_fields(array $submission): array
    {
        $form_id = absint(
            $submission['form']['id'] ?? 0
        );

        if (!$form_id) {
            return [];
        }

        global $wpdb;

        $table = $wpdb->prefix . 'bcob_forms';

        $schema = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT form_schema FROM {$table} WHERE id = %d LIMIT 1",
                $form_id
            )
        );

        if (!$schema) {
            return [];
        }

        $form = json_decode($schema, true);

        if (!is_array($form)) {
            return [];
        }

        $fields = [];

        foreach (($form['steps'] ?? []) as $step) {
            foreach (($step['fields'] ?? []) as $field) {
                if (is_array($field)) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    private static function get_field_classes(array $field): array
    {
        $classes = [];

        foreach (
            [
                $field['class'] ?? '',
                $field['classes'] ?? '',
                $field['css_class'] ?? '',
                $field['custom_class'] ?? '',
                $field['settings']['class'] ?? '',
                $field['settings']['classes'] ?? '',
                $field['settings']['css_class'] ?? '',
            ] as $value
        ) {
            if (is_array($value)) {
                $classes = array_merge($classes, $value);
                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $classes = array_merge(
                    $classes,
                    preg_split('/\s+/', trim($value))
                );
            }
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        'sanitize_html_class',
                        $classes
                    )
                )
            )
        );
    }

    private static function get_submission_value(
        array $submission,
        string $field_id
    ): mixed {
        if (!$field_id) {
            return '';
        }

        $value =
            $submission['values'][$field_id]['value']
            ?? '';

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return $value;
    }

    public static function has_valid_total_gross_amount(
        array $payload
    ): bool {
        $amount =
            $payload['invoice']['totalGrossAmount']
            ?? null;

        if (!is_scalar($amount)) {
            return false;
        }

        $amount =
            trim(
                (string) $amount
            );

        return
            $amount !== '' &&
            is_numeric($amount);
    }

    private static function get_payment_amount(
        array $submission
    ): string {
        $lead_id =
            absint(
                $submission['lead_id'] ?? 0
            );

        if (!$lead_id) {
            return '';
        }

        $payment =
            Payment::get_latest_for_lead(
                $lead_id
            );

        if (!$payment) {
            return '';
        }

        return number_format(
            (float) $payment->amount,
            2,
            '.',
            ''
        );
    }

    private static function get_currency(
        array $submission
    ): string {
        $lead_id =
            absint(
                $submission['lead_id'] ?? 0
            );

        if ($lead_id) {
            $payment =
                Payment::get_latest_for_lead(
                    $lead_id
                );

            if ($payment) {
                return strtoupper(
                    sanitize_text_field(
                        (string) $payment->currency
                    )
                );
            }
        }

        $settings =
            get_option(
                'bcob_settings',
                []
            );

        return strtoupper(
            sanitize_text_field(
                $settings['currency_code']
                    ?? 'EUR'
            )
        );
    }

    private static function normalize_value(
        mixed $value
    ): string {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        if ($value === null) {
            return '';
        }

        return sanitize_text_field(
            (string) $value
        );
    }
}
