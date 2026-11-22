<?php

namespace BestConnectOnboarding\Admin\Settings;

defined('ABSPATH') || exit;

class Integrations
{
    public function __construct()
    {
        add_action(
            'bcob_settings_tab_integrations',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $settings = get_option('bcob_settings', []);

?>
        <div class="bcob-settings-section">
            <div class="bcob-settings-divider">

                <h3>

                    <i class="ri-bank-card-line"></i>

                    <?php esc_html_e('Open AI', 'best-connect-onboarding'); ?>

                </h3>

                <p>

                    <?php esc_html_e('Integrate Open AI using api key for document parsing and autofilling forms and PDFs', 'best-connect-onboarding'); ?>

                </p>

            </div>
            <div class="bcob-grid bcob-grid-2">
                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('API Key', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        class="password-toggle"
                        type="password"
                        name="settings[bcob_openai_api_key]"
                        value="<?php echo esc_attr($settings['bcob_openai_api_key'] ?? ''); ?>">

                </div>
                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Select Model', 'best-connect-onboarding'); ?>

                    </label>

                    <select name="settings[bcob_openai_model]" id="bcob_openai_model">
                        <?php
                        $openai_models = [
                            'gpt-5.6'      => 'GPT-5.6',
                            'gpt-5.6-mini' => 'GPT-5.6 Mini',
                            'gpt-5.5'      => 'GPT-5.5',
                            'gpt-5.4'      => 'GPT-5.4',
                            'gpt-5.4-mini' => 'GPT-5.4 Mini',
                            'gpt-5'        => 'GPT-5',
                            'gpt-5-mini'   => 'GPT-5 Mini',
                            'gpt-5-nano'   => 'GPT-5 Nano',
                            'gpt-4.1'      => 'GPT-4.1',
                            'gpt-4.1-mini' => 'GPT-4.1 Mini',
                            'gpt-4.1-nano' => 'GPT-4.1 Nano',
                            'gpt-4o'       => 'GPT-4o',
                            'gpt-4o-mini'  => 'GPT-4o Mini',
                        ];
                        ?>

                        <?php foreach ($openai_models as $model_id => $model_name) : ?>
                            <option value="<?php echo esc_attr($model_id); ?>"
                                <?php selected($settings['bcob_openai_model'] ?? 'gpt-5', $model_id); ?>>
                                <?php echo esc_html($model_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>
            </div>


            <div class="bcob-settings-divider"></div>

            <div class="bcob-settings-divider">

                <h3>

                    <i class="ri-bank-card-line"></i>

                    <?php esc_html_e('Stripe', 'best-connect-onboarding'); ?>

                </h3>

                <p>

                    <?php esc_html_e('Accept online payments securely using your Stripe account.', 'best-connect-onboarding'); ?>

                </p>

            </div>

            <div class="bcob-checkbox">

                <label>

                    <input
                        type="checkbox"
                        name="settings[stripe_enabled]"
                        value="1"
                        <?php checked($settings['stripe_enabled'] ?? '', '1'); ?>>

                    <?php esc_html_e('Enable Stripe', 'best-connect-onboarding'); ?>

                </label>

            </div>

            <div class="bcob-form-group">

                <label>

                    <?php esc_html_e('Mode', 'best-connect-onboarding'); ?>

                </label>

                <select name="settings[stripe_mode]">

                    <option value="test" <?php selected($settings['stripe_mode'] ?? 'test', 'test'); ?>>

                        <?php esc_html_e('Test', 'best-connect-onboarding'); ?>

                    </option>

                    <option value="live" <?php selected($settings['stripe_mode'] ?? '', 'live'); ?>>

                        <?php esc_html_e('Live', 'best-connect-onboarding'); ?>

                    </option>

                </select>

            </div>

            <div class="bcob-grid bcob-grid-2">

                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Test Publishable Key', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        type="text"
                        name="settings[stripe_test_publishable_key]"
                        value="<?php echo esc_attr($settings['stripe_test_publishable_key'] ?? ''); ?>">

                </div>

                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Test Secret Key', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        class="password-toggle"
                        type="password"
                        name="settings[stripe_test_secret_key]"
                        value="<?php echo esc_attr($settings['stripe_test_secret_key'] ?? ''); ?>">

                </div>

            </div>

            <div class="bcob-grid bcob-grid-2">

                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Live Publishable Key', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        type="text"
                        name="settings[stripe_live_publishable_key]"
                        value="<?php echo esc_attr($settings['stripe_live_publishable_key'] ?? ''); ?>">

                </div>

                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Live Secret Key', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        class="password-toggle"
                        type="password"
                        name="settings[stripe_live_secret_key]"
                        value="<?php echo esc_attr($settings['stripe_live_secret_key'] ?? ''); ?>">

                </div>

            </div>

            <div class="bcob-form-group">

                <label>

                    <?php esc_html_e('Webhook Signing Secret', 'best-connect-onboarding'); ?>

                </label>

                <input
                    class="password-toggle"
                    type="password"
                    name="settings[stripe_webhook_secret]"
                    value="<?php echo esc_attr($settings['stripe_webhook_secret'] ?? ''); ?>">

            </div>

            <div class="bcob-stripe-webhook-instructions">
                <h3>Configure Stripe Webhooks</h3>

                <p>
                    To receive payment updates from Stripe, create a webhook endpoint
                    in your Stripe Dashboard and select the events listed below.
                </p>

                <h4>1. Create the webhook endpoint</h4>

                <ol>
                    <li>Open your <strong>Stripe Dashboard</strong>.</li>
                    <li>Go to <strong>Developers → Webhooks</strong>.</li>
                    <li>Click <strong>Add endpoint</strong>.</li>
                    <li>Enter the webhook URL shown below.</li>
                    <li>Select the required events listed below.</li>
                    <li>Save the endpoint.</li>
                </ol>

                <div class="bcob-stripe-webhook-box">
                    <strong>Webhook endpoint URL</strong>

                    <code><?php echo esc_html(rest_url('bcob/v1/stripe/webhook')); ?></code>
                </div>

                <h4>2. Select these events</h4>

                <p>
                    Enable the following events when creating the webhook endpoint:
                </p>

                <ul>
                    <li><code>payment_intent.succeeded</code></li>
                    <li><code>payment_intent.processing</code></li>
                    <li><code>payment_intent.payment_failed</code></li>
                    <li><code>invoice.paid</code></li>
                    <li><code>invoice.payment_failed</code></li>
                    <li><code>customer.subscription.updated</code></li>
                    <li><code>customer.subscription.deleted</code></li>
                </ul>

                <h4>3. Copy the signing secret</h4>

                <p>
                    After creating the endpoint, open the webhook endpoint in Stripe
                    and reveal the <strong>Signing secret</strong>.
                </p>

                <p>
                    The signing secret starts with:
                </p>

                <div class="bcob-stripe-webhook-box">
                    <code>whsec_...</code>
                </div>

                <p>
                    Copy this value and paste it into the
                    <strong>Stripe Webhook Signing Secret</strong> field below.
                </p>

                <div class="bcob-stripe-webhook-note">
                    <strong>Important:</strong>
                    Use the webhook endpoint and signing secret that belong to the
                    same Stripe mode. Test-mode payments require the Test-mode webhook
                    signing secret, while Live-mode payments require the Live-mode
                    webhook signing secret.
                </div>

                <h4>Localhost testing</h4>

                <p>
                    If you are testing on localhost, Stripe cannot directly send
                    webhooks to your local WordPress installation. Use the Stripe CLI
                    to forward webhook events to your local endpoint.
                </p>

                <div class="bcob-stripe-webhook-box">
                    <code>
                        stripe listen --forward-to
                        <?php echo esc_html(rest_url('bcob/v1/stripe/webhook')); ?>
                    </code>
                </div>

                <p>
                    The Stripe CLI will provide a temporary <code>whsec_...</code>
                    signing secret. Use that secret for your local Test-mode webhook
                    configuration.
                </p>
            </div>

            <div class="bcob-form-group">

                <button
                    type="button"
                    id="bcob-connect-stripe"
                    class="button button-secondary">
                    <span class="bcob-button-text">
                        <i class="ri-links-line"></i>
                        <?php esc_html_e('Test API Connection', 'best-connect-onboarding'); ?>
                    </span>

                    <span class="bcob-button-loader" hidden>
                        <i class="ri-loader-4-line ri-spin"></i>
                    </span>
                </button>

                <div id="bcob-stripe-connect-status"></div>
            </div>

            <hr class="bcob-settings-separator">

            <div class="bcob-settings-divider">

                <h3>

                    <?php esc_html_e('Available Payment Methods', 'best-connect-onboarding'); ?>

                </h3>

                <p>

                    <?php esc_html_e('After connecting your Stripe account, available payment methods will appear here for selection.', 'best-connect-onboarding'); ?>

                </p>

            </div>

            <?php

            $methods = [
                'card'               => 'Cards',
                'link'               => 'Link',
                'apple_pay'          => 'Apple Pay',
                'google_pay'         => 'Google Pay',
                'klarna'             => 'Klarna',
                'affirm'             => 'Affirm',
                'afterpay_clearpay'  => 'Afterpay / Clearpay',
                'cashapp'            => 'Cash App Pay',
                'amazon_pay'         => 'Amazon Pay',
                'paypal'             => 'PayPal',
                'alipay'             => 'Alipay',
                'wechat_pay'         => 'WeChat Pay',
                'grabpay'            => 'GrabPay',
                'ideal'              => 'iDEAL',
                'bancontact'         => 'Bancontact',
                'eps'                => 'EPS',
                'giropay'            => 'Giropay',
                'przelewy24'         => 'Przelewy24',
                'multibanco'         => 'Multibanco',
                'sepa_debit'         => 'SEPA Direct Debit',
                'bacs_debit'         => 'Bacs Direct Debit',
                'au_becs_debit'      => 'BECS Direct Debit',
                'acss_debit'         => 'ACSS Debit',
                'us_bank_account'    => 'US Bank Account',
                'oxxo'               => 'OXXO',
                'boleto'             => 'Boleto',
                'konbini'            => 'Konbini',
                'promptpay'          => 'PromptPay',
                'fpx'                => 'FPX',
                'paynow'             => 'PayNow',
                'zip'                => 'Zip'
            ];

            $enabled = $settings['stripe_enabled_payment_methods'] ?? [];

            ?>

            <div class="bcob-grid bcob-grid-6">

                <?php foreach ($methods as $key => $label) : ?>

                    <label class="bcob-checkbox">

                        <input
                            type="checkbox"
                            name="settings[stripe_enabled_payment_methods][]"
                            value="<?php echo esc_attr($key); ?>"
                            <?php checked(in_array($key, $enabled, true)); ?>>

                        <?php echo esc_html($label); ?>

                    </label>

                <?php endforeach; ?>

            </div>

        </div>
<?php
    }
}
