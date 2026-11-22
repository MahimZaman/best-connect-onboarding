<?php

/**
 * Radio Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

use BestConnectOnboarding\Forms\FieldBase;

defined('ABSPATH') || exit;

class RadioField extends FieldBase
{
    /**
     * Get field type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'radio';
    }

    /**
     * Get field title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Radio', 'best-connect-onboarding');
    }

    /**
     * Get field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-radio-button-line';
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
    ?>
        <label for="" class="bcob-field-label">
            <?php _e($field->label, 'best-connnect-onboarding'); ?>
        </label>
        <div class="bcob-radio-group">

            <?php foreach ($field->options as $i => $option) : ?>

                <label class="bcob-radio-card">

                    <input
                        type="radio"
                        name="<?php echo esc_attr($field->id); ?>"
                        value="<?php echo esc_attr($option->value); ?>"
                        <?php checked($field->default, $option->value); ?>>

                    <span class="bcob-radio-card__box">

                        <span class="bcob-radio-card__circle">
                            <span class="bcob-radio-card__dot"></span>
                        </span>

                        <span class="bcob-radio-card__label">
                            <?php echo esc_html($option->label); ?>
                        </span>

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

        if ($this->is_required() && empty($value)) {
            return new \WP_Error(
                'required_radio',
                sprintf(
                    __('Please select an option for %s.', 'best-connect-onboarding'),
                    $this->get_label()
                )
            );
        }

        $allowed = wp_list_pluck((array) $this->get('options', []), 'value');

        if ($value !== '' && !in_array($value, $allowed, true)) {
            return new \WP_Error(
                'invalid_option',
                __('Invalid option selected.', 'best-connect-onboarding')
            );
        }

        return true;
    }
}
