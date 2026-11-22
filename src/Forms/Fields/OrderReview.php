<?php

/**
 * Text Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

defined('ABSPATH') || exit;

use BestConnectOnboarding\Forms\FieldBase;

class OrderReview extends FieldBase
{

    /**
     * Field type.
     *
     * @return string
     */
    public function get_type(): string
    {

        return 'order-review';
    }

    /**
     * Field title.
     *
     * @return string
     */
    public function get_title(): string
    {

        return __('Order Review', 'best-connect-onboarding');
    }

    /**
     * Field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {

        return 'ri-list-ordered-2';
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
                'default'     => 'Order Review',
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
        <div class="bcob-summary-wrapper">
            <div class="bcob-summary"></div>
            <div class="bcob-summary__note">
                <?php _e($field->description ?? '', 'best-connect-onboarding'); ?>
            </div>
        </div>
<?php
    }
}
