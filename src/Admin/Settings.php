<?php

namespace BestConnectOnboarding\Admin;

use BestConnectOnboarding\Admin\Settings\General;
use BestConnectOnboarding\Admin\Settings\Styles;
use BestConnectOnboarding\Admin\Settings\Integrations;
use BestConnectOnboarding\Admin\Settings\Advanced;
use BestConnectOnboarding\Admin\Settings\Tools;

defined('ABSPATH') || exit;

class Settings
{
    /**
     * Option Name
     */
    protected const OPTION = 'bcob_settings';

    public function __construct()
    {
        add_action(
            'wp_ajax_bcob_export_settings',
            [self::class, 'ajax_export']
        );

        add_action(
            'wp_ajax_bcob_import_settings',
            [self::class, 'ajax_import']
        );
        add_action(
            'all_admin_notices',
            [self::class, 'abandonment_magic_link_notice']
        );
    }

    /**
     * Registered Tabs
     *
     * @return array
     */
    protected static function get_tabs(): array
    {
        $tabs = [

            'general' => [
                'label' => __('General', 'best-connect-onboarding'),
                'icon'  => 'ri-settings-3-line',
            ],

            'styles' => [
                'label' => __('Prebuilt Styles', 'best-connect-onboarding'),
                'icon'  => 'ri-palette-line',
            ],

            'integrations' => [
                'label' => __('Integrations', 'best-connect-onboarding'),
                'icon'  => 'ri-plug-line',
            ],

            'advanced' => [
                'label' => __('Advanced', 'best-connect-onboarding'),
                'icon'  => 'ri-tools-line',
            ],

            'tools' => [
                'label' => __('Tools', 'best-connect-onboarding'),
                'icon'  => 'ri-hammer-line',
            ],

        ];

        return apply_filters(
            'bcob_settings_tabs',
            $tabs
        );
    }

    /**
     * Register Built-in Tabs
     */
    protected static function register_tabs(): void
    {
        new General();

        new Styles();

        new Integrations();

        new Advanced();

        new Tools();
    }

    /**
     * Save settings
     */
    protected static function save(): void
    {
        if (
            empty($_POST['bcob_save_settings'])
        ) {
            return;
        }

        check_admin_referer(
            'bcob_save_settings'
        );

        if (
            ! current_user_can('manage_options')
        ) {
            wp_die(
                esc_html__(
                    'Permission denied.',
                    'best-connect-onboarding'
                )
            );
        }

        $settings = (array) get_option(
            self::OPTION,
            []
        );

        $posted = $_POST['settings'] ?? [];

        if (! is_array($posted)) {
            $posted = [];
        }

        $wysiwyg_fields = [
            'bcob_abandonment_email',
        ];

        foreach ($posted as $key => $value) {

            $key = sanitize_key($key);

            if (is_array($value)) {

                $settings[$key] = map_deep(
                    $value,
                    'sanitize_text_field'
                );
            } elseif (in_array($key, $wysiwyg_fields, true)) {

                $settings[$key] = wp_kses_post(
                    wp_unslash($value)
                );
            } else {

                $settings[$key] = sanitize_text_field(
                    wp_unslash($value)
                );
            }
        }

        $contract_link_expiry_hours =
            absint(
                $settings['bcob_contract_link_expiry_hours'] ?? 24
            );

        $allowed_contract_expiry_hours = [
            1,
            6,
            12,
            24,
            72,
            168,
            720,
        ];

        $settings['bcob_contract_link_expiry_hours'] =
            in_array(
                $contract_link_expiry_hours,
                $allowed_contract_expiry_hours,
                true
            )
            ? (string) $contract_link_expiry_hours
            : '24';

        $coupon_failed_attempt_limit =
            absint(
                $settings['bcob_coupon_failed_attempt_limit']
                    ?? 10
            );

        $settings['bcob_coupon_failed_attempt_limit'] =
            (string) max(
                1,
                min(
                    100,
                    $coupon_failed_attempt_limit
                )
            );

        $coupon_rate_limit_window =
            absint(
                $settings['bcob_coupon_rate_limit_window']
                    ?? 15
            );

        $settings['bcob_coupon_rate_limit_window'] =
            (string) max(
                1,
                min(
                    1440,
                    $coupon_rate_limit_window
                )
            );

        /**
         * Allow developers to save
         * additional settings.
         */
        do_action_ref_array(
            'bcob_save_settings',
            [
                &$settings,
                $posted,
            ]
        );

        update_option(
            self::OPTION,
            $settings,
            false
        );

        add_settings_error(
            'bcob_settings',
            'saved',
            __('Settings saved.', 'best-connect-onboarding'),
            'updated'
        );
    }

    public static function abandonment_magic_link_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings =
            (array) get_option(
                self::OPTION,
                []
            );

        if (
            (
                $settings['bcob_abandonment_enabled']
                ?? '0'
            ) !== '1'
        ) {
            return;
        }

        $template =
            trim(
                (string) (
                    $settings['bcob_abandonment_email']
                    ?? ''
                )
            );

        if (
            strpos(
                $template,
                '{{magic_link}}'
            ) !== false
        ) {
            return;
        }

        $settings_url =
            admin_url(
                'admin.php?page=bcob-settings&tab=advanced'
            );

?>
        <div
            id="bcob-abandonment-magic-link-warning"
            style="
            background: #fff;
            border-left: 4px solid #dba617;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin: 5px 15px 15px 0;
            padding: 12px 16px;
        ">

            <p style="margin:0;">

                <strong>
                    <?php
                    esc_html_e(
                        'Best Connect Onboarding: Resume link missing.',
                        'best-connect-onboarding'
                    );
                    ?>
                </strong>

                <?php
                esc_html_e(
                    'Abandonment reminders are enabled, but the reminder template does not contain {{magic_link}}. Customers will receive reminder emails without a resume link.',
                    'best-connect-onboarding'
                );
                ?>

                <a
                    href="<?php echo esc_url($settings_url); ?>"
                    style="margin-left:8px;font-weight:600;">

                    <?php
                    esc_html_e(
                        'Fix reminder template',
                        'best-connect-onboarding'
                    );
                    ?>

                </a>

            </p>

        </div>
    <?php
    }

    /**
     * AJAX: Export settings
     */
    public static function ajax_export(): void
    {
        check_ajax_referer(
            'bcob_settings_import_export',
            'nonce'
        );

        if (! current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __(
                    'Permission denied.',
                    'best-connect-onboarding'
                ),
            ], 403);
        }

        $settings = get_option(
            self::OPTION,
            []
        );

        if (! is_array($settings)) {
            $settings = [];
        }

        $json = wp_json_encode(
            $settings,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            wp_send_json_error([
                'message' => __(
                    'Unable to export settings.',
                    'best-connect-onboarding'
                ),
            ]);
        }

        wp_send_json_success([
            'filename' =>
            'bcob-settings-' .
                gmdate('Y-m-d-H-i-s') .
                '.json',

            'content' => $json,
        ]);
    }

    /**
     * AJAX: Import settings
     */
    public static function ajax_import(): void
    {
        check_ajax_referer(
            'bcob_settings_import_export',
            'nonce'
        );

        if (! current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __(
                    'Permission denied.',
                    'best-connect-onboarding'
                ),
            ], 403);
        }

        if (
            empty($_FILES['bcob_settings_file']) ||
            ! is_array($_FILES['bcob_settings_file'])
        ) {
            wp_send_json_error([
                'message' => __(
                    'Please select a JSON settings file.',
                    'best-connect-onboarding'
                ),
            ]);
        }

        $file = $_FILES['bcob_settings_file'];

        if (
            ! empty($file['error']) ||
            empty($file['tmp_name']) ||
            ! is_uploaded_file($file['tmp_name'])
        ) {
            wp_send_json_error([
                'message' => __(
                    'The settings file could not be uploaded.',
                    'best-connect-onboarding'
                ),
            ]);
        }

        $contents = file_get_contents(
            $file['tmp_name']
        );

        if ($contents === false) {
            wp_send_json_error([
                'message' => __(
                    'The settings file could not be read.',
                    'best-connect-onboarding'
                ),
            ]);
        }

        $imported = json_decode(
            $contents,
            true
        );

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            ! is_array($imported)
        ) {
            wp_send_json_error([
                'message' => __(
                    'Invalid JSON settings file.',
                    'best-connect-onboarding'
                ),
            ]);
        }

        update_option(
            self::OPTION,
            $imported,
            false
        );

        wp_send_json_success([
            'message' => __(
                'Settings imported successfully.',
                'best-connect-onboarding'
            ),
        ]);
    }

    /**
     * Render Page
     */
    public static function render(): void
    {
        self::register_tabs();

        self::save();

        settings_errors(
            'bcob_settings'
        );

        $tabs = self::get_tabs();

        $current = isset($_GET['tab'])
            ? sanitize_key($_GET['tab'])
            : array_key_first($tabs);

    ?>

        <div class="wrap bcob-settings-page">

            <form
                method="post"
                enctype="multipart/form-data">

                <?php wp_nonce_field('bcob_save_settings'); ?>

                <input
                    type="hidden"
                    name="bcob_save_settings"
                    value="1">

                <div class="bcob-settings">

                    <!-- Sidebar -->

                    <aside class="bcob-settings-sidebar">

                        <ul>

                            <?php foreach ($tabs as $tab_id => $tab) : ?>

                                <li>

                                    <a
                                        href="#<?php echo esc_attr($tab_id); ?>"
                                        class="bcob-settings-tab <?php echo $current === $tab_id ? 'active' : ''; ?>"
                                        data-tab="<?php echo esc_attr($tab_id); ?>">

                                        <?php if (! empty($tab['icon'])) : ?>

                                            <i class="<?php echo esc_attr($tab['icon']); ?>"></i>

                                        <?php endif; ?>

                                        <span>

                                            <?php echo esc_html($tab['label']); ?>

                                        </span>

                                    </a>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </aside>

                    <!-- Content -->

                    <div class="bcob-settings-content">

                        <?php foreach ($tabs as $tab_id => $tab) : ?>

                            <div
                                id="<?php echo esc_attr($tab_id); ?>"
                                class="bcob-settings-panel <?php echo $current === $tab_id ? 'active' : ''; ?>">

                                <div class="bcob-card">

                                    <div class="bcob-card-header">

                                        <h2>

                                            <?php echo esc_html($tab['label']); ?>

                                        </h2>

                                    </div>

                                    <div class="bcob-card-body">

                                        <?php
                                        do_action(
                                            'bcob_settings_tab_' . $tab_id
                                        );
                                        ?>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

                <div class="bcob-settings-footer">

                    <button
                        type="submit"
                        name="bcob_save_settings"
                        value="1"
                        class="button button-primary button-large">

                        <?php esc_html_e(
                            'Save Changes',
                            'best-connect-onboarding'
                        ); ?>

                    </button>

                    <div class="bcob-settings-import-export">

                        <button
                            type="button"
                            id="bcob-export-settings"
                            class="button button-large">

                            <i class="ri-download-2-line"></i>

                            <?php esc_html_e(
                                'Export Settings',
                                'best-connect-onboarding'
                            ); ?>

                        </button>

                        <label
                            for="bcob-settings-file"
                            class="button button-large">

                            <i class="ri-upload-2-line"></i>

                            <?php esc_html_e(
                                'Choose JSON',
                                'best-connect-onboarding'
                            ); ?>

                        </label>

                        <input
                            id="bcob-settings-file"
                            type="file"
                            name="bcob_settings_file"
                            accept=".json,application/json"
                            hidden>

                        <button
                            type="button"
                            id="bcob-import-settings"
                            class="button button-large">

                            <i class="ri-upload-cloud-2-line"></i>

                            <?php esc_html_e(
                                'Import Settings',
                                'best-connect-onboarding'
                            ); ?>

                        </button>

                    </div>

                    <script>
                        jQuery(function($) {

                            const ajaxUrl =
                                '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

                            const nonce =
                                '<?php echo esc_js(wp_create_nonce('bcob_settings_import_export')); ?>';

                            const $file =
                                $('#bcob-settings-file');

                            const $fileLabel =
                                $('label[for="bcob-settings-file"]');

                            /*
                             * File selection
                             */
                            $file.on('change', function() {

                                const file =
                                    this.files && this.files.length ?
                                    this.files[0].name :
                                    '';

                                if (file) {
                                    $fileLabel.text(file);
                                } else {
                                    $fileLabel.html(
                                        '<i class="ri-upload-2-line"></i> Choose JSON'
                                    );
                                }

                            });

                            /*
                             * Export settings
                             */
                            $('#bcob-export-settings').on('click', function() {

                                const $button = $(this);

                                $button.prop('disabled', true);

                                $.ajax({
                                    url: ajaxUrl,
                                    type: 'POST',
                                    dataType: 'json',
                                    data: {
                                        action: 'bcob_export_settings',
                                        nonce: nonce
                                    },

                                    success: function(response) {

                                        if (
                                            !response ||
                                            !response.success ||
                                            !response.data
                                        ) {
                                            alert(
                                                response?.data?.message ||
                                                'Unable to export settings.'
                                            );

                                            return;
                                        }

                                        const blob =
                                            new Blob(
                                                [
                                                    response.data.content
                                                ], {
                                                    type: 'application/json;charset=utf-8'
                                                }
                                            );

                                        const url =
                                            window.URL.createObjectURL(blob);

                                        const link =
                                            document.createElement('a');

                                        link.href = url;

                                        link.download =
                                            response.data.filename ||
                                            'bcob-settings.json';

                                        document.body.appendChild(link);

                                        link.click();

                                        link.remove();

                                        window.URL.revokeObjectURL(url);
                                    },

                                    error: function() {

                                        alert(
                                            'Unable to export settings.'
                                        );
                                    },

                                    complete: function() {

                                        $button.prop(
                                            'disabled',
                                            false
                                        );
                                    }
                                });

                            });

                            /*
                             * Import settings
                             */
                            $('#bcob-import-settings').on('click', function() {

                                if (
                                    !$file[0].files ||
                                    !$file[0].files.length
                                ) {
                                    alert(
                                        'Please select a JSON settings file first.'
                                    );

                                    return;
                                }

                                const formData =
                                    new FormData();

                                formData.append(
                                    'action',
                                    'bcob_import_settings'
                                );

                                formData.append(
                                    'nonce',
                                    nonce
                                );

                                formData.append(
                                    'bcob_settings_file',
                                    $file[0].files[0]
                                );

                                const $button =
                                    $(this);

                                $button.prop(
                                    'disabled',
                                    true
                                );

                                $.ajax({
                                    url: ajaxUrl,
                                    type: 'POST',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    dataType: 'json',

                                    success: function(response) {

                                        if (
                                            !response ||
                                            !response.success
                                        ) {
                                            alert(
                                                response?.data?.message ||
                                                'Unable to import settings.'
                                            );

                                            return;
                                        }

                                        alert(
                                            response.data.message ||
                                            'Settings imported successfully.'
                                        );

                                        window.location.reload();
                                    },

                                    error: function() {

                                        alert(
                                            'Unable to import settings.'
                                        );
                                    },

                                    complete: function() {

                                        $button.prop(
                                            'disabled',
                                            false
                                        );
                                    }
                                });

                            });

                        });
                    </script>

                </div>

            </form>

        </div>

<?php
    }
}
