<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

$cron_hooks = [
    'bcob_abandonment_cron',
    'bcob_gdpr_cleanup',
    'bcob_middleware_retry',
];

foreach ($cron_hooks as $hook) {
    wp_clear_scheduled_hook($hook);
}

$temp_directory =
    sys_get_temp_dir();

if (
    $temp_directory &&
    is_dir($temp_directory)
) {
    $temp_files =
        glob(
            trailingslashit(
                $temp_directory
            ) .
                'bcob_*'
        );

    if (is_array($temp_files)) {
        foreach ($temp_files as $file) {
            if (!is_file($file)) {
                continue;
            }

            if (
                !preg_match(
                    '/^bcob_(ai|pdf|signature|review)_/',
                    basename($file)
                )
            ) {
                continue;
            }

            @unlink($file);
        }
    }
}

$attachment_ids =
    $wpdb->get_col(
        "SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm
            ON pm.post_id = p.ID
        WHERE p.post_type = 'attachment'
        AND pm.meta_key IN (
            '_bcob_generated',
            '_bcob_private_contract'
        )"
    );

foreach ($attachment_ids as $attachment_id) {
    wp_delete_attachment(
        absint($attachment_id),
        true
    );
}

$plugin_post_ids =
    $wpdb->get_col(
        "SELECT ID
        FROM {$wpdb->posts}
        WHERE post_type IN (
            'bcob_membership',
            'bcob_coupon'
        )"
    );

foreach ($plugin_post_ids as $post_id) {
    wp_delete_post(
        absint($post_id),
        true
    );
}

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta}
        WHERE meta_key LIKE %s",
        $wpdb->esc_like(
            '_bcob_'
        ) . '%'
    )
);

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta}
        WHERE meta_key LIKE %s",
        $wpdb->esc_like(
            'bcob_'
        ) . '%'
    )
);

$option_names =
    $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name
            FROM {$wpdb->options}
            WHERE option_name LIKE %s",
            $wpdb->esc_like(
                'bcob_'
            ) . '%'
        )
    );

foreach ($option_names as $option_name) {
    delete_option(
        $option_name
    );
}

$transient_options =
    $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name
            FROM {$wpdb->options}
            WHERE option_name LIKE %s",
            $wpdb->esc_like(
                '_transient_bcob_'
            ) . '%'
        )
    );

foreach ($transient_options as $option_name) {
    $transient_name =
        substr(
            $option_name,
            strlen(
                '_transient_'
            )
        );

    if ($transient_name !== '') {
        delete_transient(
            $transient_name
        );
    }
}

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options}
        WHERE option_name LIKE %s
        OR option_name LIKE %s",
        $wpdb->esc_like(
            '_transient_bcob_'
        ) . '%',
        $wpdb->esc_like(
            '_transient_timeout_bcob_'
        ) . '%'
    )
);

$tables = [
    $wpdb->prefix . 'bcob_logs',
    $wpdb->prefix . 'bcob_magic_links',
    $wpdb->prefix . 'bcob_membership_counters',
    $wpdb->prefix . 'bcob_abandoned_sessions',
    $wpdb->prefix . 'bcob_payments',
    $wpdb->prefix . 'bcob_prefill_links',
    $wpdb->prefix . 'bcob_leads',
    $wpdb->prefix . 'bcob_forms',
];

foreach ($tables as $table) {
    $wpdb->query(
        "DROP TABLE IF EXISTS {$table}"
    );
}

$private_directory = '';

if (
    defined(
        'BCOB_PRIVATE_CONTRACT_DIR'
    ) &&
    BCOB_PRIVATE_CONTRACT_DIR
) {
    $private_directory =
        wp_normalize_path(
            (string)
            BCOB_PRIVATE_CONTRACT_DIR
        );
} else {
    $document_root =
        isset(
            $_SERVER['DOCUMENT_ROOT']
        )
        ? realpath(
            $_SERVER['DOCUMENT_ROOT']
        )
        : false;

    if ($document_root) {
        $private_directory =
            wp_normalize_path(
                dirname(
                    $document_root
                ) .
                    '/bcob-private-contracts'
            );
    }
}

if (
    $private_directory !== '' &&
    !is_link(
        $private_directory
    )
) {
    $real_private =
        realpath(
            $private_directory
        );

    if (
        $real_private &&
        is_dir(
            $real_private
        )
    ) {
        $real_private =
            wp_normalize_path(
                $real_private
            );

        $safe = true;

        if (
            $real_private === '/' ||
            preg_match(
                '/^[A-Za-z]:\/?$/',
                $real_private
            )
        ) {
            $safe = false;
        }

        $protected_paths = [
            ABSPATH,
            defined('WP_CONTENT_DIR')
                ? WP_CONTENT_DIR
                : '',
            defined('WP_PLUGIN_DIR')
                ? WP_PLUGIN_DIR
                : '',
            isset($_SERVER['DOCUMENT_ROOT'])
                ? $_SERVER['DOCUMENT_ROOT']
                : '',
        ];

        $private_compare =
            strtolower(
                trailingslashit(
                    $real_private
                )
            );

        foreach (
            $protected_paths
            as $protected_path
        ) {
            if (!$protected_path) {
                continue;
            }

            $protected_real =
                realpath(
                    $protected_path
                );

            if (!$protected_real) {
                continue;
            }

            $protected_compare =
                strtolower(
                    trailingslashit(
                        wp_normalize_path(
                            $protected_real
                        )
                    )
                );

            if (
                $private_compare ===
                $protected_compare ||
                str_starts_with(
                    $private_compare,
                    $protected_compare
                ) ||
                str_starts_with(
                    $protected_compare,
                    $private_compare
                )
            ) {
                $safe = false;
                break;
            }
        }

        if ($safe) {
            $remove_directory =
                static function (
                    string $path
                ) use (
                    &$remove_directory
                ): void {
                    if (
                        is_link($path) ||
                        is_file($path)
                    ) {
                        @unlink($path);
                        return;
                    }

                    if (!is_dir($path)) {
                        return;
                    }

                    $items =
                        scandir($path);

                    if (!is_array($items)) {
                        return;
                    }

                    foreach (
                        $items
                        as $item
                    ) {
                        if (
                            $item === '.' ||
                            $item === '..'
                        ) {
                            continue;
                        }

                        $remove_directory(
                            $path .
                                DIRECTORY_SEPARATOR .
                                $item
                        );
                    }

                    @rmdir($path);
                };

            $remove_directory(
                $real_private
            );
        }
    }
}
