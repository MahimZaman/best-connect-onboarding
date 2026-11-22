<?php

namespace BestConnectOnboarding\Prefill;

defined('ABSPATH') || exit;

class Prefill
{
    private const EXPIRY_OPTIONS = [
        '1_hour' => '1 Hour',
        '24_hours' => '24 Hours',
        '1_week' => '1 Week',
        '1_month' => '1 Month',
        '1_year' => '1 Year',
    ];

    public function __construct()
    {
        add_action('wp_ajax_bcob_create_prefill', [$this, 'create']);
        add_action('wp_ajax_bcob_get_prefill', [$this, 'get']);
        add_action('wp_ajax_nopriv_bcob_get_prefill', [$this, 'get']);
        add_action('wp_ajax_bcob_update_prefill_expiry', [$this, 'update_expiry']);
        add_action('wp_ajax_bcob_delete_prefill', [$this, 'delete']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'You do not have permission to access this page.',
                    'best-connect-onboarding'
                )
            );
        }

        self::admin_assets();

        global $wpdb;

        $table = $wpdb->prefix . 'bcob_prefill_links';

        $per_page = 20;

        $current_page = isset($_GET['paged'])
            ? max(
                1,
                absint(
                    wp_unslash($_GET['paged'])
                )
            )
            : 1;

        $offset =
            ($current_page - 1) *
            $per_page;


        /*
 * Total number of links
 */

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*)
    FROM {$table}"
        );

        $total_pages = max(
            1,
            (int) ceil(
                $total / $per_page
            )
        );


        /*
 * Current page
 */

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
            p.*,
            f.name AS form_name
        FROM {$table} p
        LEFT JOIN {$wpdb->prefix}bcob_forms f
            ON f.id = p.form_id
        ORDER BY p.created_at DESC
        LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
?>

        <div class="wrap bcob-prefill-page">

            <header class="bcob-prefill-header">
                <div>
                    <span class="bcob-prefill-eyebrow">
                        Best Connect Onboarding
                    </span>

                    <h1>Pre-fill Links</h1>

                    <p>
                        Manage generated onboarding links and saved form data.
                    </p>
                </div>

                <div class="bcob-prefill-count">
                    <strong>
                        <?php echo esc_html(
                            number_format_i18n($total)
                        ); ?>
                    </strong>

                    <span>Total links</span>
                </div>
            </header>

            <section class="bcob-prefill-card">

                <div class="bcob-prefill-table-wrap">

                    <table class="bcob-prefill-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Form</th>
                                <th>Token</th>
                                <th>Link</th>
                                <th>Created</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($rows)) : ?>

                                <tr>
                                    <td colspan="8">

                                        <div class="bcob-prefill-empty">
                                            <i class="ri-link-m"></i>

                                            <strong>
                                                No pre-fill links yet
                                            </strong>

                                            <span>
                                                Generated links will appear here.
                                            </span>
                                        </div>

                                    </td>
                                </tr>

                            <?php else : ?>

                                <?php foreach ($rows as $row) :

                                    $data = json_decode(
                                        $row->prefill_data,
                                        true
                                    );

                                    $link = !empty($data['page_url'])
                                        ? add_query_arg(
                                            'lead',
                                            $row->token,
                                            remove_query_arg(
                                                'lead',
                                                $data['page_url']
                                            )
                                        )
                                        : '';

                                    $expired =
                                        $row->expires_at &&
                                        strtotime(
                                            $row->expires_at
                                        ) <= current_time(
                                            'timestamp'
                                        );

                                    $prefill_status =
                                        $expired
                                        ? 'expired'
                                        : sanitize_key(
                                            $row->status ?? 'active'
                                        );

                                    $status_label =
                                        match ($prefill_status) {
                                            'used' =>
                                            'Used',

                                            'processing' =>
                                            'Processing',

                                            'expired' =>
                                            'Expired',

                                            default =>
                                            'Active',
                                        };

                                ?>

                                    <tr data-id="<?php echo esc_attr($row->id); ?>">

                                        <td>
                                            #<?php echo esc_html($row->id); ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo esc_html(
                                                $row->form_name
                                                    ?: 'Form #' . $row->form_id
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <code>
                                                <?php
                                                echo esc_html(
                                                    substr($row->token, 0, 16)
                                                );
                                                ?>…
                                            </code>
                                        </td>

                                        <td>

                                            <?php if ($link) : ?>

                                                <div class="bcob-prefill-link">

                                                    <input
                                                        type="text"
                                                        readonly
                                                        value="<?php echo esc_attr($link); ?>">

                                                    <button
                                                        type="button"
                                                        class="button bcob-prefill-copy"
                                                        data-copy="<?php echo esc_attr($link); ?>">
                                                        <i class="ri-file-copy-line"></i>
                                                        Copy
                                                    </button>

                                                </div>

                                            <?php else : ?>

                                                —

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?php echo esc_html($row->created_at); ?>
                                        </td>

                                        <td>

                                            <?php
                                            $saved_expiry = $data['expiry'] ?? '1_month';
                                            ?>

                                            <select
                                                class="bcob-prefill-expiry"
                                                data-id="<?php echo esc_attr($row->id); ?>">
                                                <?php foreach (self::EXPIRY_OPTIONS as $value => $label) : ?>

                                                    <option
                                                        value="<?php echo esc_attr($value); ?>"
                                                        <?php selected($saved_expiry, $value); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </option>

                                                <?php endforeach; ?>
                                            </select>

                                            <small class="bcob-expiry-date">
                                                <?php
                                                echo $row->expires_at
                                                    ? esc_html($row->expires_at)
                                                    : '—';
                                                ?>
                                            </small>

                                        </td>

                                        <td>

                                            <span class="bcob-prefill-status <?php echo in_array($prefill_status, ['expired', 'used'], true) ? 'is-expired' : 'is-active'; ?>">
                                                <span></span>
                                                <strong>
                                                    <?php echo esc_html($status_label); ?>
                                                </strong>
                                            </span>

                                        </td>

                                        <td>

                                            <button
                                                type="button"
                                                class="bcob-prefill-delete"
                                                data-id="<?php echo esc_attr($row->id); ?>">
                                                <i class="ri-delete-bin-6-line"></i>
                                                Delete
                                            </button>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <?php if ($total_pages > 1) : ?>

                    <footer class="bcob-prefill-pagination">

                        <span class="bcob-prefill-pagination-summary">

                            <?php

                            $from =
                                $total > 0
                                ? $offset + 1
                                : 0;

                            $to =
                                min(
                                    $offset + $per_page,
                                    $total
                                );

                            printf(
                                esc_html__(
                                    'Showing %1$s–%2$s of %3$s',
                                    'best-connect-onboarding'
                                ),
                                esc_html(
                                    number_format_i18n($from)
                                ),
                                esc_html(
                                    number_format_i18n($to)
                                ),
                                esc_html(
                                    number_format_i18n($total)
                                )
                            );

                            ?>

                        </span>


                        <nav
                            aria-label="<?php esc_attr_e(
                                            'Pre-fill links pagination',
                                            'best-connect-onboarding'
                                        ); ?>">

                            <?php

                            $pagination_url =
                                menu_page_url(
                                    'bcob-prefill-links',
                                    false
                                );

                            $previous_url =
                                add_query_arg(
                                    'paged',
                                    max(
                                        1,
                                        $current_page - 1
                                    ),
                                    $pagination_url
                                );

                            $next_url =
                                add_query_arg(
                                    'paged',
                                    min(
                                        $total_pages,
                                        $current_page + 1
                                    ),
                                    $pagination_url
                                );

                            ?>


                            <a
                                class="bcob-prefill-page-button <?php echo $current_page <= 1 ? 'is-disabled' : ''; ?>"
                                href="<?php echo esc_url($previous_url); ?>"
                                aria-label="<?php esc_attr_e(
                                                'Previous page',
                                                'best-connect-onboarding'
                                            ); ?>">

                                <i class="ri-arrow-left-line"></i>

                            </a>


                            <span class="bcob-prefill-page-number">

                                <?php echo esc_html(
                                    $current_page
                                ); ?>

                                /

                                <?php echo esc_html(
                                    $total_pages
                                ); ?>

                            </span>


                            <a
                                class="bcob-prefill-page-button <?php echo $current_page >= $total_pages ? 'is-disabled' : ''; ?>"
                                href="<?php echo esc_url($next_url); ?>"
                                aria-label="<?php esc_attr_e(
                                                'Next page',
                                                'best-connect-onboarding'
                                            ); ?>">

                                <i class="ri-arrow-right-line"></i>

                            </a>

                        </nav>

                    </footer>

                <?php endif; ?>

            </section>

        </div>

<?php
    }

    private static function admin_assets(): void
    {
        wp_enqueue_style(
            'bcob-admin-prefill',
            BCOB_URL . 'assets/css/admin-prefill.css',
            [],
            BCOB_VER
        );

        wp_enqueue_script(
            'bcob-admin-prefill',
            BCOB_URL . 'assets/js/admin/admin-prefill.js',
            ['jquery'],
            BCOB_VER,
            true
        );

        wp_localize_script(
            'bcob-admin-prefill',
            'BCOBPrefillAdmin',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bcob_prefill_admin'),
            ]
        );
    }

    public function frontend_assets(): void
    {
        wp_enqueue_style(
            'bcob-prefill',
            BCOB_URL . 'assets/css/frontend/prefill.css',
            [],
            BCOB_VER
        );
        wp_enqueue_script(
            'bcob-prefill',
            BCOB_URL . 'assets/js/frontend/prefill.js',
            ['jquery'],
            BCOB_VER,
            true
        );

        wp_localize_script(
            'bcob-prefill',
            'BCOBPrefill',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bcob_prefill'),
                'is_admin' => current_user_can('manage_options'),
            ]
        );
    }

    public function create(): void
    {
        check_ajax_referer(
            'bcob_prefill',
            'nonce'
        );

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                ['message' => 'Permission denied.'],
                403
            );
        }

        $form_id = absint(
            $_POST['form_id'] ?? 0
        );

        $membership = sanitize_text_field(
            wp_unslash($_POST['membership'] ?? '')
        );

        $portal_index = sanitize_text_field(
            wp_unslash($_POST['portal_index'] ?? '')
        );

        $page_url = esc_url_raw(
            wp_unslash($_POST['page_url'] ?? '')
        );

        $expiry = sanitize_key(
            wp_unslash($_POST['expiry'] ?? '1_month')
        );

        $values = json_decode(
            wp_unslash($_POST['values'] ?? '{}'),
            true
        );

        if (
            !$form_id ||
            !$page_url ||
            !is_array($values) ||
            !isset(self::EXPIRY_OPTIONS[$expiry])
        ) {
            wp_send_json_error(
                ['message' => 'Invalid pre-fill data.'],
                400
            );
        }

        global $wpdb;

        $forms_table = $wpdb->prefix . 'bcob_forms';
        $prefill_table = $wpdb->prefix . 'bcob_prefill_links';

        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$forms_table} WHERE id = %d LIMIT 1",
                $form_id
            )
        );

        if (!$form) {
            wp_send_json_error(
                ['message' => 'Form not found.'],
                404
            );
        }

        $created_at = current_time('mysql');
        $expires_at = self::calculate_expiry(
            $created_at,
            $expiry
        );

        $token = bin2hex(
            random_bytes(32)
        );

        $page_url = remove_query_arg(
            'lead',
            $page_url
        );

        $data = [
            'page_url' => $page_url,
            'membership' => $membership,
            'portal_index' => $portal_index,
            'expiry' => $expiry,
            'values' => $values,
        ];

        $inserted = $wpdb->insert(
            $prefill_table,
            [
                'form_id' => $form_id,
                'token' => $token,
                'prefill_data' => wp_json_encode($data),
                'expires_at' => $expires_at,
                'created_at' => $created_at,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if (!$inserted) {
            wp_send_json_error(
                ['message' => 'Unable to save the pre-fill link.'],
                500
            );
        }

        wp_send_json_success(
            [
                'url' => add_query_arg(
                    'lead',
                    $token,
                    $page_url
                ),
            ]
        );
    }

    public function get(): void
    {
        $token =
            sanitize_text_field(
                wp_unslash(
                    $_POST['token'] ?? ''
                )
            );

        if (
            !preg_match(
                '/^[a-f0-9]{64}$/',
                $token
            )
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    'Invalid pre-fill link.',
                ],
                400
            );
        }

        global $wpdb;

        $table =
            $wpdb->prefix .
            'bcob_prefill_links';

        $now =
            current_time(
                'mysql'
            );

        $claimed =
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                SET
                    status = 'processing',
                    claimed_at = %s
                WHERE token = %s
                AND status = 'active'
                AND (
                    expires_at IS NULL
                    OR expires_at > %s
                )",
                    $now,
                    $token,
                    $now
                )
            );

        if ($claimed !== 1) {
            $row =
                $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT *
                    FROM {$table}
                    WHERE token = %s
                    LIMIT 1",
                        $token
                    )
                );

            if (!$row) {
                wp_send_json_error(
                    [
                        'message' =>
                        'Pre-fill link not found.',
                    ],
                    404
                );
            }

            if (
                $row->expires_at &&
                strtotime(
                    $row->expires_at
                ) <=
                current_time(
                    'timestamp'
                )
            ) {
                wp_send_json_error(
                    [
                        'message' =>
                        'Pre-fill link has expired.',
                    ],
                    410
                );
            }

            $status =
                sanitize_key(
                    $row->status ?? ''
                );

            if ($status === 'used') {
                wp_send_json_error(
                    [
                        'message' =>
                        'Pre-fill link has already been used.',
                    ],
                    410
                );
            }

            if ($status === 'processing') {
                wp_send_json_error(
                    [
                        'message' =>
                        'Pre-fill link is already being processed.',
                    ],
                    409
                );
            }

            wp_send_json_error(
                [
                    'message' =>
                    'Pre-fill link is unavailable.',
                ],
                409
            );
        }

        $release_claim =
            static function () use (
                $wpdb,
                $table,
                $token
            ): void {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table}
                    SET
                        status = 'active',
                        claimed_at = NULL
                    WHERE token = %s
                    AND status = 'processing'",
                        $token
                    )
                );
            };

        $row =
            $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                FROM {$table}
                WHERE token = %s
                AND status = 'processing'
                LIMIT 1",
                    $token
                )
            );

        if (!$row) {
            $release_claim();

            wp_send_json_error(
                [
                    'message' =>
                    'Pre-fill link not found.',
                ],
                404
            );
        }

        $data =
            json_decode(
                $row->prefill_data,
                true
            );

        if (!is_array($data)) {
            $release_claim();

            wp_send_json_error(
                [
                    'message' =>
                    'Invalid pre-fill data.',
                ],
                500
            );
        }

        $form =
            $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT form_schema
                FROM {$wpdb->prefix}bcob_forms
                WHERE id = %d
                LIMIT 1",
                    (int) $row->form_id
                )
            );

        if (!$form) {
            $release_claim();

            wp_send_json_error(
                [
                    'message' =>
                    'Form not found.',
                ],
                404
            );
        }

        $schema =
            json_decode(
                $form->form_schema,
                true
            );

        if (!is_array($schema)) {
            $release_claim();

            wp_send_json_error(
                [
                    'message' =>
                    'Invalid form schema.',
                ],
                500
            );
        }

        $allowed_fields = [];

        foreach (
            ($schema['steps'] ?? [])
            as $step
        ) {
            if (!is_array($step)) {
                continue;
            }

            foreach (
                ($step['fields'] ?? [])
                as $field
            ) {
                if (!is_array($field)) {
                    continue;
                }

                $field_id =
                    sanitize_text_field(
                        $field['id'] ?? ''
                    );

                $field_key =
                    sanitize_text_field(
                        $field['key'] ?? ''
                    );

                if ($field_id !== '') {
                    $allowed_fields[$field_id] = true;
                } elseif ($field_key !== '') {
                    $allowed_fields[$field_key] = true;
                }
            }
        }

        $values = [];

        foreach (
            ($data['values'] ?? [])
            as $key => $value
        ) {
            if (
                is_string($key) &&
                isset(
                    $allowed_fields[$key]
                )
            ) {
                $values[$key] = $value;
            }
        }

        $used_at =
            current_time(
                'mysql'
            );

        $consumed =
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                SET
                    status = 'used',
                    claimed_at = NULL,
                    used_at = %s
                WHERE token = %s
                AND status = 'processing'",
                    $used_at,
                    $token
                )
            );

        if ($consumed !== 1) {
            $release_claim();

            wp_send_json_error(
                [
                    'message' =>
                    'Unable to consume the pre-fill link.',
                ],
                500
            );
        }

        wp_send_json_success(
            [
                'form_id' =>
                (int) $row->form_id,

                'membership' =>
                $data['membership']
                    ?? '',

                'portal_index' =>
                $data['portal_index']
                    ?? '',

                'values' =>
                $values,
            ]
        );
    }

    public function update_expiry(): void
    {
        check_ajax_referer(
            'bcob_prefill_admin',
            'nonce'
        );

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                ['message' => 'Permission denied.'],
                403
            );
        }

        $id = absint(
            $_POST['id'] ?? 0
        );

        $expiry = sanitize_key(
            wp_unslash($_POST['expiry'] ?? '')
        );

        if (
            !$id ||
            !isset(self::EXPIRY_OPTIONS[$expiry])
        ) {
            wp_send_json_error(
                ['message' => 'Invalid expiry option.'],
                400
            );
        }

        global $wpdb;

        $table = $wpdb->prefix . 'bcob_prefill_links';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT created_at, prefill_data
            FROM {$table}
            WHERE id = %d
            LIMIT 1",
                $id
            )
        );

        if (!$row) {
            wp_send_json_error(
                ['message' => 'Pre-fill link not found.'],
                404
            );
        }

        $expires_at = self::calculate_expiry(
            $row->created_at,
            $expiry
        );

        $data = json_decode(
            $row->prefill_data,
            true
        );

        if (!is_array($data)) {
            $data = [];
        }

        $data['expiry'] = $expiry;

        $updated = $wpdb->update(
            $table,
            [
                'expires_at' => $expires_at,
                'prefill_data' => wp_json_encode($data),
            ],
            [
                'id' => $id,
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );

        if ($updated === false) {
            wp_send_json_error(
                ['message' => 'Unable to update expiry.'],
                500
            );
        }

        wp_send_json_success(
            [
                'expires_at' => $expires_at,
                'expired' => strtotime($expires_at) <= current_time('timestamp'),
            ]
        );
    }

    public function delete(): void
    {
        check_ajax_referer(
            'bcob_prefill_admin',
            'nonce'
        );

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                ['message' => 'Permission denied.'],
                403
            );
        }

        $id = absint(
            $_POST['id'] ?? 0
        );

        if (!$id) {
            wp_send_json_error(
                ['message' => 'Invalid pre-fill link.'],
                400
            );
        }

        global $wpdb;

        $table = $wpdb->prefix . 'bcob_prefill_links';

        $deleted = $wpdb->delete(
            $table,
            [
                'id' => $id,
            ],
            [
                '%d',
            ]
        );

        if (!$deleted) {
            wp_send_json_error(
                ['message' => 'Pre-fill link not found.'],
                404
            );
        }

        wp_send_json_success();
    }

    private static function calculate_expiry(
        string $created_at,
        string $expiry
    ): string {
        $date = new \DateTime(
            $created_at,
            wp_timezone()
        );

        switch ($expiry) {
            case '1_hour':
                $date->modify('+1 hour');
                break;

            case '24_hours':
                $date->modify('+24 hours');
                break;

            case '1_week':
                $date->modify('+1 week');
                break;

            case '1_month':
                $date->modify('+1 month');
                break;

            case '1_year':
                $date->modify('+1 year');
                break;
        }

        return $date->format('Y-m-d H:i:s');
    }
}
