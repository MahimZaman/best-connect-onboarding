<?php

/**
 * HTML Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

use BestConnectOnboarding\Forms\FieldBase;

defined('ABSPATH') || exit;

class HtmlField extends FieldBase
{
    /**
     * Get field type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'html';
    }

    /**
     * Get field title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('HTML', 'best-connect-onboarding');
    }

    /**
     * Get field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-code-box-line';
    }

    /**
     * Render field settings.
     *
     * @return void
     */
    protected function render_field_settings(): void
    {
?>

        <div class="bcob-form-group">

            <label><?php esc_html_e('HTML Content', 'best-connect-onboarding'); ?></label>

            <textarea
                rows="10"
                name="html"
                data-field="html"><?php echo esc_textarea($this->get('html')); ?></textarea>

            <p class="description">
                <?php esc_html_e('HTML entered here will be rendered on the frontend.', 'best-connect-onboarding'); ?>
            </p>

        </div>

    <?php
    }

    /**
     * Render field.
     *
     * @return void
     */
    public function render($field = null): void
    {
    ?>

        <div class="bcob-field bcob-field-html bcob-col-<?php echo esc_attr($field->width); ?>">

            <?php
            echo wp_kses_post($field->html);
            ?>

        </div>

<?php
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
                'html' => __(
                    '<h3>Heading</h3><p>Your custom HTML goes here.</p>',
                    'best-connect-onboarding'
                ),
            ]
        );
    }

    /**
     * Validate value.
     *
     * @param mixed $value Submitted value.
     * @return true
     */
    public function validate($value)
    {
        return true;
    }
}
