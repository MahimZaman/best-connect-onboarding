<?php

namespace BestConnectOnboarding\Payments;

defined('ABSPATH') || exit;

class Payment
{
    /**
     * Create or update a payment transaction.
     *
     * @param int   $lead_id Lead ID.
     * @param array $submission Submission data.
     * @return int Payment ID.
     */
    public static function create(
        int $lead_id,
        array $submission,
        ?float $amount = null,
        ?string $currency = null,
        array $offer_context = []
    ): int {
        global $wpdb;

        $payment = $submission['payment'] ?? [];
        $response = $payment['response'] ?? [];

        $membership_id =
            absint(
                $offer_context['membership_id'] ?? 0
            );

        $portal_index =
            array_key_exists(
                'portal_index',
                $offer_context
            )
            ? absint(
                $offer_context['portal_index']
            )
            : null;

        $form_id =
            absint(
                $offer_context['form_id'] ?? 0
            );

        $snapshot_data =
            $offer_context['price_snapshot'] ?? [];

        if (
            !$lead_id ||
            !$membership_id ||
            $portal_index === null ||
            !$form_id ||
            !is_array($snapshot_data) ||
            empty($snapshot_data)
        ) {
            throw new \Exception(
                __(
                    'Invalid payment context.',
                    'best-connect-onboarding'
                )
            );
        }

        $price_snapshot =
            wp_json_encode(
                $snapshot_data
            );

        if ($price_snapshot === false) {
            throw new \Exception(
                __(
                    'Unable to create payment snapshot.',
                    'best-connect-onboarding'
                )
            );
        }

        if ($amount === null) {
            $amount =
                (float) (
                    $response['amount'] ?? 0
                ) / 100;
        }

        if ($currency === null) {
            $currency =
                (string) (
                    $response['currency'] ?? ''
                );
        }

        $amount = max(
            0,
            $amount
        );

        $currency =
            strtoupper(
                sanitize_text_field(
                    $currency
                )
            );

        $payment_type =
            ($payment['type'] ?? 'one_time') === 'recurring'
            ? 'recurring'
            : 'one_time';

        $payment_intent_id =
            sanitize_text_field(
                $payment['payment_intent_id']
                    ?? $response['paymentIntent']['id']
                    ?? $response['id']
                    ?? ''
            );

        $subscription_id =
            sanitize_text_field(
                $payment['subscription_id']
                    ?? $response['subscription']['id']
                    ?? ''
            );

        $customer_id =
            sanitize_text_field(
                $payment['customer_id']
                    ?? ''
            );

        $price_id =
            sanitize_text_field(
                $payment['price_id']
                    ?? ''
            );

        $product_id =
            sanitize_text_field(
                $payment['product_id']
                    ?? ''
            );

        $invoice_id =
            sanitize_text_field(
                $payment['invoice_id']
                    ?? ''
            );

        $status =
            sanitize_key(
                $payment['status']
                    ?? $response['status']
                    ?? 'pending'
            );

        $transaction_id =
            sanitize_text_field(
                $payment_intent_id
                    ?: $invoice_id
                    ?: $subscription_id
            );

        $table =
            $wpdb->prefix .
            'bcob_payments';

        $existing_id = 0;

        if ($payment_intent_id !== '') {
            $existing_id =
                (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id
                    FROM {$table}
                    WHERE payment_intent_id = %s
                    ORDER BY id DESC
                    LIMIT 1",
                        $payment_intent_id
                    )
                );
        }

        if (
            !$existing_id &&
            $invoice_id !== ''
        ) {
            $existing_id =
                (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id
                    FROM {$table}
                    WHERE invoice_id = %s
                    ORDER BY id DESC
                    LIMIT 1",
                        $invoice_id
                    )
                );
        }

        if ($existing_id) {
            $existing_payment =
                $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT
                        lead_id,
                        form_id,
                        membership_id,
                        portal_index,
                        price_snapshot
                    FROM {$table}
                    WHERE id = %d
                    LIMIT 1",
                        $existing_id
                    )
                );

            if (
                !$existing_payment ||
                (int) $existing_payment->lead_id !== $lead_id ||
                (int) $existing_payment->form_id !== $form_id
            ) {
                throw new \Exception(
                    __(
                        'Invalid payment authorization.',
                        'best-connect-onboarding'
                    )
                );
            }

            if (
                $existing_payment->membership_id !== null &&
                (int) $existing_payment->membership_id !==
                $membership_id
            ) {
                throw new \Exception(
                    __(
                        'Payment context does not match.',
                        'best-connect-onboarding'
                    )
                );
            }

            if (
                $existing_payment->portal_index !== null &&
                (int) $existing_payment->portal_index !==
                $portal_index
            ) {
                throw new \Exception(
                    __(
                        'Payment context does not match.',
                        'best-connect-onboarding'
                    )
                );
            }

            if (
                $existing_payment->membership_id === null &&
                $existing_payment->portal_index === null &&
                empty($existing_payment->price_snapshot)
            ) {
                $wpdb->update(
                    $table,
                    [
                        'membership_id' =>
                        $membership_id,

                        'portal_index' =>
                        $portal_index,

                        'price_snapshot' =>
                        $price_snapshot,
                    ],
                    [
                        'id' =>
                        $existing_id,
                    ],
                    [
                        '%d',
                        '%d',
                        '%s',
                    ],
                    [
                        '%d',
                    ]
                );
            }

            self::update(
                $existing_id,
                [
                    'status' =>
                    $status,

                    'payment_type' =>
                    $payment_type,

                    'payment_intent_id' =>
                    $payment_intent_id,

                    'subscription_id' =>
                    $subscription_id,

                    'customer_id' =>
                    $customer_id,

                    'price_id' =>
                    $price_id,

                    'product_id' =>
                    $product_id,

                    'invoice_id' =>
                    $invoice_id,

                    'transaction_id' =>
                    $transaction_id,

                    'response' =>
                    wp_json_encode(
                        $response
                    ),
                ]
            );

            return $existing_id;
        }

        $result =
            $wpdb->insert(
                $table,
                [
                    'lead_id' =>
                    $lead_id,

                    'form_id' =>
                    $form_id,

                    'membership_id' =>
                    $membership_id,

                    'portal_index' =>
                    $portal_index,

                    'price_snapshot' =>
                    $price_snapshot,

                    'provider' =>
                    sanitize_text_field(
                        $payment['provider']
                            ?? 'stripe'
                    ),

                    'transaction_id' =>
                    $transaction_id,

                    'payment_type' =>
                    $payment_type,

                    'payment_intent_id' =>
                    $payment_intent_id,

                    'subscription_id' =>
                    $subscription_id,

                    'customer_id' =>
                    $customer_id,

                    'price_id' =>
                    $price_id,

                    'product_id' =>
                    $product_id,

                    'invoice_id' =>
                    $invoice_id,

                    'amount' =>
                    $amount,

                    'currency' =>
                    $currency,

                    'status' =>
                    $status,

                    'response' =>
                    wp_json_encode(
                        $response
                    ),

                    'created_at' =>
                    current_time(
                        'mysql'
                    ),

                    'updated_at' =>
                    current_time(
                        'mysql'
                    ),
                ],
                [
                    '%d',
                    '%d',
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%f',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

        if ($result === false) {
            throw new \Exception(
                $wpdb->last_error
            );
        }

        return (int) $wpdb->insert_id;
    }

    public static function render()
    {
        global $wpdb;

        $payments_table = $wpdb->prefix . 'bcob_payments';
        $leads_table    = $wpdb->prefix . 'bcob_leads';
        $forms_table    = $wpdb->prefix . 'bcob_forms';

        $per_page = 20;

        $current_page = isset($_GET['paged'])
            ? max(1, absint(wp_unslash($_GET['paged'])))
            : 1;

        $offset = ($current_page - 1) * $per_page;


        /*
 * Filters
 */

        $search = isset($_GET['s'])
            ? sanitize_text_field(wp_unslash($_GET['s']))
            : '';

        $status = isset($_GET['status'])
            ? sanitize_key(wp_unslash($_GET['status']))
            : '';

        $provider = isset($_GET['provider'])
            ? sanitize_key(wp_unslash($_GET['provider']))
            : '';


        /*
 * Build WHERE clause
 */

        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {

            $like =
                '%' .
                $wpdb->esc_like($search) .
                '%';

            $where[] = '(
        p.id LIKE %s
        OR p.lead_id LIKE %s
        OR p.transaction_id LIKE %s
        OR l.token LIKE %s
    )';

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== '') {

            $where[] =
                'p.status = %s';

            $params[] =
                $status;
        }

        if ($provider !== '') {

            $where[] =
                'p.provider = %s';

            $params[] =
                $provider;
        }

        $where_sql =
            implode(
                ' AND ',
                $where
            );


        /*
 * Total records
 */

        $count_sql = "
    SELECT COUNT(*)
    FROM {$payments_table} p
    LEFT JOIN {$leads_table} l
        ON l.id = p.lead_id
    WHERE {$where_sql}
";

        $total = (int) (
            $params
            ? $wpdb->get_var(
                $wpdb->prepare(
                    $count_sql,
                    $params
                )
            )
            : $wpdb->get_var($count_sql)
        );

        $total_pages =
            max(
                1,
                (int) ceil(
                    $total / $per_page
                )
            );


        /*
 * Payments
 */

        $list_sql = "
    SELECT
        p.*,
        l.status AS lead_status,
        f.name AS form_name
    FROM {$payments_table} p
    LEFT JOIN {$leads_table} l
        ON l.id = p.lead_id
    LEFT JOIN {$forms_table} f
        ON f.id = p.form_id
    WHERE {$where_sql}
    ORDER BY p.created_at DESC, p.id DESC
    LIMIT %d OFFSET %d
";

        $list_params =
            $params;

        $list_params[] =
            $per_page;

        $list_params[] =
            $offset;

        $payments =
            $wpdb->get_results(
                $wpdb->prepare(
                    $list_sql,
                    $list_params
                )
            );


        /*
 * Available statuses/providers
 */

        $statuses =
            $wpdb->get_col(
                "
        SELECT DISTINCT status
        FROM {$payments_table}
        WHERE status <> ''
        ORDER BY status ASC
        "
            );

        $providers =
            $wpdb->get_col(
                "
        SELECT DISTINCT provider
        FROM {$payments_table}
        WHERE provider <> ''
        ORDER BY provider ASC
        "
            );


        /*
 * Helpers
 */

        $format_status = static function ($value): string {

            $value =
                str_replace(
                    ['_', '-'],
                    ' ',
                    (string) $value
                );

            return ucwords($value);
        };

        $format_amount = static function ($amount, $currency): string {

            return strtoupper(
                (string) $currency
            ) . ' ' .
                number_format_i18n(
                    (float) $amount,
                    2
                );
        };

        $format_date = static function ($date): string {

            if (!$date) {
                return '—';
            }

            $timestamp =
                strtotime($date);

            if (!$timestamp) {
                return $date;
            }

            return wp_date(
                get_option(
                    'date_format'
                ) . ' ' .
                    get_option(
                        'time_format'
                    ),
                $timestamp
            );
        };

        $base_url =
            menu_page_url(
                'bcob-payments',
                false
            );

        $query_args = [];

        if ($search !== '') {
            $query_args['s'] =
                $search;
        }

        if ($status !== '') {
            $query_args['status'] =
                $status;
        }

        if ($provider !== '') {
            $query_args['provider'] =
                $provider;
        }


        /*
 * Page
 */

?>

        <div class="wrap bcob-payments-page">

            <div class="bcob-page-header">

                <div>
                    <span class="bcob-eyebrow">
                        <?php esc_html_e(
                            'Transactions',
                            'best-connect-onboarding'
                        ); ?>
                    </span>

                    <h1>
                        <?php esc_html_e(
                            'Payments',
                            'best-connect-onboarding'
                        ); ?>
                    </h1>

                    <p>
                        <?php esc_html_e(
                            'View and monitor all payment transactions processed through your onboarding forms.',
                            'best-connect-onboarding'
                        ); ?>
                    </p>
                </div>

                <div class="bcob-page-count">

                    <strong>
                        <?php echo esc_html(
                            number_format_i18n($total)
                        ); ?>
                    </strong>

                    <span>
                        <?php esc_html_e(
                            'payments',
                            'best-connect-onboarding'
                        ); ?>
                    </span>

                </div>

            </div>


            <section class="bcob-card bcob-payments-card">


                <!-- Filters -->

                <div class="bcob-payments-toolbar">

                    <form
                        method="get"
                        class="bcob-payments-filters">

                        <input
                            type="hidden"
                            name="page"
                            value="bcob-payments">

                        <div class="bcob-search-field">

                            <i class="ri-search-line"></i>

                            <input
                                type="search"
                                name="s"
                                value="<?php echo esc_attr($search); ?>"
                                placeholder="<?php esc_attr_e(
                                                    'Search payment, lead or transaction...',
                                                    'best-connect-onboarding'
                                                ); ?>">

                        </div>


                        <select
                            name="status"
                            aria-label="<?php esc_attr_e(
                                            'Filter by payment status',
                                            'best-connect-onboarding'
                                        ); ?>">

                            <option value="">
                                <?php esc_html_e(
                                    'All statuses',
                                    'best-connect-onboarding'
                                ); ?>
                            </option>

                            <?php foreach ($statuses as $payment_status) : ?>

                                <option
                                    value="<?php echo esc_attr($payment_status); ?>"
                                    <?php selected(
                                        $status,
                                        $payment_status
                                    ); ?>>

                                    <?php echo esc_html(
                                        $format_status(
                                            $payment_status
                                        )
                                    ); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>


                        <select
                            name="provider"
                            aria-label="<?php esc_attr_e(
                                            'Filter by provider',
                                            'best-connect-onboarding'
                                        ); ?>">

                            <option value="">
                                <?php esc_html_e(
                                    'All providers',
                                    'best-connect-onboarding'
                                ); ?>
                            </option>

                            <?php foreach ($providers as $payment_provider) : ?>

                                <option
                                    value="<?php echo esc_attr($payment_provider); ?>"
                                    <?php selected(
                                        $provider,
                                        $payment_provider
                                    ); ?>>

                                    <?php echo esc_html(
                                        ucfirst(
                                            $payment_provider
                                        )
                                    ); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>


                        <button
                            type="submit"
                            class="button button-primary">

                            <?php esc_html_e(
                                'Filter',
                                'best-connect-onboarding'
                            ); ?>

                        </button>


                        <?php if (
                            $search !== '' ||
                            $status !== '' ||
                            $provider !== ''
                        ) : ?>

                            <a
                                href="<?php echo esc_url($base_url); ?>"
                                class="bcob-clear-filter">

                                <?php esc_html_e(
                                    'Clear',
                                    'best-connect-onboarding'
                                ); ?>

                            </a>

                        <?php endif; ?>

                    </form>

                </div>


                <!-- Table -->

                <div class="bcob-table-wrap">

                    <table class="bcob-payments-table">

                        <thead>

                            <tr>

                                <th>
                                    <?php esc_html_e(
                                        'Payment',
                                        'best-connect-onboarding'
                                    ); ?>
                                </th>

                                <th>
                                    <?php esc_html_e(
                                        'Lead',
                                        'best-connect-onboarding'
                                    ); ?>
                                </th>

                                <th>
                                    <?php esc_html_e(
                                        'Form',
                                        'best-connect-onboarding'
                                    ); ?>
                                </th>

                                <th>
                                    <?php esc_html_e(
                                        'Amount',
                                        'best-connect-onboarding'
                                    ); ?>
                                </th>

                                <th>
                                    <?php esc_html_e(
                                        'Provider',
                                        'best-connect-onboarding'
                                    ); ?>
                                </th>

                                <th>
                                    <?php esc_html_e(
                                        'Status',
                                        'best-connect-onboarding'
                                    ); ?>
                                </th>

                                <th>
                                    <?php esc_html_e(
                                        'Created',
                                        'best-connect-onboarding'
                                    ); ?>
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php if (empty($payments)) : ?>

                                <tr>

                                    <td
                                        colspan="7"
                                        class="bcob-payments-empty">

                                        <div class="bcob-empty-state">

                                            <div class="bcob-empty-icon">
                                                <i class="ri-bank-card-line"></i>
                                            </div>

                                            <h2>
                                                <?php esc_html_e(
                                                    'No payments found',
                                                    'best-connect-onboarding'
                                                ); ?>
                                            </h2>

                                            <p>
                                                <?php esc_html_e(
                                                    'There are no payment transactions matching your current filters.',
                                                    'best-connect-onboarding'
                                                ); ?>
                                            </p>

                                        </div>

                                    </td>

                                </tr>

                            <?php else : ?>

                                <?php foreach ($payments as $payment) : ?>

                                    <?php

                                    $payment_status =
                                        sanitize_html_class(
                                            $payment->status
                                        );

                                    $provider_class =
                                        sanitize_html_class(
                                            strtolower(
                                                $payment->provider
                                            )
                                        );

                                    ?>

                                    <tr>

                                        <td
                                            data-label="<?php esc_attr_e(
                                                            'Payment',
                                                            'best-connect-onboarding'
                                                        ); ?>">

                                            <div class="bcob-payment-primary">

                                                <span class="bcob-payment-icon">

                                                    <i class="ri-bank-card-line"></i>

                                                </span>

                                                <div>

                                                    <strong>
                                                        #<?php echo esc_html(
                                                                $payment->id
                                                            ); ?>
                                                    </strong>

                                                    <small>
                                                        <?php echo esc_html(
                                                            $payment->transaction_id
                                                                ?: '—'
                                                        ); ?>
                                                    </small>

                                                </div>

                                            </div>

                                        </td>


                                        <td
                                            data-label="<?php esc_attr_e(
                                                            'Lead',
                                                            'best-connect-onboarding'
                                                        ); ?>">

                                            <a
                                                class="bcob-payment-lead"
                                                href="<?php echo esc_url(
                                                            add_query_arg(
                                                                [
                                                                    'lead_id' =>
                                                                    (int) $payment->lead_id,
                                                                ],
                                                                menu_page_url(
                                                                    'bcob-leads',
                                                                    false
                                                                )
                                                            )
                                                        ); ?>">

                                                <strong>
                                                    #<?php echo esc_html(
                                                            $payment->lead_id
                                                        ); ?>
                                                </strong>

                                                <span>
                                                    <?php echo esc_html(
                                                        $format_status(
                                                            $payment->lead_status
                                                        )
                                                    ); ?>
                                                </span>

                                            </a>

                                        </td>


                                        <td
                                            data-label="<?php esc_attr_e(
                                                            'Form',
                                                            'best-connect-onboarding'
                                                        ); ?>">

                                            <span class="bcob-payment-form">

                                                <?php echo esc_html(
                                                    $payment->form_name
                                                        ?: sprintf(
                                                            __(
                                                                'Form #%d',
                                                                'best-connect-onboarding'
                                                            ),
                                                            (int) $payment->form_id
                                                        )
                                                ); ?>

                                            </span>

                                        </td>


                                        <td
                                            data-label="<?php esc_attr_e(
                                                            'Amount',
                                                            'best-connect-onboarding'
                                                        ); ?>">

                                            <strong class="bcob-payment-amount">

                                                <?php echo esc_html(
                                                    $format_amount(
                                                        $payment->amount,
                                                        $payment->currency
                                                    )
                                                ); ?>

                                            </strong>

                                        </td>


                                        <td
                                            data-label="<?php esc_attr_e(
                                                            'Provider',
                                                            'best-connect-onboarding'
                                                        ); ?>">

                                            <span
                                                class="bcob-payment-provider bcob-provider-<?php echo esc_attr(
                                                                                                $provider_class
                                                                                            ); ?>">

                                                <?php echo esc_html(
                                                    ucfirst(
                                                        $payment->provider
                                                    )
                                                ); ?>

                                            </span>

                                        </td>


                                        <td
                                            data-label="<?php esc_attr_e(
                                                            'Status',
                                                            'best-connect-onboarding'
                                                        ); ?>">

                                            <span
                                                class="bcob-payment-status bcob-payment-status-<?php echo esc_attr(
                                                                                                    $payment_status
                                                                                                ); ?>">

                                                <span class="bcob-payment-status-dot"></span>

                                                <?php echo esc_html(
                                                    $format_status(
                                                        $payment->status
                                                    )
                                                ); ?>

                                            </span>

                                        </td>


                                        <td
                                            data-label="<?php esc_attr_e(
                                                            'Created',
                                                            'best-connect-onboarding'
                                                        ); ?>">

                                            <time
                                                datetime="<?php echo esc_attr(
                                                                $payment->created_at
                                                            ); ?>">

                                                <?php echo esc_html(
                                                    $format_date(
                                                        $payment->created_at
                                                    )
                                                ); ?>

                                            </time>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>


                <!-- Pagination -->

                <?php if ($total_pages > 1) : ?>

                    <footer class="bcob-pagination">

                        <span class="bcob-pagination-summary">

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
                                            'Payments pagination',
                                            'best-connect-onboarding'
                                        ); ?>">

                            <?php

                            $pagination_args =
                                $query_args;

                            $pagination_args['paged'] =
                                max(
                                    1,
                                    $current_page - 1
                                );

                            $prev_url =
                                add_query_arg(
                                    $pagination_args,
                                    $base_url
                                );

                            $pagination_args['paged'] =
                                min(
                                    $total_pages,
                                    $current_page + 1
                                );

                            $next_url =
                                add_query_arg(
                                    $pagination_args,
                                    $base_url
                                );

                            ?>


                            <a
                                class="bcob-page-button <?php echo $current_page <= 1 ? 'is-disabled' : ''; ?>"
                                href="<?php echo esc_url($prev_url); ?>"
                                aria-label="<?php esc_attr_e(
                                                'Previous page',
                                                'best-connect-onboarding'
                                            ); ?>">

                                <i class="ri-arrow-left-line"></i>

                            </a>


                            <span class="bcob-page-number">

                                <?php echo esc_html(
                                    $current_page
                                ); ?>

                                /

                                <?php echo esc_html(
                                    $total_pages
                                ); ?>

                            </span>


                            <a
                                class="bcob-page-button <?php echo $current_page >= $total_pages ? 'is-disabled' : ''; ?>"
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

    /**
     * Update a payment record.
     *
     * @param int   $payment_id Payment ID.
     * @param array $data Payment data.
     * @return bool
     */
    public static function update(
        int $payment_id,
        array $data
    ): bool {
        global $wpdb;

        if (!$payment_id || empty($data)) {
            return false;
        }

        $allowed = [
            'status'            => '%s',
            'payment_type'      => '%s',
            'payment_intent_id' => '%s',
            'subscription_id'   => '%s',
            'customer_id'       => '%s',
            'price_id'          => '%s',
            'product_id'        => '%s',
            'invoice_id'        => '%s',
            'transaction_id'    => '%s',
            'response'          => '%s',
        ];

        $values = [];
        $formats = [];

        foreach ($allowed as $key => $format) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $values[$key] = $data[$key];
            $formats[] = $format;
        }

        if (!$values) {
            return false;
        }

        $values['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        return false !== $wpdb->update(
            $wpdb->prefix . 'bcob_payments',
            $values,
            [
                'id' => $payment_id,
            ],
            $formats,
            [
                '%d',
            ]
        );
    }

    /**
     * Find a payment by PaymentIntent.
     *
     * @param string $payment_intent_id PaymentIntent ID.
     * @return object|null
     */
    public static function get_by_payment_intent(
        string $payment_intent_id
    ): ?object {
        global $wpdb;

        if ($payment_intent_id === '') {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
				FROM {$wpdb->prefix}bcob_payments
				WHERE payment_intent_id = %s
				ORDER BY id DESC
				LIMIT 1",
                $payment_intent_id
            )
        ) ?: null;
    }

    /**
     * Find a payment by subscription.
     *
     * @param string $subscription_id Subscription ID.
     * @return object|null
     */
    public static function get_by_subscription(
        string $subscription_id
    ): ?object {
        global $wpdb;

        if ($subscription_id === '') {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
				FROM {$wpdb->prefix}bcob_payments
				WHERE subscription_id = %s
				ORDER BY id DESC
				LIMIT 1",
                $subscription_id
            )
        ) ?: null;
    }

    /**
     * Find the latest payment for a lead.
     *
     * @param int $lead_id Lead ID.
     * @return object|null
     */
    public static function get_latest_for_lead(
        int $lead_id
    ): ?object {
        global $wpdb;

        if (!$lead_id) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
				FROM {$wpdb->prefix}bcob_payments
				WHERE lead_id = %d
				ORDER BY id DESC
				LIMIT 1",
                $lead_id
            )
        ) ?: null;
    }

    /**
     * Update payment status.
     *
     * @param int    $payment_id Payment ID.
     * @param string $status Status.
     * @return bool
     */
    public static function update_status(
        int $payment_id,
        string $status
    ): bool {
        return self::update(
            $payment_id,
            [
                'status' => sanitize_key($status),
            ]
        );
    }
}
