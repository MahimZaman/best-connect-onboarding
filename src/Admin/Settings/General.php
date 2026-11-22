<?php

namespace BestConnectOnboarding\Admin\Settings;

defined('ABSPATH') || exit;

class General
{
    public function __construct()
    {
        add_action(
            'bcob_settings_tab_general',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $settings = get_option('bcob_settings', []);

        $currencies = [
            'USD' => 'United States (US) dollar ($)',
            'EUR' => 'Euro (€)',
            'GBP' => 'Pound sterling (£)',
            'AUD' => 'Australian dollar ($)',
            'BDT' => 'Bangladeshi taka (৳)',
            'CAD' => 'Canadian dollar ($)',
            'CHF' => 'Swiss franc (CHF)',
            'CNY' => 'Chinese yuan (¥)',
            'DKK' => 'Danish krone (kr.)',
            'HKD' => 'Hong Kong dollar ($)',
            'INR' => 'Indian rupee (₹)',
            'JPY' => 'Japanese yen (¥)',
            'KRW' => 'South Korean won (₩)',
            'MYR' => 'Malaysian ringgit (RM)',
            'NOK' => 'Norwegian krone (kr)',
            'NZD' => 'New Zealand dollar ($)',
            'PKR' => 'Pakistani rupee (₨)',
            'RUB' => 'Russian ruble (₽)',
            'SAR' => 'Saudi riyal (ر.س)',
            'SEK' => 'Swedish krona (kr)',
            'SGD' => 'Singapore dollar ($)',
            'THB' => 'Thai baht (฿)',
            'TRY' => 'Turkish lira (₺)',
            'AED' => 'UAE dirham (د.إ)',
            'VND' => 'Vietnamese đồng (₫)',
            'ZAR' => 'South African rand (R)',
        ];

?>

        <div class="bcob-settings-section">
            <div class="bcob-settings-divider">

                <h3>

                    <i class="ri-map-pin-user-fill"></i>

                    <?php esc_html_e('Administrator', 'best-connect-onboarding'); ?>

                </h3>

                <p>

                    <?php esc_html_e('In this email, all the debugging and error related emails will land.'); ?>

                </p>

            </div>

            <div class="bcob-grid bcob-grid-3">

                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Admin Email Address', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        type="email"
                        name="settings[admin_debug_email]"
                        placeholde="example@support.com"
                        value="<?php echo esc_attr($settings['admin_debug_email'] ?? ','); ?>">

                </div>

            </div>

            <div class="bcob-settings-divider" style="margin-top: 30px">

                <h3>

                    <i class="ri-money-dollar-circle-line"></i>

                    <?php esc_html_e('Currency Options', 'best-connect-onboarding'); ?>

                </h3>

                <p>

                    <?php esc_html_e('Configure how prices are displayed throughout the onboarding process.', 'best-connect-onboarding'); ?>

                </p>

            </div>

            <div class="bcob-form-group">

                <label>

                    <?php esc_html_e('Currency', 'best-connect-onboarding'); ?>

                </label>

                <select
                    name="settings[currency]"
                    class="bcob-select2">

                    <?php foreach ($currencies as $code => $label) : ?>

                        <option
                            value="<?php echo esc_attr($code); ?>"
                            <?php selected($settings['currency'] ?? 'USD', $code); ?>>

                            <?php echo esc_html($label); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="bcob-form-group">

                <label>

                    <?php esc_html_e('Currency Position', 'best-connect-onboarding'); ?>

                </label>

                <select name="settings[currency_position]">

                    <option
                        value="left"
                        <?php selected($settings['currency_position'] ?? 'left', 'left'); ?>>

                        <?php esc_html_e('Left ($99.99)', 'best-connect-onboarding'); ?>

                    </option>

                    <option
                        value="right"
                        <?php selected($settings['currency_position'] ?? '', 'right'); ?>>

                        <?php esc_html_e('Right (99.99$)', 'best-connect-onboarding'); ?>

                    </option>

                    <option
                        value="left_space"
                        <?php selected($settings['currency_position'] ?? '', 'left_space'); ?>>

                        <?php esc_html_e('Left with space ($ 99.99)', 'best-connect-onboarding'); ?>

                    </option>

                    <option
                        value="right_space"
                        <?php selected($settings['currency_position'] ?? '', 'right_space'); ?>>

                        <?php esc_html_e('Right with space (99.99 $)', 'best-connect-onboarding'); ?>

                    </option>

                </select>

            </div>

            <div class="bcob-grid bcob-grid-3">

                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Thousand Separator', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        type="text"
                        maxlength="1"
                        name="settings[thousand_separator]"
                        value="<?php echo esc_attr($settings['thousand_separator'] ?? ','); ?>">

                </div>

                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Decimal Separator', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        type="text"
                        maxlength="1"
                        name="settings[decimal_separator]"
                        value="<?php echo esc_attr($settings['decimal_separator'] ?? '.'); ?>">

                </div>

                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Number of Decimals', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        type="number"
                        min="0"
                        max="6"
                        name="settings[number_of_decimals]"
                        value="<?php echo esc_attr($settings['number_of_decimals'] ?? '2'); ?>">

                </div>

            </div>

            <div class="bcob-settings-section">

                <div class="bcob-settings-divider">

                    <h3>

                        <i class="ri-id-card-line"></i>

                        <?php esc_html_e('Membership Number', 'best-connect-onboarding'); ?>

                    </h3>

                    <p>

                        <?php esc_html_e('Configure the membership number prefix and starting serial number.', 'best-connect-onboarding'); ?>

                    </p>

                </div>

                <div class="bcob-grid bcob-grid-2">

                    <div class="bcob-form-group">

                        <label>

                            <?php esc_html_e('Membership Number Prefix', 'best-connect-onboarding'); ?>

                        </label>

                        <input
                            type="text"
                            maxlength="20"
                            name="settings[bcob_membership_prefix]"
                            value="<?php echo esc_attr($settings['bcob_membership_prefix'] ?? 'BC'); ?>">

                    </div>

                    <div class="bcob-form-group">

                        <label>

                            <?php esc_html_e('Starting Serial Number', 'best-connect-onboarding'); ?>

                        </label>

                        <input
                            type="number"
                            min="1"
                            name="settings[bcob_membership_starting_serial]"
                            value="<?php echo esc_attr($settings['bcob_membership_starting_serial'] ?? '1000'); ?>">

                    </div>

                </div>

            </div>

        </div>

<?php
    }
}
