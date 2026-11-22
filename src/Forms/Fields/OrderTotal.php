<?php

/**
 * Text Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

defined('ABSPATH') || exit;

use BestConnectOnboarding\Forms\FieldBase;

class OrderTotal extends FieldBase
{

    /**
     * Field type.
     *
     * @return string
     */
    public function get_type(): string
    {

        return 'order-total';
    }

    /**
     * Field title.
     *
     * @return string
     */
    public function get_title(): string
    {

        return __('Order Total', 'best-connect-onboarding');
    }

    /**
     * Field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {

        return 'ri-calculator-line';
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
                'default'     => 'Order Total',
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
        <div class="bcob-pay-today">

            <div class="bcob-pay-today__left">

                <div class="bcob-pay-today__label">
                    <?php _e(($field->label ?? 'FÄLLIG HEUTE'), 'best-connect-onboarding'); ?>
                </div>

                <div class="bcob-pay-today__price">
                    <?php echo bcob_price(0) ?>
                </div>

            </div>

            <div class="bcob-pay-today__right">
                <?php _e(($field->description ?? ''), 'best-connect-onboarding'); ?>
            </div>

        </div>
<?php
    }
}
