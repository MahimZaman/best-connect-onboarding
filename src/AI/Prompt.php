<?php

namespace BestConnectOnboarding\AI;

if (! defined('ABSPATH')) {
    exit;
}

class Prompt
{
    public function extract(
        string $file_id,
        array $fields
    ): array {
        $schema = [];

        foreach ($fields as $field) {
            if (
                empty($field['id']) ||
                empty($field['type'])
            ) {
                continue;
            }

            $item = [
                'id' =>
                $field['id'],

                'type' =>
                $field['type'],

                'label' =>
                $field['label'] ?? '',
            ];

            if (
                !empty($field['placeholder'])
            ) {
                $item['placeholder'] =
                    $field['placeholder'];
            }

            if (
                !empty($field['options']) &&
                is_array(
                    $field['options']
                )
            ) {
                $item['options'] =
                    $field['options'];
            }

            $schema[] = $item;
        }

        $prompt = implode(
            PHP_EOL,
            [
                'You are an expert document parser.',
                '',
                'Analyze the uploaded document and extract as much information as possible.',
                '',
                'Below is the form schema in JSON format.',
                '',
                'Rules:',
                '- Return ONLY valid JSON.',
                '- The JSON keys MUST be the provided field IDs.',
                '- The field TYPE is authoritative.',
                '- The LABEL provides additional context.',
                '- The PLACEHOLDER is an example of the expected value.',
                '- Match fields by meaning, not by exact wording.',
                '- If a value cannot be found, return an empty string.',
                '- For radio/select fields return ONE allowed option value exactly.',
                '- For checkbox fields return an ARRAY of allowed option values.',
                '- Never invent option values.',
                '- Never return explanations or markdown.',
                '',
                'FORM_SCHEMA:',
                wp_json_encode(
                    $schema,
                    JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                ),
            ]
        );

        return [
            [
                'type' =>
                'input_file',

                'file_id' =>
                $file_id,
            ],
            [
                'type' =>
                'input_text',

                'text' =>
                $prompt,
            ],
        ];
    }

    public function map_pdf_fields(
        string $file_id,
        array $pdf_fields,
        array $submission_fields
    ): array {
        $prompt = implode(
            PHP_EOL,
            [
                'You are an expert human document analyst filling an existing PDF form.',
                '',
                'The uploaded PDF is the actual contract that must be completed.',
                '',
                'Your task is to determine what submitted value belongs in each PDF AcroForm field.',
                '',
                'IMPORTANT:',
                'AcroForm names such as "Textfeld 9", "Textfeld 17", or "Kontrollkästchen 3" may be meaningless internal identifiers.',
                'Never assume the numeric order of field names represents their meaning.',
                '',
                'Read and understand the actual uploaded PDF visually.',
                '',
                'Use:',
                '- visible labels',
                '- surrounding text',
                '- layout',
                '- field position',
                '- field type',
                '- semantic meaning',
                '- the submitted BCOB data',
                '',
                'The supplied PDF_FIELDS list contains the exact AcroForm fields available in the PDF.',
                'You MUST use the exact PDF field name from that list.',
                'Never invent a PDF field name.',
                '',
                'YOUR OUTPUT:',
                'For every PDF field that can confidently be filled, return:',
                '- pdf_field: the exact AcroForm field name',
                '- value: the actual submitted value that belongs in that field',
                '- checked: true or false for checkboxes',
                '- confidence: a number from 0 to 1',
                '',
                'TEXT FIELDS:',
                'Return the actual value from the BCOB submission.',
                '',
                'Example:',
                'If "Textfeld 17" is visually identified as the Nachname field and the submission contains "Hotzy", return:',
                '"pdf_field": "Textfeld 17"',
                '"value": "Hotzy"',
                '"checked": null',
                '',
                'Do NOT return the BCOB field ID.',
                'Do NOT return a separate BCOB field mapping.',
                'The final value must be returned directly.',
                '',
                'CHECKBOXES:',
                'Determine whether the checkbox should be selected based on its visible meaning and the submitted data.',
                '',
                'For a selected checkbox:',
                '"checked": true',
                '"value": null',
                '',
                'For an unselected checkbox:',
                '"checked": false',
                '"value": null',
                '',
                'Do not invent checkbox appearance values.',
                'PHP will determine the actual AcroForm checkbox value required by the PDF.',
                '',
                'SEMANTIC MATCHING:',
                'Do not require exact wording.',
                'Understand equivalent meanings, translations, spelling differences, spacing differences, abbreviations and concatenated words.',
                '',
                'Examples:',
                '"Vorname" = "First name"',
                '"Nachname" = "Last name"',
                '"E-Mail" = "E-Mail-Adresse"',
                '"Straße & Hausnummer" = "Straße, Nr."',
                '"Gas beziehen" may correspond to "Gasbezug", "GasEnzo", or another semantically equivalent visible label.',
                '',
                'Use the actual PDF context to determine the meaning.',
                '',
                'SELECT / RADIO / DROPDOWN FIELDS:',
                'If the PDF field has supplied options, the returned value MUST exactly match one of those options.',
                'Never invent an option.',
                '',
                'IMPORTANT SAFETY RULE:',
                'It is better to leave a field unmapped than to put the wrong value into it.',
                'Only return a mapping when confidence is at least 0.65.',
                '',
                'PDF_FIELDS:',
                wp_json_encode(
                    $pdf_fields,
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                ),
                '',
                'BCOB_SUBMISSION_FIELDS:',
                wp_json_encode(
                    $submission_fields,
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                ),
            ]
        );

        return [
            [
                'type' =>
                'input_file',

                'file_id' =>
                $file_id,
            ],

            [
                'type' =>
                'input_text',

                'text' =>
                $prompt,
            ],
        ];
    }

    public function response(
        string $prompt
    ): array {
        return [
            [
                'type' =>
                'input_text',

                'text' =>
                trim($prompt),
            ],
        ];
    }
}
