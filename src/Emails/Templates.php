<?php

namespace BestConnectOnboarding\Emails;

defined('ABSPATH') || exit;

class Templates
{
    public static function render(
        string $template,
        array $submission,
        array $extra = []
    ): string {
        $tags = [];

        foreach (
            ($submission['values'] ?? [])
            as $field_id => $field
        ) {
            $value =
                $field['value'] ?? '';

            if (is_array($value)) {
                $value =
                    implode(
                        ', ',
                        $value
                    );
            }

            $tags['{{' . $field_id . '}}'] =
                esc_html(
                    (string) $value
                );
        }

        $form_name =
            $submission['form']['title']
            ?? '';

        if (!$form_name) {
            $form_name =
                $submission['form']['name']
                ?? '';
        }

        $tags['{{site_name}}'] =
            esc_html(
                get_bloginfo('name')
            );

        $tags['{{site_url}}'] =
            esc_url(
                home_url('/')
            );

        $tags['{{form_name}}'] =
            esc_html(
                (string) $form_name
            );

        $tags['{{submission_date}}'] =
            esc_html(
                wp_date(
                    get_option('date_format')
                )
            );

        $tags['{{submission_time}}'] =
            esc_html(
                wp_date(
                    get_option('time_format')
                )
            );

        foreach (
            $extra as $key => $value
        ) {
            $tags['{{' . $key . '}}'] =
                (string) $value;
        }

        return strtr(
            $template,
            $tags
        );
    }

    public static function get_settings(
        array $submission
    ): array {
        $form_id =
            absint(
                $submission['form']['id'] ?? 0
            );

        if (!$form_id) {
            return [];
        }

        global $wpdb;

        $table =
            $wpdb->prefix . 'bcob_forms';

        $schema =
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT form_schema
                     FROM {$table}
                     WHERE id = %d
                     LIMIT 1",
                    $form_id
                )
            );

        if (!$schema) {
            return [];
        }

        $form =
            json_decode(
                $schema,
                true
            );

        if (!is_array($form)) {
            return [];
        }

        return
            is_array(
                $form['settings'] ?? null
            )
            ? $form['settings']
            : [];
    }
}
