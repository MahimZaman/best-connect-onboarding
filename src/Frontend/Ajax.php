<?php

namespace BestConnectOnboarding\Frontend;

use BestConnectOnboarding\Payments\Payment;
use BestConnectOnboarding\Leads\Lead;
use BestConnectOnboarding\PDFs\PdfManager;
use BestConnectOnboarding\MagicLinks\MagicLinks;
use BestConnectOnboarding\Emails\Mailer;
use BestConnectOnboarding\Memberships\MembershipNumber;
use BestConnectOnboarding\Forms\FormRepository;
use BestConnectOnboarding\Helpers\Logger;

defined('ABSPATH') || exit;

class Ajax
{
    public function __construct()
    {
        new MagicLinks();
        new ResendConfirmationAjax();

        add_action(
            'wp_ajax_bcob_ai_extract',
            [$this, 'ai_extract']
        );

        add_action(
            'wp_ajax_nopriv_bcob_ai_extract',
            [$this, 'ai_extract']
        );

        add_action(
            'wp_ajax_bcob_submit',
            [$this, 'submit']
        );

        add_action(
            'wp_ajax_nopriv_bcob_submit',
            [$this, 'submit']
        );

        add_action(
            'wp_ajax_bcob_confirm_pdf',
            [$this, 'confirm_pdf']
        );

        add_action(
            'wp_ajax_nopriv_bcob_confirm_pdf',
            [$this, 'confirm_pdf']
        );

        add_action(
            'wp_ajax_bcob_contract_file',
            [PdfManager::class, 'serve_contract']
        );

        add_action(
            'wp_ajax_nopriv_bcob_contract_file',
            [PdfManager::class, 'serve_contract']
        );

        add_action(
            'wp_ajax_bcob_refresh_nonces',
            [$this, 'refresh_nonces']
        );

        add_action(
            'wp_ajax_nopriv_bcob_refresh_nonces',
            [$this, 'refresh_nonces']
        );
    }

    public static function refresh_nonces(): void
    {
        nocache_headers();

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0',
            true
        );

        wp_send_json_success(
            [
                'frontend' =>
                wp_create_nonce(
                    'bcob_nonce'
                ),

                'payment' =>
                wp_create_nonce(
                    'bcob_frontend'
                ),

                'load_form' =>
                wp_create_nonce(
                    'bcob_load_form'
                ),
            ]
        );
    }

    public static function submit(): void
    {
        check_ajax_referer(
            'bcob_nonce',
            'nonce'
        );

        try {
            $submission =
                json_decode(
                    wp_unslash(
                        $_POST['submission'] ?? ''
                    ),
                    true
                );

            if (!is_array($submission)) {
                throw new \Exception(
                    __(
                        'Invalid submission data.',
                        'best-connect-onboarding'
                    )
                );
            }

            $page_url =
                esc_url_raw(
                    wp_unslash(
                        $_POST['page_url'] ?? ''
                    )
                );

            if (!$page_url) {
                $page_url = home_url('/');
            }

            $lead_id =
                absint(
                    $submission['lead_id'] ?? 0
                );

            $lead_token =
                sanitize_text_field(
                    $submission['lead_token'] ?? ''
                );

            if ($lead_id) {
                if (!$lead_token) {
                    throw new \Exception(
                        __(
                            'Lead authorization is required.',
                            'best-connect-onboarding'
                        )
                    );
                }

                $lead =
                    Lead::get_by_token(
                        $lead_token
                    );

                if (
                    !$lead ||
                    (int) $lead->id !== $lead_id
                ) {
                    throw new \Exception(
                        __(
                            'Invalid lead authorization.',
                            'best-connect-onboarding'
                        )
                    );
                }
            } else {
                $lead =
                    Lead::create(
                        $submission
                    );

                $lead_id =
                    (int) $lead['id'];

                $lead_token =
                    $lead['token'];
            }

            $submission['lead_id'] =
                $lead_id;

            $lead =
                Lead::get(
                    $lead_id
                );

            if (!$lead) {
                throw new \Exception(
                    __(
                        'Lead could not be identified.',
                        'best-connect-onboarding'
                    )
                );
            }

            $submission['form'] =
                is_array(
                    $submission['form'] ?? null
                )
                ? $submission['form']
                : [];

            $submission['form']['id'] =
                (int) $lead->form_id;

            $stored_submission =
                json_decode(
                    $lead->submission ?? '',
                    true
                );

            if (
                is_array(
                    $stored_submission
                ) &&
                !empty($stored_submission['payment_context']) &&
                is_array(
                    $stored_submission['payment_context']
                )
            ) {
                $payment_context =
                    $stored_submission['payment_context'];

                if (
                    absint(
                        $payment_context['form_id'] ?? 0
                    ) !==
                    (int) $lead->form_id
                ) {
                    throw new \Exception(
                        __(
                            'Invalid payment context.',
                            'best-connect-onboarding'
                        )
                    );
                }

                $submission['payment_context'] =
                    $payment_context;

                $submission['membership'] = [
                    'id' =>
                    absint(
                        $payment_context['membership_id'] ?? 0
                    ),

                    'portal_index' =>
                    absint(
                        $payment_context['portal_index'] ?? 0
                    ),

                    'form' =>
                    (int) $lead->form_id,

                    'context_signature' =>
                    sanitize_text_field(
                        $stored_submission['membership']['context_signature'] ?? ''
                    ),
                ];

                $snapshot =
                    $payment_context['price_snapshot'] ?? [];

                if (is_array($snapshot)) {
                    $submission['products'] =
                        $snapshot['items'] ?? [];

                    $submission['subtotal'] =
                        (float) (
                            $snapshot['subtotal']
                            ?? 0
                        );

                    $submission['discount_total'] =
                        (float) (
                            $snapshot['discount']
                            ?? 0
                        );

                    $submission['total'] =
                        (float) (
                            $snapshot['total']
                            ?? 0
                        );

                    $submission['amount'] =
                        (float) (
                            $snapshot['total']
                            ?? 0
                        );

                    $submission['currency'] =
                        sanitize_text_field(
                            $snapshot['currency']
                                ?? ''
                        );
                }

                if (
                    isset(
                        $stored_submission['discount']
                    )
                ) {
                    $submission['discount'] =
                        $stored_submission['discount'];
                }
            }

            $submission['lead_token'] =
                $lead_token;

            $submission['page_url'] =
                $page_url;

            if (
                !Lead::update_submission_by_token(
                    $lead_token,
                    $submission
                )
            ) {
                throw new \Exception(
                    __(
                        'Lead could not be updated.',
                        'best-connect-onboarding'
                    )
                );
            }

            $payment_required =
                self::is_payment_required(
                    $submission
                );

            if ($payment_required) {
                $payment =
                    Payment::get_latest_for_lead(
                        $lead_id
                    );

                if (
                    !$payment ||
                    $payment->status !== 'succeeded'
                ) {
                    throw new \Exception(
                        __(
                            'The payment has not been completed.',
                            'best-connect-onboarding'
                        )
                    );
                }
            }

            $result =
                self::complete_paid_lead(
                    $lead_id
                );

            wp_send_json_success(
                $result
            );
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' =>
                $e->getMessage(),
            ]);
        }
    }

    public static function complete_paid_lead(
        int $lead_id
    ): array {
        $lead =
            Lead::get($lead_id);

        if (!$lead) {
            throw new \Exception(
                __(
                    'Lead not found.',
                    'best-connect-onboarding'
                )
            );
        }

        $submission =
            json_decode(
                $lead->submission ?? '',
                true
            );

        if (!is_array($submission)) {
            throw new \Exception(
                __(
                    'Invalid lead submission.',
                    'best-connect-onboarding'
                )
            );
        }

        $page_url =
            esc_url_raw(
                $submission['page_url']
                    ?? home_url('/')
            );

        $payment_required =
            self::is_payment_required(
                $submission
            );

        if ($payment_required) {
            $payment =
                Payment::get_latest_for_lead(
                    $lead_id
                );

            if (
                !$payment ||
                $payment->status !== 'succeeded'
            ) {
                throw new \Exception(
                    __(
                        'The payment has not been completed.',
                        'best-connect-onboarding'
                    )
                );
            }

            $stored_response =
                json_decode(
                    $payment->response ?? '',
                    true
                );

            if (
                is_array($stored_response) &&
                !empty($stored_response)
            ) {
                $submission['payment']['response'] =
                    $stored_response;
            }

            $submission['payment']['status'] =
                'succeeded';

            $submission['payment']['payment_intent_id'] =
                $payment->payment_intent_id;

            $submission['payment']['subscription_id'] =
                $payment->subscription_id;

            $submission['payment']['customer_id'] =
                $payment->customer_id;

            $submission['payment']['invoice_id'] =
                $payment->invoice_id;

            $submission['payment']['type'] =
                $payment->payment_type;
        } else {
            $submission['payment'] = [
                'required' => false,
                'status'   => 'not_required',
            ];
        }

        $contracts =
            $submission['contracts'] ?? [];

        if (!is_array($contracts) || empty($contracts)) {
            Logger::info(
                'pdf',
                'Starting contract generation.',
                [
                    'operation' =>
                    'complete_paid_lead',

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),

                    'lead_id' =>
                    $lead_id,
                ]
            );

            $contracts =
                PdfManager::generate_for_submission(
                    $submission,
                    $lead_id
                );

            Logger::info(
                'pdf',
                'Contract generation returned.',
                [
                    'operation' =>
                    'complete_paid_lead',

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),

                    'lead_id' =>
                    $lead_id,

                    'status' =>
                    empty($contracts)
                        ? 'empty'
                        : 'generated',
                ]
            );

            if (empty($contracts)) {
                throw new \Exception(
                    __(
                        'Unable to create the contract.',
                        'best-connect-onboarding'
                    )
                );
            }

            $submission['contracts'] =
                $contracts;
        }

        Lead::update_submission(
            $lead_id,
            $submission
        );

        $lead_token =
            sanitize_text_field(
                $lead->token
            );

        $preview_contracts =
            array_values(
                array_filter(
                    $contracts,
                    static function ($contract) {
                        return (
                            $contract['preview'] ?? true
                        ) !== false;
                    }
                )
            );

        $preview_contracts =
            array_map(
                static function ($contract) {
                    $contract['url'] =
                        PdfManager::get_contract_url(
                            absint(
                                $contract['attachment_id']
                                    ?? 0
                            )
                        );

                    return $contract;
                },
                $preview_contracts
            );

        if (!empty($preview_contracts)) {
            return [
                'lead_id' =>
                $lead_id,

                'lead_token' =>
                $lead_token,

                'contracts' =>
                $preview_contracts,
            ];
        }

        return self::complete_submission(
            $submission,
            $lead_id,
            $lead_token,
            $page_url
        );
    }

    private static function complete_submission(
        array $submission,
        int $lead_id,
        string $lead_token,
        string $page_url
    ): array {
        $lead =
            Lead::get($lead_id);

        if (
            $lead &&
            $lead->status === 'completed'
        ) {
            return [
                'lead_id' =>
                $lead_id,

                'lead_token' =>
                $lead_token,

                'redirect' =>
                add_query_arg(
                    [
                        'success' =>
                        $lead_token,
                    ],
                    $page_url
                ),
            ];
        }

        $magic_links =
            new MagicLinks();

        $verification_token =
            $magic_links->generate(
                $lead_id,
                $page_url
            );

        $verification_url =
            $magic_links->url(
                $verification_token,
                $page_url
            );

        Lead::update_status(
            $lead_id,
            'paid_pending_verification'
        );

        $confirmation_sent =
            Mailer::send_confirmation(
                $submission,
                $verification_url
            );

        if (!$confirmation_sent) {
            Logger::error(
                'mail',
                'Confirmation email could not be sent.',
                [
                    'operation' =>
                    'complete_submission',

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),

                    'lead_id' =>
                    $lead_id,
                ]
            );
        }

        $backoffice_sent =
            Mailer::send_backoffice(
                $submission
            );

        if (!$backoffice_sent) {
            Logger::error(
                'mail',
                'Back-office email could not be sent.',
                [
                    'operation' =>
                    'complete_submission',

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),

                    'lead_id' =>
                    $lead_id,
                ]
            );
        }

        if (
            $confirmation_sent &&
            $backoffice_sent
        ) {
            Logger::info(
                'mail',
                'Submission emails dispatched.',
                [
                    'operation' =>
                    'complete_submission',

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),

                    'lead_id' =>
                    $lead_id,
                ]
            );
        }

        return [
            'lead_id' =>
            $lead_id,

            'lead_token' =>
            $lead_token,

            'redirect' =>
            add_query_arg(
                [
                    'success' =>
                    $lead_token,
                ],
                $page_url
            ),

            'verification_url' =>
            defined('WP_DEBUG') &&
                WP_DEBUG
                ? $verification_url
                : '',
        ];
    }

    public static function confirm_pdf(): void
    {
        check_ajax_referer(
            'bcob_nonce',
            'nonce'
        );

        try {
            $lead_id =
                absint(
                    $_POST['lead_id'] ?? 0
                );

            $lead_token =
                sanitize_text_field(
                    wp_unslash(
                        $_POST['lead_token'] ?? ''
                    )
                );

            $attachment_id =
                absint(
                    $_POST['attachment_id'] ?? 0
                );

            $fields =
                json_decode(
                    wp_unslash(
                        $_POST['fields'] ?? '{}'
                    ),
                    true
                );

            if (
                !$lead_id ||
                !$lead_token ||
                !$attachment_id ||
                !is_array($fields)
            ) {
                throw new \Exception(
                    __(
                        'Invalid contract data.',
                        'best-connect-onboarding'
                    )
                );
            }

            $lead =
                Lead::get_by_token(
                    $lead_token
                );

            if (
                !$lead ||
                (int) $lead->id !== $lead_id
            ) {
                throw new \Exception(
                    __(
                        'Invalid lead authorization.',
                        'best-connect-onboarding'
                    )
                );
            }

            $final_contract =
                PdfManager::finalize_review_pdf(
                    $lead_id,
                    $attachment_id,
                    $fields
                );

            if (!$final_contract) {
                throw new \Exception(
                    __(
                        'Unable to finalize the reviewed contract.',
                        'best-connect-onboarding'
                    )
                );
            }

            $lead =
                Lead::get(
                    $lead_id
                );

            if (!$lead) {
                throw new \Exception(
                    __(
                        'Lead not found after contract finalization.',
                        'best-connect-onboarding'
                    )
                );
            }

            $submission =
                json_decode(
                    $lead->submission ?? '',
                    true
                );

            if (!is_array($submission)) {
                throw new \Exception(
                    __(
                        'Invalid lead submission.',
                        'best-connect-onboarding'
                    )
                );
            }

            $lead_token =
                sanitize_text_field(
                    $lead->token
                );

            $page_url =
                esc_url_raw(
                    $submission['page_url']
                        ?? home_url('/')
                );

            $result =
                self::complete_submission(
                    $submission,
                    $lead_id,
                    $lead_token,
                    $page_url
                );

            wp_send_json_success(
                $result
            );
        } catch (\Throwable $e) {
            wp_send_json_error(
                [
                    'message' =>
                    $e->getMessage(),
                ]
            );
        }
    }

    private static function is_payment_required(
        array $submission
    ): bool {
        $form_id =
            absint(
                $submission['form']['id'] ?? 0
            );

        if (!$form_id) {
            throw new \Exception(
                __(
                    'Form could not be identified.',
                    'best-connect-onboarding'
                )
            );
        }

        $repository =
            new FormRepository();

        $form =
            $repository->get(
                $form_id
            );

        if (!$form) {
            throw new \Exception(
                __(
                    'Form not found.',
                    'best-connect-onboarding'
                )
            );
        }

        $schema =
            json_decode(
                $form->form_schema ?? '',
                true
            );

        if (
            !is_array($schema) ||
            !isset($schema['steps']) ||
            !is_array($schema['steps'])
        ) {
            throw new \Exception(
                __(
                    'Invalid form schema.',
                    'best-connect-onboarding'
                )
            );
        }

        foreach ($schema['steps'] as $step) {
            if (
                !is_array($step) ||
                !isset($step['fields']) ||
                !is_array($step['fields'])
            ) {
                continue;
            }

            foreach ($step['fields'] as $field) {
                if (
                    is_array($field) &&
                    ($field['type'] ?? '') === 'payment'
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ai_extract(): void
    {
        check_ajax_referer(
            'bcob_nonce',
            'nonce'
        );

        if (
            empty($_FILES['file']) ||
            !is_array($_FILES['file'])
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'No file uploaded.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $file =
            $_FILES['file'];

        if (
            !isset($file['error']) ||
            $file['error'] !==
            UPLOAD_ERR_OK
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'File upload failed.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $max_size =
            7 * MB_IN_BYTES;

        if (
            (int) $file['size'] >
            $max_size
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'The uploaded file is too large. Maximum size is 7 MB.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $allowed_mimes = [
            'jpg' =>
            'image/jpeg',

            'jpeg' =>
            'image/jpeg',

            'png' =>
            'image/png',

            'gif' =>
            'image/gif',

            'heic' =>
            'image/heic',

            'pdf' =>
            'application/pdf',
        ];

        $file_type =
            wp_check_filetype_and_ext(
                $file['tmp_name'],
                $file['name'],
                $allowed_mimes
            );

        if (
            empty($file_type['ext']) ||
            empty($file_type['type']) ||
            !isset(
                $allowed_mimes[$file_type['ext']]
            ) ||
            $file_type['type'] !==
            $allowed_mimes[$file_type['ext']]
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'This file type is not supported.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $fields =
            json_decode(
                wp_unslash(
                    $_POST['fields'] ?? ''
                ),
                true
            );

        if (
            empty($fields) ||
            !is_array($fields)
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'Invalid field list.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $temporary_base =
            tempnam(
                sys_get_temp_dir(),
                'bcob_ai_'
            );

        if ($temporary_base === false) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'Unable to create a temporary upload.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $extension =
            sanitize_key(
                $file_type['ext']
            );

        $temporary =
            $temporary_base .
            '.' .
            $extension;

        @unlink(
            $temporary_base
        );

        if (
            !move_uploaded_file(
                $file['tmp_name'],
                $temporary
            )
        ) {
            @unlink(
                $temporary
            );

            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'Unable to process the uploaded file.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $ai =
            new \BestConnectOnboarding\AI\AI();

        try {
            $data =
                $ai->extract(
                    $temporary,
                    $fields
                );

            $error =
                $ai->get_last_error();
        } finally {
            if (
                file_exists(
                    $temporary
                )
            ) {
                @unlink(
                    $temporary
                );
            }
        }

        if (!empty($error)) {
            Logger::error(
                'ai',
                'AI document extraction failed.',
                [
                    'operation' =>
                    'ai_extract',

                    'form_id' =>
                    absint(
                        $_POST['form_id']
                            ?? 0
                    ),
                ]
            );

            wp_send_json_error(
                [
                    'message' =>
                    $error,
                ]
            );
        }

        wp_send_json_success(
            $data
        );
    }
}
