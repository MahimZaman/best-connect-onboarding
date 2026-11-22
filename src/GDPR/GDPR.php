<?php

namespace BestConnectOnboarding\GDPR;

use BestConnectOnboarding\Leads\Lead;
use BestConnectOnboarding\PDFs\PdfManager;

defined('ABSPATH') || exit;

class GDPR
{
    private const CRON_HOOK =
    'bcob_gdpr_cleanup';

    private const BATCH_SIZE =
    100;

    public function __construct()
    {
        add_filter(
            'wp_privacy_personal_data_exporters',
            [self::class, 'register_exporter']
        );

        add_filter(
            'wp_privacy_personal_data_erasers',
            [self::class, 'register_eraser']
        );

        add_action(
            self::CRON_HOOK,
            [self::class, 'cleanup']
        );

        if (
            !wp_next_scheduled(
                self::CRON_HOOK
            )
        ) {
            wp_schedule_event(
                time() + HOUR_IN_SECONDS,
                'daily',
                self::CRON_HOOK
            );
        }
    }

    public static function register_exporter(
        array $exporters
    ): array {
        $exporters['best-connect-onboarding'] = [
            'exporter_friendly_name' =>
            __(
                'Best Connect Onboarding',
                'best-connect-onboarding'
            ),

            'callback' => [
                self::class,
                'export_personal_data',
            ],
        ];

        return $exporters;
    }

    public static function register_eraser(
        array $erasers
    ): array {
        $erasers['best-connect-onboarding'] = [
            'eraser_friendly_name' =>
            __(
                'Best Connect Onboarding',
                'best-connect-onboarding'
            ),

            'callback' => [
                self::class,
                'erase_personal_data',
            ],
        ];

        return $erasers;
    }

    public static function export_personal_data(
        string $email_address,
        int $page = 1
    ): array {
        global $wpdb;

        $email =
            strtolower(
                sanitize_email(
                    $email_address
                )
            );

        if (!is_email($email)) {
            return [
                'data' => [],
                'done' => true,
            ];
        }

        $page =
            max(
                1,
                $page
            );

        $limit = 50;

        $offset =
            ($page - 1) *
            $limit;

        $like =
            '%' .
            $wpdb->esc_like(
                $email
            ) .
            '%';

        $data = [];

        $lead_candidates =
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT *
                    FROM {$wpdb->prefix}bcob_leads
                    WHERE submission LIKE %s
                    ORDER BY id ASC
                    LIMIT %d OFFSET %d",
                    $like,
                    $limit,
                    $offset
                )
            );

        foreach (
            $lead_candidates
            as $lead
        ) {
            $submission =
                json_decode(
                    $lead->submission,
                    true
                );

            if (
                !is_array($submission) ||
                !self::contains_email(
                    $submission,
                    $email
                )
            ) {
                continue;
            }

            $export = [
                [
                    'name' =>
                    __('Lead ID', 'best-connect-onboarding'),

                    'value' =>
                    (string) $lead->id,
                ],

                [
                    'name' =>
                    __('Form ID', 'best-connect-onboarding'),

                    'value' =>
                    (string) $lead->form_id,
                ],

                [
                    'name' =>
                    __('Status', 'best-connect-onboarding'),

                    'value' =>
                    (string) $lead->status,
                ],

                [
                    'name' =>
                    __('Created', 'best-connect-onboarding'),

                    'value' =>
                    (string) $lead->created_at,
                ],

                [
                    'name' =>
                    __('Updated', 'best-connect-onboarding'),

                    'value' =>
                    (string) $lead->updated_at,
                ],
            ];

            if (
                !empty($lead->membership_number)
            ) {
                $export[] = [
                    'name' =>
                    __('Membership Number', 'best-connect-onboarding'),

                    'value' =>
                    (string)
                    $lead->membership_number,
                ];
            }

            foreach (
                ($submission['values'] ?? [])
                as $field
            ) {
                if (!is_array($field)) {
                    continue;
                }

                $value =
                    $field['value'] ?? '';

                if (is_array($value)) {
                    $value =
                        implode(
                            ', ',
                            array_map(
                                'strval',
                                $value
                            )
                        );
                }

                if (
                    !is_scalar($value) ||
                    (string) $value === ''
                ) {
                    continue;
                }

                $export[] = [
                    'name' =>
                    sanitize_text_field(
                        $field['label']
                            ?? $field['key']
                            ?? __('Form Field', 'best-connect-onboarding')
                    ),

                    'value' =>
                    (string) $value,
                ];
            }

            $contracts =
                PdfManager::get_contracts_for_lead(
                    (int) $lead->id
                );

            foreach (
                $contracts
                as $index => $contract
            ) {
                $export[] = [
                    'name' =>
                    sprintf(
                        __(
                            'Contract %d',
                            'best-connect-onboarding'
                        ),
                        $index + 1
                    ),

                    'value' =>
                    trim(
                        (
                            $contract['title']
                            ?? __('Contract', 'best-connect-onboarding')
                        ) .
                            (
                                !empty($contract['url'])
                                ? ' — ' .
                                $contract['url']
                                : ''
                            )
                    ),
                ];
            }

            $magic_links =
                $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT
                            id,
                            is_used,
                            expires_at,
                            used_at,
                            signature_datetime,
                            created_at
                        FROM {$wpdb->prefix}bcob_magic_links
                        WHERE lead_id = %d
                        ORDER BY id ASC",
                        $lead->id
                    )
                );

            foreach (
                $magic_links
                as $magic_link
            ) {
                $export[] = [
                    'name' =>
                    sprintf(
                        __(
                            'Confirmation Link #%d',
                            'best-connect-onboarding'
                        ),
                        $magic_link->id
                    ),

                    'value' =>
                    wp_json_encode(
                        [
                            'used' =>
                            (bool)
                            $magic_link->is_used,

                            'created_at' =>
                            $magic_link->created_at,

                            'expires_at' =>
                            $magic_link->expires_at,

                            'used_at' =>
                            $magic_link->used_at,

                            'signature_datetime' =>
                            $magic_link->signature_datetime,
                        ],
                        JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                    ),
                ];
            }

            $data[] = [
                'group_id' =>
                'bcob-onboarding',

                'group_label' =>
                __(
                    'Onboarding Submissions',
                    'best-connect-onboarding'
                ),

                'item_id' =>
                'bcob-lead-' .
                    $lead->id,

                'data' =>
                $export,
            ];
        }

        $abandonments =
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT *
                    FROM {$wpdb->prefix}bcob_abandoned_sessions
                    WHERE email = %s
                    ORDER BY id ASC
                    LIMIT %d OFFSET %d",
                    $email,
                    $limit,
                    $offset
                )
            );

        foreach (
            $abandonments
            as $session
        ) {
            $session_data =
                json_decode(
                    $session->form_data,
                    true
                );

            $data[] = [
                'group_id' =>
                'bcob-abandonment',

                'group_label' =>
                __(
                    'Onboarding Reminder Sessions',
                    'best-connect-onboarding'
                ),

                'item_id' =>
                'bcob-abandonment-' .
                    $session->id,

                'data' => [
                    [
                        'name' =>
                        __('Email', 'best-connect-onboarding'),

                        'value' =>
                        $session->email,
                    ],

                    [
                        'name' =>
                        __('Form ID', 'best-connect-onboarding'),

                        'value' =>
                        (string)
                        $session->form_id,
                    ],

                    [
                        'name' =>
                        __('Current Step', 'best-connect-onboarding'),

                        'value' =>
                        (string)
                        $session->current_step,
                    ],

                    [
                        'name' =>
                        __('Status', 'best-connect-onboarding'),

                        'value' =>
                        $session->status,
                    ],

                    [
                        'name' =>
                        __('Stored Data', 'best-connect-onboarding'),

                        'value' =>
                        wp_json_encode(
                            is_array($session_data)
                                ? $session_data
                                : [],
                            JSON_UNESCAPED_UNICODE |
                                JSON_UNESCAPED_SLASHES
                        ),
                    ],

                    [
                        'name' =>
                        __('Created', 'best-connect-onboarding'),

                        'value' =>
                        $session->created_at,
                    ],

                    [
                        'name' =>
                        __('Updated', 'best-connect-onboarding'),

                        'value' =>
                        $session->updated_at,
                    ],
                ],
            ];
        }

        $prefill_candidates =
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT *
                    FROM {$wpdb->prefix}bcob_prefill_links
                    WHERE prefill_data LIKE %s
                    ORDER BY id ASC
                    LIMIT %d OFFSET %d",
                    $like,
                    $limit,
                    $offset
                )
            );

        foreach (
            $prefill_candidates
            as $prefill
        ) {
            $prefill_data =
                json_decode(
                    $prefill->prefill_data,
                    true
                );

            if (
                !is_array($prefill_data) ||
                !self::contains_email(
                    $prefill_data,
                    $email
                )
            ) {
                continue;
            }

            $data[] = [
                'group_id' =>
                'bcob-prefill',

                'group_label' =>
                __(
                    'Onboarding Pre-fill Links',
                    'best-connect-onboarding'
                ),

                'item_id' =>
                'bcob-prefill-' .
                    $prefill->id,

                'data' => [
                    [
                        'name' =>
                        __('Form ID', 'best-connect-onboarding'),

                        'value' =>
                        (string)
                        $prefill->form_id,
                    ],

                    [
                        'name' =>
                        __('Stored Data', 'best-connect-onboarding'),

                        'value' =>
                        wp_json_encode(
                            $prefill_data,
                            JSON_UNESCAPED_UNICODE |
                                JSON_UNESCAPED_SLASHES
                        ),
                    ],

                    [
                        'name' =>
                        __('Created', 'best-connect-onboarding'),

                        'value' =>
                        $prefill->created_at,
                    ],

                    [
                        'name' =>
                        __('Expires', 'best-connect-onboarding'),

                        'value' =>
                        $prefill->expires_at,
                    ],
                ],
            ];
        }

        $done =
            count($lead_candidates) < $limit &&
            count($abandonments) < $limit &&
            count($prefill_candidates) < $limit;

        return [
            'data' =>
            $data,

            'done' =>
            $done,
        ];
    }

    public static function erase_personal_data(
        string $email_address,
        int $page = 1
    ): array {
        global $wpdb;

        $email =
            strtolower(
                sanitize_email(
                    $email_address
                )
            );

        if (!is_email($email)) {
            return [
                'items_removed' =>
                false,

                'items_retained' =>
                false,

                'messages' =>
                [],

                'done' =>
                true,
            ];
        }

        $items_removed = false;
        $items_retained = false;
        $messages = [];

        $like =
            '%' .
            $wpdb->esc_like(
                $email
            ) .
            '%';

        $lead_candidates =
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT *
                    FROM {$wpdb->prefix}bcob_leads
                    WHERE submission LIKE %s
                    ORDER BY id ASC
                    LIMIT %d",
                    $like,
                    self::BATCH_SIZE
                )
            );

        foreach (
            $lead_candidates
            as $lead
        ) {
            $submission =
                json_decode(
                    $lead->submission,
                    true
                );

            if (
                !is_array($submission) ||
                !self::contains_email(
                    $submission,
                    $email
                )
            ) {
                continue;
            }

            if (
                !self::external_delivery_complete(
                    $lead
                )
            ) {
                $items_retained = true;

                $messages[] =
                    sprintf(
                        __(
                            'Lead #%d was retained because external delivery has not been fully confirmed.',
                            'best-connect-onboarding'
                        ),
                        absint(
                            $lead->id
                        )
                    );

                continue;
            }

            if (
                self::delete_lead(
                    $lead
                )
            ) {
                $items_removed =
                    true;
            } else {
                $items_retained = true;

                $messages[] =
                    sprintf(
                        __(
                            'Lead #%d could not be completely erased.',
                            'best-connect-onboarding'
                        ),
                        absint(
                            $lead->id
                        )
                    );
            }
        }

        $deleted_abandonments =
            $wpdb->delete(
                $wpdb->prefix .
                    'bcob_abandoned_sessions',
                [
                    'email' =>
                    $email,
                ],
                [
                    '%s',
                ]
            );

        if ($deleted_abandonments) {
            $items_removed =
                true;
        }

        $prefill_candidates =
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT *
                    FROM {$wpdb->prefix}bcob_prefill_links
                    WHERE prefill_data LIKE %s
                    ORDER BY id ASC
                    LIMIT %d",
                    $like,
                    self::BATCH_SIZE
                )
            );

        foreach (
            $prefill_candidates
            as $prefill
        ) {
            $prefill_data =
                json_decode(
                    $prefill->prefill_data,
                    true
                );

            if (
                !is_array($prefill_data) ||
                !self::contains_email(
                    $prefill_data,
                    $email
                )
            ) {
                continue;
            }

            $deleted =
                $wpdb->delete(
                    $wpdb->prefix .
                        'bcob_prefill_links',
                    [
                        'id' =>
                        $prefill->id,
                    ],
                    [
                        '%d',
                    ]
                );

            if ($deleted) {
                $items_removed =
                    true;
            }
        }

        if (
            self::remove_suppression(
                $email
            )
        ) {
            $items_removed =
                true;
        }

        return [
            'items_removed' =>
            $items_removed,

            'items_retained' =>
            $items_retained,

            'messages' =>
            $messages,

            'done' =>
            !self::has_personal_data(
                $email
            ),
        ];
    }

    public static function erase_by_lead_token(
        string $token
    ): bool {
        $token =
            sanitize_text_field(
                $token
            );

        if ($token === '') {
            return false;
        }

        $lead =
            Lead::get_by_token(
                $token
            );

        if (!$lead) {
            return false;
        }

        return self::delete_lead(
            $lead
        );
    }

    public static function cleanup(): void
    {
        $settings =
            get_option(
                'bcob_settings',
                []
            );

        $lead_days =
            self::retention_days(
                $settings['bcob_retention_leads_days'] ?? 365,
                365
            );

        $abandonment_days =
            self::retention_days(
                $settings['bcob_retention_abandonment_days'] ?? 30,
                30
            );

        $magic_days =
            self::retention_days(
                $settings['bcob_retention_magic_links_days'] ?? 30,
                30
            );

        $prefill_days =
            self::retention_days(
                $settings['bcob_retention_prefill_days'] ?? 30,
                30
            );

        $temp_days =
            self::retention_days(
                $settings['bcob_retention_temp_files_days'] ?? 1,
                1
            );

        $logs_days =
            self::retention_days(
                $settings['bcob_retention_logs_days'] ?? 30,
                30
            );

        self::cleanup_abandonment(
            $abandonment_days
        );

        self::cleanup_magic_links(
            $magic_days
        );

        self::cleanup_prefill(
            $prefill_days
        );

        self::cleanup_leads(
            $lead_days
        );

        self::cleanup_orphan_contracts();

        self::cleanup_logs(
            $logs_days
        );

        self::cleanup_temp_files(
            $temp_days
        );
    }

    private static function cleanup_abandonment(
        int $days
    ): void {
        global $wpdb;

        $cutoff =
            self::cutoff(
                $days
            );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE
                FROM {$wpdb->prefix}bcob_abandoned_sessions
                WHERE updated_at < %s",
                $cutoff
            )
        );
    }

    private static function cleanup_magic_links(
        int $days
    ): void {
        global $wpdb;

        $cutoff =
            self::cutoff(
                $days
            );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE
                FROM {$wpdb->prefix}bcob_magic_links
                WHERE expires_at < %s",
                $cutoff
            )
        );
    }

    private static function cleanup_prefill(
        int $days
    ): void {
        global $wpdb;

        $cutoff =
            self::cutoff(
                $days
            );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE
                FROM {$wpdb->prefix}bcob_prefill_links
                WHERE
                    (
                        expires_at IS NOT NULL
                        AND expires_at < %s
                    )
                    OR
                    (
                        expires_at IS NULL
                        AND created_at < %s
                    )",
                $cutoff,
                $cutoff
            )
        );
    }

    private static function external_delivery_complete(
        object $lead
    ): bool {
        $submission =
            json_decode(
                $lead->submission ?? '',
                true
            );

        if (!is_array($submission)) {
            return false;
        }

        $middleware_status =
            sanitize_key(
                $submission['middleware']['status']
                    ?? ''
            );

        $backoffice_status =
            sanitize_key(
                $submission['delivery']['backoffice_contract_email']['status']
                    ?? ''
            );

        return
            $middleware_status === 'success' &&
            $backoffice_status === 'success';
    }

    private static function cleanup_leads(
        int $days
    ): void {
        global $wpdb;

        $cutoff =
            self::cutoff(
                $days
            );

        $last_id = 0;

        do {
            $leads =
                $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT *
                    FROM {$wpdb->prefix}bcob_leads
                    WHERE updated_at < %s
                    AND id > %d
                    ORDER BY id ASC
                    LIMIT %d",
                        $cutoff,
                        $last_id,
                        self::BATCH_SIZE
                    )
                );

            foreach ($leads as $lead) {
                $last_id =
                    max(
                        $last_id,
                        absint(
                            $lead->id
                        )
                    );

                if (
                    !self::external_delivery_complete(
                        $lead
                    )
                ) {
                    continue;
                }

                self::delete_lead(
                    $lead
                );
            }
        } while (
            count($leads) ===
            self::BATCH_SIZE
        );
    }

    private static function delete_lead(
        object $lead
    ): bool {
        global $wpdb;

        $lead_id =
            absint(
                $lead->id ?? 0
            );

        if (!$lead_id) {
            return false;
        }

        if (
            !self::external_delivery_complete(
                $lead
            )
        ) {
            return false;
        }

        $submission =
            json_decode(
                $lead->submission ?? '',
                true
            );

        $attachment_ids = [];

        if (is_array($submission)) {
            foreach (
                ($submission['contracts'] ?? [])
                as $contract
            ) {
                $attachment_id =
                    absint(
                        $contract['attachment_id']
                            ?? 0
                    );

                if ($attachment_id) {
                    $attachment_ids[] =
                        $attachment_id;
                }
            }
        }

        $linked_attachments =
            get_posts(
                [
                    'post_type' =>
                    'attachment',

                    'post_status' =>
                    'any',

                    'posts_per_page' =>
                    -1,

                    'fields' =>
                    'ids',

                    'meta_key' =>
                    '_bcob_lead_id',

                    'meta_value' =>
                    $lead_id,
                ]
            );

        $attachment_ids =
            array_unique(
                array_merge(
                    $attachment_ids,
                    array_map(
                        'absint',
                        $linked_attachments
                    )
                )
            );

        foreach (
            $attachment_ids
            as $attachment_id
        ) {
            if (
                !get_post_meta(
                    $attachment_id,
                    '_bcob_generated',
                    true
                ) &&
                !get_post_meta(
                    $attachment_id,
                    '_bcob_private_contract',
                    true
                )
            ) {
                continue;
            }

            if (
                !self::delete_contract_attachment(
                    $attachment_id
                )
            ) {
                return false;
            }
        }

        $wpdb->delete(
            $wpdb->prefix .
                'bcob_magic_links',
            [
                'lead_id' =>
                $lead_id,
            ],
            [
                '%d',
            ]
        );

        wp_clear_scheduled_hook(
            'bcob_middleware_retry',
            [
                $lead_id,
            ]
        );

        delete_post_meta_by_key(
            '_bcob_used_lead_' .
                $lead_id
        );

        $deleted =
            $wpdb->delete(
                $wpdb->prefix .
                    'bcob_leads',
                [
                    'id' =>
                    $lead_id,
                ],
                [
                    '%d',
                ]
            );

        return $deleted !== false;
    }

    private static function delete_contract_attachment(
        int $attachment_id
    ): bool {
        if (!$attachment_id) {
            return false;
        }

        if (
            get_post_meta(
                $attachment_id,
                '_bcob_private_contract',
                true
            )
        ) {
            $path =
                PdfManager::get_contract_file_path(
                    $attachment_id
                );

            if (
                $path &&
                is_file($path)
            ) {
                if (
                    !@unlink($path) &&
                    is_file($path)
                ) {
                    return false;
                }
            }
        }

        return (bool) wp_delete_attachment(
            $attachment_id,
            true
        );
    }

    private static function cleanup_orphan_contracts(): void
    {
        global $wpdb;

        $attachments =
            get_posts(
                [
                    'post_type' =>
                    'attachment',

                    'post_status' =>
                    'any',

                    'posts_per_page' =>
                    200,

                    'fields' =>
                    'ids',

                    'meta_key' =>
                    '_bcob_private_contract',

                    'meta_value' =>
                    1,
                ]
            );

        foreach (
            $attachments
            as $attachment_id
        ) {
            $lead_id =
                absint(
                    get_post_meta(
                        $attachment_id,
                        '_bcob_lead_id',
                        true
                    )
                );

            if (
                $lead_id &&
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id
                        FROM {$wpdb->prefix}bcob_leads
                        WHERE id = %d
                        LIMIT 1",
                        $lead_id
                    )
                )
            ) {
                continue;
            }

            self::delete_contract_attachment(
                $attachment_id
            );
        }
    }

    private static function cleanup_logs(
        int $days
    ): void {
        global $wpdb;

        $table =
            $wpdb->prefix .
            'bcob_logs';

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
            return;
        }

        $cutoff =
            self::cutoff(
                $days
            );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE
            FROM {$table}
            WHERE created_at < %s",
                $cutoff
            )
        );
    }

    private static function cleanup_temp_files(
        int $days
    ): void {
        $directory =
            sys_get_temp_dir();

        if (
            !$directory ||
            !is_dir($directory)
        ) {
            return;
        }

        $cutoff =
            time() -
            ($days * DAY_IN_SECONDS);

        $files =
            glob(
                trailingslashit(
                    $directory
                ) .
                    'bcob_*'
            );

        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (
                !is_file($file)
            ) {
                continue;
            }

            $name =
                basename($file);

            if (
                !preg_match(
                    '/^bcob_(ai|pdf|signature|review)_/',
                    $name
                )
            ) {
                continue;
            }

            $modified =
                filemtime($file);

            if (
                $modified !== false &&
                $modified < $cutoff
            ) {
                @unlink($file);
            }
        }
    }

    private static function has_personal_data(
        string $email
    ): bool {
        global $wpdb;

        $abandonment =
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                    FROM {$wpdb->prefix}bcob_abandoned_sessions
                    WHERE email = %s
                    LIMIT 1",
                    $email
                )
            );

        if ($abandonment) {
            return true;
        }

        $like =
            '%' .
            $wpdb->esc_like(
                $email
            ) .
            '%';

        $leads =
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT submission
                    FROM {$wpdb->prefix}bcob_leads
                    WHERE submission LIKE %s",
                    $like
                )
            );

        foreach ($leads as $lead) {
            $submission =
                json_decode(
                    $lead->submission,
                    true
                );

            if (
                is_array($submission) &&
                self::contains_email(
                    $submission,
                    $email
                )
            ) {
                return true;
            }
        }

        $prefills =
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT prefill_data
                    FROM {$wpdb->prefix}bcob_prefill_links
                    WHERE prefill_data LIKE %s",
                    $like
                )
            );

        foreach ($prefills as $prefill) {
            $data =
                json_decode(
                    $prefill->prefill_data,
                    true
                );

            if (
                is_array($data) &&
                self::contains_email(
                    $data,
                    $email
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private static function contains_email(
        mixed $value,
        string $email
    ): bool {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (
                    self::contains_email(
                        $item,
                        $email
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        if (!is_string($value)) {
            return false;
        }

        $candidate =
            strtolower(
                sanitize_email(
                    trim($value)
                )
            );

        return (
            is_email($candidate) &&
            $candidate === $email
        );
    }

    private static function remove_suppression(
        string $email
    ): bool {
        $suppressed =
            get_option(
                'bcob_abandonment_suppressed_emails',
                []
            );

        if (!is_array($suppressed)) {
            return false;
        }

        $hash =
            hash_hmac(
                'sha256',
                strtolower(
                    trim($email)
                ),
                wp_salt('auth')
            );

        if (
            !array_key_exists(
                $hash,
                $suppressed
            )
        ) {
            return false;
        }

        unset(
            $suppressed[$hash]
        );

        update_option(
            'bcob_abandonment_suppressed_emails',
            $suppressed,
            false
        );

        return true;
    }

    private static function retention_days(
        mixed $value,
        int $default
    ): int {
        $days =
            absint($value);

        return $days > 0
            ? $days
            : $default;
    }

    private static function cutoff(
        int $days
    ): string {
        return wp_date(
            'Y-m-d H:i:s',
            current_time('timestamp') -
                ($days * DAY_IN_SECONDS),
            wp_timezone()
        );
    }
}
