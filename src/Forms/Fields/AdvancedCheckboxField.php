<?php

/**
 * Advanced Checkbox Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

use BestConnectOnboarding\Forms\FieldBase;

defined('ABSPATH') || exit;

class AdvancedCheckboxField extends FieldBase
{
    /**
     * Get field type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'advanced_checkbox';
    }

    /**
     * Get field title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Advanced Checkbox', 'best-connect-onboarding');
    }

    /**
     * Get icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-checkbox-multiple-line';
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

            <label><?php esc_html_e('Card Options', 'best-connect-onboarding'); ?></label>

            <div
                class="bcob-advanced-options-builder"
                data-field="options">
            </div>

            <p class="description">
                <?php esc_html_e('Each option supports Title, Description, Value, Icon and Image.', 'best-connect-onboarding'); ?>
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
        if (! empty($field->options)) : ?>
            <div class="bcob-checkbox-cards">
                <?php foreach ($field->options as $i => $option) :
                    $default = $field->default ? explode(',', $field->default) : [];
                ?>
                    <label class="bcob-checkbox-card" <?php echo in_array(esc_attr($option->heading), $default) ? 'style="pointer-events:none"' : '' ?>>
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr($field->id); ?>[]"
                            value="<?php echo esc_attr($option->heading); ?>"
                            data-value="<?php echo esc_attr($option->heading) ?>"
                            <?php echo in_array(esc_attr($option->heading), $default) ? 'checked' : '' ?>>

                        <span class="bcob-checkbox-card__box">

                            <span class="bcob-checkbox-card__icon">
                                <?php if (! empty($option->image)) : ?>

                                    <?php
                                    $extension = strtolower(pathinfo(parse_url($option->image, PHP_URL_PATH), PATHINFO_EXTENSION));

                                    if ($extension === 'svg') {

                                        $upload_dir = wp_get_upload_dir();

                                        if (strpos($option->image, $upload_dir['baseurl']) === 0) {
                                            $svg_file = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $option->image);

                                            if (file_exists($svg_file)) {
                                                echo file_get_contents($svg_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                                            }
                                        }
                                    } else { ?>

                                        <img src="<?php echo esc_url($option->image); ?>" alt="">

                                    <?php } ?>

                                <?php else : ?>

                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z" />
                                    </svg>

                                <?php endif; ?>
                            </span>

                            <span class="bcob-checkbox-card__check">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M5 12L10 17L19 8" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>

                            <span class="bcob-checkbox-card__content">
                                <span class="bcob-checkbox-card__title">
                                    <?php echo esc_html($option->heading); ?>
                                </span>

                                <?php if (! empty($option->description)) : ?>
                                    <span class="bcob-checkbox-card__description">
                                        <?php echo esc_html($option->description); ?>
                                    </span>
                                <?php endif; ?>
                            </span>

                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
<?php endif;
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
                'options' => [
                    [
                        'title'       => __('Option One', 'best-connect-onboarding'),
                        'description' => __('Description for option one.', 'best-connect-onboarding'),
                        'value'       => 'option_one',
                        'icon'        => 'ri-star-line',
                        'image'       => '',
                    ],
                    [
                        'title'       => __('Option Two', 'best-connect-onboarding'),
                        'description' => __('Description for option two.', 'best-connect-onboarding'),
                        'value'       => 'option_two',
                        'icon'        => 'ri-heart-line',
                        'image'       => '',
                    ],
                ],
            ]
        );
    }

    /**
     * Validate field.
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

        if ($this->is_required() && empty($value)) {
            return new \WP_Error(
                'required',
                sprintf(
                    __('Please select at least one %s.', 'best-connect-onboarding'),
                    $this->get_label()
                )
            );
        }

        return true;
    }
}
