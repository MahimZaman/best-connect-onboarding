<?php

namespace BestConnectOnboarding\Coupon;

use BestConnectOnboarding\Helpers\Logger;

defined('ABSPATH') || exit;

class Coupon
{
    public function init()
    {
        add_action('init', [$this, 'register_post_type']);

        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post', [$this, 'save_coupon']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_filter('manage_bcob_coupon_posts_columns', [$this, 'columns']);
        add_action('manage_bcob_coupon_posts_custom_column', [$this, 'column_content'], 10, 2);

        add_action('wp_ajax_bcob_apply_coupon', [$this, 'apply_coupon']);
        add_action('wp_ajax_nopriv_bcob_apply_coupon', [$this, 'apply_coupon']);
    }

    private static function get_coupon_by_code(
        string $code
    ): ?\WP_Post {
        $code = trim(
            sanitize_text_field($code)
        );

        if ($code === '') {
            return null;
        }

        $query =
            new \WP_Query(
                [
                    'post_type' =>
                    'bcob_coupon',

                    'post_status' =>
                    'publish',

                    'title' =>
                    $code,

                    'posts_per_page' =>
                    1,

                    'no_found_rows' =>
                    true,
                ]
            );

        if (empty($query->posts)) {
            return null;
        }

        return $query->posts[0];
    }

    private static function sanitize_date(
        string $date
    ): string {
        $date = trim($date);

        if ($date === '') {
            return '';
        }

        $parsed =
            \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date,
                wp_timezone()
            );

        if (
            !$parsed ||
            $parsed->format('Y-m-d') !== $date
        ) {
            return '';
        }

        return $date;
    }

    private static function validate_coupon(
        \WP_Post $coupon,
        float $subtotal,
        int $form_id
    ) {
        $active =
            get_post_meta(
                $coupon->ID,
                '_is_active',
                true
            );

        if (!$active) {
            return new \WP_Error(
                'inactive',
                __(
                    'This coupon is inactive.',
                    'best-connect-onboarding'
                )
            );
        }

        $today =
            current_datetime()
            ->format('Y-m-d');

        $start_date =
            get_post_meta(
                $coupon->ID,
                '_start_date',
                true
            );

        if (
            $start_date &&
            $today < $start_date
        ) {
            return new \WP_Error(
                'not_started',
                __(
                    'This coupon is not valid yet.',
                    'best-connect-onboarding'
                )
            );
        }

        $expiry =
            get_post_meta(
                $coupon->ID,
                '_expiry_date',
                true
            );

        if (
            $expiry &&
            $today > $expiry
        ) {
            return new \WP_Error(
                'expired',
                __(
                    'This coupon has expired.',
                    'best-connect-onboarding'
                )
            );
        }

        $scope =
            get_post_meta(
                $coupon->ID,
                '_coupon_scope',
                true
            ) ?: 'global';

        if ($scope === 'forms') {
            $forms =
                array_map(
                    'absint',
                    (array) get_post_meta(
                        $coupon->ID,
                        '_coupon_forms',
                        true
                    )
                );

            if (
                !$form_id ||
                !in_array(
                    $form_id,
                    $forms,
                    true
                )
            ) {
                return new \WP_Error(
                    'invalid_form',
                    __(
                        'This coupon is not available for this form.',
                        'best-connect-onboarding'
                    )
                );
            }
        }

        $usage_limit =
            (int) get_post_meta(
                $coupon->ID,
                '_usage_limit',
                true
            );

        $used =
            (int) get_post_meta(
                $coupon->ID,
                '_used_count',
                true
            );

        if (
            $usage_limit > 0 &&
            $used >= $usage_limit
        ) {
            return new \WP_Error(
                'usage_limit',
                __(
                    'Coupon usage limit reached.',
                    'best-connect-onboarding'
                )
            );
        }

        $minimum =
            (float) get_post_meta(
                $coupon->ID,
                '_min_order_amount',
                true
            );

        if ($subtotal < $minimum) {
            return new \WP_Error(
                'minimum',
                sprintf(
                    __(
                        'Minimum order amount is %s.',
                        'best-connect-onboarding'
                    ),
                    bcob_price($minimum)
                )
            );
        }

        return true;
    }

    public static function calculate_discount(
        string $code,
        float $subtotal,
        int $form_id = 0
    ): float {
        $coupon =
            self::get_coupon_by_code(
                $code
            );

        if (!$coupon) {
            return 0;
        }

        $validation =
            self::validate_coupon(
                $coupon,
                $subtotal,
                $form_id
            );

        if (is_wp_error($validation)) {
            return 0;
        }

        $type =
            get_post_meta(
                $coupon->ID,
                '_coupon_type',
                true
            ) ?: 'percentage';

        $discount =
            (float) get_post_meta(
                $coupon->ID,
                '_discount_value',
                true
            );

        $maximum =
            (float) get_post_meta(
                $coupon->ID,
                '_max_discount',
                true
            );

        if ($type === 'percentage') {
            $amount =
                ($subtotal * $discount) / 100;
        } else {
            $amount =
                $discount;
        }

        if ($maximum > 0) {
            $amount =
                min(
                    $amount,
                    $maximum
                );
        }

        return round(
            max(0, $amount),
            2
        );
    }

    public static function record_usage(
        string $code,
        int $lead_id
    ): bool {
        if (
            $code === '' ||
            !$lead_id
        ) {
            return false;
        }

        $coupon =
            self::get_coupon_by_code(
                $code
            );

        if (!$coupon) {
            return false;
        }

        $marker_key =
            '_bcob_used_lead_' .
            $lead_id;

        if (
            metadata_exists(
                'post',
                $coupon->ID,
                $marker_key
            )
        ) {
            return true;
        }

        if (
            !add_post_meta(
                $coupon->ID,
                $marker_key,
                'pending',
                true
            )
        ) {
            return true;
        }

        if (
            !metadata_exists(
                'post',
                $coupon->ID,
                '_used_count'
            )
        ) {
            add_post_meta(
                $coupon->ID,
                '_used_count',
                0,
                true
            );
        }

        global $wpdb;

        $usage_limit =
            (int) get_post_meta(
                $coupon->ID,
                '_usage_limit',
                true
            );

        if ($usage_limit > 0) {
            $updated =
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->postmeta}
                    SET meta_value =
                        CAST(meta_value AS UNSIGNED) + 1
                    WHERE post_id = %d
                    AND meta_key = '_used_count'
                    AND CAST(meta_value AS UNSIGNED) < %d
                    LIMIT 1",
                        $coupon->ID,
                        $usage_limit
                    )
                );
        } else {
            $updated =
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->postmeta}
                    SET meta_value =
                        CAST(meta_value AS UNSIGNED) + 1
                    WHERE post_id = %d
                    AND meta_key = '_used_count'
                    LIMIT 1",
                        $coupon->ID
                    )
                );
        }

        if ($updated !== 1) {
            delete_post_meta(
                $coupon->ID,
                $marker_key
            );

            return false;
        }

        update_post_meta(
            $coupon->ID,
            $marker_key,
            'used'
        );

        return true;
    }

    private static function rate_limit_settings(): array
    {
        $settings =
            get_option(
                'bcob_settings',
                []
            );

        if (!is_array($settings)) {
            $settings = [];
        }

        return [
            'limit' =>
            max(
                1,
                min(
                    100,
                    absint(
                        $settings['bcob_coupon_failed_attempt_limit'] ?? 10
                    )
                )
            ),

            'window' =>
            max(
                1,
                min(
                    1440,
                    absint(
                        $settings['bcob_coupon_rate_limit_window'] ?? 15
                    )
                )
            ) * MINUTE_IN_SECONDS,
        ];
    }

    private static function request_ip(): string
    {
        return sanitize_text_field(
            wp_unslash(
                $_SERVER['REMOTE_ADDR']
                    ?? ''
            )
        );
    }

    private static function rate_limit_key(
        string $type,
        string $identifier
    ): string {
        return
            'bcob_coupon_' .
            sanitize_key($type) .
            '_' .
            hash_hmac(
                'sha256',
                $identifier,
                wp_salt('nonce')
            );
    }

    private static function rate_limit_identifiers(): array
    {
        $identifiers = [];

        $ip =
            self::request_ip();

        if ($ip !== '') {
            $identifiers['ip'] =
                $ip;
        }

        $session_id =
            sanitize_text_field(
                wp_unslash(
                    $_POST['session_id']
                        ?? ''
                )
            );

        if ($session_id !== '') {
            $identifiers['session'] =
                substr(
                    $session_id,
                    0,
                    100
                );
        }

        return $identifiers;
    }

    private static function is_rate_limited(): bool
    {
        $settings =
            self::rate_limit_settings();

        foreach (
            self::rate_limit_identifiers()
            as $type =>
            $identifier
        ) {
            $key =
                self::rate_limit_key(
                    $type,
                    $identifier
                );

            $attempts =
                absint(
                    get_transient(
                        $key
                    )
                );

            if (
                $attempts >=
                $settings['limit']
            ) {
                return true;
            }
        }

        return false;
    }

    private static function record_failed_attempt(): void
    {
        $settings =
            self::rate_limit_settings();

        foreach (
            self::rate_limit_identifiers()
            as $type =>
            $identifier
        ) {
            $key =
                self::rate_limit_key(
                    $type,
                    $identifier
                );

            $attempts =
                absint(
                    get_transient(
                        $key
                    )
                );

            set_transient(
                $key,
                $attempts + 1,
                $settings['window']
            );
        }
    }

    private static function coupon_error(
        string $error_code,
        int $form_id = 0,
        int $coupon_id = 0
    ): void {
        self::record_failed_attempt();

        Logger::warning(
            'coupon',
            'Coupon application failed.',
            [
                'operation' =>
                'coupon_validation',

                'error_code' =>
                $error_code,

                'form_id' =>
                $form_id,

                'coupon_id' =>
                $coupon_id,

                'status' =>
                'failed',
            ]
        );

        wp_send_json(
            [
                'success' =>
                false,

                'message' =>
                __(
                    'Unable to apply coupon.',
                    'best-connect-onboarding'
                ),
            ]
        );
    }

    /**
     * Apply coupon.
     *
     * @return void
     */
    public function apply_coupon(): void
    {
        check_ajax_referer(
            'bcob_nonce',
            'nonce'
        );

        $form_id =
            absint(
                $_POST['form_id'] ?? 0
            );

        if (
            self::is_rate_limited()
        ) {
            Logger::warning(
                'coupon',
                'Coupon application blocked by rate limit.',
                [
                    'operation' =>
                    'coupon_validation',

                    'error_code' =>
                    'rate_limited',

                    'form_id' =>
                    $form_id,

                    'status' =>
                    'blocked',
                ]
            );

            wp_send_json(
                [
                    'success' =>
                    false,

                    'message' =>
                    __(
                        'Unable to apply coupon.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $code =
            sanitize_text_field(
                wp_unslash(
                    $_POST['coupon'] ?? ''
                )
            );

        $subtotal =
            isset($_POST['subtotal'])
            ? max(
                0,
                (float) $_POST['subtotal']
            )
            : 0;

        if ($code === '') {
            self::coupon_error(
                'missing_code',
                $form_id
            );
        }

        $coupon =
            self::get_coupon_by_code(
                $code
            );

        if (!$coupon) {
            self::coupon_error(
                'not_found',
                $form_id
            );
        }

        $validation =
            self::validate_coupon(
                $coupon,
                $subtotal,
                $form_id
            );

        if (
            is_wp_error(
                $validation
            )
        ) {
            self::coupon_error(
                sanitize_key(
                    $validation->get_error_code()
                ),
                $form_id,
                (int) $coupon->ID
            );
        }

        $type =
            get_post_meta(
                $coupon->ID,
                '_coupon_type',
                true
            ) ?: 'percentage';

        $discount =
            (float) get_post_meta(
                $coupon->ID,
                '_discount_value',
                true
            );

        $amount =
            self::calculate_discount(
                $code,
                $subtotal,
                $form_id
            );

        if ($type === 'percentage') {
            $message =
                sprintf(
                    __(
                        '%s%% Rabatt wurde angewendet.',
                        'best-connect-onboarding'
                    ),
                    rtrim(
                        rtrim(
                            number_format(
                                $discount,
                                2,
                                '.',
                                ''
                            ),
                            '0'
                        ),
                        '.'
                    )
                );
        } else {
            $message =
                sprintf(
                    __(
                        '%s wurden abgezogen.',
                        'best-connect-onboarding'
                    ),
                    bcob_price(
                        $amount
                    )
                );
        }

        wp_send_json(
            [
                'success' =>
                true,

                'message' =>
                $message,

                'data' => [
                    'id' =>
                    $coupon->ID,

                    'code' =>
                    $coupon->post_title,

                    'type' =>
                    $type,

                    'value' =>
                    $discount,

                    'amount' =>
                    round(
                        $amount,
                        2
                    ),
                ],
            ]
        );
    }

    public function render()
    {
        echo 'Coupon';
    }

    public function register_post_type()
    {

        $labels = [
            'name'                  => __('Coupons', 'best-connect-onboarding'),
            'singular_name'         => __('Coupon', 'best-connect-onboarding'),
            'add_new'               => __('Add Coupon', 'best-connect-onboarding'),
            'add_new_item'          => __('Add New Coupon', 'best-connect-onboarding'),
            'edit_item'             => __('Edit Coupon', 'best-connect-onboarding'),
            'new_item'              => __('New Coupon', 'best-connect-onboarding'),
            'view_item'             => __('View Coupon', 'best-connect-onboarding'),
            'search_items'          => __('Search Coupons', 'best-connect-onboarding'),
            'not_found'             => __('No coupons found.', 'best-connect-onboarding'),
            'not_found_in_trash'    => __('No coupons found in trash.', 'best-connect-onboarding'),
            'menu_name'             => __('Coupons', 'best-connect-onboarding'),
        ];

        register_post_type(
            'bcob_coupon',
            [
                'labels'              => $labels,
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'supports'            => ['title'],
                'capability_type'     => 'post',
                'has_archive'         => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
                'show_in_nav_menus'   => false,
                'menu_position'       => 30,
                'menu_icon'           => 'dashicons-tickets-alt',
            ]
        );
    }

    public function register_meta_boxes()
    {

        add_meta_box(
            'bcob_coupon_details',
            __('Coupon Details', 'best-connect-onboarding'),
            [$this, 'render_meta_box'],
            'bcob_coupon',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post)
    {

        wp_nonce_field('bcob_coupon_nonce', 'bcob_coupon_nonce');

        $type          = get_post_meta($post->ID, '_coupon_type', true);
        $discount      = get_post_meta($post->ID, '_discount_value', true);
        $usage_limit   = get_post_meta($post->ID, '_usage_limit', true);
        $expiry        = get_post_meta($post->ID, '_expiry_date', true);
        $minimum       = get_post_meta($post->ID, '_min_order_amount', true);
        $maximum       = get_post_meta($post->ID, '_max_discount', true);
        $active        = get_post_meta($post->ID, '_is_active', true);
        $note          = get_post_meta($post->ID, '_admin_note', true);
        $used          = (int) get_post_meta($post->ID, '_used_count', true);

        $start_date =
            get_post_meta(
                $post->ID,
                '_start_date',
                true
            );

        $scope =
            get_post_meta(
                $post->ID,
                '_coupon_scope',
                true
            ) ?: 'global';

        $coupon_forms =
            array_map(
                'absint',
                (array) get_post_meta(
                    $post->ID,
                    '_coupon_forms',
                    true
                )
            );

        global $wpdb;

        $forms =
            $wpdb->get_results(
                "SELECT id, name
        FROM {$wpdb->prefix}bcob_forms
        ORDER BY name ASC"
            );

        $type = $type ? $type : 'percentage';

?>

        <div class="bcob-coupon-wrapper">
            <div class="bcob-grid">
                <div class="bcob-field bcob-full">

                    <label for="coupon_code">
                        <?php esc_html_e('Coupon Code', 'best-connect-onboarding'); ?>
                    </label>

                    <div class="bcob-input-group">

                        <input
                            type="text"
                            id="coupon_code"
                            name="coupon_code"
                            value="<?php echo esc_attr($post->post_title); ?>"
                            placeholder="<?php esc_attr_e('Enter coupon code', 'best-connect-onboarding'); ?>"
                            required>

                        <button
                            type="button"
                            class="button button-secondary"
                            id="bcob-generate-coupon">
                            <i class="ri-refresh-line"></i>
                            <?php esc_html_e('Generate', 'best-connect-onboarding'); ?>
                        </button>

                    </div>

                </div>

                <div class="bcob-field">
                    <label><?php esc_html_e('Coupon Type', 'best-connect-onboarding'); ?></label>

                    <div class="bcob-radio-group">

                        <label>
                            <input
                                type="radio"
                                name="coupon_type"
                                value="percentage"
                                <?php checked($type, 'percentage'); ?>>
                            Percentage
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="coupon_type"
                                value="fixed"
                                <?php checked($type, 'fixed'); ?>>
                            Fixed Amount
                        </label>

                    </div>
                </div>

                <div class="bcob-field">
                    <label for="discount_value">
                        <?php esc_html_e('Discount Value', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="discount_value"
                        name="discount_value"
                        value="<?php echo esc_attr($discount); ?>">
                </div>

                <div class="bcob-field">
                    <label for="usage_limit">
                        <?php esc_html_e('Usage Limit', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        type="number"
                        min="0"
                        id="usage_limit"
                        name="usage_limit"
                        value="<?php echo esc_attr($usage_limit); ?>">

                    <small>0 = Unlimited</small>
                </div>

                <div class="bcob-field">
                    <label for="start_date">
                        <?php esc_html_e('Valid From', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="<?php echo esc_attr($start_date); ?>">
                </div>

                <div class="bcob-field">
                    <label for="expiry_date">
                        <?php esc_html_e('Valid Until', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        type="date"
                        id="expiry_date"
                        name="expiry_date"
                        value="<?php echo esc_attr($expiry); ?>">
                </div>

                <div class="bcob-field">
                    <label for="min_order_amount">
                        <?php esc_html_e('Minimum Order Amount', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="min_order_amount"
                        name="min_order_amount"
                        value="<?php echo esc_attr($minimum); ?>">
                </div>

                <div class="bcob-field">
                    <label for="max_discount">
                        <?php esc_html_e('Maximum Discount', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="max_discount"
                        name="max_discount"
                        value="<?php echo esc_attr($maximum); ?>">
                </div>

                <div class="bcob-field bcob-full">

                    <label>
                        <?php esc_html_e('Coupon Availability', 'best-connect-onboarding'); ?>
                    </label>

                    <div class="bcob-radio-group">

                        <label>
                            <input
                                type="radio"
                                name="coupon_scope"
                                value="global"
                                <?php checked($scope, 'global'); ?>>

                            <?php esc_html_e('Global — all forms', 'best-connect-onboarding'); ?>
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="coupon_scope"
                                value="forms"
                                <?php checked($scope, 'forms'); ?>>

                            <?php esc_html_e('Specific forms', 'best-connect-onboarding'); ?>
                        </label>

                    </div>

                </div>

                <div class="bcob-field bcob-full">

                    <label for="coupon_forms">
                        <?php esc_html_e('Assigned Forms', 'best-connect-onboarding'); ?>
                    </label>

                    <select
                        id="coupon_forms"
                        name="coupon_forms[]"
                        multiple
                        size="6">

                        <?php foreach ($forms as $form) : ?>

                            <option
                                value="<?php echo esc_attr($form->id); ?>"
                                <?php selected(
                                    in_array(
                                        (int) $form->id,
                                        $coupon_forms,
                                        true
                                    ),
                                    true
                                ); ?>>

                                <?php echo esc_html($form->name); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <p class="description">
                        <?php esc_html_e(
                            'Used only when Specific forms is selected.',
                            'best-connect-onboarding'
                        ); ?>
                    </p>

                </div>

                <div class="bcob-field bcob-full">

                    <label class="bcob-switch">

                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?php checked($active, '1'); ?>>

                        <span><?php esc_html_e('Coupon is Active', 'best-connect-onboarding'); ?></span>

                    </label>

                </div>

                <div class="bcob-field bcob-full">

                    <label for="admin_note">
                        <?php esc_html_e('Admin Note', 'best-connect-onboarding'); ?>
                    </label>

                    <textarea
                        id="admin_note"
                        name="admin_note"
                        rows="5"><?php echo esc_textarea($note); ?></textarea>

                </div>

            </div>

            <div class="bcob-stats">

                <div class="bcob-stat">
                    <span class="bcob-stat-label"><?php esc_html_e('Times Used', 'best-connect-onboarding'); ?></span>
                    <strong><?php echo esc_html($used); ?></strong>
                </div>

                <div class="bcob-stat">
                    <span class="bcob-stat-label"><?php esc_html_e('Status', 'best-connect-onboarding'); ?></span>

                    <strong>
                        <?php
                        echo $active
                            ? esc_html__('Active', 'best-connect-onboarding')
                            : esc_html__('Inactive', 'best-connect-onboarding');
                        ?>
                    </strong>
                </div>

            </div>

        </div>

<?php
    }

    /**
     * Save coupon.
     */
    public function save_coupon($post_id)
    {

        if (! isset($_POST['bcob_coupon_nonce'])) {
            return;
        }

        if (! wp_verify_nonce($_POST['bcob_coupon_nonce'], 'bcob_coupon_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'bcob_coupon') {
            return;
        }

        $type = isset($_POST['coupon_type'])
            ? sanitize_text_field(wp_unslash($_POST['coupon_type']))
            : 'percentage';

        $discount = isset($_POST['discount_value'])
            ? (float) $_POST['discount_value']
            : 0;

        $usage_limit = isset($_POST['usage_limit'])
            ? absint($_POST['usage_limit'])
            : 0;

        $start_date =
            isset($_POST['start_date'])
            ? self::sanitize_date(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['start_date']
                    )
                )
            )
            : '';

        $scope =
            isset($_POST['coupon_scope']) &&
            sanitize_key(
                wp_unslash(
                    $_POST['coupon_scope']
                )
            ) === 'forms'
            ? 'forms'
            : 'global';

        $coupon_forms =
            isset($_POST['coupon_forms']) &&
            is_array($_POST['coupon_forms'])
            ? array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'absint',
                            $_POST['coupon_forms']
                        )
                    )
                )
            )
            : [];

        $expiry =
            isset($_POST['expiry_date'])
            ? self::sanitize_date(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['expiry_date']
                    )
                )
            )
            : '';

        $minimum = isset($_POST['min_order_amount'])
            ? (float) $_POST['min_order_amount']
            : '';

        $maximum = isset($_POST['max_discount'])
            ? (float) $_POST['max_discount']
            : '';

        $note = isset($_POST['admin_note'])
            ? sanitize_textarea_field(wp_unslash($_POST['admin_note']))
            : '';

        $active = isset($_POST['is_active']) ? '1' : '0';

        if ($discount <= 0) {
            $discount = 0;
        }

        $coupon_code = isset($_POST['coupon_code'])
            ? sanitize_text_field(wp_unslash($_POST['coupon_code']))
            : '';

        if (! empty($coupon_code)) {
            remove_action('save_post', [$this, 'save_coupon']);

            wp_update_post(
                [
                    'ID'         => $post_id,
                    'post_title' => strtoupper($coupon_code),
                ]
            );

            add_action('save_post', [$this, 'save_coupon']);
        }

        update_post_meta($post_id, '_coupon_type', $type);
        update_post_meta($post_id, '_discount_value', $discount);
        update_post_meta($post_id, '_usage_limit', $usage_limit);
        update_post_meta($post_id, '_expiry_date', $expiry);
        update_post_meta($post_id, '_min_order_amount', $minimum);
        update_post_meta($post_id, '_max_discount', $maximum);
        update_post_meta($post_id, '_is_active', $active);
        update_post_meta($post_id, '_admin_note', $note);
        update_post_meta($post_id, '_start_date', $start_date);
        update_post_meta($post_id, '_coupon_scope', $scope);
        update_post_meta($post_id, '_coupon_forms', $coupon_forms);
    }

    /**
     * Admin columns.
     */
    public function columns($columns)
    {

        return [
            'cb'         => $columns['cb'],
            'title'      => __('Coupon Code', 'best-connect-onboarding'),
            'type'       => __('Type', 'best-connect-onboarding'),
            'discount'   => __('Discount', 'best-connect-onboarding'),
            'limit'      => __('Usage Limit', 'best-connect-onboarding'),
            'used'       => __('Used', 'best-connect-onboarding'),
            'expiry'     => __('Expiry Date', 'best-connect-onboarding'),
            'status'     => __('Status', 'best-connect-onboarding'),
            'date'       => __('Date', 'best-connect-onboarding'),
        ];
    }

    /**
     * Render admin column content.
     */
    public function column_content($column, $post_id)
    {

        switch ($column) {

            case 'type':
                echo esc_html(
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            get_post_meta($post_id, '_coupon_type', true)
                        )
                    )
                );
                break;

            case 'discount':

                $type  = get_post_meta($post_id, '_coupon_type', true);
                $value = get_post_meta($post_id, '_discount_value', true);

                if (! $value) {
                    echo '&mdash;';
                    break;
                }

                if ('percentage' === $type) {
                    echo esc_html($value) . '%';
                } else {
                    echo bcob_price($value);
                }

                break;

            case 'limit':

                $limit = (int) get_post_meta($post_id, '_usage_limit', true);

                echo $limit > 0
                    ? esc_html($limit)
                    : esc_html__('Unlimited', 'best-connect-onboarding');

                break;

            case 'used':

                echo esc_html(
                    (int) get_post_meta($post_id, '_used_count', true)
                );

                break;

            case 'expiry':

                $expiry = get_post_meta($post_id, '_expiry_date', true);

                echo $expiry
                    ? esc_html(date_i18n(get_option('date_format'), strtotime($expiry)))
                    : '&mdash;';

                break;

            case 'status':

                $active = get_post_meta($post_id, '_is_active', true);

                if ($active) {
                    echo '<span class="bcob-status bcob-status-active">' .
                        esc_html__('Active', 'best-connect-onboarding') .
                        '</span>';
                } else {
                    echo '<span class="bcob-status bcob-status-inactive">' .
                        esc_html__('Inactive', 'best-connect-onboarding') .
                        '</span>';
                }

                break;
        }
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets($hook)
    {

        global $post_type;

        if ('bcob_coupon' !== $post_type) {
            return;
        }

        wp_enqueue_style(
            'bcob-coupon-admin',
            BCOB_URL . 'assets/css/coupon-admin.css',
            [],
            BCOB_VER
        );

        wp_enqueue_script(
            'bcob-coupon-admin',
            BCOB_URL . 'assets/js/admin/coupon-admin.js',
            [],
            BCOB_VER,
            true
        );
    }
}
