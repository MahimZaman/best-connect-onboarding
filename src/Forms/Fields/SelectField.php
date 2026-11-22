<?php

/**
 * Select Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

use BestConnectOnboarding\Forms\FieldBase;

defined('ABSPATH') || exit;

class SelectField extends FieldBase
{
    /**
     * Get field type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'select';
    }

    /**
     * Get field title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Select', 'best-connect-onboarding');
    }

    /**
     * Get field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-arrow-down-s-line';
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

            <label><?php esc_html_e('Options', 'best-connect-onboarding'); ?></label>

            <textarea
                rows="8"
                data-field="options"
                placeholder="Option 1&#10;Option 2&#10;Option 3"><?php

                                                                    $options = array_map(
                                                                        static function ($option) {

                                                                            if (is_array($option)) {
                                                                                return $option['label'] ?? '';
                                                                            }

                                                                            return (string) $option;
                                                                        },
                                                                        (array) $this->get('options', [])
                                                                    );

                                                                    echo esc_textarea(
                                                                        implode("\n", $options)
                                                                    );

                                                                    ?></textarea>

            <p class="description">
                <?php esc_html_e('Enter one option per line.', 'best-connect-onboarding'); ?>
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
        if (!$field) {
            return;
        }

        $input_id =
            !empty($field->html_id)
            ? $field->html_id
            : $field->id;

        $options =
            isset($field->options) &&
            is_array($field->options)
            ? $field->options
            : [];

        $multiple =
            !empty($field->multiple);

        $default =
            $field->default ?? '';

        if ($multiple && !is_array($default)) {
            $default =
                $default === ''
                ? []
                : [$default];
        }
    ?>

        <div
            class="bcob-field bcob-field-select bcob-col-<?php echo esc_attr(
                                                                $field->width ?? '100'
                                                            ); ?>">

            <?php if (!empty($field->label)) : ?>

                <label
                    class="bcob-field-label"
                    for="<?php echo esc_attr($input_id); ?>">

                    <?php echo esc_html($field->label); ?>

                    <?php if (!empty($field->required)) : ?>
                        <span class="bcob-required">*</span>
                    <?php endif; ?>

                </label>

            <?php endif; ?>

            <?php if (!empty($field->description)) : ?>

                <p class="bcob-field-description">
                    <?php echo esc_html($field->description); ?>
                </p>

            <?php endif; ?>

            <select
                id="<?php echo esc_attr($input_id); ?>"
                name="<?php echo esc_attr($field->id); ?><?php echo $multiple ? '[]' : ''; ?>"
                class="<?php echo esc_attr($field->class ?? ''); ?>"
                <?php echo $multiple ? 'multiple' : ''; ?>
                <?php echo !empty($field->required) ? 'required' : ''; ?>>

                <?php foreach ($options as $option) : ?>

                    <?php
                    $option_value =
                        is_object($option)
                        ? ($option->value ?? '')
                        : ($option['value'] ?? '');

                    $option_label =
                        is_object($option)
                        ? ($option->label ?? '')
                        : ($option['label'] ?? '');

                    $is_selected =
                        $multiple
                        ? in_array(
                            (string) $option_value,
                            array_map(
                                'strval',
                                $default
                            ),
                            true
                        )
                        : (string) $default ===
                        (string) $option_value;
                    ?>

                    <option
                        value="<?php echo esc_attr($option_value); ?>"
                        <?php echo $is_selected ? 'selected' : ''; ?>>

                        <?php echo esc_html($option_label); ?>

                    </option>

                <?php endforeach; ?>

            </select>

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
                'options' => [],
            ]
        );
    }

    /**
     * Validate value.
     *
     * @param mixed $value Submitted value.
     *
     * @return true|\WP_Error
     */
    public function validate($value)
    {
        $validation = parent::validate($value);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $allowed = wp_list_pluck(
            (array) $this->get('options', []),
            'value'
        );

        if ($this->get('multiple')) {

            $value = (array) $value;

            foreach ($value as $selected) {

                if (!in_array($selected, $allowed, true)) {
                    return new \WP_Error(
                        'invalid_option',
                        __('Invalid option selected.', 'best-connect-onboarding')
                    );
                }
            }

            return true;
        }

        if ($value !== '' && !in_array($value, $allowed, true)) {
            return new \WP_Error(
                'invalid_option',
                __('Invalid option selected.', 'best-connect-onboarding')
            );
        }

        return true;
    }
}
