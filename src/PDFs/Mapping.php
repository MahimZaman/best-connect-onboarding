<?php

declare(strict_types=1);

namespace BestConnectOnboarding\PDFs;

if (! defined('ABSPATH')) {
    exit;
}

use Dskripchenko\PhpPdf\Pdf\Reader\PdfDictionary;
use Dskripchenko\PhpPdf\Pdf\Reader\PdfName;
use Dskripchenko\PhpPdf\Pdf\Reader\PdfReference;
use Dskripchenko\PhpPdf\Pdf\Reader\PdfString;
use Dskripchenko\PhpPdf\Pdf\Reader\ReaderDocument;
use BestConnectOnboarding\PDFs\Normalizer;
use BestConnectOnboarding\Helpers\Logger;

class Mapping
{
    /**
     * Initialize the PDF mapping feature.
     */
    public function init(): void
    {
        add_action(
            'admin_enqueue_scripts',
            [$this, 'enqueue_assets']
        );

        add_action(
            'wp_ajax_bcob_get_pdf_acroform_fields',
            [$this, 'ajax_get_pdf_acroform_fields']
        );

        add_action(
            'wp_ajax_bcob_normalize_contract_pdf',
            [$this, 'ajax_normalize_contract_pdf']
        );
    }

    /**
     * Normalize a contract PDF attachment so that
     * the contract uses a classic xref table.
     */
    public function ajax_normalize_contract_pdf(): void
    {
        check_ajax_referer(
            'bcob_pdf_mapping',
            'nonce'
        );

        if (
            !current_user_can(
                'manage_options'
            )
        ) {
            wp_send_json_error(
                __(
                    'You do not have permission to normalize contract PDFs.',
                    'best-connect-onboarding'
                ),
                403
            );
        }

        $pdf_id =
            isset($_POST['pdf_id'])
            ? absint($_POST['pdf_id'])
            : 0;

        if (!$pdf_id) {
            wp_send_json_error(
                __(
                    'Invalid PDF attachment.',
                    'best-connect-onboarding'
                )
            );
        }

        try {

            $normalized_id =
                Normalizer::normalize_attachment(
                    $pdf_id
                );

            $url =
                wp_get_attachment_url(
                    $normalized_id
                );

            if (!$url) {
                throw new \RuntimeException(
                    'Unable to get normalized PDF URL.'
                );
            }

            wp_send_json_success(
                [
                    'id' =>
                    $normalized_id,

                    'url' =>
                    $url,

                    'title' =>
                    get_the_title(
                        $normalized_id
                    ),

                    'source_id' =>
                    $pdf_id,
                ]
            );
        } catch (\Throwable $e) {

            Logger::error(
                'pdf',
                'PDF normalization failed.',
                [
                    'operation' =>
                    'normalize_pdf',

                    'attachment_id' =>
                    $pdf_id,
                ]
            );

            wp_send_json_error(
                $e->getMessage()
            );
        }
    }

    /**
     * Enqueue all assets required by this feature.
     */
    public function enqueue_assets($hook): void
    {
        if (!is_admin()) {
            return;
        }

        $page =
            sanitize_key(
                wp_unslash(
                    $_GET['page'] ?? ''
                )
            );

        if (
            $page !==
            'bcob-form-edit'
        ) {
            return;
        }

        wp_enqueue_style(
            'bcob-pdf-mapping',
            BCOB_URL . 'assets/css/pdf-mapping.css',
            [],
            BCOB_VER
        );

        /*
         * PDF.js is owned by the PDF Mapping feature.
         *
         * If another part of the plugin already enqueues this same handle,
         * WordPress will not enqueue it twice.
         */
        wp_register_script(
            'bcob-pdfjs-loader',
            BCOB_URL .
                'assets/js/vendor/pdfjs-loader.js',
            [],
            BCOB_VER,
            true
        );

        wp_localize_script(
            'bcob-pdfjs-loader',
            'BCOBPdfJs',
            [
                'module_url' =>
                BCOB_URL .
                    'assets/js/vendor/pdfjs/pdf.min.mjs',

                'worker_url' =>
                BCOB_URL .
                    'assets/js/vendor/pdfjs/pdf.worker.min.mjs',

                'version' =>
                '6.3.289',
            ]
        );

        wp_enqueue_script(
            'bcob-pdfjs-loader'
        );

        wp_enqueue_script(
            'bcob-pdf-mapping',
            BCOB_URL . 'assets/js/admin/pdf-mapping.js',
            [
                'jquery',
                'bcob-pdfjs-loader',
            ],
            BCOB_VER,
            true
        );

        wp_localize_script(
            'bcob-pdf-mapping',
            'bcobPdfMapping',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('bcob_pdf_mapping'),

                'i18n' => [
                    'loadingPdf'       => __('Loading PDF...', 'best-connect-onboarding'),
                    'readingFields'    => __('Reading PDF fields...', 'best-connect-onboarding'),
                    'noFields'         => __('No AcroForm fields found.', 'best-connect-onboarding'),
                    'mapped'            => __('mapped', 'best-connect-onboarding'),
                    'fields'            => __('fields', 'best-connect-onboarding'),
                    'field'             => __('field', 'best-connect-onboarding'),
                    'doNotMap'          => __('Do not map', 'best-connect-onboarding'),
                    'loadingPreview'    => __('Loading PDF preview...', 'best-connect-onboarding'),
                    'unableToLoad'     => __('Unable to load PDF.', 'best-connect-onboarding'),
                ],
            ]
        );
    }

    /**
     * Render the complete PDF mapping modal.
     */
    public function render(): void
    {
?>
        <div
            id="bcob-pdf-mapping-modal"
            class="bcob-pdf-mapping-modal"
            hidden>

            <div class="bcob-pdf-mapping-overlay"></div>

            <div
                class="bcob-pdf-mapping-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="bcob-pdf-mapping-title">

                <div class="bcob-pdf-mapping-header">

                    <div class="bcob-pdf-mapping-header-content">

                        <div class="bcob-pdf-mapping-icon">
                            <i class="ri-links-line"></i>
                        </div>

                        <div>

                            <h2 id="bcob-pdf-mapping-title">
                                <?php
                                esc_html_e(
                                    'Contract Field Mapping',
                                    'best-connect-onboarding'
                                );
                                ?>
                            </h2>

                            <p class="bcob-pdf-mapping-contract-name"></p>

                        </div>

                    </div>

                    <button
                        type="button"
                        class="bcob-pdf-mapping-close"
                        aria-label="<?php esc_attr_e('Close', 'best-connect-onboarding'); ?>">

                        <i class="ri-close-line"></i>

                    </button>

                </div>

                <div class="bcob-pdf-mapping-toolbar">

                    <div class="bcob-pdf-mapping-toolbar-left">

                        <span class="bcob-pdf-mapping-status">
                            <i class="ri-loader-4-line ri-spin"></i>

                            <?php
                            esc_html_e(
                                'Loading PDF...',
                                'best-connect-onboarding'
                            );
                            ?>
                        </span>

                    </div>

                    <div class="bcob-pdf-mapping-toolbar-right">

                        <button
                            type="button"
                            class="button bcob-pdf-mapping-zoom-out"
                            title="<?php esc_attr_e('Zoom out', 'best-connect-onboarding'); ?>">
                            <i class="ri-zoom-out-line"></i>
                        </button>

                        <span class="bcob-pdf-mapping-zoom-value">
                            100%
                        </span>

                        <button
                            type="button"
                            class="button bcob-pdf-mapping-zoom-in"
                            title="<?php esc_attr_e('Zoom in', 'best-connect-onboarding'); ?>">
                            <i class="ri-zoom-in-line"></i>
                        </button>

                        <span class="bcob-pdf-mapping-toolbar-divider"></span>

                        <button
                            type="button"
                            class="button bcob-pdf-mapping-prev-page"
                            title="<?php esc_attr_e('Previous page', 'best-connect-onboarding'); ?>">
                            <i class="ri-arrow-left-s-line"></i>
                        </button>

                        <span class="bcob-pdf-mapping-page">
                            1 / 1
                        </span>

                        <button
                            type="button"
                            class="button bcob-pdf-mapping-next-page"
                            title="<?php esc_attr_e('Next page', 'best-connect-onboarding'); ?>">
                            <i class="ri-arrow-right-s-line"></i>
                        </button>

                    </div>

                </div>

                <div class="bcob-pdf-mapping-body">

                    <div class="bcob-pdf-mapping-preview">

                        <div class="bcob-pdf-mapping-canvas-wrap">

                            <div class="bcob-pdf-mapping-pdf-loader">

                                <i class="ri-loader-4-line ri-spin"></i>

                                <span>
                                    <?php
                                    esc_html_e(
                                        'Loading PDF preview...',
                                        'best-connect-onboarding'
                                    );
                                    ?>
                                </span>

                            </div>

                            <canvas
                                id="bcob-pdf-mapping-canvas">
                            </canvas>

                        </div>

                    </div>

                    <aside class="bcob-pdf-mapping-sidebar">

                        <div class="bcob-pdf-mapping-sidebar-header">

                            <div>

                                <h3>
                                    <?php
                                    esc_html_e(
                                        'AcroForm Fields',
                                        'best-connect-onboarding'
                                    );
                                    ?>
                                </h3>

                                <p>
                                    <?php
                                    esc_html_e(
                                        'Map each PDF field to a field from this form.',
                                        'best-connect-onboarding'
                                    );
                                    ?>
                                </p>

                            </div>

                            <span class="bcob-pdf-mapping-field-count">
                                0
                            </span>

                        </div>

                        <div class="bcob-pdf-mapping-fields"></div>

                    </aside>

                </div>

                <div class="bcob-pdf-mapping-footer">

                    <div class="bcob-pdf-mapping-summary">

                        <?php
                        esc_html_e(
                            'No fields mapped.',
                            'best-connect-onboarding'
                        );
                        ?>

                    </div>

                    <div class="bcob-pdf-mapping-footer-actions">

                        <button
                            type="button"
                            class="button bcob-pdf-mapping-cancel">

                            <?php
                            esc_html_e(
                                'Cancel',
                                'best-connect-onboarding'
                            );
                            ?>

                        </button>

                        <button
                            type="button"
                            class="button button-primary bcob-pdf-mapping-save">

                            <i class="ri-save-line"></i>

                            <?php
                            esc_html_e(
                                'Save Mapping',
                                'best-connect-onboarding'
                            );
                            ?>

                        </button>

                    </div>

                </div>

            </div>

        </div>
<?php
    }

    /**
     * AJAX handler for reading AcroForm fields.
     *
     * Uses dskripchenko/php-pdf 1.9.3 directly.
     *
     * This is intentionally NOT using plusforta/pdf-forms because that
     * parser does not support compressed xref streams.
     */
    public function ajax_get_pdf_acroform_fields(): void
    {
        check_ajax_referer(
            'bcob_pdf_mapping',
            'nonce'
        );

        if (! current_user_can('manage_options')) {
            wp_send_json_error(
                __(
                    'You do not have permission to inspect PDF fields.',
                    'best-connect-onboarding'
                ),
                403
            );
        }

        $pdf_id = isset($_POST['pdf_id'])
            ? absint($_POST['pdf_id'])
            : 0;

        if (! $pdf_id) {
            wp_send_json_error(
                __(
                    'Invalid PDF.',
                    'best-connect-onboarding'
                )
            );
        }

        if (
            get_post_type($pdf_id) !== 'attachment'
            || get_post_mime_type($pdf_id) !== 'application/pdf'
        ) {
            wp_send_json_error(
                __(
                    'The selected file is not a valid PDF attachment.',
                    'best-connect-onboarding'
                )
            );
        }

        $file_path = get_attached_file($pdf_id);

        if (
            ! $file_path
            || ! is_string($file_path)
            || ! is_readable($file_path)
        ) {
            wp_send_json_error(
                __(
                    'The PDF file could not be read.',
                    'best-connect-onboarding'
                )
            );
        }

        $bytes = file_get_contents($file_path);

        if ($bytes === false || $bytes === '') {
            wp_send_json_error(
                __(
                    'The PDF file is empty or could not be loaded.',
                    'best-connect-onboarding'
                )
            );
        }

        try {

            /*
             * php-pdf 1.9.3 explicitly supports:
             *
             * - classic xref tables
             * - xref streams
             * - object streams
             * - corrupt xref recovery
             *
             * ReaderDocument::fromBytes() handles that internally.
             */
            $document = ReaderDocument::fromBytes($bytes);

            $fields = $this->extract_acroform_fields(
                $document
            );

            wp_send_json_success(
                [
                    'url'    => wp_get_attachment_url($pdf_id),
                    'title'  => get_the_title($pdf_id),
                    'fields' => $fields,
                ]
            );
        } catch (\Throwable $e) {

            Logger::error(
                'pdf',
                'PDF field inspection failed.',
                [
                    'operation' =>
                    'inspect_pdf_fields',

                    'attachment_id' =>
                    $pdf_id,
                ]
            );

            wp_send_json_error(
                sprintf(
                    __(
                        'Unable to read the PDF AcroForm fields: %s',
                        'best-connect-onboarding'
                    ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Extract all terminal AcroForm fields from the PDF field tree.
     *
     * AcroForm fields are stored under:
     *
     * Catalog
     *   -> AcroForm
     *      -> Fields
     *         -> field tree
     *
     * We recursively walk that tree and inherit:
     *
     * - /T
     * - /FT
     * - /Ff
     *
     * from parent nodes.
     *
     * @return array<int,array<string,mixed>>
     */
    private function extract_acroform_fields(
        ReaderDocument $document
    ): array {

        $catalog = $document->catalog();

        $acro_form = $document->deref(
            $catalog->get('AcroForm')
        );

        if (! $acro_form instanceof PdfDictionary) {
            return [];
        }

        $fields_value = $document->deref(
            $acro_form->get('Fields')
        );

        if (! is_array($fields_value)) {
            return [];
        }

        $result = [];

        foreach ($fields_value as $field_reference) {

            $field = $document->deref(
                $field_reference
            );

            if (! $field instanceof PdfDictionary) {
                continue;
            }

            $this->walk_field_tree(
                $document,
                $field,
                '',
                null,
                null,
                $result,
                []
            );
        }

        return $this->deduplicate_fields($result);
    }

    /**
     * Recursively walk an AcroForm field tree.
     *
     * @param array<int,array<string,mixed>> $result
     * @param array<int,int>                 $visited
     */
    private function walk_field_tree(
        ReaderDocument $document,
        PdfDictionary $field,
        string $parent_name,
        ?string $parent_type,
        ?int $parent_flags,
        array &$result,
        array $visited
    ): void {

        /*
         * Protect against malformed/cyclic PDF field trees.
         *
         * We cannot use spl_object_id() because the reader may return
         * immutable value objects for the same PDF object.
         *
         * Use object reference number when available.
         */
        $field_id = null;

        /*
         * A /T field can itself be a reference, but we resolve it below.
         * The visited guard is therefore best-effort here.
         */

        $name = $this->pdf_string(
            $document->deref(
                $field->get('T')
            )
        );

        $type = $this->pdf_name(
            $document->deref(
                $field->get('FT')
            )
        );

        $flags_value = $document->deref(
            $field->get('Ff')
        );

        $flags = is_int($flags_value)
            ? $flags_value
            : (
                is_numeric($flags_value)
                ? (int) $flags_value
                : null
            );

        if ($type === null) {
            $type = $parent_type;
        }

        if ($flags === null) {
            $flags = $parent_flags;
        }

        $full_name = $parent_name;

        if ($name !== null && $name !== '') {

            $full_name = $parent_name !== ''
                ? $parent_name . '.' . $name
                : $name;
        }

        /*
         * Children can be either:
         *
         * - field nodes
         * - widget annotations
         * - indirect references to either
         */
        $kids = $document->deref(
            $field->get('Kids')
        );

        $has_kids =
            is_array($kids)
            && ! empty($kids);

        /*
         * A terminal field has an actual field type and no child fields,
         * or it is a widget that carries the terminal field properties.
         */
        if (
            $type !== null
            && (
                ! $has_kids
                || $this->is_terminal_widget($document, $field)
            )
        ) {

            $field_data =
                $this->build_field_data(
                    $document,
                    $field,
                    $full_name,
                    $type,
                    $flags
                );

            if ($field_data !== null) {
                $result[] = $field_data;
            }
        }

        if (! $has_kids) {
            return;
        }

        foreach ($kids as $kid_reference) {

            $kid = $document->deref(
                $kid_reference
            );

            if (! $kid instanceof PdfDictionary) {
                continue;
            }

            /*
             * Some PDFs have a field node with /Kids that are widget
             * annotations. If the child is merely a widget and doesn't
             * define another field name/type, don't create a duplicate
             * logical field.
             */
            $kid_type = $this->pdf_name(
                $document->deref(
                    $kid->get('FT')
                )
            );

            $kid_name = $this->pdf_string(
                $document->deref(
                    $kid->get('T')
                )
            );

            $kid_subtype = $this->pdf_name(
                $document->deref(
                    $kid->get('Subtype')
                )
            );

            if (
                $kid_subtype === 'Widget'
                && $kid_type === null
                && $kid_name === null
            ) {
                continue;
            }

            $this->walk_field_tree(
                $document,
                $kid,
                $full_name,
                $type,
                $flags,
                $result,
                $visited
            );
        }
    }

    /**
     * Determine whether a dictionary is a terminal widget.
     */
    private function is_terminal_widget(
        ReaderDocument $document,
        PdfDictionary $field
    ): bool {

        $subtype = $this->pdf_name(
            $document->deref(
                $field->get('Subtype')
            )
        );

        return $subtype === 'Widget';
    }

    private function build_field_data(
        ReaderDocument $document,
        PdfDictionary $field,
        string $name,
        string $type,
        ?int $flags
    ): ?array {

        if ($name === '') {
            return null;
        }

        $normalized_type = $this->normalize_field_type(
            $type,
            $flags
        );

        $value = $this->extract_field_value(
            $document,
            $field
        );

        $options = $this->extract_field_options(
            $document,
            $field,
            $normalized_type
        );

        $data = [
            'name'    => $name,
            'type'    => $normalized_type,
            'value'   => $value,
            'options' => $options,
        ];

        if ($normalized_type === 'checkbox') {
            $data['on_value'] = $this->get_checkbox_on_value($options);
            $data['off_value'] = $this->get_checkbox_off_value($options);
        }

        return $data;
    }

    /**
     * Normalize PDF /FT + /Ff into a frontend-friendly type.
     */
    private function normalize_field_type(
        string $type,
        ?int $flags
    ): string {

        switch ($type) {

            case 'Tx':
                return 'text';

            case 'Btn':

                $flags = $flags ?? 0;

                /*
                 * PDF button flags:
                 *
                 * 32768  = Radio
                 * 65536  = Pushbutton
                 */
                if (($flags & 65536) !== 0) {
                    return 'push';
                }

                if (($flags & 32768) !== 0) {
                    return 'radio';
                }

                return 'checkbox';

            case 'Ch':

                $flags = $flags ?? 0;

                /*
                 * 131072 = Combo
                 */
                if (($flags & 131072) !== 0) {
                    return 'dropdown';
                }

                return 'list';

            case 'Sig':
                return 'signature';

            default:
                return strtolower($type);
        }
    }

    /**
     * Extract the current field value.
     */
    private function extract_field_value(
        ReaderDocument $document,
        PdfDictionary $field
    ): string {

        $value = $document->deref(
            $field->get('V')
        );

        return $this->pdf_scalar_string(
            $value
        );
    }

    /**
     * Extract choice/button options.
     *
     * For dropdown/list fields we inspect /Opt.
     *
     * For checkbox/radio fields we additionally inspect widget
     * appearance-state names from /AP /N.
     *
     * @return array<int,string>
     */
    private function extract_field_options(
        ReaderDocument $document,
        PdfDictionary $field,
        string $type
    ): array {

        $options = [];

        /*
         * Choice field options.
         */
        if (
            $type === 'dropdown'
            || $type === 'list'
        ) {

            $opt = $document->deref(
                $field->get('Opt')
            );

            if (is_array($opt)) {

                foreach ($opt as $option) {

                    $option = $document->deref(
                        $option
                    );

                    if ($option instanceof PdfString) {
                        $options[] = $option->bytes;
                        continue;
                    }

                    if (is_array($option)) {

                        $display =
                            isset($option[0])
                            ? $this->pdf_scalar_string(
                                $document->deref(
                                    $option[0]
                                )
                            )
                            : '';

                        $export =
                            isset($option[1])
                            ? $this->pdf_scalar_string(
                                $document->deref(
                                    $option[1]
                                )
                            )
                            : '';

                        if ($export !== '') {
                            $options[] = $export;
                        } elseif ($display !== '') {
                            $options[] = $display;
                        }
                    }
                }
            }
        }

        /*
         * Button field export states.
         */
        if (
            $type === 'checkbox'
            || $type === 'radio'
        ) {

            $this->collect_button_options(
                $document,
                $field,
                $options
            );

            /*
             * Radio/checkbox widgets are often stored in /Kids.
             */
            $kids = $document->deref(
                $field->get('Kids')
            );

            if (is_array($kids)) {

                foreach ($kids as $kid_reference) {

                    $kid = $document->deref(
                        $kid_reference
                    );

                    if (! $kid instanceof PdfDictionary) {
                        continue;
                    }

                    $this->collect_button_options(
                        $document,
                        $kid,
                        $options
                    );
                }
            }
        }

        $options = array_map(
            'strval',
            $options
        );

        $options = array_filter(
            $options,
            static fn($value) => $value !== ''
        );

        return array_values(
            array_unique($options)
        );
    }

    /**
     * Read appearance state names from /AP /N.
     *
     * For a checkbox, this is commonly something such as:
     *
     * /Yes
     * /Off
     */
    private function collect_button_options(
        ReaderDocument $document,
        PdfDictionary $field,
        array &$options
    ): void {

        $appearance =
            $document->deref(
                $field->get('AP')
            );

        if (! $appearance instanceof PdfDictionary) {
            return;
        }

        $normal =
            $document->deref(
                $appearance->get('N')
            );

        if (! $normal instanceof PdfDictionary) {
            return;
        }

        foreach (
            $normal->all()
            as $name => $unused
        ) {

            if (
                is_string($name)
                && $name !== ''
            ) {
                $options[] = $name;
            }
        }
    }

    /**
     * Convert a PDF scalar to a string.
     */
    private function pdf_scalar_string(
        mixed $value
    ): string {

        if ($value instanceof PdfString) {
            return $this->decode_pdf_text(
                $value->bytes
            );
        }

        if ($value instanceof PdfName) {
            return $value->value;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return '';
    }

    /**
     * Extract a PDF Name object as a string.
     */
    private function pdf_name(mixed $value): ?string
    {
        if ($value instanceof PdfName) {
            return $value->value;
        }

        if (is_string($value)) {
            return ltrim($value, '/');
        }

        return null;
    }

    private function get_checkbox_on_value(array $options): string
    {
        foreach ($options as $option) {
            $option = (string) $option;

            if (strcasecmp($option, 'Off') !== 0) {
                return $option;
            }
        }

        return '';
    }

    private function get_checkbox_off_value(array $options): string
    {
        foreach ($options as $option) {
            $option = (string) $option;

            if (strcasecmp($option, 'Off') === 0) {
                return $option;
            }
        }

        return 'Off';
    }

    /**
     * Extract a PDF string value.
     */
    private function pdf_string(
        mixed $value
    ): ?string {

        if ($value instanceof PdfString) {
            return $this->decode_pdf_text(
                $value->bytes
            );
        }

        if ($value instanceof PdfName) {
            return $value->value;
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Decode common PDF UTF-16BE strings.
     *
     * PDF strings may contain a UTF-16BE BOM.
     */
    private function decode_pdf_text(
        string $value
    ): string {

        if (
            strlen($value) >= 2
            && substr($value, 0, 2) === "\xFE\xFF"
        ) {

            $decoded = @mb_convert_encoding(
                substr($value, 2),
                'UTF-8',
                'UTF-16BE'
            );

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Remove duplicate logical fields.
     *
     * Multiple widgets may point to the same field name.
     *
     * @param array<int,array<string,mixed>> $fields
     *
     * @return array<int,array<string,mixed>>
     */
    private function deduplicate_fields(
        array $fields
    ): array {

        $unique = [];

        foreach ($fields as $field) {

            $name =
                isset($field['name'])
                ? (string) $field['name']
                : '';

            if ($name === '') {
                continue;
            }

            if (! isset($unique[$name])) {
                $unique[$name] = $field;
                continue;
            }

            /*
             * Merge options discovered from multiple widgets.
             */
            $existing_options =
                isset($unique[$name]['options'])
                && is_array(
                    $unique[$name]['options']
                )
                ? $unique[$name]['options']
                : [];

            $new_options =
                isset($field['options'])
                && is_array(
                    $field['options']
                )
                ? $field['options']
                : [];

            $unique[$name]['options'] =
                array_values(
                    array_unique(
                        array_merge(
                            $existing_options,
                            $new_options
                        )
                    )
                );

            /*
             * Keep the first meaningful value.
             */
            if (
                empty($unique[$name]['value'])
                && ! empty($field['value'])
            ) {
                $unique[$name]['value'] =
                    $field['value'];
            }
        }

        return array_values($unique);
    }
}
