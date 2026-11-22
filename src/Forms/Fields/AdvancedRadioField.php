<?php

/**
 * Advanced Radio Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

use BestConnectOnboarding\Forms\FieldBase;

defined('ABSPATH') || exit;

class AdvancedRadioField extends FieldBase
{
    /**
     * Get field type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'advanced_radio';
    }

    /**
     * Get field title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Advanced Radio', 'best-connect-onboarding');
    }

    /**
     * Get field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-layout-grid-line';
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
        if (empty($field->options)) {
            return;
        }
    ?>

        <div class="bcob-advanced-radio-cards">

            <?php foreach ($field->options as $i => $option) : ?>

                <label class="bcob-advanced-radio-card">

                    <input
                        type="radio"
                        name="<?php echo esc_attr($field->id); ?>"
                        value="<?php echo esc_attr($option->heading); ?>"
                        <?php checked($i, 0); ?>>

                    <span class="bcob-advanced-radio-card__box">

                        <span class="bcob-advanced-radio-card__icon">

                            <?php if (!empty($option->image)) : ?>

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

                        <span class="bcob-advanced-radio-card__check"></span>

                        <span class="bcob-advanced-radio-card__content">

                            <span class="bcob-advanced-radio-card__title">
                                <?php echo esc_html($option->heading); ?>
                            </span>

                            <?php if (!empty($option->description)) : ?>

                                <span class="bcob-advanced-radio-card__description">
                                    <?php echo esc_html($option->description); ?>
                                </span>

                            <?php endif; ?>

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
                'options' => [
                    [
                        'title'       => __('Option One', 'best-connect-onboarding'),
                        'description' => __('Description for option one.', 'best-connect-onboarding'),
                        'value'       => 'option_one',
                        'icon'        => 'ri-home-line',
                        'image'       => '',
                    ],
                    [
                        'title'       => __('Option Two', 'best-connect-onboarding'),
                        'description' => __('Description for option two.', 'best-connect-onboarding'),
                        'value'       => 'option_two',
                        'icon'        => 'ri-building-line',
                        'image'       => '',
                    ],
                ],
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

        if ($this->is_required() && empty($value)) {
            return new \WP_Error(
                'required',
                sprintf(
                    __('Please select a %s.', 'best-connect-onboarding'),
                    $this->get_label()
                )
            );
        }

        $allowed = wp_list_pluck(
            (array) $this->get('options', []),
            'value'
        );

        if ($value !== '' && !in_array($value, $allowed, true)) {
            return new \WP_Error(
                'invalid_option',
                __('Invalid option selected.', 'best-connect-onboarding')
            );
        }

        return true;
    }
}
