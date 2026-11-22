<?php

/**
 * Email Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

use BestConnectOnboarding\Forms\FieldBase;

defined('ABSPATH') || exit;

class EmailField extends FieldBase
{
    /**
     * Field type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'email';
    }

    /**
     * Field title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Email', 'best-connect-onboarding');
    }

    /**
     * Field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-mail-line';
    }

    /**
     * Render field specific settings.
     *
     * @return void
     */
    protected function render_field_settings(): void
    {
?>

        <div class="bcob-form-group">

            <label><?php esc_html_e('Autocomplete', 'best-connect-onboarding'); ?></label>

            <select data-field="autocomplete">
                <option value="email" <?php selected($this->get('autocomplete', 'email'), 'email'); ?>>
                    <?php esc_html_e('Email', 'best-connect-onboarding'); ?>
                </option>
                <option value="off" <?php selected($this->get('autocomplete'), 'off'); ?>>
                    <?php esc_html_e('Off', 'best-connect-onboarding'); ?>
                </option>
            </select>

        </div>

    <?php
    }

    /**
     * Render frontend field.
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
                type="email"
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
     * Default values.
     *
     * @return array
     */
    public function get_defaults(): array
    {
        return array_merge(
            parent::get_defaults(),
            [
                'autocomplete' => 'email',
            ]
        );
    }

    /**
     * Validate value.
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

        if (!is_email($value)) {
            return new \WP_Error(
                'invalid_email',
                sprintf(
                    __('%s must be a valid email address.', 'best-connect-onboarding'),
                    $this->get_label()
                )
            );
        }

        return true;
    }
}
