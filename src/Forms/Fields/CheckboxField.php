<?php

/**
 * Checkbox Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

use BestConnectOnboarding\Forms\FieldBase;

defined('ABSPATH') || exit;

class CheckboxField extends FieldBase
{
    /**
     * Get field type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'checkbox';
    }

    /**
     * Get field title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Checkbox', 'best-connect-onboarding');
    }

    /**
     * Get field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-checkbox-line';
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
     * Render frontend field.
     *
     * @return void
     */
    public function render($field = null): void
    {
        if (!$field) {
            return;
        }
        $uid = $field->id;
        $id = $field->html_id;
        $class = $field->class;
        $label = $field->label;
        $validation = json_encode($field->validation);
        $options = $field->options ?: [];
        $required = $field->required ?? '';
    ?>

        <div class="bcob-checkbox <?php echo $class; ?>" <?php echo $id ? 'id = "' . $id . '"' : ''; ?> data-validation='<?php echo $validation ?>' aria-title="<?php echo $label; ?>">
            <?php foreach ($options as $option):

            ?>

                <label class="bcob-checkbox__label">

                    <input
                        type="checkbox"
                        class="bcob-checkbox__input"
                        name="<?php echo $uid; ?>[]" data-required="<?php echo $required ? 'required' : ''; ?>" value="<?php echo $option->label ?>">

                    <span class="bcob-checkbox__box"></span>

                    <span class="bcob-checkbox__text">
                        <?php _e($option->label, 'best-connect-onboarding'); ?>
                    </span>

                </label>

            <?php endforeach; ?>
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
     * @return true|\WP_Error
     */
    public function validate($value)
    {
        $validation = parent::validate($value);

        if (is_wp_error($validation)) {
            return $validation;
        }

        if ($this->is_required() && empty($value)) {
            return new \WP_Error(
                'required_checkbox',
                sprintf(
                    __('Please select at least one option for %s.', 'best-connect-onboarding'),
                    $this->get_label()
                )
            );
        }

        return true;
    }
}
