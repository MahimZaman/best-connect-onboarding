<?php

/**
 * Text Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

defined('ABSPATH') || exit;

use BestConnectOnboarding\Forms\FieldBase;

class PaymentField extends FieldBase
{

    /**
     * Field type.
     *
     * @return string
     */
    public function get_type(): string
    {

        return 'payment';
    }

    /**
     * Field title.
     *
     * @return string
     */
    public function get_title(): string
    {

        return __('Payment Field', 'best-connect-onboarding');
    }

    /**
     * Field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {

        return 'ri-bank-card-2-line';
    }

    /**
     * Default values.
     *
     * @return array
     */
    public function get_defaults(): array
    {
        return array_merge(
            parent::get_defaults(),
            [
                'payment_type' => 'one_time',
                'billing_interval' => 'month',
                'billing_interval_count' => 1,
            ]
        );
    }

    /**
     * Builder settings.
     *
     * @return void
     */
    protected function render_field_settings(): void
    {
?>

        <div class="bcob-form-group">

            <label><?php esc_html_e('Payment Type', 'best-connect-onboarding'); ?></label>

            <select data-field="payment_type">

                <option
                    value="one_time"
                    <?php selected($this->get('payment_type', 'one_time'), 'one_time'); ?>>

                    <?php esc_html_e('One Time Payment', 'best-connect-onboarding'); ?>

                </option>

                <option
                    value="recurring"
                    <?php selected($this->get('payment_type'), 'recurring'); ?>>

                    <?php esc_html_e('Recurring Subscription', 'best-connect-onboarding'); ?>

                </option>

            </select>

        </div>

        <div class="bcob-form-group">

            <label>
                <?php esc_html_e('Billing Interval', 'best-connect-onboarding'); ?>
            </label>

            <select data-field="billing_interval">

                <option
                    value="day"
                    <?php selected($this->get('billing_interval', 'month'), 'day'); ?>>

                    <?php esc_html_e('Daily', 'best-connect-onboarding'); ?>

                </option>

                <option
                    value="week"
                    <?php selected($this->get('billing_interval', 'month'), 'week'); ?>>

                    <?php esc_html_e('Weekly', 'best-connect-onboarding'); ?>

                </option>

                <option
                    value="month"
                    <?php selected($this->get('billing_interval', 'month'), 'month'); ?>>

                    <?php esc_html_e('Monthly', 'best-connect-onboarding'); ?>

                </option>

                <option
                    value="year"
                    <?php selected($this->get('billing_interval', 'month'), 'year'); ?>>

                    <?php esc_html_e('Yearly', 'best-connect-onboarding'); ?>

                </option>

            </select>

        </div>

        <div class="bcob-form-group">

            <label>
                <?php esc_html_e('Interval Count', 'best-connect-onboarding'); ?>
            </label>

            <input
                type="number"
                min="1"
                data-field="billing_interval_count"
                value="<?php echo esc_attr($this->get('billing_interval_count', 1)); ?>">

        </div>

    <?php
    }

    public function supports(string $feature): bool
    {
        return in_array(
            $feature,
            [
                'description',
                'width',
                'class',
                'html_id'
            ],
            true
        );
    }

    /**
     * Render frontend.
     *
     * @return void
     */
    public function render($field = null): void
    {
    ?>
        <div class="bcob-payment-wrapper">

            <div class="bcob-payment-header">

                <h5 class="bcob-payment__title">
                    <?php _e($field->label ?? 'Payment Details', 'best-connect-onboarding'); ?>
                </h5>

                <div class="bcob-payment__powered">
                    <?php _e($field->description ?? 'Powered by Stripe', 'best-connect-onboarding'); ?>
                </div>

            </div>

            <div class="bcob-payment">

                <div
                    id="bcob-payment-element"

                    data-type="<?php echo esc_attr($field->payment_type); ?>"

                    data-interval="<?php echo esc_attr($field->billing_interval); ?>"

                    data-interval-count="<?php echo esc_attr($field->billing_interval_count); ?>">
                    <div class="bcob-payment-loading">
                        <span class="bcob-payment-loading__spinner"></span>
                        <span class="bcob-payment-loading__text">Loading secure payment…</span>
                    </div>
                </div>

                <div
                    id="bcob-payment-error"
                    class="bcob-payment-error"
                    role="alert"
                    aria-live="polite">
                </div>

            </div>

        </div>
<?php
    }
}
