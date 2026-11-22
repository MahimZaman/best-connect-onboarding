<?php

namespace BestConnectOnboarding\Admin;

defined('ABSPATH') || exit;

class Leads
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'best-connect-onboarding'));
        }

        self::assets();

        $lead_id = isset($_GET['lead_id']) ? absint(wp_unslash($_GET['lead_id'])) : 0;

        $lead_id ? self::detail($lead_id) : self::list();
    }

    private static function assets(): void
    {
        wp_enqueue_style(
            'bcob-admin-leads',
            BCOB_URL . 'assets/css/admin-leads.css',
            [],
            BCOB_VER
        );

        wp_enqueue_script(
            'bcob-admin-leads',
            BCOB_URL . 'assets/js/admin/admin-leads.js',
            [],
            BCOB_VER,
            true
        );
    }

    private static function list(): void
    {
        global $wpdb;

        $leads_table = $wpdb->prefix . 'bcob_leads';
        $forms_table = $wpdb->prefix . 'bcob_forms';

        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
        $offset = ($page - 1) * $per_page;

        $search = isset($_GET['s'])
            ? sanitize_text_field(wp_unslash($_GET['s']))
            : '';

        $status = isset($_GET['status'])
            ? sanitize_key(wp_unslash($_GET['status']))
            : '';

        $form_id = isset($_GET['form_id'])
            ? absint(wp_unslash($_GET['form_id']))
            : 0;

        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';

            $where[] = '(
        CAST(l.id AS CHAR) LIKE %s
        OR l.token LIKE %s
        OR l.submission LIKE %s
        OR f.name LIKE %s
    )';

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== '') {
            $where[] = 'l.status = %s';
            $params[] = $status;
        }

        if ($form_id) {
            $where[] = 'l.form_id = %d';
            $params[] = $form_id;
        }

        $where = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(*) FROM {$leads_table} l WHERE {$where}";

        $total = (int) $wpdb->get_var(
            $params ? $wpdb->prepare($count_sql, $params) : $count_sql
        );

        $pages = max(1, (int) ceil($total / $per_page));

        $sql = "
            SELECT
                l.id,
                l.form_id,
                l.token,
                l.submission,
                l.status,
                l.created_at,
                l.updated_at,
                f.name AS form_name
            FROM {$leads_table} l
            LEFT JOIN {$forms_table} f ON f.id = l.form_id
            WHERE {$where}
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT %d OFFSET %d
        ";

        $leads = $wpdb->get_results(
            $wpdb->prepare($sql, array_merge($params, [$per_page, $offset]))
        );

        $forms = $wpdb->get_results(
            "SELECT id, name FROM {$forms_table} ORDER BY name ASC"
        );

        $statuses = $wpdb->get_col(
            "SELECT DISTINCT status FROM {$leads_table} WHERE status <> '' ORDER BY status ASC"
        );

        $base_url = menu_page_url('bcob-leads', false);
?>
        <div class="wrap bcob-leads-page">

            <div class="bcob-page-header">
                <div>
                    <h1><?php esc_html_e('Leads', 'best-connect-onboarding'); ?></h1>
                    <p><?php esc_html_e('View customer onboarding submissions.', 'best-connect-onboarding'); ?></p>
                </div>

                <strong><?php echo esc_html(number_format_i18n($total)); ?> <?php esc_html_e('leads', 'best-connect-onboarding'); ?></strong>
            </div>

            <form class="bcob-filters" method="get">
                <input type="hidden" name="page" value="bcob-leads">

                <input
                    type="search"
                    name="s"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="<?php esc_attr_e('Search ID, email, name or form...', 'best-connect-onboarding'); ?>">

                <select name="form_id">
                    <option value="0"><?php esc_html_e('All forms', 'best-connect-onboarding'); ?></option>

                    <?php foreach ($forms as $form) : ?>
                        <option value="<?php echo esc_attr($form->id); ?>" <?php selected($form_id, (int) $form->id); ?>>
                            <?php echo esc_html($form->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value=""><?php esc_html_e('All statuses', 'best-connect-onboarding'); ?></option>

                    <?php foreach ($statuses as $item) : ?>
                        <option value="<?php echo esc_attr($item); ?>" <?php selected($status, $item); ?>>
                            <?php echo esc_html(ucfirst($item)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Filter', 'best-connect-onboarding'); ?>
                </button>

                <?php if ($search || $status || $form_id) : ?>
                    <a href="<?php echo esc_url($base_url); ?>">
                        <?php esc_html_e('Clear', 'best-connect-onboarding'); ?>
                    </a>
                <?php endif; ?>
            </form>

            <div class="bcob-card">
                <table class="bcob-leads-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Lead', 'best-connect-onboarding'); ?></th>
                            <th><?php esc_html_e('Form', 'best-connect-onboarding'); ?></th>
                            <th><?php esc_html_e('Status', 'best-connect-onboarding'); ?></th>
                            <th><?php esc_html_e('Created', 'best-connect-onboarding'); ?></th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$leads) : ?>
                            <tr>
                                <td colspan="5" class="bcob-empty">
                                    <?php esc_html_e('No leads found.', 'best-connect-onboarding'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($leads as $lead) : ?>
                                <?php
                                $submission = self::decode($lead->submission);
                                $identity = self::identity($submission['values']);
                                $url = add_query_arg('lead_id', (int) $lead->id, $base_url);
                                $delivery_open =
                                    $lead->status === 'completed' &&
                                    !self::delivery_complete(
                                        $submission
                                    );
                                ?>

                                <tr>
                                    <td>
                                        <a class="bcob-lead" href="<?php echo esc_url($url); ?>">
                                            <span class="bcob-avatar">
                                                <?php echo esc_html(self::initials($identity['name'], (int) $lead->id)); ?>
                                            </span>

                                            <span>
                                                <strong><?php echo esc_html($identity['name']); ?></strong>
                                                <small>#<?php echo esc_html($lead->id); ?></small>
                                            </span>
                                        </a>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            $lead->form_name ?: sprintf(
                                                __('Form #%d', 'best-connect-onboarding'),
                                                (int) $lead->form_id
                                            )
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php if ($delivery_open) : ?>

                                            <span
                                                class="bcob-status payment_failed"
                                                title="<?php esc_attr_e(
                                                            'DATEV or back-office contract delivery is incomplete.',
                                                            'best-connect-onboarding'
                                                        ); ?>">

                                                <i></i>

                                                <?php esc_html_e(
                                                    'Open delivery case',
                                                    'best-connect-onboarding'
                                                ); ?>

                                            </span>

                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php echo esc_html(self::date($lead->created_at)); ?>
                                    </td>

                                    <td class="bcob-action">
                                        <a href="<?php echo esc_url($url); ?>" aria-label="<?php esc_attr_e('View lead', 'best-connect-onboarding'); ?>">
                                            <i class="ri-arrow-right-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($pages > 1) : ?>
                    <div class="bcob-pagination">
                        <span>
                            <?php
                            printf(
                                esc_html__('Showing %1$s–%2$s of %3$s', 'best-connect-onboarding'),
                                esc_html(number_format_i18n($offset + 1)),
                                esc_html(number_format_i18n(min($offset + $per_page, $total))),
                                esc_html(number_format_i18n($total))
                            );
                            ?>
                        </span>

                        <div>
                            <?php
                            $args = array_filter([
                                'page' => 'bcob-leads',
                                's' => $search,
                                'status' => $status,
                                'form_id' => $form_id,
                            ]);

                            $args['paged'] = max(1, $page - 1);
                            ?>

                            <a href="<?php echo esc_url(add_query_arg($args, $base_url)); ?>"><i class="ri-arrow-left-long-line"></i></a>

                            <span><?php echo esc_html($page); ?> / <?php echo esc_html($pages); ?></span>

                            <?php
                            $args['paged'] = min($pages, $page + 1);
                            ?>

                            <a href="<?php echo esc_url(add_query_arg($args, $base_url)); ?>">→</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    private static function detail(int $lead_id): void
    {
        global $wpdb;

        $leads_table = $wpdb->prefix . 'bcob_leads';
        $forms_table = $wpdb->prefix . 'bcob_forms';

        $lead = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT l.*, f.name AS form_name, f.form_schema
                FROM {$leads_table} l
                LEFT JOIN {$forms_table} f ON f.id = l.form_id
                WHERE l.id = %d
                LIMIT 1",
                $lead_id
            )
        );


        if (!$lead) {
            wp_die(
                esc_html__('Lead not found.', 'best-connect-onboarding'),
                esc_html__('Lead not found', 'best-connect-onboarding'),
                ['response' => 404]
            );
        }

        $submission = self::decode($lead->submission);
        $form_data = $submission['values'] ?? [];
        $identity = self::identity($form_data);
        $base_url = menu_page_url('bcob-leads', false);
        $delivery_state =
            self::delivery_state(
                $submission
            );

        $delivery_open =
            $lead->status === 'completed' &&
            !self::delivery_complete(
                $submission
            );
    ?>

        <div class="wrap bcob-leads-page">

            <a class="bcob-back" href="<?php echo esc_url($base_url); ?>">
                <i class="ri-arrow-left-long-line"></i> <?php esc_html_e('Back to leads', 'best-connect-onboarding'); ?>
            </a>

            <div class="bcob-detail-header">
                <div class="bcob-detail-person">
                    <span class="bcob-avatar bcob-avatar-large">
                        <?php echo esc_html(self::initials($identity['name'], (int) $lead->id)); ?>
                    </span>

                    <div>
                        <small>#<?php echo esc_html($lead->id); ?></small>
                        <h1><?php echo esc_html($identity['name']); ?></h1>
                        <p><?php echo esc_html($lead->form_name); ?></p>
                    </div>
                </div>

                <span class="bcob-status <?php echo esc_html($lead->status) ?>">
                    <i></i>
                    <?php echo esc_html(ucfirst($lead->status)); ?>
                </span>
            </div>

            <div class="bcob-detail-layout">

                <main>

                    <section class="bcob-card">
                        <div class="bcob-section-title">
                            <h2><?php esc_html_e('Form data', 'best-connect-onboarding'); ?></h2>
                        </div>

                        <?php
                        $has_form_data = false;

                        foreach ($form_data as $field) {
                            if (!isset($field['value']) || $field['value'] === '' || $field['value'] === null) {
                                continue;
                            }

                            if (is_array($field['value']) && empty(array_filter($field['value'], function ($value) {
                                return $value !== '' && $value !== null;
                            }))) {
                                continue;
                            }

                            $has_form_data = true;
                        ?>
                            <div class="bcob-data-row">
                                <div class="bcob-data-label">
                                    <?php echo esc_html($field['label'] ?? ''); ?>
                                </div>

                                <div class="bcob-data-value">
                                    <?php echo wp_kses_post(self::value($field['value'])); ?>
                                </div>
                            </div>
                        <?php
                        }

                        if (!$has_form_data) :
                        ?>
                            <div class="bcob-empty">
                                <?php esc_html_e('No form data available.', 'best-connect-onboarding'); ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <?php if (!empty($submission['products'])) : ?>
                        <section class="bcob-card">
                            <div class="bcob-section-title">
                                <h2><?php esc_html_e('Products', 'best-connect-onboarding'); ?></h2>
                            </div>

                            <?php foreach ($submission['products'] as $product) : ?>
                                <?php if (empty($product['name']) && empty($product['price'])) continue; ?>

                                <div class="bcob-data-row">
                                    <div class="bcob-data-label">
                                        <?php echo esc_html($product['name'] ?? 'Product'); ?>
                                    </div>

                                    <div class="bcob-data-value">
                                        <?php
                                        if (isset($product['price']) && $product['price'] !== '') {
                                            echo esc_html(number_format_i18n((float) $product['price'], 2));
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>

                    <?php if (
                        !empty($submission['subtotal']) ||
                        !empty($submission['tax']) ||
                        !empty($submission['discount_total']) ||
                        !empty($submission['total']) ||
                        !empty($submission['currency'])
                    ) : ?>
                        <section class="bcob-card">
                            <div class="bcob-section-title">
                                <h2><?php esc_html_e('Payment summary', 'best-connect-onboarding'); ?></h2>
                            </div>

                            <?php if (!empty($submission['subtotal'])) : ?>
                                <div class="bcob-data-row">
                                    <div class="bcob-data-label"><?php esc_html_e('Subtotal', 'best-connect-onboarding'); ?></div>
                                    <div class="bcob-data-value">
                                        <?php echo esc_html(number_format_i18n((float) $submission['subtotal'], 2)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($submission['tax'])) : ?>
                                <div class="bcob-data-row">
                                    <div class="bcob-data-label"><?php esc_html_e('Tax', 'best-connect-onboarding'); ?></div>
                                    <div class="bcob-data-value">
                                        <?php echo esc_html(number_format_i18n((float) $submission['tax'], 2)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($submission['discount_total'])) : ?>
                                <div class="bcob-data-row">
                                    <div class="bcob-data-label"><?php esc_html_e('Discount', 'best-connect-onboarding'); ?></div>
                                    <div class="bcob-data-value">
                                        <?php echo esc_html(number_format_i18n((float) $submission['discount_total'], 2)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($submission['total'])) : ?>
                                <div class="bcob-data-row">
                                    <div class="bcob-data-label"><?php esc_html_e('Total', 'best-connect-onboarding'); ?></div>
                                    <div class="bcob-data-value">
                                        <?php echo esc_html(number_format_i18n((float) $submission['total'], 2)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($submission['currency'])) : ?>
                                <div class="bcob-data-row">
                                    <div class="bcob-data-label"><?php esc_html_e('Currency', 'best-connect-onboarding'); ?></div>
                                    <div class="bcob-data-value">
                                        <?php echo esc_html($submission['currency']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <?php if (!empty($submission['payment'])) : ?>
                        <section class="bcob-card">
                            <div class="bcob-section-title">
                                <h2><?php esc_html_e('Payment', 'best-connect-onboarding'); ?></h2>
                            </div>

                            <?php if (!empty($submission['payment']['provider'])) : ?>
                                <div class="bcob-data-row">
                                    <div class="bcob-data-label"><?php esc_html_e('Provider', 'best-connect-onboarding'); ?></div>
                                    <div class="bcob-data-value">
                                        <?php echo esc_html(ucfirst($submission['payment']['provider'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($submission['payment']['type'])) : ?>
                                <div class="bcob-data-row">
                                    <div class="bcob-data-label"><?php esc_html_e('Type', 'best-connect-onboarding'); ?></div>
                                    <div class="bcob-data-value">
                                        <?php echo esc_html(ucfirst($submission['payment']['type'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php
                            $payment_status = $submission['payment']['response']['paymentIntent']['status'] ?? '';

                            if ($payment_status) :
                            ?>
                                <div class="bcob-data-row">
                                    <div class="bcob-data-label"><?php esc_html_e('Status', 'best-connect-onboarding'); ?></div>
                                    <div class="bcob-data-value">
                                        <?php echo esc_html(ucfirst($payment_status)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php
                            $payment_id = $submission['payment']['response']['paymentIntent']['id'] ?? '';

                            if ($payment_id) :
                            ?>
                                <div class="bcob-data-row">
                                    <div class="bcob-data-label"><?php esc_html_e('Payment ID', 'best-connect-onboarding'); ?></div>
                                    <div class="bcob-data-value">
                                        <?php echo esc_html($payment_id); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <section class="bcob-card">

                        <div class="bcob-section-title">
                            <h2>
                                <?php esc_html_e(
                                    'External Delivery',
                                    'best-connect-onboarding'
                                ); ?>
                            </h2>
                        </div>

                        <?php if ($delivery_open) : ?>

                            <div class="notice notice-warning inline">
                                <p>
                                    <strong>
                                        <?php esc_html_e(
                                            'Open delivery case',
                                            'best-connect-onboarding'
                                        ); ?>
                                    </strong>

                                    —
                                    <?php esc_html_e(
                                        'This lead will not be removed by retention cleanup until both external deliveries are confirmed.',
                                        'best-connect-onboarding'
                                    ); ?>
                                </p>
                            </div>

                        <?php endif; ?>

                        <div class="bcob-data-row">

                            <div class="bcob-data-label">
                                <?php esc_html_e(
                                    'DATEV Middleware',
                                    'best-connect-onboarding'
                                ); ?>
                            </div>

                            <div class="bcob-data-value">
                                <?php
                                echo esc_html(
                                    self::delivery_label(
                                        $delivery_state['middleware']
                                    )
                                );
                                ?>
                            </div>

                        </div>

                        <div class="bcob-data-row">

                            <div class="bcob-data-label">
                                <?php esc_html_e(
                                    'Back-office Contract Email',
                                    'best-connect-onboarding'
                                ); ?>
                            </div>

                            <div class="bcob-data-value">
                                <?php
                                echo esc_html(
                                    self::delivery_label(
                                        $delivery_state['backoffice']
                                    )
                                );
                                ?>
                            </div>

                        </div>

                    </section>

                    <?php
                    $middleware =
                        is_array(
                            $submission['middleware'] ?? null
                        )
                        ? $submission['middleware']
                        : [];
                    ?>

                    <?php if (!empty($middleware)) : ?>

                        <section class="bcob-card">

                            <div class="bcob-section-title">
                                <h2>
                                    <?php esc_html_e(
                                        'DATEV Middleware',
                                        'best-connect-onboarding'
                                    ); ?>
                                </h2>
                            </div>

                            <div class="bcob-data-row">

                                <div class="bcob-data-label">
                                    <?php esc_html_e(
                                        'Status',
                                        'best-connect-onboarding'
                                    ); ?>
                                </div>

                                <div class="bcob-data-value">
                                    <?php
                                    echo esc_html(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $middleware['status']
                                                    ?? ''
                                            )
                                        )
                                    );
                                    ?>
                                </div>

                            </div>

                            <div class="bcob-data-row">

                                <div class="bcob-data-label">
                                    <?php esc_html_e(
                                        'Attempts',
                                        'best-connect-onboarding'
                                    ); ?>
                                </div>

                                <div class="bcob-data-value">
                                    <?php
                                    echo esc_html(
                                        absint(
                                            $middleware['attempts']
                                                ?? 0
                                        ) .
                                            ' / ' .
                                            absint(
                                                $middleware['max_attempts']
                                                    ?? 3
                                            )
                                    );
                                    ?>
                                </div>

                            </div>

                            <?php if (
                                !empty($middleware['http_status'])
                            ) : ?>

                                <div class="bcob-data-row">

                                    <div class="bcob-data-label">
                                        <?php esc_html_e(
                                            'HTTP Status',
                                            'best-connect-onboarding'
                                        ); ?>
                                    </div>

                                    <div class="bcob-data-value">
                                        <?php
                                        echo esc_html(
                                            absint(
                                                $middleware['http_status']
                                            )
                                        );
                                        ?>
                                    </div>

                                </div>

                            <?php endif; ?>

                            <?php if (
                                !empty($middleware['last_attempt_at'])
                            ) : ?>

                                <div class="bcob-data-row">

                                    <div class="bcob-data-label">
                                        <?php esc_html_e(
                                            'Last Attempt',
                                            'best-connect-onboarding'
                                        ); ?>
                                    </div>

                                    <div class="bcob-data-value">
                                        <?php
                                        echo esc_html(
                                            $middleware['last_attempt_at']
                                        );
                                        ?>
                                    </div>

                                </div>

                            <?php endif; ?>

                            <?php if (
                                !empty($middleware['next_retry_at'])
                            ) : ?>

                                <div class="bcob-data-row">

                                    <div class="bcob-data-label">
                                        <?php esc_html_e(
                                            'Next Retry',
                                            'best-connect-onboarding'
                                        ); ?>
                                    </div>

                                    <div class="bcob-data-value">
                                        <?php
                                        echo esc_html(
                                            $middleware['next_retry_at']
                                        );
                                        ?>
                                    </div>

                                </div>

                            <?php endif; ?>

                            <?php if (
                                !empty($middleware['last_error'])
                            ) : ?>

                                <div class="bcob-data-row">

                                    <div class="bcob-data-label">
                                        <?php esc_html_e(
                                            'Last Error',
                                            'best-connect-onboarding'
                                        ); ?>
                                    </div>

                                    <div class="bcob-data-value">
                                        <?php
                                        echo esc_html(
                                            $middleware['last_error']
                                        );
                                        ?>
                                    </div>

                                </div>

                            <?php endif; ?>

                        </section>

                    <?php endif; ?>

                    <?php if (!empty($submission['contracts'])) : ?>
                        <section class="bcob-card">
                            <div class="bcob-section-title">
                                <h2><?php esc_html_e('Contracts', 'best-connect-onboarding'); ?></h2>
                            </div>

                            <?php foreach ($submission['contracts'] as $contract) : ?>
                                <?php if (empty($contract['title']) && empty($contract['url'])) continue; ?>

                                <div class="bcob-data-row">
                                    <div class="bcob-data-label">
                                        <?php echo esc_html($contract['title'] ?? 'Contract'); ?>
                                    </div>

                                    <div class="bcob-data-value">
                                        <?php if (!empty($contract['url'])) : ?>
                                            <a href="<?php echo esc_url($contract['url']); ?>" target="_blank" rel="noopener">
                                                <?php esc_html_e('View contract', 'best-connect-onboarding'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>

                </main>

                <aside>

                    <section class="bcob-card bcob-info">
                        <h2><?php esc_html_e('Lead information', 'best-connect-onboarding'); ?></h2>

                        <div>
                            <span><?php esc_html_e('Lead ID', 'best-connect-onboarding'); ?></span>
                            <strong>#<?php echo esc_html($lead->id); ?></strong>
                        </div>

                        <div>
                            <span><?php esc_html_e('Form', 'best-connect-onboarding'); ?></span>
                            <strong><?php echo esc_html($lead->form_name); ?></strong>
                        </div>

                        <div>
                            <span><?php esc_html_e('Created', 'best-connect-onboarding'); ?></span>
                            <strong><?php echo esc_html(self::date($lead->created_at, true)); ?></strong>
                        </div>

                        <div>
                            <span><?php esc_html_e('Updated', 'best-connect-onboarding'); ?></span>
                            <strong><?php echo esc_html(self::date($lead->updated_at, true)); ?></strong>
                        </div>
                    </section>

                    <section class="bcob-card bcob-info">
                        <h2><?php esc_html_e('Token', 'best-connect-onboarding'); ?></h2>

                        <div class="bcob-token">
                            <span><?php echo esc_html(self::short_token($lead->token, 12)); ?></span>

                            <button
                                type="button"
                                class="bcob-copy"
                                data-copy="<?php echo esc_attr($lead->token); ?>">
                                <?php esc_html_e('Copy', 'best-connect-onboarding'); ?>
                            </button>
                        </div>
                    </section>

                </aside>

            </div>
        </div>
<?php
    }

    private static function decode(string $json): array
    {
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    private static function labels(string $schema): array
    {
        $data = json_decode($schema, true);
        $labels = [];

        if (!is_array($data)) {
            return $labels;
        }

        foreach (($data['steps'] ?? []) as $step) {
            foreach (($step['fields'] ?? []) as $field) {
                if (!empty($field['key'])) {
                    $labels[$field['key']] = $field['label'] ?? self::humanize($field['key']);
                }
            }
        }

        return $labels;
    }

    private static function identity(array $data): array
    {
        $email = '';

        foreach ($data as $field) {
            if (!is_array($field) || ($field['type'] ?? '') !== 'email') {
                continue;
            }

            if (!empty($field['value']) && is_string($field['value'])) {
                $email = $field['value'];
                break;
            }
        }

        return [
            'name'  => $email ?: 'Lead',
            'email' => $email,
            'phone' => '',
        ];
    }

    private static function first(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && trim((string) $data[$key]) !== '') {
                return trim((string) $data[$key]);
            }
        }

        return '';
    }

    private static function value($value): string
    {
        if (is_array($value)) {
            $value = array_filter($value, function ($item) {
                return $item !== '' && $item !== null;
            });

            return $value
                ? esc_html(implode(', ', $value))
                : '<span class="bcob-empty-value">—</span>';
        }

        if ($value === '' || $value === null) {
            return '<span class="bcob-empty-value">—</span>';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return nl2br(esc_html((string) $value));
    }

    private static function humanize(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    private static function initials(string $name, int $id): string
    {
        if ($name === __('Unnamed lead', 'best-connect-onboarding')) {
            return (string) ($id % 10);
        }

        $parts = preg_split('/\s+/', trim($name));
        $initials = strtoupper(substr($parts[0], 0, 1));

        if (count($parts) > 1) {
            $initials .= strtoupper(substr(end($parts), 0, 1));
        }

        return substr($initials, 0, 2);
    }

    private static function short_token(string $token, int $length = 8): string
    {
        return strlen($token) > $length
            ? substr($token, 0, $length) . '…'
            : $token;
    }

    private static function delivery_state(
        array $submission
    ): array {
        return [
            'middleware' =>
            sanitize_key(
                $submission['middleware']['status']
                    ?? ''
            ),

            'backoffice' =>
            sanitize_key(
                $submission['delivery']['backoffice_contract_email']['status']
                    ?? ''
            ),
        ];
    }

    private static function delivery_complete(
        array $submission
    ): bool {
        $state =
            self::delivery_state(
                $submission
            );

        return
            $state['middleware'] === 'success' &&
            $state['backoffice'] === 'success';
    }

    private static function delivery_label(
        string $status
    ): string {
        if ($status === '') {
            return __(
                'Not confirmed',
                'best-connect-onboarding'
            );
        }

        return ucwords(
            str_replace(
                '_',
                ' ',
                $status
            )
        );
    }

    private static function date(string $date, bool $time = false): string
    {
        $timestamp = strtotime($date);

        if (!$timestamp) {
            return $date;
        }

        return wp_date(
            $time ? 'M j, Y · g:i a' : 'M j, Y',
            $timestamp
        );
    }
}
