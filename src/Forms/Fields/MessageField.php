<?php

/**
 * Message Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

use BestConnectOnboarding\Forms\FieldBase;

defined('ABSPATH') || exit;

class MessageField extends FieldBase
{
    /**
     * Field type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'message';
    }

    /**
     * Field title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Message', 'best-connect-onboarding');
    }

    /**
     * Field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-text-wrap';
    }

    /**
     * Field specific settings.
     *
     * @return void
     */
    protected function render_field_settings(): void
    {
?>

        <div class="bcob-grid bcob-grid-2">

            <div class="bcob-form-group">

                <label><?php esc_html_e('Rows', 'best-connect-onboarding'); ?></label>

                <input
                    type="number"
                    min="2"
                    data-field="rows"
                    value="<?php echo esc_attr($this->get('rows', 5)); ?>">

            </div>

            <div class="bcob-form-group">

                <label><?php esc_html_e('Maximum Length', 'best-connect-onboarding'); ?></label>

                <input
                    type="number"
                    min="1"
                    data-field="maxlength"
                    value="<?php echo esc_attr($this->get('maxlength')); ?>">

            </div>

        </div>

    <?php
    }

    /**
     * Frontend renderer.
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

        $rows =
            !empty($field->rows)
            ? max(
                2,
                absint($field->rows)
            )
            : 5;

        $maxlength =
            !empty($field->maxlength)
            ? absint($field->maxlength)
            : 0;
    ?>

        <div
            class="bcob-field bcob-field-message bcob-col-<?php echo esc_attr(
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

            <textarea
                id="<?php echo esc_attr($input_id); ?>"
                name="<?php echo esc_attr($field->id); ?>"
                class="<?php echo esc_attr($field->class ?? ''); ?>"
                rows="<?php echo esc_attr($rows); ?>"
                placeholder="<?php echo esc_attr($field->placeholder ?? ''); ?>"
                <?php echo $maxlength > 0
                    ? 'maxlength="' . esc_attr($maxlength) . '"'
                    : ''; ?>
                <?php echo !empty($field->required) ? 'required' : ''; ?>><?php
                                                                            echo esc_textarea(
                                                                                $field->default ?? ''
                                                                            );
                                                                            ?></textarea>

        </div>

<?php
    }

    /**
     * Default schema.
     *
     * @return array
     */
    public function get_defaults(): array
    {
        return array_merge(
            parent::get_defaults(),
            [
                'rows'      => 5,
                'maxlength' => '',
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

        $value = (string) $value;

        if (
            $this->get('maxlength') &&
            strlen($value) > (int) $this->get('maxlength')
        ) {
            return new \WP_Error(
                'maxlength',
                sprintf(
                    __('%s exceeds the maximum allowed length.', 'best-connect-onboarding'),
                    $this->get_label()
                )
            );
        }

        return true;
    }
}
