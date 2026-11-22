<?php

namespace BestConnectOnboarding\AI;

if (! defined('ABSPATH')) {
    exit;
}

class AI
{
    protected string $api_key = '';

    protected string $model = 'gpt-5';

    protected string $pdf_mapping_model = 'gpt-5-mini';

    protected int $timeout = 150;

    protected ?string $last_error = null;

    public function __construct()
    {
        $settings =
            get_option(
                'bcob_settings',
                []
            );

        $this->api_key =
            trim(
                $settings['bcob_openai_api_key'] ?? ''
            );

        $this->model =
            trim(
                $settings['bcob_openai_model'] ?? 'gpt-5'
            );
    }

    public function extract(
        string $file,
        array $fields
    ): array {
        $file_api =
            new File();

        $file_id =
            $file_api->upload(
                $file
            );

        if (!$file_id) {
            $this->last_error =
                $file_api->get_last_error();

            return [];
        }

        try {
            $prompt =
                new Prompt();

            return $this->request(
                [
                    'model' =>
                    $this->model,

                    'input' => [
                        [
                            'role' =>
                            'user',

                            'content' =>
                            $prompt->extract(
                                $file_id,
                                $fields
                            ),
                        ],
                    ],
                ]
            );
        } finally {
            $file_api->delete(
                $file_id
            );
        }
    }

    public function map_pdf_fields(
        string $pdf,
        array $pdf_fields,
        array $submission_fields
    ): array {
        if (!$this->api_key) {
            $this->last_error =
                __(
                    'OpenAI API key is missing.',
                    'best-connect-onboarding'
                );

            return [];
        }

        $file_api =
            new File();

        $file_id =
            $file_api->upload(
                $pdf
            );

        if (!$file_id) {
            $this->last_error =
                $file_api->get_last_error();

            return [];
        }

        try {
            $prompt =
                new Prompt();

            return $this->request(
                [
                    'model' =>
                    $this->pdf_mapping_model,

                    'store' =>
                    false,

                    'input' => [
                        [
                            'role' =>
                            'user',

                            'content' =>
                            $prompt->map_pdf_fields(
                                $file_id,
                                $pdf_fields,
                                $submission_fields
                            ),
                        ],
                    ],

                    'text' => [
                        'format' => [
                            'type' =>
                            'json_schema',

                            'name' =>
                            'bcob_pdf_mapping',

                            'description' =>
                            'Semantically maps PDF AcroForm fields to BCOB submission fields and determines checkbox selection.',

                            'strict' =>
                            true,

                            'schema' => [
                                'type' =>
                                'object',

                                'additionalProperties' =>
                                false,

                                'properties' => [
                                    'fields' => [
                                        'type' =>
                                        'array',

                                        'items' => [
                                            'type' =>
                                            'object',

                                            'additionalProperties' =>
                                            false,

                                            'properties' => [
                                                'pdf_field' => [
                                                    'type' =>
                                                    'string',
                                                ],

                                                'value' => [
                                                    'anyOf' => [
                                                        [
                                                            'type' =>
                                                            'string',
                                                        ],
                                                        [
                                                            'type' =>
                                                            'array',

                                                            'items' => [
                                                                'type' =>
                                                                'string',
                                                            ],
                                                        ],
                                                        [
                                                            'type' =>
                                                            'null',
                                                        ],
                                                    ],
                                                ],

                                                'checked' => [
                                                    'anyOf' => [
                                                        [
                                                            'type' =>
                                                            'boolean',
                                                        ],
                                                        [
                                                            'type' =>
                                                            'null',
                                                        ],
                                                    ],
                                                ],

                                                'confidence' => [
                                                    'type' =>
                                                    'number',
                                                ],
                                            ],

                                            'required' => [
                                                'pdf_field',
                                                'value',
                                                'checked',
                                                'confidence',
                                            ],
                                        ],
                                    ],
                                ],

                                'required' => [
                                    'fields',
                                ],
                            ],
                        ],
                    ],
                ]
            );
        } finally {
            $file_api->delete(
                $file_id
            );
        }
    }

    public function response(
        string $prompt
    ): string {
        $builder =
            new Prompt();

        $response =
            $this->request(
                [
                    'model' =>
                    $this->model,

                    'input' => [
                        [
                            'role' =>
                            'user',

                            'content' =>
                            $builder->response(
                                $prompt
                            ),
                        ],
                    ],
                ]
            );

        return $response['text'] ?? '';
    }

    protected function request(
        array $payload
    ): array {
        if (!$this->api_key) {
            $this->last_error =
                __(
                    'OpenAI API key is missing.',
                    'best-connect-onboarding'
                );

            return [];
        }

        $response =
            wp_remote_post(
                'https://api.openai.com/v1/responses',
                [
                    'timeout' =>
                    $this->timeout,

                    'headers' => [
                        'Authorization' =>
                        'Bearer ' .
                            $this->api_key,

                        'Content-Type' =>
                        'application/json',
                    ],

                    'body' =>
                    wp_json_encode(
                        $payload
                    ),
                ]
            );

        if (
            is_wp_error(
                $response
            )
        ) {
            $this->last_error =
                $response->get_error_message();

            return [];
        }

        $body =
            json_decode(
                wp_remote_retrieve_body(
                    $response
                ),
                true
            );

        if (
            isset(
                $body['error']
            )
        ) {
            $this->last_error =
                $body['error']['message']
                ??
                __(
                    'Unknown OpenAI error.',
                    'best-connect-onboarding'
                );

            return [];
        }

        $text = '';

        foreach (
            ($body['output'] ?? [])
            as $output
        ) {
            foreach (
                ($output['content'] ?? [])
                as $content
            ) {
                if (
                    'output_text' !==
                    ($content['type'] ?? '')
                ) {
                    continue;
                }

                $text .=
                    $content['text'] ?? '';
            }
        }

        if (!$text) {
            $this->last_error =
                __(
                    'OpenAI returned an empty response.',
                    'best-connect-onboarding'
                );

            return [];
        }

        $json =
            json_decode(
                $text,
                true
            );

        if (
            JSON_ERROR_NONE ===
            json_last_error()
        ) {
            return $json;
        }

        $this->last_error =
            __(
                'OpenAI returned invalid JSON.',
                'best-connect-onboarding'
            );

        return [];
    }

    public function get_last_error(): ?string
    {
        return $this->last_error;
    }
}
