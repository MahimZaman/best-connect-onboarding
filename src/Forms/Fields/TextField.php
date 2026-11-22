<?php

/**
 * Text Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

defined('ABSPATH') || exit;

use BestConnectOnboarding\Forms\FieldBase;

class TextField extends FieldBase
{

    /**
     * Field type.
     *
     * @return string
     */
    public function get_type(): string
    {

        return 'text';
    }

    /**
     * Field title.
     *
     * @return string
     */
    public function get_title(): string
    {

        return __('Text', 'best-connect-onboarding');
    }

    /**
     * Field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {

        return 'ri-text';
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
                'placeholder' => '',
                'default'     => 'Text',
                'min_length'  => '',
                'max_length'  => '',
                'input_mode'  => 'text',
                'autocomplete' => 'on',
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

        <div class="bcob-grid bcob-grid-2">

            <div class="bcob-form-group">

                <label>Minimum Length</label>

                <input
                    type="number"
                    data-field="min_length">

            </div>

            <div class="bcob-form-group">

                <label>Maximum Length</label>

                <input
                    type="number"
                    data-field="max_length">

            </div>

        </div>

    <?php

    }

    /**
     * Render frontend.
     *
     * @return void
     */
    public function render($field = null): void
    {

        $input_id = !empty($field->html_id) ? $field->html_id : $field->id;
    ?>

        <div
            class="bcob-field bcob-field-text">
            <?php if (!empty($field->label)) : ?>
                <label
                    class="bcob-field-label"
                    for="<?php echo esc_attr($input_id); ?>">
                    <?php echo esc_html($field->label); ?>

                    <?php if (!empty($field->required)) : ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>

            <input
                id="<?php echo esc_attr($input_id); ?>"
                name="<?php echo esc_attr($field->id); ?>"
                type="text"
                class="bcob-input"
                value="<?php echo esc_attr($field->default); ?>"
                placeholder="<?php echo esc_attr($field->placeholder); ?>"
                inputmode="<?php echo esc_attr($field->input_mode ?: 'text'); ?>"
                autocomplete="<?php echo esc_attr($field->autocomplete ?: 'on'); ?>"
                <?php echo !empty($field->min_length) ? 'minlength="' . (int)$field->min_length . '"' : ''; ?>
                <?php echo !empty($field->max_length) ? 'maxlength="' . (int)$field->max_length . '"' : ''; ?>
                <?php echo !empty($field->required) ? 'required' : ''; ?>>

            <?php if (!empty($field->description)) : ?>
                <div class="bcob-field-description">
                    <?php echo nl2br(esc_html($field->description)); ?>
                </div>
            <?php endif; ?>
        </div>
<?php
    }
}
