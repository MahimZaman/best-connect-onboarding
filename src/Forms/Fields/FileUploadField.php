<?php

/**
 * File Upload Field
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Fields;

use BestConnectOnboarding\Forms\FieldBase;

defined('ABSPATH') || exit;

class FileUploadField extends FieldBase
{
    /**
     * Get field type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'file';
    }

    /**
     * Get field title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('File Upload', 'best-connect-onboarding');
    }

    /**
     * Get field icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-upload-cloud-fill';
    }

    /**
     * Render field settings.
     *
     * @return void
     */
    protected function render_field_settings(): void
    {
?>

        <div class="bcob-grid bcob-grid-2">

            <div class="bcob-form-group">

                <label><?php esc_html_e('Allowed File Types', 'best-connect-onboarding'); ?></label>

                <input
                    type="text"
                    data-field="accept"
                    value="<?php echo esc_attr($this->get('accept')); ?>"
                    placeholder=".pdf,.jpg,.jpeg,.png,.doc,.docx">

                <small><?php esc_html_e('Comma separated extensions.', 'best-connect-onboarding'); ?></small>

            </div>

            <div class="bcob-form-group">

                <label><?php esc_html_e('Maximum File Size (MB)', 'best-connect-onboarding'); ?></label>

                <input
                    type="number"
                    min="1"
                    data-field="max_size"
                    value="<?php echo esc_attr($this->get('max_size', 10)); ?>">

            </div>

        </div>
        <div class="bcob-grid bcob-grid-2">
            <div class="bcob-form-group">

                <label>

                    <input
                        type="checkbox"
                        value="1"
                        data-field="multiple"
                        <?php checked($this->get('multiple')); ?>>

                    <?php esc_html_e('Allow Multiple Files', 'best-connect-onboarding'); ?>

                </label>

            </div>
            <div class="bcob-form-group">

                <label>

                    <input
                        type="checkbox"
                        value="1"
                        data-field="ai_filler"
                        <?php checked($this->get('ai_filler')); ?>>

                    <?php esc_html_e('Is Ai Auto-Filler?', 'best-connect-onboarding'); ?>

                </label>

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
        $accept = !empty($field->accept) ? $field->accept : '';
        $max_size = !empty($field->max_size) ? (int) $field->max_size : 0;

        $accept_list = array_map('trim', explode(',', $accept));
        $accept_text = implode(', ', $accept_list);

        $input_id = !empty($field->html_id) ? $field->html_id : $field->id;
        $ai_filler = $field->ai_filler ?? 0;
    ?>

        <div class="bcob-field bcob-field-file <?php echo esc_attr($field->class); ?>">
            <label class="bcob-file-upload" for="<?php echo esc_attr($input_id); ?>">
                <div class="bcob-file-upload__icon">
                    <i class="<?php echo esc_attr('ri-upload-cloud-fill'); ?>"></i>
                </div>

                <div class="bcob-file-upload__title">
                    <?php esc_html_e('Dateien hier ablegen oder klicken', 'best-connect-onboarding'); ?>
                </div>

                <div class="bcob-file-upload__meta"
                    data-default="<?php echo esc_attr($accept_text .
                                        ($field->multiple ? ' · ' . __('Mehrere Dateien erlaubt', 'best-connect-onboarding') : '') .
                                        ($max_size ? ' · max. ' . $max_size . ' MB Foto/Scans müssen gut lesbar sein · Ihre Daten werden DSGVO-konform verarbeitet' : '')); ?>">
                    <?php
                    echo esc_html(
                        $accept_text .
                            ($field->multiple ? ' · ' . __('Mehrere Dateien erlaubt', 'best-connect-onboarding') : '') .
                            ($max_size ? ' · max. ' . $max_size . ' MB Foto/Scans müssen gut lesbar sein · Ihre Daten werden DSGVO-konform verarbeitet' : '')
                    );
                    ?>
                </div>

                <input
                    id="<?php echo esc_attr($input_id); ?>"
                    name="<?php echo esc_attr($field->id); ?><?php echo $field->multiple ? '[]' : ''; ?>"
                    type="file"
                    data-aiFiller=<?php echo esc_attr($ai_filler); ?>
                    accept="<?php echo esc_attr($accept); ?>"
                    <?php echo $field->multiple ? 'multiple' : ''; ?>
                    <?php echo !empty($field->required) ? 'required' : ''; ?>>
            </label>
            <div class="bcob-upload-list"></div>
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
                'accept'   => '.pdf,.jpg,.jpeg,.png',
                'multiple' => false,
                'ai_filler' => false,
                'max_size' => 10,
            ]
        );
    }

    /**
     * Validate uploaded file.
     *
     * Validation of mime type, upload errors and size should be
     * performed by the submission handler after upload.
     *
     * @param mixed $value Uploaded value.
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
                'required_file',
                sprintf(
                    __('Please upload %s.', 'best-connect-onboarding'),
                    $this->get_label()
                )
            );
        }

        return true;
    }
}
