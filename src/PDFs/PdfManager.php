<?php

namespace BestConnectOnboarding\PDFs;

use BestConnectOnboarding\AI\AI;
use BestConnectOnboarding\Leads\Lead;
use PlusForta\PdfForms\PdfForm;

use Dskripchenko\PhpPdf\Pdf\Reader\PdfDictionary;
use Dskripchenko\PhpPdf\Pdf\Reader\PdfName;
use Dskripchenko\PhpPdf\Pdf\Reader\PdfReference;
use Dskripchenko\PhpPdf\Pdf\Reader\PdfString;
use Dskripchenko\PhpPdf\Pdf\Reader\ReaderDocument;
use Dskripchenko\PhpPdf\Pdf\Reader\PdfStream;

use PlusForta\PdfForms\Form\AcroFormReader;
use PlusForta\PdfForms\Form\FieldType;
use PlusForta\PdfForms\Parser\PdfObject as PlusPdfObject;
use PlusForta\PdfForms\Parser\PdfParser;
use PlusForta\PdfForms\Writer\PdfWriter;

use BestConnectOnboarding\Helpers\Logger;

defined('ABSPATH') || exit;

class PdfManager
{
    private const AI_CONFIDENCE_THRESHOLD = 0.65;

    public static function generate_for_submission(
        array $submission,
        int $lead_id
    ): array {
        Logger::info(
            'pdf',
            'Entered PDF contract generator.',
            [
                'operation' =>
                'generate_for_submission',

                'form_id' =>
                absint(
                    $submission['form']['id']
                        ?? 0
                ),

                'lead_id' =>
                $lead_id,
            ]
        );

        $form_id = absint(
            $submission['form']['id'] ?? 0
        );

        if (!$form_id || !$lead_id) {
            Logger::warning(
                'pdf',
                'Contract generation stopped because the form or lead could not be identified.',
                [
                    'operation' =>
                    'generate_for_submission',

                    'form_id' =>
                    $form_id,

                    'lead_id' =>
                    $lead_id,
                ]
            );

            return [];
        }

        $schema = self::get_form_schema($form_id);

        if (!$schema) {
            Logger::warning(
                'pdf',
                'Contract generation stopped because the form schema could not be loaded.',
                [
                    'operation' =>
                    'generate_for_submission',

                    'form_id' =>
                    $form_id,

                    'lead_id' =>
                    $lead_id,
                ]
            );

            return [];
        }

        $contracts = $schema['settings']['contracts'] ?? [];

        Logger::info(
            'pdf',
            'Contract configuration loaded.',
            [
                'operation' =>
                'generate_for_submission',

                'form_id' =>
                $form_id,

                'lead_id' =>
                $lead_id,

                'status' =>
                empty($contracts)
                    ? 'empty'
                    : 'available',
            ]
        );

        if (empty($contracts)) {
            Logger::warning(
                'pdf',
                'Contract generation stopped because no contract configuration exists.',
                [
                    'operation' =>
                    'generate_for_submission',

                    'form_id' =>
                    $form_id,

                    'lead_id' =>
                    $lead_id,
                ]
            );

            return [];
        }

        if (
            isset($contracts['conditions']) &&
            is_array($contracts['conditions'])
        ) {
            $contracts = [$contracts];
        }

        if (!is_array($contracts)) {
            return [];
        }

        $generated = [];

        foreach ($contracts as $contract) {
            if (!is_array($contract)) {
                continue;
            }

            $pdfs = self::get_matching_pdfs(
                $contract,
                $submission
            );

            Logger::info(
                'pdf',
                'Contract condition evaluation completed.',
                [
                    'operation' =>
                    'match_contract',

                    'form_id' =>
                    $form_id,

                    'lead_id' =>
                    $lead_id,

                    'status' =>
                    empty($pdfs)
                        ? 'no_match'
                        : 'matched',
                ]
            );

            if (empty($pdfs)) {
                Logger::warning(
                    'pdf',
                    'No contract PDF matched the configured contract conditions.',
                    [
                        'operation' =>
                        'match_contract',

                        'form_id' =>
                        $form_id,

                        'lead_id' =>
                        $lead_id,
                    ]
                );

                continue;
            }

            foreach ($pdfs as $pdf) {
                $template_id = absint(
                    $pdf['id'] ?? 0
                );

                Logger::info(
                    'pdf',
                    'Matched contract PDF identified.',
                    [
                        'operation' =>
                        'generate_for_submission',

                        'form_id' =>
                        $form_id,

                        'lead_id' =>
                        $lead_id,

                        'attachment_id' =>
                        $template_id,

                        'status' =>
                        $template_id
                            ? 'valid'
                            : 'missing',
                    ]
                );

                if (!$template_id) {
                    continue;
                }

                $result = self::generate_pdf(
                    $template_id,
                    $pdf['title'] ?? '',
                    $submission,
                    $lead_id,
                    $pdf['preview'] ?? true
                );

                Logger::info(
                    'pdf',
                    'Individual PDF generation returned.',
                    [
                        'operation' =>
                        'generate_for_submission',

                        'form_id' =>
                        $form_id,

                        'lead_id' =>
                        $lead_id,

                        'attachment_id' =>
                        $template_id,

                        'status' =>
                        $result
                            ? 'generated'
                            : 'empty',
                    ]
                );

                if ($result) {
                    $generated[] = $result;
                }
            }
        }

        return self::unique_contracts(
            $generated
        );
    }

    public static function generate_for_lead(
        int $lead_id
    ): array {
        if (!$lead_id) {
            return [];
        }

        $lead = Lead::get($lead_id);

        if (!$lead) {
            return [];
        }

        $submission = json_decode(
            $lead->submission ?? '',
            true
        );

        if (
            !is_array($submission) ||
            empty($submission)
        ) {
            return [];
        }

        $contracts =
            self::generate_for_submission(
                $submission,
                $lead_id
            );

        if (!empty($contracts)) {
            $submission['contracts'] =
                $contracts;

            Lead::update_submission(
                $lead_id,
                $submission
            );
        }

        return $contracts;
    }

    public static function regenerate_contract(
        int $lead_id,
        int $template_id
    ): ?array {
        if (!$lead_id || !$template_id) {
            return null;
        }

        $lead = Lead::get($lead_id);

        if (!$lead) {
            return null;
        }

        $submission = json_decode(
            $lead->submission ?? '',
            true
        );

        if (!is_array($submission)) {
            return null;
        }

        $form_id = absint(
            $submission['form']['id'] ?? 0
        );

        if (!$form_id) {
            return null;
        }

        $schema = self::get_form_schema(
            $form_id
        );

        if (!$schema) {
            return null;
        }

        $contracts =
            $schema['settings']['contracts']
            ?? [];

        if (
            isset($contracts['conditions']) &&
            is_array($contracts['conditions'])
        ) {
            $contracts = [$contracts];
        }

        if (!is_array($contracts)) {
            return null;
        }

        foreach ($contracts as $contract) {
            if (!is_array($contract)) {
                continue;
            }

            $pdfs = self::get_matching_pdfs(
                $contract,
                $submission
            );

            foreach ($pdfs as $pdf) {
                if (
                    absint($pdf['id'] ?? 0) !==
                    $template_id
                ) {
                    continue;
                }

                $result = self::generate_pdf(
                    $template_id,
                    $pdf['title'] ?? '',
                    $submission,
                    $lead_id,
                    $pdf['preview'] ?? true
                );

                if (!$result) {
                    return null;
                }

                self::replace_contract(
                    $submission,
                    $result
                );

                Lead::update_submission(
                    $lead_id,
                    $submission
                );

                return $result;
            }
        }

        return null;
    }

    public static function get_contracts_for_lead(
        int $lead_id
    ): array {
        if (!$lead_id) {
            return [];
        }

        $lead = Lead::get($lead_id);

        if (!$lead) {
            return [];
        }

        $submission = json_decode(
            $lead->submission ?? '',
            true
        );

        if (!is_array($submission)) {
            return [];
        }

        $contracts =
            $submission['contracts'] ?? [];

        if (!is_array($contracts)) {
            return [];
        }

        $valid = [];

        foreach ($contracts as $contract) {
            $attachment_id = absint(
                $contract['attachment_id'] ?? 0
            );

            if (!$attachment_id) {
                continue;
            }

            $attachment = get_post(
                $attachment_id
            );

            if (
                !$attachment ||
                'attachment' !==
                $attachment->post_type
            ) {
                continue;
            }

            $url =
                self::get_contract_url(
                    $attachment_id
                );

            if (!$url) {
                continue;
            }

            $contract['url'] = $url;

            $valid[] = $contract;
        }

        return $valid;
    }

    private static function get_contract_link_expiry_hours(): int
    {
        $settings =
            get_option(
                'bcob_settings',
                []
            );

        $hours =
            absint(
                $settings['bcob_contract_link_expiry_hours'] ?? 24
            );

        return in_array(
            $hours,
            [
                1,
                6,
                12,
                24,
                72,
                168,
                720,
            ],
            true
        )
            ? $hours
            : 24;
    }

    public static function get_contract_url(
        int $attachment_id
    ): ?string {
        if (!$attachment_id) {
            return null;
        }

        $lead_id =
            absint(
                get_post_meta(
                    $attachment_id,
                    '_bcob_lead_id',
                    true
                )
            );

        if (!$lead_id) {
            return null;
        }

        $lead =
            Lead::get(
                $lead_id
            );

        if (
            !$lead ||
            empty($lead->token)
        ) {
            return null;
        }

        $expires =
            time() +
            (
                self::get_contract_link_expiry_hours()
                * HOUR_IN_SECONDS
            );

        $access =
            self::get_contract_access_signature(
                $lead_id,
                $attachment_id,
                (string) $lead->token,
                $expires
            );

        return add_query_arg(
            [
                'action' =>
                'bcob_contract_file',

                'lead_id' =>
                $lead_id,

                'attachment_id' =>
                $attachment_id,

                'expires' =>
                $expires,

                'access' =>
                $access,
            ],
            admin_url(
                'admin-ajax.php'
            )
        );
    }

    private static function get_contract_access_signature(
        int $lead_id,
        int $attachment_id,
        string $lead_token,
        int $expires
    ): string {
        return hash_hmac(
            'sha256',
            $lead_id .
                '|' .
                $attachment_id .
                '|' .
                $expires .
                '|' .
                $lead_token,
            wp_salt('auth')
        );
    }

    private static function generate_pdf(
        int $template_id,
        string $title,
        array $submission,
        int $lead_id,
        bool $preview = true
    ): ?array {
        Logger::info(
            'pdf',
            'Entered individual PDF generator.',
            [
                'operation' =>
                'generate_pdf',

                'form_id' =>
                absint(
                    $submission['form']['id']
                        ?? 0
                ),

                'lead_id' =>
                $lead_id,

                'attachment_id' =>
                $template_id,
            ]
        );
        $source = get_attached_file($template_id);

        if (
            !$source ||
            !file_exists($source)
        ) {
            Logger::error(
                'pdf',
                'Contract PDF template file could not be found.',
                [
                    'operation' =>
                    'generate_pdf',

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),

                    'lead_id' =>
                    $lead_id,

                    'attachment_id' =>
                    $template_id,
                ]
            );

            return null;
        }

        $stage = 'starting';

        try {
            $stage = 'inspect_pdf';

            $fields = self::inspect_pdf($source);

            if (empty($fields)) {
                Logger::error(
                    'pdf',
                    'No AcroForm fields were detected in the contract PDF.',
                    [
                        'operation' =>
                        'inspect_pdf',

                        'form_id' =>
                        absint(
                            $submission['form']['id']
                                ?? 0
                        ),

                        'lead_id' =>
                        $lead_id,

                        'attachment_id' =>
                        $template_id,
                    ]
                );

                return null;
            }

            /*
         * Primary mapping source:
         * saved mappings from the form schema.
         *
         * The AI mapper remains available through
         * create_mapping(), but is not used here.
         */
            $stage = 'create_schema_mapping';

            $mapping = self::create_schema_mapping(
                $template_id,
                $fields,
                $submission
            );

            if (empty($mapping)) {
                Logger::error(
                    'pdf',
                    'Saved PDF mappings could not be resolved for the contract.',
                    [
                        'operation' =>
                        'create_schema_mapping',

                        'form_id' =>
                        absint(
                            $submission['form']['id']
                                ?? 0
                        ),

                        'lead_id' =>
                        $lead_id,

                        'attachment_id' =>
                        $template_id,
                    ]
                );
                return null;
            }

            $stage = 'get_destination';

            $destination =
                self::get_destination(
                    $title,
                    $lead_id
                );

            $is_modern =
                self::pdf_uses_xref_streams(
                    $source
                );

            $has_widget_fields = false;

            foreach ($mapping as $map) {
                if (!is_array($map)) {
                    continue;
                }

                $type =
                    strtolower(
                        (string) (
                            $map['type'] ?? ''
                        )
                    );

                if (
                    $type === 'checkbox' ||
                    $type === 'radio'
                ) {
                    $has_widget_fields = true;
                    break;
                }
            }

            Logger::info(
                'pdf',
                'PDF fill strategy selected.',
                [
                    'operation' =>
                    'generate_pdf',

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),

                    'lead_id' =>
                    $lead_id,

                    'attachment_id' =>
                    $template_id,

                    'status' =>
                    $is_modern
                        ? (
                            $has_widget_fields
                            ? 'modern_with_widgets'
                            : 'modern_text_only'
                        )
                        : (
                            $has_widget_fields
                            ? 'legacy_with_widgets'
                            : 'legacy_text_only'
                        ),
                ]
            );

            $stage = 'fill_pdf';

            self::fill_pdf(
                $source,
                $destination,
                $mapping
            );

            $stage = 'create_attachment';

            $attachment_id =
                self::create_attachment(
                    $destination,
                    $template_id,
                    $title,
                    $lead_id
                );

            if (!$attachment_id) {
                Logger::error(
                    'pdf',
                    'Generated PDF could not be registered as a WordPress attachment.',
                    [
                        'operation' =>
                        'create_attachment',

                        'form_id' =>
                        absint(
                            $submission['form']['id']
                                ?? 0
                        ),

                        'lead_id' =>
                        $lead_id,

                        'attachment_id' =>
                        $template_id,
                    ]
                );
                if (file_exists($destination)) {
                    unlink($destination);
                }

                return null;
            }

            return [
                'attachment_id' =>
                $attachment_id,

                'template_id' =>
                $template_id,

                'title' =>
                $title ?:
                    get_the_title(
                        $template_id
                    ),

                'preview' =>
                $preview,

                'generated_at' =>
                current_time('mysql'),
            ];
        } catch (\Throwable $e) {
            Logger::error(
                'pdf',
                'Contract PDF generation failed.',
                [
                    'operation' =>
                    'generate_pdf',

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),

                    'lead_id' =>
                    $lead_id,

                    'attachment_id' =>
                    $template_id,
                    'status' =>
                    $stage,
                ]
            );

            return null;
        }
    }

    private static function inspect_pdf(
        string $pdf
    ): array {
        $form =
            PdfForm::open($pdf);

        $fields =
            $form->getFields();

        if (empty($fields)) {
            return [];
        }

        $result = [];

        foreach (
            $fields as $name => $field
        ) {
            $type = '';

            if (
                isset($field->type) &&
                is_object($field->type) &&
                isset($field->type->value)
            ) {
                $type =
                    (string) $field->type->value;
            } elseif (
                isset($field->type)
            ) {
                $type =
                    (string) $field->type;
            }

            $options = [];

            if (
                isset($field->options) &&
                is_array($field->options)
            ) {
                $options =
                    array_values(
                        array_map(
                            'strval',
                            $field->options
                        )
                    );
            }

            /*
         * For checkboxes, PlusForta may expose
         * the current value but not necessarily
         * the actual appearance state.
         *
         * We keep the existing field information
         * available to the AI.
         */
            $value =
                isset($field->value)
                ? (string) $field->value
                : '';

            $result[] = [
                'name' =>
                (string) $name,

                'type' =>
                $type,

                'value' =>
                $value,

                'options' =>
                $options,
            ];
        }

        return $result;
    }

    private static function create_schema_mapping(
        int $template_id,
        array $pdf_fields,
        array $submission
    ): array {

        $form_id = absint(
            $submission['form']['id'] ?? 0
        );

        if (!$form_id) {
            return [];
        }

        $schema = self::get_form_schema($form_id);

        if (empty($schema)) {
            return [];
        }

        $contracts =
            $schema['settings']['contracts'] ?? [];

        if (!is_array($contracts)) {
            return [];
        }

        /*
     * Support both:
     *
     * contracts = [
     *     'relation' => ...,
     *     'conditions' => [...]
     * ]
     *
     * and:
     *
     * contracts = [
     *     0 => [
     *         'relation' => ...,
     *         'conditions' => [...]
     *     ],
     *     1 => [...]
     * ]
     */

        if (
            isset($contracts['conditions']) &&
            is_array($contracts['conditions'])
        ) {
            $contracts = [$contracts];
        }

        $saved_mappings = [];

        foreach ($contracts as $contract) {

            if (!is_array($contract)) {
                continue;
            }

            $conditions =
                $contract['conditions'] ?? [];

            if (!is_array($conditions)) {
                continue;
            }

            foreach ($conditions as $condition) {

                if (!is_array($condition)) {
                    continue;
                }

                $pdf =
                    $condition['pdf'] ?? [];

                if (!is_array($pdf)) {
                    continue;
                }

                if (
                    absint($pdf['id'] ?? 0) !==
                    $template_id
                ) {
                    continue;
                }

                $mappings =
                    $pdf['mappings'] ?? [];

                if (!is_array($mappings)) {
                    continue;
                }

                foreach ($mappings as $mapping) {

                    if (!is_array($mapping)) {
                        continue;
                    }

                    $pdf_field =
                        (string) (
                            $mapping['pdf_field'] ?? ''
                        );

                    $form_field =
                        (string) (
                            $mapping['form_field'] ?? ''
                        );

                    if (
                        $pdf_field === '' ||
                        $form_field === ''
                    ) {
                        continue;
                    }

                    $saved_mappings[] =
                        $mapping;
                }
            }
        }

        if (empty($saved_mappings)) {
            Logger::warning(
                'pdf',
                'No saved PDF field mapping was found.',
                [
                    'operation' =>
                    'create_schema_mapping',

                    'form_id' =>
                    $form_id,

                    'attachment_id' =>
                    $template_id,
                ]
            );

            return [];
        }

        /*
     * Index actual AcroForm fields.
     */
        $pdf_definitions = [];

        foreach ($pdf_fields as $field) {

            if (
                empty($field['name'])
            ) {
                continue;
            }

            $pdf_definitions[(string) $field['name']] = $field;
        }

        $result = [];

        foreach ($saved_mappings as $mapping) {

            $pdf_field =
                (string) (
                    $mapping['pdf_field'] ?? ''
                );

            if (
                !$pdf_field ||
                !isset(
                    $pdf_definitions[$pdf_field]
                )
            ) {
                continue;
            }

            $definition =
                $pdf_definitions[$pdf_field];

            $pdf_type =
                strtolower(
                    (string) (
                        $mapping['pdf_type']
                        ??
                        $definition['type']
                        ??
                        ''
                    )
                );

            $form_field =
                (string) (
                    $mapping['form_field'] ?? ''
                );

            /*
         * IP / Timing / Token.
         */
            if (
                $form_field ===
                'ip-timing-token'
            ) {

                $result[$pdf_field] = [
                    'type' =>
                    $pdf_type,

                    'value' =>
                    self::get_ip_timing_token_value(
                        $submission
                    ),
                ];

                continue;
            }

            $submitted =
                $submission['values'][$form_field]['value']
                ?? null;

            /*
         * Checkbox.
         *
         * Do NOT convert this to a normal
         * PdfForm fill value.
         */
            if (
                $pdf_type === 'checkbox'
            ) {

                $condition =
                    $mapping['condition']
                    ?? [];

                if (
                    !is_array($condition)
                ) {
                    $condition = [];
                }

                $result[$pdf_field] = [
                    'type' =>
                    'checkbox',

                    'checked' =>
                    self::mapping_condition_matches(
                        $submitted,
                        $condition
                    ),

                    'checked_value' =>
                    (string) (
                        $mapping['checked_value']
                        ?? ''
                    ),

                    'unchecked_value' =>
                    (string) (
                        $mapping['unchecked_value']
                        ?? 'Off'
                    ),
                ];

                continue;
            }

            /*
         * Radio.
         *
         * The mapped form value is converted
         * into the PDF radio export value.
         */
            if (
                $pdf_type === 'radio'
            ) {

                $value_map =
                    $mapping['value_map']
                    ?? [];

                if (
                    !is_array($value_map)
                ) {
                    continue;
                }

                $submitted_value =
                    self::normalize_mapping_value(
                        $submitted
                    );

                foreach (
                    $value_map as
                    $pdf_value => $form_value
                ) {

                    if (
                        $submitted_value ===
                        self::normalize_mapping_value(
                            $form_value
                        )
                    ) {

                        $result[$pdf_field] = [
                            'type' =>
                            'radio',

                            'value' =>
                            (string)
                            $pdf_value,
                        ];

                        break;
                    }
                }

                continue;
            }

            /*
         * Normal text / select / textarea /
         * number / email / etc.
         */
            if (
                $submitted === null
            ) {
                continue;
            }

            if (
                is_array($submitted)
            ) {

                $submitted =
                    array_values(
                        array_map(
                            'strval',
                            $submitted
                        )
                    );
            } else {

                $submitted =
                    (string) $submitted;
            }

            /*
         * Validate PDF option fields only.
         */
            if (
                !empty($definition['options']
                    ?? []) &&
                !self::pdf_value_is_allowed(
                    $submitted,
                    $definition
                )
            ) {
                continue;
            }

            $result[$pdf_field] = [
                'type' =>
                $pdf_type,

                'value' =>
                $submitted,
            ];
        }

        if (empty($result)) {
            Logger::error(
                'pdf',
                'Saved mappings exist but none matched the actual PDF fields or submitted values.',
                [
                    'operation' =>
                    'resolve_pdf_mappings',

                    'form_id' =>
                    $form_id,

                    'attachment_id' =>
                    $template_id,
                ]
            );
        }

        return $result;
    }

    private static function normalize_mapping_value(
        $value
    ): string {
        if (is_array($value)) {
            return implode(
                ',',
                array_map(
                    'strval',
                    $value
                )
            );
        }

        if ($value === null) {
            return '';
        }

        return trim(
            (string) $value
        );
    }

    private static function create_mapping(
        string $pdf,
        array $pdf_fields,
        array $submission
    ): array {
        $submission_fields = [];

        foreach (
            ($submission['values'] ?? [])
            as $field_id => $field
        ) {
            if (!is_array($field)) {
                continue;
            }

            $value =
                $field['value'] ?? null;

            if (
                $value === null ||
                $value === ''
            ) {
                continue;
            }

            if (is_array($value)) {
                $value =
                    array_values(
                        array_unique(
                            array_map(
                                'strval',
                                $value
                            )
                        )
                    );
            } else {
                $value =
                    (string) $value;
            }

            $submission_fields[] = [
                'id' =>
                (string) $field_id,

                'label' =>
                (string) (
                    $field['label'] ?? ''
                ),

                'type' =>
                (string) (
                    $field['type'] ?? ''
                ),

                'value' =>
                $value,
            ];
        }

        if (empty($submission_fields)) {
            return [];
        }

        $ai =
            new AI();

        $response =
            $ai->map_pdf_fields(
                $pdf,
                $pdf_fields,
                $submission_fields
            );

        if (!$response) {
            Logger::error(
                'ai',
                'AI PDF field mapping failed.',
                [
                    'operation' =>
                    'create_pdf_mapping',

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            return [];
        }

        return self::validate_mapping(
            $response,
            $pdf_fields
        );
    }

    private static function validate_mapping(
        array $response,
        array $pdf_fields
    ): array {
        $allowed_fields = [];

        foreach (
            $pdf_fields as $field
        ) {
            if (
                empty($field['name'])
            ) {
                continue;
            }

            $allowed_fields[$field['name']] = $field;
        }

        $mapping = [];

        foreach (
            ($response['fields'] ?? [])
            as $item
        ) {
            if (!is_array($item)) {
                continue;
            }

            $pdf_field =
                (string) (
                    $item['pdf_field'] ?? ''
                );

            $confidence =
                (float) (
                    $item['confidence'] ?? 0
                );

            if (!$pdf_field) {
                continue;
            }

            if (
                !isset(
                    $allowed_fields[$pdf_field]
                )
            ) {
                continue;
            }

            if (
                $confidence <
                self::AI_CONFIDENCE_THRESHOLD
            ) {
                continue;
            }

            if (
                isset(
                    $mapping[$pdf_field]
                )
            ) {
                continue;
            }

            $definition =
                $allowed_fields[$pdf_field];

            $type =
                strtolower(
                    (string) (
                        $definition['type']
                        ?? ''
                    )
                );

            /*
         * Checkbox.
         */
            if (
                'checkbox' === $type
            ) {
                if (
                    !isset(
                        $item['checked']
                    )
                ) {
                    continue;
                }

                if (
                    !is_bool(
                        $item['checked']
                    )
                ) {
                    continue;
                }

                $mapping[$pdf_field] = [
                    'type' =>
                    'checkbox',

                    'checked' =>
                    $item['checked'],

                    'confidence' =>
                    $confidence,
                ];

                continue;
            }

            /*
         * Normal text/selectable field.
         */
            if (
                !array_key_exists(
                    'value',
                    $item
                )
            ) {
                continue;
            }

            $value =
                $item['value'];

            if (
                is_array($value)
            ) {
                $value =
                    array_values(
                        array_map(
                            'strval',
                            $value
                        )
                    );
            } elseif (
                $value !== null
            ) {
                $value =
                    (string) $value;
            }

            /*
         * If the PDF exposes options,
         * AI must return an existing option.
         */
            if (
                !self::pdf_value_is_allowed(
                    $value,
                    $definition
                )
            ) {
                continue;
            }

            $mapping[$pdf_field] = [
                'type' =>
                $type,

                'value' =>
                $value,

                'confidence' =>
                $confidence,
            ];
        }

        return $mapping;
    }

    private static function mapping_condition_matches(
        $actual,
        array $condition
    ): bool {

        $operator =
            (string) (
                $condition['operator'] ?? '='
            );

        $expected =
            $condition['value'] ?? '';

        if (is_array($actual)) {
            $actual =
                array_map(
                    'strval',
                    $actual
                );
        }

        switch ($operator) {

            case '=':
            case '==':
                if (is_array($actual)) {
                    return in_array(
                        (string) $expected,
                        $actual,
                        true
                    );
                }

                return (string) $actual ===
                    (string) $expected;

            case '!=':
                if (is_array($actual)) {
                    return !in_array(
                        (string) $expected,
                        $actual,
                        true
                    );
                }

                return (string) $actual !==
                    (string) $expected;

            case 'contains':
                if (is_array($actual)) {
                    foreach ($actual as $item) {
                        if (
                            strpos(
                                (string) $item,
                                (string) $expected
                            ) !== false
                        ) {
                            return true;
                        }
                    }

                    return false;
                }

                return strpos(
                    (string) $actual,
                    (string) $expected
                ) !== false;

            case 'not_contains':
                return !self::mapping_condition_matches(
                    $actual,
                    [
                        'operator' =>
                        'contains',

                        'value' =>
                        $expected,
                    ]
                );

            case 'truthy':
                if (is_array($actual)) {
                    return !empty($actual);
                }

                return !empty(trim((string) $actual));

            default:
                return false;
        }
    }

    private static function pdf_value_is_allowed(
        $value,
        array $field
    ): bool {
        $options =
            $field['options'] ?? [];

        if (empty($options)) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (
                    !in_array(
                        $item,
                        $options,
                        true
                    )
                ) {
                    return false;
                }
            }

            return true;
        }

        return in_array(
            $value,
            $options,
            true
        );
    }

    private static function get_ip_timing_token_value(
        array $submission
    ): string {

        $ip =
            $_SERVER['REMOTE_ADDR']
            ?? '';

        $timing =
            $submission['timing']
            ?? $submission['meta']['timing']
            ?? '';

        $token =
            $submission['token']
            ?? $submission['meta']['token']
            ?? '';

        return implode(
            ' / ',
            array_filter(
                [
                    (string) $ip,
                    (string) $timing,
                    (string) $token,
                ],
                static function ($value) {
                    return $value !== '';
                }
            )
        );
    }

    private static function pdf_uses_xref_streams(
        string $source
    ): bool {

        $bytes = file_get_contents($source);

        if ($bytes === false) {
            throw new \RuntimeException(
                'Unable to read PDF source.'
            );
        }

        return preg_match(
            '/\/Type\s*\/XRef\b/',
            $bytes
        ) === 1;
    }

    private static function fill_pdf(
        string $source,
        string $destination,
        array $mapping
    ): void {
        $uses_modern_xref =
            self::pdf_uses_xref_streams(
                $source
            );

        if (!$uses_modern_xref) {
            self::fill_pdf_legacy(
                $source,
                $destination,
                $mapping
            );

            return;
        }

        $has_widget_fields = false;

        foreach ($mapping as $map) {
            if (!is_array($map)) {
                continue;
            }

            $type =
                strtolower(
                    (string) (
                        $map['type'] ?? ''
                    )
                );

            if (
                $type === 'checkbox' ||
                $type === 'radio'
            ) {
                $has_widget_fields = true;
                break;
            }
        }

        if (!$has_widget_fields) {
            self::fill_pdf_modern(
                $source,
                $destination,
                $mapping
            );

            return;
        }

        $normalized =
            Normalizer::normalize(
                $source
            );

        try {
            self::fill_pdf_legacy(
                $normalized,
                $destination,
                $mapping
            );
        } finally {
            if (
                $normalized !== $source &&
                file_exists($normalized)
            ) {
                @unlink($normalized);
            }
        }
    }

    private static function fill_pdf_modern(
        string $source,
        string $destination,
        array $mapping
    ): void {

        $bytes = file_get_contents($source);

        if ($bytes === false) {
            throw new \RuntimeException(
                'Unable to read PDF source.'
            );
        }

        /*
     * Confirm that the modern reader can parse
     * this exact PDF.
     */
        $document =
            ReaderDocument::fromBytes(
                $bytes
            );

        if (!$document) {
            throw new \RuntimeException(
                'Unable to read modern PDF.'
            );
        }

        /*
     * The modern reader is used here to safely
     * parse PDFs containing xref streams and
     * object streams.
     *
     * The actual AcroForm mutation is delegated
     * to the modern form implementation.
     */
        $form =
            PdfForm::open($source);

        $fields =
            $form->getFields();

        if (empty($fields)) {
            throw new \RuntimeException(
                'No PDF fields found.'
            );
        }

        $fill = [];

        foreach (
            $mapping as $pdf_field => $map
        ) {

            if (!is_array($map)) {
                continue;
            }

            if (
                !isset(
                    $fields[$pdf_field]
                )
            ) {
                continue;
            }

            $type =
                strtolower(
                    (string) (
                        $map['type'] ?? ''
                    )
                );

            /*
         * Do not send checkbox/radio values
         * through the normal filler.
         *
         * They require widget-state handling.
         */
            if (
                $type === 'checkbox' ||
                $type === 'radio'
            ) {
                continue;
            }

            if (
                !array_key_exists(
                    'value',
                    $map
                )
            ) {
                continue;
            }

            $value =
                $map['value'];

            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $value =
                    implode(
                        ', ',
                        array_map(
                            'strval',
                            $value
                        )
                    );
            }

            $fill[$pdf_field] =
                (string) $value;
        }

        /*
     * If the modern parser can read the PDF
     * but PlusForta cannot write it, do not
     * silently fall through to the legacy
     * parser.
     */
        if (!empty($fill)) {

            $form
                ->fill($fill)
                ->needAppearances(false)
                ->save($destination);
        } else {

            if (
                !copy(
                    $source,
                    $destination
                )
            ) {
                throw new \RuntimeException(
                    'Unable to create modern PDF output.'
                );
            }
        }
    }

    private static function fill_pdf_legacy(
        string $source,
        string $destination,
        array $mapping
    ): void {

        /*
     * First pass:
     * Fill normal fields only.
     *
     * Checkbox and radio fields are deliberately
     * excluded because they require low-level
     * AcroForm widget state manipulation.
     */
        $form =
            PdfForm::open($source);

        $fields =
            $form->getFields();

        if (empty($fields)) {
            throw new \RuntimeException(
                'No PDF fields found.'
            );
        }

        $fill = [];

        foreach (
            $mapping as $pdf_field => $map
        ) {

            if (
                !is_array($map)
            ) {
                continue;
            }

            if (
                !isset(
                    $fields[$pdf_field]
                )
            ) {
                continue;
            }

            $type =
                strtolower(
                    (string) (
                        $map['type'] ?? ''
                    )
                );

            /*
         * Checkbox:
         * handled below with PdfWriter.
         */
            if (
                $type === 'checkbox'
            ) {
                continue;
            }

            /*
         * Radio:
         * handled below with PdfWriter.
         */
            if (
                $type === 'radio'
            ) {
                continue;
            }

            if (
                !array_key_exists(
                    'value',
                    $map
                )
            ) {
                continue;
            }

            $value =
                $map['value'];

            if (
                $value === null
            ) {
                continue;
            }

            if (
                is_array($value)
            ) {
                $value =
                    implode(
                        ', ',
                        array_map(
                            'strval',
                            $value
                        )
                    );
            }

            $fill[$pdf_field] =
                (string) $value;
        }

        /*
     * Create an intermediate PDF.
     */
        $temporary =
            tempnam(
                sys_get_temp_dir(),
                'bcob_pdf_'
            );

        if (
            $temporary === false
        ) {
            throw new \RuntimeException(
                'Unable to create temporary PDF.'
            );
        }

        try {

            if (!empty($fill)) {

                $form
                    ->fill($fill)
                    ->needAppearances(false)
                    ->save($temporary);
            } else {

                if (
                    !copy(
                        $source,
                        $temporary
                    )
                ) {
                    throw new \RuntimeException(
                        'Unable to create temporary PDF.'
                    );
                }
            }

            /*
         * Low-level pass.
         *
         * This is the same parser/writer mechanism
         * already used by your finalize_review_pdf().
         */
            $bytes =
                (string)
                file_get_contents(
                    $temporary
                );

            $parser =
                new PdfParser(
                    $bytes
                );

            $reader =
                new AcroFormReader(
                    $parser
                );

            $pdf_fields =
                $reader->getFields();

            $writer =
                new PdfWriter(
                    $parser->getRawData(),
                    $parser->getTrailer()
                );

            $changed = false;

            foreach (
                $pdf_fields as $name => $field
            ) {

                if (
                    !isset(
                        $mapping[$name]
                    )
                ) {
                    continue;
                }

                $map =
                    $mapping[$name];

                if (
                    !is_array($map)
                ) {
                    continue;
                }

                $type =
                    strtolower(
                        (string) (
                            $map['type'] ?? ''
                        )
                    );

                /*
             * CHECKBOX
             */
                if (
                    $type === 'checkbox'
                ) {

                    self::set_checkbox_state(
                        $parser,
                        $writer,
                        $field,
                        !empty($map['checked'])
                    );

                    $changed = true;

                    continue;
                }

                /*
             * RADIO
             */
                if (
                    $type === 'radio'
                ) {

                    self::set_radio_state(
                        $parser,
                        $writer,
                        $field,
                        (string) (
                            $map['value']
                            ?? ''
                        )
                    );

                    $changed = true;

                    continue;
                }
            }

            /*
         * Write low-level changes.
         */
            if ($changed) {

                $final_bytes =
                    $writer->write();

                if (
                    $final_bytes === ''
                ) {
                    throw new \RuntimeException(
                        'Unable to write low-level PDF changes.'
                    );
                }

                file_put_contents(
                    $temporary,
                    $final_bytes
                );
            }

            /*
         * Final output.
         */
            if (
                !copy(
                    $temporary,
                    $destination
                )
            ) {
                throw new \RuntimeException(
                    'Unable to create final PDF.'
                );
            }

            if (
                !file_exists(
                    $destination
                )
            ) {
                throw new \RuntimeException(
                    'PDF was not created.'
                );
            }
        } finally {

            if (
                file_exists(
                    $temporary
                )
            ) {
                @unlink($temporary);
            }
        }
    }

    private static function set_checkbox_state(
        PdfParser $parser,
        PdfWriter $writer,
        $field,
        bool $checked
    ): void {

        $on_state = '';

        foreach (
            $field->widgetObjectNumbers
            as $widget_object_number
        ) {

            $widget =
                $parser->getObject(
                    $widget_object_number,
                    0
                );

            if (
                $widget === null ||
                !$widget->isDictionary()
            ) {
                continue;
            }

            $ap =
                $widget->get('AP');

            if (
                $ap !== null &&
                $ap->isReference()
            ) {
                $ap =
                    $parser->resolve($ap);
            }

            if (
                $ap === null ||
                !$ap->isDictionary()
            ) {
                continue;
            }

            $normal =
                $ap->get('N');

            if (
                $normal !== null &&
                $normal->isReference()
            ) {
                $normal =
                    $parser->resolve($normal);
            }

            if (
                $normal === null ||
                !$normal->isDictionary()
            ) {
                continue;
            }

            foreach (
                $normal->value()
                as $state_name => $unused
            ) {

                $state =
                    (string) $state_name;

                if (
                    strtolower($state) ===
                    'off'
                ) {
                    continue;
                }

                $on_state =
                    $state;

                break;
            }

            if (
                $on_state !== ''
            ) {
                break;
            }
        }

        if (
            $checked &&
            $on_state === ''
        ) {
            return;
        }

        /*
     * Set field /V.
     */
        $field_object =
            $parser->getObject(
                $field->objectNumber,
                0
            );

        if (
            $field_object !== null &&
            $field_object->isDictionary()
        ) {

            $field_object =
                $field_object->set(
                    'V',
                    PlusPdfObject::name(
                        $checked
                            ? $on_state
                            : 'Off'
                    )
                );

            $writer->setObject(
                $field->objectNumber,
                0,
                $field_object
            );
        }

        /*
     * Set every widget /AS.
     */
        foreach (
            $field->widgetObjectNumbers
            as $widget_object_number
        ) {

            $widget =
                $parser->getObject(
                    $widget_object_number,
                    0
                );

            if (
                $widget === null ||
                !$widget->isDictionary()
            ) {
                continue;
            }

            $widget =
                $widget->set(
                    'AS',
                    PlusPdfObject::name(
                        $checked
                            ? $on_state
                            : 'Off'
                    )
                );

            $writer->setObject(
                $widget_object_number,
                0,
                $widget
            );
        }
    }

    private static function set_radio_state(
        PdfParser $parser,
        PdfWriter $writer,
        $field,
        string $selected_value
    ): void {

        if (
            $selected_value === ''
        ) {
            return;
        }

        $selected_widget =
            null;

        /*
     * Find the radio widget whose
     * appearance state matches the
     * mapped export value.
     */
        foreach (
            $field->widgetObjectNumbers
            as $widget_object_number
        ) {

            $widget =
                $parser->getObject(
                    $widget_object_number,
                    0
                );

            if (
                $widget === null ||
                !$widget->isDictionary()
            ) {
                continue;
            }

            $ap =
                $widget->get('AP');

            if (
                $ap !== null &&
                $ap->isReference()
            ) {
                $ap =
                    $parser->resolve($ap);
            }

            if (
                $ap === null ||
                !$ap->isDictionary()
            ) {
                continue;
            }

            $normal =
                $ap->get('N');

            if (
                $normal !== null &&
                $normal->isReference()
            ) {
                $normal =
                    $parser->resolve($normal);
            }

            if (
                $normal === null ||
                !$normal->isDictionary()
            ) {
                continue;
            }

            foreach (
                $normal->value()
                as $state_name => $unused
            ) {

                $state =
                    (string) $state_name;

                if (
                    strtolower($state) ===
                    'off'
                ) {
                    continue;
                }

                if (
                    $state ===
                    $selected_value
                ) {
                    $selected_widget =
                        $widget_object_number;

                    break 2;
                }
            }
        }

        /*
     * Set each radio widget's /AS.
     */
        foreach (
            $field->widgetObjectNumbers
            as $widget_object_number
        ) {

            $widget =
                $parser->getObject(
                    $widget_object_number,
                    0
                );

            if (
                $widget === null ||
                !$widget->isDictionary()
            ) {
                continue;
            }

            /*
         * Determine this widget's actual
         * non-Off appearance state.
         */
            $on_state = '';

            $ap =
                $widget->get('AP');

            if (
                $ap !== null &&
                $ap->isReference()
            ) {
                $ap =
                    $parser->resolve($ap);
            }

            if (
                $ap !== null &&
                $ap->isDictionary()
            ) {

                $normal =
                    $ap->get('N');

                if (
                    $normal !== null &&
                    $normal->isReference()
                ) {
                    $normal =
                        $parser->resolve($normal);
                }

                if (
                    $normal !== null &&
                    $normal->isDictionary()
                ) {

                    foreach (
                        $normal->value()
                        as $state_name => $unused
                    ) {

                        $state =
                            (string) $state_name;

                        if (
                            strtolower($state) ===
                            'off'
                        ) {
                            continue;
                        }

                        $on_state =
                            $state;

                        break;
                    }
                }
            }

            $selected =
                $widget_object_number ===
                $selected_widget;

            $widget =
                $widget->set(
                    'AS',
                    PlusPdfObject::name(
                        $selected
                            ? $on_state
                            : 'Off'
                    )
                );

            $writer->setObject(
                $widget_object_number,
                0,
                $widget
            );
        }

        /*
     * Set the parent radio field's /V.
     */
        $field_object =
            $parser->getObject(
                $field->objectNumber,
                0
            );

        if (
            $field_object !== null &&
            $field_object->isDictionary()
        ) {

            $field_object =
                $field_object->set(
                    'V',
                    PlusPdfObject::name(
                        $selected_value
                    )
                );

            $writer->setObject(
                $field->objectNumber,
                0,
                $field_object
            );
        }
    }

    public static function finalize_magic_link_signature(
        int $lead_id,
        string $signature_ip,
        string $signature_datetime,
        int $magic_link_id
    ): ?array {
        if (
            !$lead_id ||
            !$magic_link_id
        ) {
            return null;
        }

        $lead =
            Lead::get($lead_id);

        if (!$lead) {
            return null;
        }

        $submission =
            json_decode(
                $lead->submission ?? '',
                true
            );

        if (!is_array($submission)) {
            return null;
        }

        $contracts =
            $submission['contracts'] ?? [];

        if (
            !is_array($contracts) ||
            empty($contracts)
        ) {
            Logger::error(
                'pdf',
                'Magic Link signing failed because the lead has no contract.',
                [
                    'operation' =>
                    'finalize_magic_link_signature',

                    'lead_id' =>
                    $lead_id,

                    'magic_link_id' =>
                    $magic_link_id,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            return null;
        }

        foreach ($contracts as $existing_contract) {
            if (!is_array($existing_contract)) {
                continue;
            }

            if (
                empty($existing_contract['signed']) ||
                empty($existing_contract['attachment_id'])
            ) {
                continue;
            }

            $existing_attachment_id =
                absint(
                    $existing_contract['attachment_id']
                );

            $existing_file =
                self::get_contract_file_path(
                    $existing_attachment_id
                );

            if (!$existing_file) {
                continue;
            }

            return $existing_contract;
        }

        $contract = null;

        foreach ($contracts as $item) {
            if (
                !empty($item['final'])
            ) {
                $contract = $item;
                break;
            }
        }

        if (!$contract) {
            $contract = end($contracts);
        }

        if (
            !is_array($contract) ||
            empty($contract['attachment_id'])
        ) {
            Logger::error(
                'pdf',
                'Magic Link signing failed because the contract attachment is missing.',
                [
                    'operation' =>
                    'finalize_magic_link_signature',

                    'lead_id' =>
                    $lead_id,

                    'magic_link_id' =>
                    $magic_link_id,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            return null;
        }

        $attachment_id =
            absint(
                $contract['attachment_id']
            );

        $source =
            self::get_contract_file_path(
                $attachment_id
            );

        if (!$source) {
            Logger::error(
                'pdf',
                'Magic Link signing failed because the reviewed contract file is missing.',
                [
                    'operation' =>
                    'finalize_magic_link_signature',

                    'lead_id' =>
                    $lead_id,

                    'magic_link_id' =>
                    $magic_link_id,

                    'attachment_id' =>
                    $attachment_id,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            return null;
        }

        $pdf_field =
            self::get_signature_pdf_field(
                $submission,
                absint(
                    $contract['template_id'] ?? 0
                )
            );

        if ($pdf_field === '') {
            Logger::error(
                'pdf',
                'Magic Link signing failed because no signature PDF field is mapped.',
                [
                    'operation' =>
                    'finalize_magic_link_signature',

                    'lead_id' =>
                    $lead_id,

                    'magic_link_id' =>
                    $magic_link_id,

                    'attachment_id' =>
                    $attachment_id,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            return null;
        }

        $signature_value =
            implode(
                "\n",
                [
                    $signature_ip,
                    wp_date(
                        'Y-m-d',
                        strtotime($signature_datetime)
                    ),
                    wp_date(
                        'H:i:s',
                        strtotime($signature_datetime)
                    ),
                    (string) $magic_link_id,
                ]
            );

        $destination =
            self::get_destination(
                ($contract['title'] ?? 'Contract') . '-signed',
                $lead_id
            );

        try {
            $form =
                PdfForm::open(
                    $source
                );

            $fields =
                $form->getFields();

            if (empty($fields)) {
                Logger::error(
                    'pdf',
                    'Magic Link signing failed because the reviewed PDF contains no AcroForm fields.',
                    [
                        'operation' =>
                        'finalize_magic_link_signature',

                        'lead_id' =>
                        $lead_id,

                        'magic_link_id' =>
                        $magic_link_id,

                        'attachment_id' =>
                        $attachment_id,

                        'form_id' =>
                        absint(
                            $submission['form']['id']
                                ?? 0
                        ),
                    ]
                );

                return null;
            }

            if (!isset($fields[$pdf_field])) {
                Logger::error(
                    'pdf',
                    'Magic Link signing failed because the mapped signature field does not exist in the reviewed PDF.',
                    [
                        'operation' =>
                        'finalize_magic_link_signature',

                        'lead_id' =>
                        $lead_id,

                        'magic_link_id' =>
                        $magic_link_id,

                        'attachment_id' =>
                        $attachment_id,

                        'form_id' =>
                        absint(
                            $submission['form']['id']
                                ?? 0
                        ),
                    ]
                );

                return null;
            }

            $temporary =
                tempnam(
                    sys_get_temp_dir(),
                    'bcob_signature_'
                );

            if ($temporary === false) {
                return null;
            }

            $form
                ->fill([
                    $pdf_field => $signature_value,
                ])
                ->needAppearances(false)
                ->save($temporary);

            $bytes =
                (string) file_get_contents(
                    $temporary
                );

            $parser =
                new PdfParser(
                    $bytes
                );

            $reader =
                new AcroFormReader(
                    $parser
                );

            $pdf_fields =
                $reader->getFields();

            $writer =
                new PdfWriter(
                    $parser->getRawData(),
                    $parser->getTrailer()
                );

            foreach ($pdf_fields as $name => $field) {
                if (
                    $field->type !==
                    FieldType::Checkbox
                ) {
                    continue;
                }

                $existing =
                    $fields[$name] ?? null;

                if (!$existing) {
                    continue;
                }

                $current_value =
                    isset($existing->value)
                    ? (string) $existing->value
                    : '';

                $checked =
                    $current_value !== '' &&
                    strtolower($current_value) !== 'off';

                $on_state = '';

                foreach (
                    $field->widgetObjectNumbers
                    as $widget_object_number
                ) {
                    $widget =
                        $parser->getObject(
                            $widget_object_number,
                            0
                        );

                    if (
                        $widget === null ||
                        !$widget->isDictionary()
                    ) {
                        continue;
                    }

                    $ap =
                        $widget->get('AP');

                    if (
                        $ap !== null &&
                        $ap->isReference()
                    ) {
                        $ap =
                            $parser->resolve($ap);
                    }

                    if (
                        $ap === null ||
                        !$ap->isDictionary()
                    ) {
                        continue;
                    }

                    $normal =
                        $ap->get('N');

                    if (
                        $normal !== null &&
                        $normal->isReference()
                    ) {
                        $normal =
                            $parser->resolve($normal);
                    }

                    if (
                        $normal === null ||
                        !$normal->isDictionary()
                    ) {
                        continue;
                    }

                    foreach (
                        $normal->value()
                        as $state_name => $unused
                    ) {
                        $state =
                            (string) $state_name;

                        if (
                            strtolower($state) ===
                            'off'
                        ) {
                            continue;
                        }

                        $on_state = $state;
                        break;
                    }

                    if ($on_state !== '') {
                        break;
                    }
                }

                if (
                    $checked &&
                    $on_state === ''
                ) {
                    continue;
                }

                $field_object =
                    $parser->getObject(
                        $field->objectNumber,
                        0
                    );

                if (
                    $field_object === null ||
                    !$field_object->isDictionary()
                ) {
                    continue;
                }

                $field_object =
                    $field_object->set(
                        'V',
                        PlusPdfObject::name(
                            $checked
                                ? $on_state
                                : 'Off'
                        )
                    );

                $writer->setObject(
                    $field->objectNumber,
                    0,
                    $field_object
                );

                foreach (
                    $field->widgetObjectNumbers
                    as $widget_object_number
                ) {
                    $widget =
                        $parser->getObject(
                            $widget_object_number,
                            0
                        );

                    if (
                        $widget === null ||
                        !$widget->isDictionary()
                    ) {
                        continue;
                    }

                    $widget =
                        $widget->set(
                            'AS',
                            PlusPdfObject::name(
                                $checked
                                    ? $on_state
                                    : 'Off'
                            )
                        );

                    $writer->setObject(
                        $widget_object_number,
                        0,
                        $widget
                    );
                }
            }

            $final_bytes =
                $writer->write();

            if ($final_bytes === '') {
                @unlink($temporary);
                return null;
            }

            file_put_contents(
                $temporary,
                $final_bytes
            );

            $final_form =
                PdfForm::open(
                    $temporary
                );

            $final_form
                ->flatten()
                ->save(
                    $destination
                );

            @unlink($temporary);

            if (
                !file_exists($destination)
            ) {
                return null;
            }

            $final_attachment_id =
                self::create_attachment(
                    $destination,
                    absint(
                        $contract['template_id'] ?? 0
                    ),
                    ($contract['title'] ?? 'Contract') . ' - Signed',
                    $lead_id
                );

            if (!$final_attachment_id) {
                return null;
            }

            self::delete_old_contracts(
                $submission,
                $final_attachment_id
            );

            $final_contract =
                $contract;

            $final_contract['attachment_id'] =
                $final_attachment_id;

            $final_contract['url'] =
                self::get_contract_url(
                    $final_attachment_id
                );

            $final_contract['final'] =
                true;

            $final_contract['signed'] =
                true;

            $final_contract['signed_at'] =
                $signature_datetime;

            $submission['contracts'] =
                [
                    $final_contract,
                ];

            Lead::update_submission(
                $lead_id,
                $submission
            );

            return $final_contract;
        } catch (\Throwable $e) {
            Logger::error(
                'pdf',
                'Magic Link contract signing encountered an exception.',
                [
                    'operation' =>
                    'finalize_magic_link_signature',

                    'lead_id' =>
                    $lead_id,

                    'magic_link_id' =>
                    $magic_link_id,

                    'attachment_id' =>
                    isset($attachment_id)
                        ? $attachment_id
                        : 0,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            if (
                isset($temporary) &&
                file_exists($temporary)
            ) {
                @unlink($temporary);
            }

            return null;
        }
    }

    private static function get_signature_pdf_field(
        array $submission,
        int $template_id
    ): string {
        $form_id =
            absint(
                $submission['form']['id'] ?? 0
            );

        if (!$form_id) {
            return '';
        }

        $schema =
            self::get_form_schema(
                $form_id
            );

        $contracts =
            $schema['settings']['contracts'] ?? [];

        if (
            isset($contracts['conditions']) &&
            is_array($contracts['conditions'])
        ) {
            $contracts = [$contracts];
        }

        if (!is_array($contracts)) {
            return '';
        }

        foreach ($contracts as $contract) {
            if (!is_array($contract)) {
                continue;
            }

            foreach (
                ($contract['conditions'] ?? [])
                as $condition
            ) {
                if (!is_array($condition)) {
                    continue;
                }

                $pdf =
                    $condition['pdf'] ?? [];

                if (!is_array($pdf)) {
                    continue;
                }

                if (
                    absint($pdf['id'] ?? 0) !==
                    $template_id
                ) {
                    continue;
                }

                foreach (
                    ($pdf['mappings'] ?? [])
                    as $mapping
                ) {
                    if (!is_array($mapping)) {
                        continue;
                    }

                    if (
                        (string) (
                            $mapping['form_field'] ?? ''
                        ) !==
                        'ip-timing-token'
                    ) {
                        continue;
                    }

                    return sanitize_text_field(
                        $mapping['pdf_field'] ?? ''
                    );
                }
            }
        }

        return '';
    }

    private static function delete_old_contracts(
        array $submission,
        int $keep_attachment_id
    ): void {
        foreach (
            ($submission['contracts'] ?? [])
            as $contract
        ) {
            $attachment_id =
                absint(
                    $contract['attachment_id'] ?? 0
                );

            if (
                !$attachment_id ||
                $attachment_id === $keep_attachment_id
            ) {
                continue;
            }

            $path =
                self::get_contract_file_path(
                    $attachment_id
                );

            if (
                $path &&
                file_exists($path)
            ) {
                @unlink($path);
            }

            wp_delete_attachment(
                $attachment_id,
                true
            );
        }
    }

    private static function get_review_allowed_fields(
        int $form_id,
        int $template_id
    ): array {
        $schema =
            self::get_form_schema(
                $form_id
            );

        if (empty($schema)) {
            return [];
        }

        $contracts =
            $schema['settings']['contracts'] ?? [];

        if (
            isset($contracts['conditions']) &&
            is_array($contracts['conditions'])
        ) {
            $contracts = [$contracts];
        }

        if (!is_array($contracts)) {
            return [];
        }

        $allowed = [];

        foreach ($contracts as $contract) {
            if (!is_array($contract)) {
                continue;
            }

            foreach (
                ($contract['conditions'] ?? [])
                as $condition
            ) {
                if (!is_array($condition)) {
                    continue;
                }

                $pdf =
                    $condition['pdf'] ?? [];

                if (!is_array($pdf)) {
                    continue;
                }

                if (
                    absint($pdf['id'] ?? 0) !==
                    $template_id
                ) {
                    continue;
                }

                foreach (
                    ($pdf['mappings'] ?? [])
                    as $mapping
                ) {
                    if (!is_array($mapping)) {
                        continue;
                    }

                    $pdf_field =
                        sanitize_text_field(
                            $mapping['pdf_field'] ?? ''
                        );

                    $form_field =
                        sanitize_text_field(
                            $mapping['form_field'] ?? ''
                        );

                    if (
                        !$pdf_field ||
                        !$form_field
                    ) {
                        continue;
                    }

                    if (
                        $form_field ===
                        'ip-timing-token'
                    ) {
                        continue;
                    }

                    $allowed[$pdf_field] = true;
                }
            }
        }

        return $allowed;
    }

    public static function finalize_review_pdf(
        int $lead_id,
        int $attachment_id,
        array $field_values
    ): ?array {
        if (
            !$lead_id ||
            !$attachment_id
        ) {
            return null;
        }

        $lead =
            Lead::get(
                $lead_id
            );

        if (!$lead) {
            return null;
        }

        $submission =
            json_decode(
                $lead->submission ?? '',
                true
            );

        if (!is_array($submission)) {
            return null;
        }

        $contracts =
            $submission['contracts'] ?? [];

        if (!is_array($contracts)) {
            return null;
        }

        $contract = null;

        foreach (
            $contracts as $item
        ) {
            if (
                absint(
                    $item['attachment_id'] ?? 0
                ) !== $attachment_id
            ) {
                continue;
            }

            $contract =
                $item;

            break;
        }

        if (!$contract) {
            return null;
        }

        $source =
            self::get_contract_file_path(
                $attachment_id
            );

        if (
            !$source ||
            !file_exists($source)
        ) {
            return null;
        }

        $title =
            sanitize_text_field(
                $contract['title'] ??
                    'Contract'
            );

        $destination =
            self::get_destination(
                $title . '-final',
                $lead_id
            );

        try {
            $form =
                PdfForm::open(
                    $source
                );

            $fields =
                $form->getFields();

            if (empty($fields)) {
                return null;
            }

            $form_id =
                absint(
                    $submission['form']['id'] ?? 0
                );

            $template_id =
                absint(
                    $contract['template_id'] ?? 0
                );

            $allowed_fields =
                self::get_review_allowed_fields(
                    $form_id,
                    $template_id
                );

            if (empty($allowed_fields)) {
                return null;
            }

            $fill = [];

            foreach (
                $field_values as $name => $value
            ) {
                if (!is_string($name)) {
                    continue;
                }

                if (
                    !isset(
                        $allowed_fields[$name]
                    )
                ) {
                    continue;
                }

                if (
                    !isset(
                        $fields[$name]
                    )
                ) {
                    continue;
                }

                $field =
                    $fields[$name];

                if (
                    $field->type ===
                    FieldType::Checkbox
                ) {
                    continue;
                }

                if (
                    is_array($value)
                ) {
                    $value =
                        implode(
                            ', ',
                            array_map(
                                'strval',
                                $value
                            )
                        );
                }

                if ($value === null) {
                    $value = '';
                }

                $fill[$name] =
                    (string) $value;
            }

            $temporary =
                tempnam(
                    sys_get_temp_dir(),
                    'bcob_review_'
                );

            if (
                $temporary === false
            ) {
                return null;
            }

            if (!empty($fill)) {
                $form
                    ->fill($fill)
                    ->needAppearances(false)
                    ->save($temporary);
            } else {
                if (
                    !copy(
                        $source,
                        $temporary
                    )
                ) {
                    @unlink($temporary);

                    return null;
                }
            }

            /*
         * Read the filled PDF with the same
         * low-level parser used by debug_field_names().
         */
            $bytes =
                (string)
                file_get_contents(
                    $temporary
                );

            $parser =
                new PdfParser(
                    $bytes
                );

            $reader =
                new AcroFormReader(
                    $parser
                );

            $pdf_fields =
                $reader->getFields();

            $writer =
                new PdfWriter(
                    $parser->getRawData(),
                    $parser->getTrailer()
                );

            foreach (
                $pdf_fields as $name => $field
            ) {
                if (
                    $field->type !==
                    FieldType::Checkbox
                ) {
                    continue;
                }

                if (
                    !array_key_exists(
                        $name,
                        $field_values
                    )
                ) {
                    continue;
                }

                $checked =
                    filter_var(
                        $field_values[$name],
                        FILTER_VALIDATE_BOOLEAN
                    );

                $on_state = '';

                foreach (
                    $field->widgetObjectNumbers
                    as $widget_object_number
                ) {
                    $widget =
                        $parser->getObject(
                            $widget_object_number,
                            0
                        );

                    if (
                        $widget === null ||
                        !$widget->isDictionary()
                    ) {
                        continue;
                    }

                    $ap =
                        $widget->get('AP');

                    if (
                        $ap !== null &&
                        $ap->isReference()
                    ) {
                        $ap =
                            $parser->resolve(
                                $ap
                            );
                    }

                    if (
                        $ap === null ||
                        !$ap->isDictionary()
                    ) {
                        continue;
                    }

                    $normal =
                        $ap->get('N');

                    if (
                        $normal !== null &&
                        $normal->isReference()
                    ) {
                        $normal =
                            $parser->resolve(
                                $normal
                            );
                    }

                    if (
                        $normal === null ||
                        !$normal->isDictionary()
                    ) {
                        continue;
                    }

                    foreach (
                        $normal->value()
                        as $state_name => $unused
                    ) {
                        $state =
                            (string) $state_name;

                        if (
                            strtolower(
                                $state
                            ) === 'off'
                        ) {
                            continue;
                        }

                        $on_state =
                            $state;

                        break;
                    }

                    if (
                        $on_state !== ''
                    ) {
                        break;
                    }
                }

                if (
                    $checked &&
                    $on_state === ''
                ) {
                    continue;
                }

                $field_object =
                    $parser->getObject(
                        $field->objectNumber,
                        0
                    );

                if (
                    $field_object === null ||
                    !$field_object->isDictionary()
                ) {
                    continue;
                }

                $field_object =
                    $field_object->set(
                        'V',
                        PlusPdfObject::name(
                            $checked
                                ? $on_state
                                : 'Off'
                        )
                    );

                $writer->setObject(
                    $field->objectNumber,
                    0,
                    $field_object
                );

                foreach (
                    $field->widgetObjectNumbers
                    as $widget_object_number
                ) {
                    $widget =
                        $parser->getObject(
                            $widget_object_number,
                            0
                        );

                    if (
                        $widget === null ||
                        !$widget->isDictionary()
                    ) {
                        continue;
                    }

                    $widget =
                        $widget->set(
                            'AS',
                            PlusPdfObject::name(
                                $checked
                                    ? $on_state
                                    : 'Off'
                            )
                        );

                    $writer->setObject(
                        $widget_object_number,
                        0,
                        $widget
                    );
                }
            }

            $final_bytes =
                $writer->write();

            if (
                $final_bytes === ''
            ) {
                @unlink($temporary);

                return null;
            }

            file_put_contents(
                $temporary,
                $final_bytes
            );

            /*
            * Keep the reviewed contract editable until
            * the Magic Link signature has been applied.
            *
            * The final signed contract is flattened later
            * in finalize_magic_link_signature().
            */
            if (
                !copy(
                    $temporary,
                    $destination
                )
            ) {
                @unlink(
                    $temporary
                );

                throw new \RuntimeException(
                    'Unable to create reviewed contract.'
                );
            }

            @unlink(
                $temporary
            );

            if (
                !file_exists(
                    $destination
                )
            ) {
                return null;
            }

            $final_attachment_id =
                self::create_attachment(
                    $destination,
                    absint(
                        $contract['template_id'] ?? 0
                    ),
                    $title . ' - Final',
                    $lead_id
                );

            if (!$final_attachment_id) {
                return null;
            }

            $contract['attachment_id'] =
                $final_attachment_id;

            $contract['url'] =
                self::get_contract_url(
                    $final_attachment_id
                );

            $contract['final'] =
                true;

            foreach (
                $contracts as $index => $item
            ) {
                if (
                    absint(
                        $item['attachment_id'] ?? 0
                    ) !== $attachment_id
                ) {
                    continue;
                }

                $contracts[$index] =
                    $contract;

                break;
            }

            $submission['contracts'] =
                array_values(
                    $contracts
                );

            Lead::update_submission(
                $lead_id,
                $submission
            );

            return $contract;
        } catch (\Throwable $e) {
            Logger::error(
                'pdf',
                'Reviewed contract finalization failed.',
                [
                    'operation' =>
                    'finalize_review_pdf',

                    'lead_id' =>
                    $lead_id,

                    'attachment_id' =>
                    $attachment_id,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            if (
                isset($temporary) &&
                file_exists($temporary)
            ) {
                @unlink($temporary);
            }

            return null;
        }
    }

    public static function get_contract_file_path(
        int $attachment_id
    ): ?string {
        if (!$attachment_id) {
            return null;
        }

        $is_private =
            (bool) get_post_meta(
                $attachment_id,
                '_bcob_private_contract',
                true
            );

        if (!$is_private) {
            $file =
                get_attached_file(
                    $attachment_id
                );

            return (
                is_string($file) &&
                $file !== '' &&
                is_file($file) &&
                is_readable($file)
            )
                ? $file
                : null;
        }

        /*
     * Private contracts use our own absolute
     * filesystem path instead of WordPress'
     * _wp_attached_file handling.
     */
        $private_path =
            get_post_meta(
                $attachment_id,
                '_bcob_private_contract_path',
                true
            );

        if (
            is_string($private_path) &&
            $private_path !== ''
        ) {
            $private_path =
                wp_normalize_path(
                    $private_path
                );

            if (
                is_file($private_path) &&
                is_readable($private_path)
            ) {
                return $private_path;
            }
        }

        /*
     * Recover contracts created before
     * _bcob_private_contract_path existed.
     *
     * WordPress may have mangled a Windows
     * absolute path such as:
     *
     * C:laragonbcob-private-contracts/2026/09/...
     */
        $legacy =
            get_post_meta(
                $attachment_id,
                '_wp_attached_file',
                true
            );

        if (
            !is_string($legacy) ||
            $legacy === ''
        ) {
            return null;
        }

        $legacy =
            str_replace(
                '\\',
                '/',
                $legacy
            );

        $marker =
            'bcob-private-contracts/';

        $position =
            stripos(
                $legacy,
                $marker
            );

        if ($position === false) {
            return null;
        }

        $relative =
            substr(
                $legacy,
                $position +
                    strlen($marker)
            );

        if (
            !$relative ||
            str_contains(
                $relative,
                '../'
            )
        ) {
            return null;
        }

        $candidate =
            wp_normalize_path(
                trailingslashit(
                    self::get_private_directory()
                ) .
                    ltrim(
                        $relative,
                        '/'
                    )
            );

        if (
            !is_file($candidate) ||
            !is_readable($candidate)
        ) {
            return null;
        }

        /*
     * Self-heal the old attachment so future
     * reads don't need legacy recovery.
     */
        update_post_meta(
            $attachment_id,
            '_bcob_private_contract_path',
            $candidate
        );

        return $candidate;
    }

    private static function get_private_directory(): string
    {
        if (
            defined('BCOB_PRIVATE_CONTRACT_DIR') &&
            BCOB_PRIVATE_CONTRACT_DIR
        ) {
            $directory =
                wp_normalize_path(
                    BCOB_PRIVATE_CONTRACT_DIR
                );
        } else {
            $document_root =
                isset($_SERVER['DOCUMENT_ROOT'])
                ? realpath(
                    $_SERVER['DOCUMENT_ROOT']
                )
                : false;

            if (!$document_root) {
                throw new \RuntimeException(
                    'Unable to determine the private contract directory.'
                );
            }

            $directory =
                wp_normalize_path(
                    dirname($document_root) .
                        '/bcob-private-contracts'
                );
        }

        if (!wp_mkdir_p($directory)) {
            throw new \RuntimeException(
                'Unable to create the private contract directory.'
            );
        }

        $real_directory =
            realpath($directory);

        $document_root =
            isset($_SERVER['DOCUMENT_ROOT'])
            ? realpath(
                $_SERVER['DOCUMENT_ROOT']
            )
            : false;

        if (!$real_directory) {
            throw new \RuntimeException(
                'Private contract directory is unavailable.'
            );
        }

        if ($document_root) {
            $private =
                strtolower(
                    trailingslashit(
                        wp_normalize_path(
                            $real_directory
                        )
                    )
                );

            $public =
                strtolower(
                    trailingslashit(
                        wp_normalize_path(
                            $document_root
                        )
                    )
                );

            if (
                $private === $public ||
                str_starts_with(
                    $private,
                    $public
                )
            ) {
                throw new \RuntimeException(
                    'The private contract directory must be outside the public web root.'
                );
            }
        }

        return $real_directory;
    }

    private static function get_destination(
        string $title,
        int $lead_id
    ): string {
        $directory =
            trailingslashit(
                self::get_private_directory()
            ) .
            gmdate('Y/m') .
            '/';

        if (!wp_mkdir_p($directory)) {
            throw new \RuntimeException(
                'Unable to create private PDF directory.'
            );
        }

        $base =
            sanitize_file_name(
                $title ?: 'contract'
            );

        $filename =
            sprintf(
                'lead-%d-%s-%s.pdf',
                $lead_id,
                $base,
                wp_generate_password(
                    16,
                    false,
                    false
                )
            );

        return $directory .
            $filename;
    }

    private static function create_attachment(
        string $file,
        int $template_id,
        string $title,
        int $lead_id
    ): int {
        $file =
            wp_normalize_path(
                $file
            );

        if (
            !$file ||
            !is_file($file) ||
            !is_readable($file)
        ) {
            return 0;
        }

        /*
     * Deliberately do NOT pass $file as the
     * wp_insert_attachment() file argument.
     *
     * WordPress attachment paths normally belong
     * to wp-content/uploads and its path handling
     * can corrupt our Windows private absolute path.
     */
        $attachment_id =
            wp_insert_attachment(
                [
                    'guid' =>
                    'bcob-private://' .
                        wp_generate_uuid4(),

                    'post_mime_type' =>
                    'application/pdf',

                    'post_title' =>
                    $title
                        ? $title . ' - Generated'
                        : 'Generated Contract',

                    'post_content' =>
                    '',

                    'post_status' =>
                    'inherit',
                ],
                '',
                0,
                true
            );

        if (
            is_wp_error($attachment_id) ||
            !$attachment_id
        ) {
            return 0;
        }

        update_post_meta(
            $attachment_id,
            '_bcob_contract_template_id',
            $template_id
        );

        update_post_meta(
            $attachment_id,
            '_bcob_generated',
            1
        );

        update_post_meta(
            $attachment_id,
            '_bcob_private_contract',
            1
        );

        update_post_meta(
            $attachment_id,
            '_bcob_private_contract_path',
            $file
        );

        update_post_meta(
            $attachment_id,
            '_bcob_lead_id',
            $lead_id
        );

        return (int) $attachment_id;
    }

    private static function get_matching_pdfs(
        array $contract,
        array $submission
    ): array {
        $conditions =
            $contract['conditions'] ?? [];

        if (
            !is_array($conditions) ||
            empty($conditions)
        ) {
            return [];
        }

        $relation =
            strtoupper(
                $contract['relation'] ?? 'AND'
            );

        $matches = [];

        foreach (
            $conditions as $condition
        ) {
            if (!is_array($condition)) {
                continue;
            }

            $matches[] = [
                'matched' =>
                self::condition_matches(
                    $condition,
                    $submission
                ),

                'pdf' =>
                $condition['pdf'] ?? [],
            ];
        }

        if (empty($matches)) {
            return [];
        }

        if ('AND' === $relation) {
            foreach ($matches as $match) {
                if (!$match['matched']) {
                    return [];
                }
            }

            $pdfs = [];

            foreach ($matches as $match) {
                if (!empty($match['pdf'])) {
                    $pdfs[] =
                        $match['pdf'];
                }
            }

            return $pdfs;
        }

        $pdfs = [];

        foreach ($matches as $match) {
            if (
                $match['matched'] &&
                !empty($match['pdf'])
            ) {
                $pdfs[] =
                    $match['pdf'];
            }
        }

        return $pdfs;
    }

    private static function condition_matches(
        array $condition,
        array $submission
    ): bool {
        $field =
            (string) (
                $condition['field'] ?? ''
            );

        if (!$field) {
            return false;
        }

        $actual =
            $submission['values'][$field]['value']
            ?? '';

        $operator =
            $condition['operator'] ?? '=';

        $expected =
            $condition['value'] ?? '';

        return self::compare(
            $actual,
            $operator,
            $expected
        );
    }

    private static function compare(
        $actual,
        string $operator,
        $expected
    ): bool {
        if (is_array($actual)) {
            $actual =
                implode(
                    ', ',
                    $actual
                );
        }

        $actual =
            (string) ($actual ?? '');

        switch ($operator) {
            case '=':
            case '==':
                return $actual ===
                    (string) $expected;

            case '!=':
                return $actual !==
                    (string) $expected;

            case '>':
                return (float) $actual >
                    (float) $expected;

            case '<':
                return (float) $actual <
                    (float) $expected;

            case '>=':
                return (float) $actual >=
                    (float) $expected;

            case '<=':
                return (float) $actual <=
                    (float) $expected;

            case 'contains':
                return strpos(
                    $actual,
                    (string) $expected
                ) !== false;

            case 'starts_with':
                return str_starts_with(
                    $actual,
                    (string) $expected
                );

            case 'ends_with':
                return str_ends_with(
                    $actual,
                    (string) $expected
                );

            case 'empty':
                return trim($actual) === '';

            case 'not_empty':
                return trim($actual) !== '';

            default:
                return false;
        }
    }

    private static function get_form_schema(
        int $form_id
    ): array {
        global $wpdb;

        $table =
            $wpdb->prefix .
            'bcob_forms';

        $schema =
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT form_schema FROM {$table} WHERE id=%d LIMIT 1",
                    $form_id
                )
            );

        if (!$schema) {
            return [];
        }

        $decoded =
            json_decode(
                $schema,
                true
            );

        return is_array($decoded)
            ? $decoded
            : [];
    }

    private static function replace_contract(
        array &$submission,
        array $new_contract
    ): void {
        $contracts =
            $submission['contracts'] ?? [];

        if (!is_array($contracts)) {
            $contracts = [];
        }

        $template_id =
            absint(
                $new_contract['template_id'] ?? 0
            );

        $replaced = false;

        foreach (
            $contracts as $index => $contract
        ) {
            if (
                absint(
                    $contract['template_id'] ?? 0
                ) !== $template_id
            ) {
                continue;
            }

            $contracts[$index] =
                $new_contract;

            $replaced = true;

            break;
        }

        if (!$replaced) {
            $contracts[] =
                $new_contract;
        }

        $submission['contracts'] =
            array_values($contracts);
    }

    private static function unique_contracts(
        array $contracts
    ): array {
        $unique = [];

        foreach ($contracts as $contract) {
            $template_id =
                absint(
                    $contract['template_id'] ?? 0
                );

            if (!$template_id) {
                continue;
            }

            $unique[$template_id] =
                $contract;
        }

        return array_values($unique);
    }

    public static function serve_contract(): void
    {
        $lead_id =
            absint(
                $_GET['lead_id'] ?? 0
            );

        $attachment_id =
            absint(
                $_GET['attachment_id'] ?? 0
            );

        $expires =
            absint(
                $_GET['expires'] ?? 0
            );

        $access =
            sanitize_text_field(
                wp_unslash(
                    $_GET['access'] ?? ''
                )
            );

        if (
            !$lead_id ||
            !$attachment_id ||
            !$expires ||
            !$access
        ) {
            status_header(404);
            exit;
        }

        if ($expires < time()) {
            status_header(410);
            exit;
        }

        $lead =
            Lead::get(
                $lead_id
            );

        if (
            !$lead ||
            empty($lead->token)
        ) {
            status_header(404);
            exit;
        }

        $attachment_lead_id =
            absint(
                get_post_meta(
                    $attachment_id,
                    '_bcob_lead_id',
                    true
                )
            );

        if (
            $attachment_lead_id !==
            $lead_id
        ) {
            status_header(403);
            exit;
        }

        $expected =
            self::get_contract_access_signature(
                $lead_id,
                $attachment_id,
                (string) $lead->token,
                $expires
            );

        if (
            !hash_equals(
                $expected,
                $access
            )
        ) {
            status_header(403);
            exit;
        }

        $submission =
            json_decode(
                $lead->submission ?? '',
                true
            );

        if (!is_array($submission)) {
            status_header(404);
            exit;
        }

        $allowed = false;

        foreach (
            ($submission['contracts'] ?? [])
            as $contract
        ) {
            if (
                absint(
                    $contract['attachment_id'] ?? 0
                ) ===
                $attachment_id
            ) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            status_header(403);
            exit;
        }

        if (
            !get_post_meta(
                $attachment_id,
                '_bcob_private_contract',
                true
            )
        ) {
            status_header(403);
            exit;
        }

        $file =
            self::get_contract_file_path(
                $attachment_id
            );

        if (
            !$file ||
            !is_file($file) ||
            !is_readable($file)
        ) {
            status_header(404);
            exit;
        }

        header(
            'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0',
            true
        );

        header(
            'Pragma: no-cache'
        );

        header(
            'Expires: 0'
        );

        header(
            'Referrer-Policy: no-referrer'
        );

        header(
            'Content-Type: application/pdf'
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        header(
            'Content-Disposition: inline; filename="' .
                str_replace(
                    '"',
                    '',
                    sanitize_file_name(
                        get_the_title(
                            $attachment_id
                        ) . '.pdf'
                    )
                ) .
                '"'
        );

        header(
            'Content-Length: ' .
                filesize($file)
        );

        readfile($file);

        exit;
    }
}
