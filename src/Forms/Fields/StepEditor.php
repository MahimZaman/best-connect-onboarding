<?php

/**
 * Text Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

defined('ABSPATH') || exit;

use BestConnectOnboarding\Forms\FieldBase;

class StepEditor extends FieldBase
{

    /**
     * Field type.
     *
     * @return string
     */
    public function get_type(): string
    {

        return 'step-editor';
    }

    /**
     * Field title.
     *
     * @return string
     */
    public function get_title(): string
    {

        return __('Step Editor', 'best-connect-onboarding');
    }

    /**
     * Field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {

        return 'ri-survey-line';
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
                'default'     => 'Step Editor',
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
        return;
    }

    public function supports(string $feature): bool
    {
        return in_array(
            $feature,
            [
                'width',
                'class',
                'html_id'
            ],
            true
        );
    }

    /**
     * Render frontend.
     *
     * @return void
     */
    public function render($field = null): void
    {
        global $bcob_form_schema;
        $steps = $bcob_form_schema['steps'] ?? [];
?>
        <div class="bcob-step-editor">

            <?php foreach ($steps as $step_index => $step) : ?>

                <section class="bcob-step-summary">

                    <div class="bcob-step-summary__header">

                        <h3 class="bcob-step-summary__title">
                            <?php echo esc_html($step['title']); ?>
                        </h3>

                        <button type="button"
                            class="bcob-step-summary__edit"
                            data-step="<?php echo esc_attr($step_index + 1); ?>">
                            Bearbeiten
                        </button>

                    </div>

                    <div class="bcob-step-summary__card">

                        <?php foreach ($step['fields'] as $step_field) : ?>

                            <?php
                            if (in_array($step_field['type'], [
                                'html',
                                'divider',
                                'heading',
                                'paragraph',
                                'payment',
                                'coupon',
                                'step-editor',
                                'order-review',
                                'order-total'
                            ], true)) {
                                continue;
                            }
                            ?>

                            <div
                                class="bcob-step-summary__row"
                                data-field="<?php echo esc_attr($step_field['id']); ?>"
                                data-type="<?php echo esc_attr($step_field['type']); ?>">

                                <div class="bcob-step-summary__label">
                                    <?php echo esc_html($step_field['label']); ?>
                                </div>

                                <div class="bcob-step-summary__value"></div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endforeach; ?>

        </div>
<?php
    }
}
