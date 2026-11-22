<?php

/**
 * Text Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

defined('ABSPATH') || exit;

use BestConnectOnboarding\Forms\FieldBase;

class CouponField extends FieldBase
{

    /**
     * Field type.
     *
     * @return string
     */
    public function get_type(): string
    {

        return 'coupon';
    }

    /**
     * Field title.
     *
     * @return string
     */
    public function get_title(): string
    {

        return __('Coupon', 'best-connect-onboarding');
    }

    /**
     * Field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {

        return 'ri-coupon-3-line';
    }

    /**
     * Default values.
     *
     * @return array
     */
    public function get_defaults(): array
    {

        return array_replace_recursive(
            parent::get_defaults(),
            [
                'default'     => 'Coupon',
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
        return;
    }

    public function supports(string $feature): bool
    {
        return in_array(
            $feature,
            [
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
        <div class="bcob-coupon">

            <div class="bcob-coupon__label">
                <?php _e('RABATTCODE', 'best-connect-onboarding'); ?>
            </div>

            <div class="bcob-coupon__row">

                <input
                    type="text"
                    class="bcob-coupon__input"
                    value="">

                <button
                    type="button"
                    class="bcob-coupon__apply">
                    <?php _e('Anwenden', 'best-connect-onboarding') ?>
                </button>

            </div>

            <div class="bcob-coupon__message"></div>

        </div>
<?php
    }
}
