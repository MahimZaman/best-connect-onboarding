<?php

/**
 * Number Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

use BestConnectOnboarding\Forms\FieldBase;

defined('ABSPATH') || exit;

class NumberField extends FieldBase
{
    /**
     * Get field type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'number';
    }

    /**
     * Get field title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Number', 'best-connect-onboarding');
    }

    /**
     * Get field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-hashtag';
    }

    /**
     * Render field settings.
     *
     * @return void
     */
    protected function render_field_settings(): void
    {
?>

        <div class="bcob-grid bcob-grid-3">

            <div class="bcob-form-group">

                <label><?php esc_html_e('Minimum', 'best-connect-onboarding'); ?></label>

                <input
                    type="number"
                    step="any"
                    data-field="min"
                    value="<?php echo esc_attr($this->get('min')); ?>">

            </div>

            <div class="bcob-form-group">

                <label><?php esc_html_e('Maximum', 'best-connect-onboarding'); ?></label>

                <input
                    type="number"
                    step="any"
                    data-field="max"
                    value="<?php echo esc_attr($this->get('max')); ?>">

            </div>

            <div class="bcob-form-group">

                <label><?php esc_html_e('Step', 'best-connect-onboarding'); ?></label>

                <input
                    type="number"
                    step="any"
                    min="0.01"
                    data-field="step"
                    value="<?php echo esc_attr($this->get('step', 1)); ?>">

            </div>

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
                type="number"
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

    /**
     * Get default values.
     *
     * @return array
     */
    public function get_defaults(): array
    {
        return array_merge(
            parent::get_defaults(),
            [
                'min'  => '',
                'max'  => '',
                'step' => 1,
            ]
        );
    }

    /**
     * Validate submitted value.
     *
     * @param mixed $value Submitted value.
     * @return true|\WP_Error
     */
    public function validate($value)
    {
        $validation = parent::validate($value);

        if (is_wp_error($validation)) {
            return $validation;
        }

        if ($value === '' || $value === null) {
            return true;
        }

        if (!is_numeric($value)) {
            return new \WP_Error(
                'invalid_number',
                sprintf(
                    __('%s must be a valid number.', 'best-connect-onboarding'),
                    $this->get_label()
                )
            );
        }

        $number = (float) $value;

        if ($this->get('min') !== '' && $number < (float) $this->get('min')) {
            return new \WP_Error(
                'number_min',
                sprintf(
                    __('%s must be at least %s.', 'best-connect-onboarding'),
                    $this->get_label(),
                    $this->get('min')
                )
            );
        }

        if ($this->get('max') !== '' && $number > (float) $this->get('max')) {
            return new \WP_Error(
                'number_max',
                sprintf(
                    __('%s must not be greater than %s.', 'best-connect-onboarding'),
                    $this->get_label(),
                    $this->get('max')
                )
            );
        }

        return true;
    }
}
