<?php

namespace BestConnectOnboarding\Admin\Settings;

defined('ABSPATH') || exit;

class Advanced
{
    public function __construct()
    {
        add_action(
            'bcob_settings_tab_advanced',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $settings = get_option('bcob_settings', []);
        $logging_enabled =
            isset(
                $settings['bcob_logging_enabled']
            )
            ? $settings['bcob_logging_enabled']
            : '1';

        $contract_link_expiry_hours =
            absint(
                $settings['bcob_contract_link_expiry_hours'] ?? 24
            );

        $coupon_failed_attempt_limit =
            max(
                1,
                min(
                    100,
                    absint(
                        $settings['bcob_coupon_failed_attempt_limit']
                            ?? 10
                    )
                )
            );

        $coupon_rate_limit_window =
            max(
                1,
                min(
                    1440,
                    absint(
                        $settings['bcob_coupon_rate_limit_window']
                            ?? 15
                    )
                )
            );

        if (
            !in_array(
                $contract_link_expiry_hours,
                [
                    1,
                    6,
                    12,
                    24,
                    72,
                    168,
                    720,
                ],
                true
            )
        ) {
            $contract_link_expiry_hours = 24;
        }

        $ga4_measurement_id = $settings['bcob_ga4_measurement_id'] ?? '';
        $gtm_container_id   = $settings['bcob_gtm_container_id'] ?? '';
        $consent_gating     = isset($settings['bcob_tracking_consent_gating'])
            ? $settings['bcob_tracking_consent_gating']
            : '1';
        $consent_tool       = $settings['bcob_tracking_consent_tool'] ?? 'borlabs_cookie';
        $consent_condition = $settings['bcob_tracking_consent_condition']
            ?? "window.BorlabsCookie?.Consents?.hasConsentForServiceGroup('statistics') === true";

        $abandonment_enabled = isset($settings['bcob_abandonment_enabled'])
            ? $settings['bcob_abandonment_enabled']
            : '0';

        $abandonment_delay_1 = $settings['bcob_abandonment_delay_1'] ?? '2';
        $abandonment_delay_2 = $settings['bcob_abandonment_delay_2'] ?? '2';
        $abandonment_email   = $settings['bcob_abandonment_email'] ?? '';

        $abandonment_consent_text =
            $settings['bcob_abandonment_consent_text']
            ?? __(
                'I agree to receive reminder emails about this unfinished onboarding process.',
                'best-connect-onboarding'
            );

        $abandonment_consent_version =
            $settings['bcob_abandonment_consent_version']
            ?? '1.0';

        $abandonment_max_reminders =
            max(
                1,
                min(
                    2,
                    absint(
                        $settings['bcob_abandonment_max_reminders']
                            ?? 2
                    )
                )
            );

        $retention_leads_days =
            max(
                1,
                absint(
                    $settings['bcob_retention_leads_days']
                        ?? 365
                )
            );

        $retention_abandonment_days =
            max(
                1,
                absint(
                    $settings['bcob_retention_abandonment_days']
                        ?? 30
                )
            );

        $retention_magic_links_days =
            max(
                1,
                absint(
                    $settings['bcob_retention_magic_links_days']
                        ?? 30
                )
            );

        $retention_prefill_days =
            max(
                1,
                absint(
                    $settings['bcob_retention_prefill_days']
                        ?? 30
                )
            );

        $retention_temp_files_days =
            max(
                1,
                absint(
                    $settings['bcob_retention_temp_files_days']
                        ?? 1
                )
            );

        $retention_logs_days =
            max(
                1,
                absint(
                    $settings['bcob_retention_logs_days']
                        ?? 30
                )
            );

        $events = [
            'step_view',
            'step_complete',
            'file_uploaded',
            'ai_extracted',
            'discount_applied',
            'payment_started',
            'signup_completed',
        ];
?>
        <div class="bcob-settings-section">

            <div class="bcob-settings-divider">

                <h3>
                    <i class="ri-bar-chart-line"></i>
                    <?php esc_html_e('Tracking & Consent (GA4)', 'best-connect-onboarding'); ?>
                </h3>

                <p>
                    <?php esc_html_e('Configure GA4, Google Tag Manager and the consent condition used before tracking events are sent.', 'best-connect-onboarding'); ?>
                </p>

            </div>

            <div class="bcob-form-group">

                <p class="bcob-settings-notice">
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            __('How to add GA4 correctly: just enter your Measurement ID here — no code editing required. On every step change the plugin automatically sends a <code>dataLayer.push</code> event. These events fire only when your consent condition has confirmed consent.', 'best-connect-onboarding')
                        )
                    );
                    ?>
                </p>

            </div>

            <div class="bcob-grid bcob-grid-2">

                <div class="bcob-form-group">

                    <label for="bcob-ga4-measurement-id">
                        <?php esc_html_e('GA4 Measurement-ID', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        id="bcob-ga4-measurement-id"
                        type="text"
                        name="settings[bcob_ga4_measurement_id]"
                        value="<?php echo esc_attr($ga4_measurement_id); ?>"
                        placeholder="G-XXXXXXXXXX">

                </div>

                <div class="bcob-form-group">

                    <label for="bcob-gtm-container-id">
                        <?php esc_html_e('GTM Container-ID (optional)', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        id="bcob-gtm-container-id"
                        type="text"
                        name="settings[bcob_gtm_container_id]"
                        value="<?php echo esc_attr($gtm_container_id); ?>"
                        placeholder="GTM-XXXXXXX">

                </div>

            </div>

            <div class="bcob-settings-divider">

                <h3>
                    <?php esc_html_e('CONSENT GATING (REQUIRED)', 'best-connect-onboarding'); ?>
                </h3>

            </div>

            <div class="bcob-checkbox">

                <input
                    type="hidden"
                    name="settings[bcob_tracking_consent_gating]"
                    value="0">

                <label>

                    <input
                        type="checkbox"
                        name="settings[bcob_tracking_consent_gating]"
                        value="1"
                        <?php checked($consent_gating, '1'); ?>>

                    <?php esc_html_e('Only fire events after cookie consent is given', 'best-connect-onboarding'); ?>

                </label>

            </div>

            <div class="bcob-grid bcob-grid-2">

                <div class="bcob-form-group">

                    <label for="bcob-tracking-consent-tool">
                        <?php esc_html_e('Consent tool', 'best-connect-onboarding'); ?>
                    </label>

                    <select
                        id="bcob-tracking-consent-tool"
                        name="settings[bcob_tracking_consent_tool]">

                        <option
                            value="borlabs_cookie"
                            <?php selected($consent_tool, 'borlabs_cookie'); ?>>
                            <?php esc_html_e('Borlabs Cookie', 'best-connect-onboarding'); ?>
                        </option>
                        <option
                            value="complianz"
                            <?php selected($consent_tool, 'complianz'); ?>>
                            <?php esc_html_e('Complianz', 'best-connect-onboarding'); ?>
                        </option>
                        <option
                            value="cookieyes"
                            <?php selected($consent_tool, 'cookieyes'); ?>>
                            <?php esc_html_e('CookieYes', 'best-connect-onboarding'); ?>
                        </option>

                        <option
                            value="cookiebot"
                            <?php selected($consent_tool, 'cookiebot'); ?>>
                            <?php esc_html_e('Cookiebot', 'best-connect-onboarding'); ?>
                        </option>
                        <option
                            value="custom"
                            <?php selected($consent_tool, 'custom'); ?>>
                            <?php esc_html_e('Custom JS function / cookie', 'best-connect-onboarding'); ?>
                        </option>

                    </select>

                </div>

                <div class="bcob-form-group">

                    <label for="bcob-tracking-consent-condition">
                        <?php esc_html_e('Check condition (cookie / JS function)', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        id="bcob-tracking-consent-condition"
                        type="text"
                        name="settings[bcob_tracking_consent_condition]"
                        value="<?php echo esc_attr($consent_condition); ?>"
                        placeholder="window.consent.statistics === true">

                    <p class="description">
                        <?php esc_html_e('Code check before every event.', 'best-connect-onboarding'); ?>
                    </p>

                </div>

            </div>

            <script>
                jQuery(function($) {

                    const $tool = $('#bcob-tracking-consent-tool');
                    const $condition = $('#bcob-tracking-consent-condition');

                    const conditions = {
                        borlabs_cookie: "window.BorlabsCookie?.Consents?.hasConsentForServiceGroup('statistics') === true",

                        complianz: "typeof window.cmplz_has_consent === 'function' && window.cmplz_has_consent('statistics') === true",

                        cookieyes: "typeof window.getCkyConsent === 'function' && window.getCkyConsent()?.categories?.analytics === true",

                        cookiebot: "window.Cookiebot?.consent?.statistics === true"
                    };

                    $tool.on('change', function() {

                        const tool = $(this).val();

                        if (conditions[tool]) {
                            $condition.val(conditions[tool]);
                        }

                    });

                });
            </script>

            <div class="bcob-settings-divider">

                <h3>
                    <?php esc_html_e('AUTOMATICALLY FIRED EVENTS', 'best-connect-onboarding'); ?>
                </h3>

                <p>
                    <?php esc_html_e('On each funnel step change (step name & index are included):', 'best-connect-onboarding'); ?>
                </p>

            </div>

            <div class="bcob-grid bcob-grid-6">

                <?php foreach ($events as $event) : ?>

                    <span class="bcob-tracking-event">
                        <?php echo esc_html($event); ?>
                    </span>

                <?php endforeach; ?>

            </div>

            <div class="bcob-settings-divider" style="margin-top: 30px;">

                <h3>
                    <i class="ri-mail-send-line"></i>
                    <?php esc_html_e('ABANDONMENT REMINDERS', 'best-connect-onboarding'); ?>
                </h3>

                <p>
                    <?php esc_html_e('Automatically remind users who started an onboarding form but did not complete it.', 'best-connect-onboarding'); ?>
                </p>

            </div>

            <div class="bcob-checkbox">

                <input
                    type="hidden"
                    name="settings[bcob_abandonment_enabled]"
                    value="0">

                <label>

                    <input
                        type="checkbox"
                        name="settings[bcob_abandonment_enabled]"
                        value="1"
                        <?php checked($abandonment_enabled, '1'); ?>>

                    <?php esc_html_e('Enable Abandonment Reminders', 'best-connect-onboarding'); ?>

                </label>

            </div>

            <div class="bcob-grid bcob-grid-2">

                <div class="bcob-form-group">

                    <label for="bcob-abandonment-consent-version">
                        <?php esc_html_e(
                            'Consent Text Version',
                            'best-connect-onboarding'
                        ); ?>
                    </label>

                    <input
                        id="bcob-abandonment-consent-version"
                        type="text"
                        name="settings[bcob_abandonment_consent_version]"
                        value="<?php echo esc_attr(
                                    $abandonment_consent_version
                                ); ?>">

                </div>

                <div class="bcob-form-group">

                    <label for="bcob-abandonment-max-reminders">
                        <?php esc_html_e(
                            'Maximum Reminders Per Email Address',
                            'best-connect-onboarding'
                        ); ?>
                    </label>

                    <select
                        id="bcob-abandonment-max-reminders"
                        name="settings[bcob_abandonment_max_reminders]">

                        <option
                            value="1"
                            <?php selected(
                                $abandonment_max_reminders,
                                1
                            ); ?>>
                            1
                        </option>

                        <option
                            value="2"
                            <?php selected(
                                $abandonment_max_reminders,
                                2
                            ); ?>>
                            2
                        </option>

                    </select>

                </div>

            </div>

            <div class="bcob-form-group">

                <label for="bcob-abandonment-consent-text">
                    <?php esc_html_e(
                        'Abandonment Reminder Consent Text',
                        'best-connect-onboarding'
                    ); ?>
                </label>

                <input
                    id="bcob-abandonment-consent-text"
                    type="text"
                    name="settings[bcob_abandonment_consent_text]"
                    value="<?php echo esc_attr(
                                $abandonment_consent_text
                            ); ?>">

            </div>

            <div class="bcob-grid bcob-grid-3">

                <div class="bcob-form-group">

                    <label for="bcob-abandonment-delay-1">
                        <?php esc_html_e('First reminder after', 'best-connect-onboarding'); ?>
                    </label>

                    <div class="bcob-input-with-suffix">

                        <input
                            id="bcob-abandonment-delay-1"
                            type="number"
                            min="1"
                            step="1"
                            name="settings[bcob_abandonment_delay_1]"
                            value="<?php echo esc_attr($abandonment_delay_1); ?>">

                        <span><?php esc_html_e('hours', 'best-connect-onboarding'); ?></span>

                    </div>

                </div>

                <div class="bcob-form-group">

                    <label for="bcob-abandonment-delay-2">
                        <?php esc_html_e('Second reminder after', 'best-connect-onboarding'); ?>
                    </label>

                    <div class="bcob-input-with-suffix">

                        <input
                            id="bcob-abandonment-delay-2"
                            type="number"
                            min="1"
                            step="1"
                            name="settings[bcob_abandonment_delay_2]"
                            value="<?php echo esc_attr($abandonment_delay_2); ?>">

                        <span><?php esc_html_e('days', 'best-connect-onboarding'); ?></span>

                    </div>

                </div>

            </div>

            <div class="bcob-form-group">

                <label for="bcob-abandonment-email">
                    <?php esc_html_e('Reminder Email Content', 'best-connect-onboarding'); ?>
                </label>

                <?php
                wp_editor(
                    $abandonment_email,
                    'bcob-abandonment-email',
                    [
                        'textarea_name' => 'settings[bcob_abandonment_email]',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'teeny' => false,
                        'quicktags' => true,
                    ]
                );
                ?>

                <p class="description">
                    <?php
                    esc_html_e(
                        'Use {{magic_link}} in the email content to insert the dynamic resume link.',
                        'best-connect-onboarding'
                    );
                    ?>
                </p>

            </div>

            <div
                class="bcob-settings-divider"
                style="margin-top: 30px;">

                <h3>
                    <i class="ri-coupon-3-line"></i>

                    <?php
                    esc_html_e(
                        'COUPON SECURITY',
                        'best-connect-onboarding'
                    );
                    ?>
                </h3>

                <p>
                    <?php
                    esc_html_e(
                        'Limit repeated failed coupon attempts by IP address and browser session.',
                        'best-connect-onboarding'
                    );
                    ?>
                </p>

            </div>

            <div class="bcob-grid bcob-grid-2">

                <div class="bcob-form-group">

                    <label for="bcob-coupon-failed-attempt-limit">
                        <?php
                        esc_html_e(
                            'Failed Attempt Limit',
                            'best-connect-onboarding'
                        );
                        ?>
                    </label>

                    <input
                        id="bcob-coupon-failed-attempt-limit"
                        type="number"
                        min="1"
                        max="100"
                        step="1"
                        name="settings[bcob_coupon_failed_attempt_limit]"
                        value="<?php echo esc_attr(
                                    $coupon_failed_attempt_limit
                                ); ?>">

                    <p class="description">
                        <?php
                        esc_html_e(
                            'Maximum failed attempts allowed during the configured time window.',
                            'best-connect-onboarding'
                        );
                        ?>
                    </p>

                </div>

                <div class="bcob-form-group">

                    <label for="bcob-coupon-rate-limit-window">
                        <?php
                        esc_html_e(
                            'Rate Limit Window',
                            'best-connect-onboarding'
                        );
                        ?>
                    </label>

                    <div class="bcob-input-with-suffix">

                        <input
                            id="bcob-coupon-rate-limit-window"
                            type="number"
                            min="1"
                            max="1440"
                            step="1"
                            name="settings[bcob_coupon_rate_limit_window]"
                            value="<?php echo esc_attr(
                                        $coupon_rate_limit_window
                                    ); ?>">

                        <span>
                            <?php
                            esc_html_e(
                                'minutes',
                                'best-connect-onboarding'
                            );
                            ?>
                        </span>

                    </div>

                </div>

            </div>

            <div
                class="bcob-settings-divider"
                style="margin-top: 30px;">

                <h3>
                    <i class="ri-shield-keyhole-line"></i>

                    <?php
                    esc_html_e(
                        'CONTRACT DOWNLOAD SECURITY',
                        'best-connect-onboarding'
                    );
                    ?>
                </h3>

                <p>
                    <?php
                    esc_html_e(
                        'Configure how long secure contract download links remain valid.',
                        'best-connect-onboarding'
                    );
                    ?>
                </p>

            </div>

            <div class="bcob-form-group">

                <label for="bcob-contract-link-expiry">

                    <?php
                    esc_html_e(
                        'Contract Download Link Expiry',
                        'best-connect-onboarding'
                    );
                    ?>

                </label>

                <select
                    id="bcob-contract-link-expiry"
                    name="settings[bcob_contract_link_expiry_hours]">

                    <option
                        value="1"
                        <?php selected(
                            $contract_link_expiry_hours,
                            1
                        ); ?>>
                        <?php esc_html_e(
                            '1 Hour',
                            'best-connect-onboarding'
                        ); ?>
                    </option>

                    <option
                        value="6"
                        <?php selected(
                            $contract_link_expiry_hours,
                            6
                        ); ?>>
                        <?php esc_html_e(
                            '6 Hours',
                            'best-connect-onboarding'
                        ); ?>
                    </option>

                    <option
                        value="12"
                        <?php selected(
                            $contract_link_expiry_hours,
                            12
                        ); ?>>
                        <?php esc_html_e(
                            '12 Hours',
                            'best-connect-onboarding'
                        ); ?>
                    </option>

                    <option
                        value="24"
                        <?php selected(
                            $contract_link_expiry_hours,
                            24
                        ); ?>>
                        <?php esc_html_e(
                            '24 Hours',
                            'best-connect-onboarding'
                        ); ?>
                    </option>

                    <option
                        value="72"
                        <?php selected(
                            $contract_link_expiry_hours,
                            72
                        ); ?>>
                        <?php esc_html_e(
                            '3 Days',
                            'best-connect-onboarding'
                        ); ?>
                    </option>

                    <option
                        value="168"
                        <?php selected(
                            $contract_link_expiry_hours,
                            168
                        ); ?>>
                        <?php esc_html_e(
                            '7 Days',
                            'best-connect-onboarding'
                        ); ?>
                    </option>

                    <option
                        value="720"
                        <?php selected(
                            $contract_link_expiry_hours,
                            720
                        ); ?>>
                        <?php esc_html_e(
                            '30 Days',
                            'best-connect-onboarding'
                        ); ?>
                    </option>

                </select>

                <p class="description">
                    <?php
                    esc_html_e(
                        'After this period the contract URL can no longer be used.',
                        'best-connect-onboarding'
                    );
                    ?>
                </p>

            </div>

            <div
                class="bcob-settings-divider"
                style="margin-top: 30px;">

                <h3>
                    <i class="ri-file-list-3-line"></i>

                    <?php
                    esc_html_e(
                        'SYSTEM LOGGING',
                        'best-connect-onboarding'
                    );
                    ?>
                </h3>

                <p>
                    <?php
                    esc_html_e(
                        'Store technical operational events for troubleshooting. Customer form values and personal data are never included.',
                        'best-connect-onboarding'
                    );
                    ?>
                </p>

            </div>

            <div class="bcob-checkbox">

                <input
                    type="hidden"
                    name="settings[bcob_logging_enabled]"
                    value="0">

                <label>

                    <input
                        type="checkbox"
                        name="settings[bcob_logging_enabled]"
                        value="1"
                        <?php checked(
                            $logging_enabled,
                            '1'
                        ); ?>>

                    <?php
                    esc_html_e(
                        'Enable System Logging',
                        'best-connect-onboarding'
                    );
                    ?>

                </label>

            </div>

            <div
                class="bcob-settings-divider"
                style="margin-top: 30px;">

                <h3>
                    <i class="ri-shield-user-line"></i>

                    <?php esc_html_e(
                        'PRIVACY & DATA RETENTION',
                        'best-connect-onboarding'
                    ); ?>
                </h3>

                <p>
                    <?php esc_html_e(
                        'Configure how long onboarding personal data and temporary files are retained before automatic cleanup.',
                        'best-connect-onboarding'
                    ); ?>
                </p>

            </div>

            <div class="bcob-grid bcob-grid-3">

                <div class="bcob-form-group">

                    <label>
                        <?php esc_html_e(
                            'Leads & Contracts',
                            'best-connect-onboarding'
                        ); ?>
                    </label>

                    <div class="bcob-input-with-suffix">

                        <input
                            type="number"
                            min="1"
                            step="1"
                            name="settings[bcob_retention_leads_days]"
                            value="<?php echo esc_attr(
                                        $retention_leads_days
                                    ); ?>">

                        <span>
                            <?php esc_html_e(
                                'days',
                                'best-connect-onboarding'
                            ); ?>
                        </span>

                    </div>

                </div>

                <div class="bcob-form-group">

                    <label>
                        <?php esc_html_e(
                            'Abandoned Sessions',
                            'best-connect-onboarding'
                        ); ?>
                    </label>

                    <div class="bcob-input-with-suffix">

                        <input
                            type="number"
                            min="1"
                            step="1"
                            name="settings[bcob_retention_abandonment_days]"
                            value="<?php echo esc_attr(
                                        $retention_abandonment_days
                                    ); ?>">

                        <span>
                            <?php esc_html_e(
                                'days',
                                'best-connect-onboarding'
                            ); ?>
                        </span>

                    </div>

                </div>

                <div class="bcob-form-group">

                    <label>
                        <?php esc_html_e(
                            'Expired Magic Links',
                            'best-connect-onboarding'
                        ); ?>
                    </label>

                    <div class="bcob-input-with-suffix">

                        <input
                            type="number"
                            min="1"
                            step="1"
                            name="settings[bcob_retention_magic_links_days]"
                            value="<?php echo esc_attr(
                                        $retention_magic_links_days
                                    ); ?>">

                        <span>
                            <?php esc_html_e(
                                'days after expiry',
                                'best-connect-onboarding'
                            ); ?>
                        </span>

                    </div>

                </div>

                <div class="bcob-form-group">

                    <label>
                        <?php esc_html_e(
                            'Expired Pre-fill Links',
                            'best-connect-onboarding'
                        ); ?>
                    </label>

                    <div class="bcob-input-with-suffix">

                        <input
                            type="number"
                            min="1"
                            step="1"
                            name="settings[bcob_retention_prefill_days]"
                            value="<?php echo esc_attr(
                                        $retention_prefill_days
                                    ); ?>">

                        <span>
                            <?php esc_html_e(
                                'days after expiry',
                                'best-connect-onboarding'
                            ); ?>
                        </span>

                    </div>

                </div>

                <div class="bcob-form-group">

                    <label>
                        <?php
                        esc_html_e(
                            'System Logs',
                            'best-connect-onboarding'
                        );
                        ?>
                    </label>

                    <div class="bcob-input-with-suffix">

                        <input
                            type="number"
                            min="1"
                            step="1"
                            name="settings[bcob_retention_logs_days]"
                            value="<?php echo esc_attr(
                                        $retention_logs_days
                                    ); ?>">

                        <span>
                            <?php
                            esc_html_e(
                                'days',
                                'best-connect-onboarding'
                            );
                            ?>
                        </span>

                    </div>

                </div>

                <div class="bcob-form-group">

                    <label>
                        <?php esc_html_e(
                            'Temporary Files',
                            'best-connect-onboarding'
                        ); ?>
                    </label>

                    <div class="bcob-input-with-suffix">

                        <input
                            type="number"
                            min="1"
                            step="1"
                            name="settings[bcob_retention_temp_files_days]"
                            value="<?php echo esc_attr(
                                        $retention_temp_files_days
                                    ); ?>">

                        <span>
                            <?php esc_html_e(
                                'days',
                                'best-connect-onboarding'
                            ); ?>
                        </span>

                    </div>

                </div>

            </div>

            <p class="description">
                <?php esc_html_e(
                    'WordPress Privacy Tools can also export or erase onboarding data for a specific email address.',
                    'best-connect-onboarding'
                ); ?>
            </p>

        </div>
<?php
    }
}
