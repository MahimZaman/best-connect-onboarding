<?php

namespace BestConnectOnboarding\Admin;

defined('ABSPATH') || exit;

class Dashboard
{
    public static function render(): void
    {
        global $wpdb;

        $forms_table    = $wpdb->prefix . 'bcob_forms';
        $leads_table    = $wpdb->prefix . 'bcob_leads';
        $payments_table = $wpdb->prefix . 'bcob_payments';

        /*
         * Forms
         */
        $total_forms = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$forms_table}"
        );

        /*
         * Leads
         */
        $total_leads = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$leads_table}"
        );

        $today_leads = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$leads_table}
             WHERE created_at >= CURDATE()"
        );

        /*
         * Payments
         */
        $total_payments = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$payments_table}"
        );

        $payment_volume = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount), 0)
             FROM {$payments_table}"
        );

        $payment_currencies = $wpdb->get_col(
            "SELECT DISTINCT currency
             FROM {$payments_table}
             WHERE currency <> ''
             ORDER BY currency ASC"
        );

        /*
         * Recent leads
         */
        $recent_leads = $wpdb->get_results(
            "SELECT
                l.id,
                l.form_id,
                l.submission,
                l.status,
                l.created_at,
                f.name AS form_name
             FROM {$leads_table} l
             LEFT JOIN {$forms_table} f
                ON f.id = l.form_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT 5"
        );

        /*
         * Recent payments
         */
        $recent_payments = $wpdb->get_results(
            "SELECT
                p.id,
                p.lead_id,
                p.form_id,
                p.provider,
                p.transaction_id,
                p.amount,
                p.currency,
                p.status,
                p.created_at,
                f.name AS form_name
             FROM {$payments_table} p
             LEFT JOIN {$forms_table} f
                ON f.id = p.form_id
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT 5"
        );

?>

        <div class="wrap bcob-dashboard">

            <div class="bcob-page-header">

                <div>
                    <h1><?php esc_html_e('Best Connect', 'best-connect-onboarding'); ?></h1>

                    <p>
                        <?php esc_html_e(
                            'Overview of your onboarding platform.',
                            'best-connect-onboarding'
                        ); ?>
                    </p>
                </div>

                <div class="bcob-header-actions">

                    <a
                        href="<?php echo esc_url(admin_url('admin.php?page=bcob-forms')); ?>"
                        class="button button-primary">
                        <i class="ri-add-line"></i>
                        <?php esc_html_e('Create Form', 'best-connect-onboarding'); ?>
                    </a>

                    <a
                        href="<?php echo esc_url(admin_url('admin.php?page=bcob-leads')); ?>"
                        class="button">
                        <i class="ri-file-list-3-line"></i>
                        <?php esc_html_e('View Leads', 'best-connect-onboarding'); ?>
                    </a>

                </div>

            </div>


            <div class="bcob-stats-grid">

                <div class="bcob-card stat-card">

                    <div class="stat-icon">
                        <i class="ri-layout-grid-line"></i>
                    </div>

                    <div class="stat-content">
                        <span><?php esc_html_e('Total Forms', 'best-connect-onboarding'); ?></span>

                        <h2>
                            <?php echo esc_html(number_format_i18n($total_forms)); ?>
                        </h2>

                        <small>
                            <?php esc_html_e('Forms created', 'best-connect-onboarding'); ?>
                        </small>
                    </div>

                </div>


                <div class="bcob-card stat-card">

                    <div class="stat-icon">
                        <i class="ri-user-follow-line"></i>
                    </div>

                    <div class="stat-content">
                        <span><?php esc_html_e('Total Leads', 'best-connect-onboarding'); ?></span>

                        <h2>
                            <?php echo esc_html(number_format_i18n($total_leads)); ?>
                        </h2>

                        <small>
                            <?php
                            printf(
                                esc_html__('%s received today', 'best-connect-onboarding'),
                                esc_html(number_format_i18n($today_leads))
                            );
                            ?>
                        </small>
                    </div>

                </div>


                <div class="bcob-card stat-card">

                    <div class="stat-icon">
                        <i class="ri-bank-card-line"></i>
                    </div>

                    <div class="stat-content">
                        <span><?php esc_html_e('Payments', 'best-connect-onboarding'); ?></span>

                        <h2>
                            <?php echo esc_html(number_format_i18n($total_payments)); ?>
                        </h2>

                        <small>
                            <?php esc_html_e('Recorded payments', 'best-connect-onboarding'); ?>
                        </small>
                    </div>

                </div>


                <div class="bcob-card stat-card">

                    <div class="stat-icon">
                        <i class="ri-money-dollar-circle-line"></i>
                    </div>

                    <div class="stat-content">

                        <span><?php esc_html_e('Payment Volume', 'best-connect-onboarding'); ?></span>

                        <h2>
                            <?php echo esc_html(number_format_i18n($payment_volume, 2)); ?>
                        </h2>

                        <small>
                            <?php
                            if (count($payment_currencies) === 1) {
                                echo esc_html($payment_currencies[0]);
                            } elseif (count($payment_currencies) > 1) {
                                esc_html_e('Multiple currencies', 'best-connect-onboarding');
                            } else {
                                esc_html_e('No payments recorded', 'best-connect-onboarding');
                            }
                            ?>
                        </small>

                    </div>

                </div>

            </div>


            <div class="bcob-dashboard-grid">


                <!-- Quick Actions -->

                <div class="bcob-card">

                    <div class="bcob-card-header">
                        <h2><?php esc_html_e('Quick Actions', 'best-connect-onboarding'); ?></h2>
                    </div>

                    <div class="bcob-actions">

                        <a href="<?php echo esc_url(admin_url('admin.php?page=bcob-forms')); ?>">
                            <i class="ri-add-circle-line"></i>
                            <span><?php esc_html_e('Create New Form', 'best-connect-onboarding'); ?></span>
                        </a>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=bcob-forms')); ?>">
                            <i class="ri-layout-line"></i>
                            <span><?php esc_html_e('Manage Forms', 'best-connect-onboarding'); ?></span>
                        </a>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=bcob-leads')); ?>">
                            <i class="ri-user-search-line"></i>
                            <span><?php esc_html_e('Browse Leads', 'best-connect-onboarding'); ?></span>
                        </a>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=bcob-settings')); ?>">
                            <i class="ri-settings-3-line"></i>
                            <span><?php esc_html_e('Plugin Settings', 'best-connect-onboarding'); ?></span>
                        </a>

                    </div>

                </div>


                <!-- Recent Leads -->

                <div class="bcob-card">

                    <div class="bcob-card-header">

                        <h2><?php esc_html_e('Recent Leads', 'best-connect-onboarding'); ?></h2>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=bcob-leads')); ?>">
                            <?php esc_html_e('View all', 'best-connect-onboarding'); ?>
                        </a>

                    </div>


                    <?php if ($recent_leads) : ?>

                        <ul class="bcob-activity">

                            <?php foreach ($recent_leads as $lead) : ?>

                                <?php
                                $submission = json_decode($lead->submission, true);
                                $email = '';

                                foreach (($submission['values'] ?? []) as $field) {
                                    if (
                                        is_array($field) &&
                                        ($field['type'] ?? '') === 'email' &&
                                        !empty($field['value'])
                                    ) {
                                        $email = (string) $field['value'];
                                        break;
                                    }
                                }

                                $lead_url = add_query_arg(
                                    [
                                        'page'    => 'bcob-leads',
                                        'lead_id' => (int) $lead->id,
                                    ],
                                    admin_url('admin.php')
                                );
                                ?>

                                <li>

                                    <a href="<?php echo esc_url($lead_url); ?>">

                                        <i class="ri-user-add-line"></i>

                                        <div>

                                            <strong>
                                                <?php
                                                printf(
                                                    esc_html__('Lead #%d', 'best-connect-onboarding'),
                                                    (int) $lead->id
                                                );
                                                ?>
                                            </strong>

                                            <span>
                                                <?php
                                                echo esc_html(
                                                    $email
                                                        ?: (
                                                            $lead->form_name
                                                            ?: __('No contact information', 'best-connect-onboarding')
                                                        )
                                                );
                                                ?>
                                            </span>

                                        </div>

                                        <small>
                                            <?php
                                            echo esc_html(
                                                human_time_diff(
                                                    strtotime($lead->created_at),
                                                    current_time('timestamp')
                                                )
                                            );
                                            ?>
                                            <?php esc_html_e('ago', 'best-connect-onboarding'); ?>
                                        </small>

                                    </a>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php else : ?>

                        <div class="bcob-dashboard-empty">
                            <?php esc_html_e('No leads have been submitted yet.', 'best-connect-onboarding'); ?>
                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- Recent Payments -->

            <div class="bcob-card bcob-dashboard-section">

                <div class="bcob-card-header">

                    <h2><?php esc_html_e('Recent Payments', 'best-connect-onboarding'); ?>

                        <span>
                            <?php
                            printf(
                                esc_html__('%d recorded', 'best-connect-onboarding'),
                                $total_payments
                            );
                            ?>
                        </span>

                </div>


                <?php if ($recent_payments) : ?>

                    <div class="bcob-payment-list">

                        <?php foreach ($recent_payments as $payment) : ?>

                            <?php
                            $lead_url = add_query_arg(
                                [
                                    'page'    => 'bcob-leads',
                                    'lead_id' => (int) $payment->lead_id,
                                ],
                                admin_url('admin.php')
                            );

                            $payment_status = strtolower(
                                sanitize_key($payment->status)
                            );
                            ?>

                            <a
                                href="<?php echo esc_url($lead_url); ?>"
                                class="bcob-payment-item">

                                <span class="bcob-payment-icon">
                                    <i class="ri-bank-card-line"></i>
                                </span>

                                <span class="bcob-payment-info">

                                    <strong>
                                        <?php echo esc_html($payment->provider); ?>
                                    </strong>

                                    <small>
                                        <?php
                                        echo esc_html(
                                            $payment->form_name
                                                ?: sprintf(
                                                    __('Lead #%d', 'best-connect-onboarding'),
                                                    (int) $payment->lead_id
                                                )
                                        );
                                        ?>
                                    </small>

                                </span>

                                <span class="bcob-payment-amount">

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            number_format_i18n(
                                                (float) $payment->amount,
                                                2
                                            )
                                                . ' '
                                                . strtoupper($payment->currency)
                                        );
                                        ?>
                                    </strong>

                                    <small class="bcob-payment-status bcob-payment-status-<?php echo esc_attr($payment_status); ?>">
                                        <?php echo esc_html(ucfirst($payment->status)); ?>
                                    </small>

                                </span>

                                <span class="bcob-payment-date">
                                    <?php
                                    echo esc_html(
                                        human_time_diff(
                                            strtotime($payment->created_at),
                                            current_time('timestamp')
                                        )
                                    );
                                    ?>
                                    <?php esc_html_e('ago', 'best-connect-onboarding'); ?>
                                </span>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php else : ?>

                    <div class="bcob-dashboard-empty">
                        <?php esc_html_e('No payments have been recorded yet.', 'best-connect-onboarding'); ?>
                    </div>

                <?php endif; ?>

            </div>

        </div>

<?php
    }
}
