<?php

/**
 * Base Field Class
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms;

defined('ABSPATH') || exit;

abstract class FieldBase
{

    /**
     * Field data.
     *
     * @var array
     */
    protected array $field = [];

    /**
     * Constructor.
     *
     * @param array $field Field data.
     */
    public function __construct(array $field = [])
    {
        $this->field = wp_parse_args(
            $field,
            $this->get_defaults()
        );
    }

    /**
     * Field type.
     *
     * @return string
     */
    abstract public function get_type(): string;

    /**
     * Field title.
     *
     * @return string
     */
    abstract public function get_title(): string;

    /**
     * Field icon.
     *
     * @return string
     */
    abstract public function get_icon(): string;

    /**
     * Builder settings.
     *
     * @return void
     */
    /**
     * Render builder settings.
     *
     * @return void
     */
    public function render_builder_settings(): void
    {

?>

        <div class="bcob-grid<?php echo $this->supports('placeholder') ? ' bcob-grid-2' : ''; ?>">

            <div class="bcob-form-group">

                <label><?php esc_html_e('Label', 'best-connect-onboarding'); ?></label>

                <input
                    type="text"
                    data-field="label"
                    value="<?php echo esc_attr($this->get('label')); ?>">

            </div>

            <?php if ($this->supports('placeholder')) : ?>

                <div class="bcob-form-group">

                    <label><?php esc_html_e('Placeholder', 'best-connect-onboarding'); ?></label>

                    <input
                        type="text"
                        data-field="placeholder"
                        value="<?php echo esc_attr($this->get('placeholder')); ?>">

                </div>

            <?php endif; ?>

        </div>

        <?php if ($this->supports('description')) : ?>

            <div class="bcob-form-group">

                <label><?php esc_html_e('Description', 'best-connect-onboarding'); ?></label>

                <textarea
                    rows="2"
                    data-field="description"><?php echo esc_textarea($this->get('description')); ?></textarea>

            </div>

        <?php endif; ?>

        <?php if ($this->supports('default')) : ?>

            <div class="bcob-form-group">

                <label><?php esc_html_e('Default Value', 'best-connect-onboarding'); ?></label>

                <input
                    type="text"
                    data-field="default"
                    value="<?php echo esc_attr($this->get('default')); ?>">

            </div>

        <?php endif; ?>

        <?php
        $has_width = $this->supports('width');
        $has_class = $this->supports('class');

        if ($has_width || $has_class) :
        ?>

            <div class="bcob-grid<?php echo ($has_width && $has_class) ? ' bcob-grid-2' : ''; ?>">

                <?php if ($has_width) : ?>

                    <div class="bcob-form-group">

                        <label><?php esc_html_e('Width', 'best-connect-onboarding'); ?></label>

                        <select data-field="width">

                            <option value="100">100%</option>
                            <option value="75">75%</option>
                            <option value="66">66%</option>
                            <option value="50">50%</option>
                            <option value="33">33%</option>
                            <option value="25">25%</option>

                        </select>

                    </div>

                <?php endif; ?>

                <?php if ($has_class) : ?>

                    <div class="bcob-form-group">

                        <label><?php esc_html_e('CSS Class', 'best-connect-onboarding'); ?></label>

                        <input
                            type="text"
                            data-field="class"
                            value="<?php echo esc_attr($this->get('class')); ?>">

                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

        <?php
        $has_html_id = $this->supports('html_id');

        if ($has_html_id) :
        ?>

            <div class="bcob-grid<?php echo $has_html_id ? ' bcob-grid-2' : ''; ?>">

                <?php if ($has_html_id) : ?>

                    <div class="bcob-form-group">

                        <label><?php esc_html_e('HTML ID', 'best-connect-onboarding'); ?></label>

                        <input
                            type="text"
                            data-field="html_id"
                            value="<?php echo esc_attr($this->get('html_id')); ?>">

                    </div>

                <?php endif; ?>


                <div class="bcob-form-group">

                    <label><?php esc_html_e('Field ID', 'best-connect-onboarding'); ?></label>

                    <input
                        type="text"
                        data-field="field_key"
                        readonly
                        value="<?php echo esc_attr($this->get_id()); ?>">

                </div>


            </div>

        <?php endif; ?>

        <?php if ($this->supports('required')) : ?>

            <label class="bcob-checkbox">

                <input
                    type="checkbox"
                    data-field="required"
                    <?php checked($this->is_required()); ?>>

                <span>

                    <?php esc_html_e('Required Field', 'best-connect-onboarding'); ?>

                </span>

            </label>

        <?php endif; ?>

        <?php if (method_exists($this, 'render_field_settings')) : ?>

            <hr>

            <?php $this->render_field_settings(); ?>

        <?php endif; ?>

<?php
    }

    /**
     * Render field specific settings.
     *
     * Child fields should override this.
     *
     * @return void
     */
    protected function render_field_settings(): void {}

    /**
     * Feature support.
     *
     * @param string $feature Feature.
     * @return bool
     */
    public function supports(string $feature): bool
    {

        $supported = [
            'placeholder',
            'default',
            'description',
            'width',
            'class',
            'html_id',
            'required',
        ];

        return in_array(
            $feature,
            $supported,
            true
        );
    }

    /**
     * Frontend field.
     *
     * @return void
     */
    abstract public function render(): void;

    /**
     * Validate field.
     *
     * @param mixed $value Submitted value.
     * @return true|\WP_Error
     */
    public function validate($value)
    {

        if (
            $this->is_required() &&
            '' === trim((string) $value)
        ) {
            return new \WP_Error(
                'required',
                sprintf(
                    __('%s is required.', 'best-connect-onboarding'),
                    $this->get_label()
                )
            );
        }

        return true;
    }

    /**
     * Default schema.
     *
     * @return array
     */
    public function get_defaults(): array
    {

        return [
            'id'          => '',
            'type'        => $this->get_type(),
            'icon'        => $this->get_icon(),
            'label'       => $this->get_title(),
            'description' => '',
            'placeholder' => '',
            'default'     => '',
            'class'       => '',
            'html_id'     => '',
            'width'       => '100',
            'required'    => false,
            'validation'  => [],
            'options'     => [],
            'settings'    => [],
        ];;
    }

    /**
     * Generate immutable ID.
     *
     * @return string
     */
    protected function generate_id(): string
    {

        return 'bcob_field_' . wp_generate_password(
            12,
            false,
            false
        );
    }

    /**
     * Field ID.
     *
     * @return string
     */
    public function get_id(): string
    {

        return $this->field['id'];
    }

    /**
     * Label.
     *
     * @return string
     */
    public function get_label(): string
    {

        return $this->field['label'];
    }

    /**
     * Required.
     *
     * @return bool
     */
    public function is_required(): bool
    {

        return (bool) $this->field['required'];
    }

    /**
     * Data.
     *
     * @return array
     */
    public function get_data(): array
    {

        return $this->field;
    }

    /**
     * Set value.
     *
     * @param string $key Key.
     * @param mixed  $value Value.
     * @return static
     */
    public function set(string $key, $value): static
    {

        if ('id' === $key) {
            return $this;
        }

        $this->field[$key] = $value;

        return $this;
    }

    /**
     * Get value.
     *
     * @param string $key Key.
     * @param mixed  $default Default.
     * @return mixed
     */
    public function get(string $key, $default = null)
    {

        return $this->field[$key] ?? $default;
    }

    /**
     * Schema.
     *
     * @return array
     */
    public function to_array(): array
    {

        return $this->field;
    }

    /**
     * Register field.
     *
     * @param array $fields Registered fields.
     * @return array
     */
    public static function register(array $fields): array
    {

        $fields[] = new static();

        return $fields;
    }
}
