<?php

namespace BestConnectOnboarding\Emails;

use BestConnectOnboarding\Helpers\Logger;
use BestConnectOnboarding\PDFs\PdfManager;
use BestConnectOnboarding\Leads\Lead;

defined('ABSPATH') || exit;

class Mailer
{
    public static function get_headers(
        array $submission
    ): array {
        $settings =
            Templates::get_settings(
                $submission
            );

        $company_name =
            sanitize_text_field(
                $settings['company_name']
                    ?? ''
            );

        $sender_name =
            sanitize_text_field(
                $settings['sender_name']
                    ?? ''
            );

        $sender_email =
            sanitize_email(
                $settings['sender_email']
                    ?? ''
            );

        if ($company_name === '') {
            $company_name =
                sanitize_text_field(
                    get_bloginfo('name')
                );
        }

        if ($sender_name === '') {
            $sender_name =
                $company_name;
        }

        if (!is_email($sender_email)) {
            $sender_email =
                sanitize_email(
                    get_option('admin_email')
                );
        }

        $sender_name =
            str_replace(
                ["\r", "\n"],
                '',
                $sender_name
            );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        if (
            $sender_name !== '' &&
            is_email($sender_email)
        ) {
            $headers[] =
                'From: ' .
                $sender_name .
                ' <' .
                $sender_email .
                '>';

            $headers[] =
                'Reply-To: ' .
                $sender_name .
                ' <' .
                $sender_email .
                '>';
        }

        return $headers;
    }

    public static function send_confirmation(
        array $submission,
        string $verification_url
    ): bool {
        $email =
            self::get_email(
                $submission
            );

        if (!$email) {
            Logger::error(
                'mail',
                'Customer confirmation email address was not available.',
                self::get_log_context(
                    $submission,
                    'send_confirmation'
                )
            );

            return false;
        }

        $settings =
            Templates::get_settings(
                $submission
            );

        $subject =
            $settings['customer_subject']
            ?? '';

        $template =
            $settings['customer_template']
            ?? '';

        if (!$subject || !$template) {
            Logger::error(
                'mail',
                'Confirmation email settings are incomplete.',
                self::get_log_context(
                    $submission,
                    'send_confirmation'
                )
            );

            return false;
        }

        $extra = [
            'verification_link' =>
            esc_url(
                $verification_url
            ),

            'validation_url' =>
            esc_url(
                $verification_url
            ),
        ];

        $subject =
            Templates::render(
                $subject,
                $submission,
                $extra
            );

        $body =
            Templates::render(
                $template,
                $submission,
                $extra
            );

        $attachments =
            self::get_contract_files(
                $submission
            );

        $sent =
            wp_mail(
                $email,
                $subject,
                $body,
                self::get_headers(
                    $submission
                ),
                $attachments
            );

        if (!$sent) {
            Logger::error(
                'mail',
                'Customer confirmation email could not be sent.',
                self::get_log_context(
                    $submission,
                    'send_confirmation'
                )
            );

            return false;
        }

        Logger::info(
            'mail',
            'Customer confirmation email sent.',
            self::get_log_context(
                $submission,
                'send_confirmation'
            )
        );

        return true;
    }

    public static function send_backoffice(
        array $submission
    ): bool {
        $settings =
            Templates::get_settings(
                $submission
            );

        $email =
            sanitize_email(
                $settings['admin_email'] ?? ''
            );

        $subject =
            $settings['admin_subject']
            ?? '';

        $template =
            $settings['admin_template']
            ?? '';

        if (
            !$email ||
            !is_email($email) ||
            !$subject ||
            !$template
        ) {
            Logger::error(
                'mail',
                'Back-office email settings are missing or invalid.',
                self::get_log_context(
                    $submission,
                    'send_backoffice'
                )
            );

            return false;
        }

        $subject =
            Templates::render(
                $subject,
                $submission
            );

        $body =
            Templates::render(
                $template,
                $submission
            );

        $sent =
            wp_mail(
                $email,
                $subject,
                $body,
                self::get_headers(
                    $submission
                )
            );

        if (!$sent) {
            Logger::error(
                'mail',
                'Back-office email could not be sent.',
                self::get_log_context(
                    $submission,
                    'send_backoffice'
                )
            );

            return false;
        }

        Logger::info(
            'mail',
            'Back-office email sent.',
            self::get_log_context(
                $submission,
                'send_backoffice'
            )
        );

        return true;
    }

    public static function send_verified_contracts(
        $lead
    ): bool {
        $submission =
            self::get_submission(
                $lead
            );

        if (!$submission) {
            return false;
        }

        $email =
            self::get_email(
                $submission
            );

        if (!$email) {
            return false;
        }

        $form_name =
            $submission['form']['title']
            ?? $submission['form']['name']
            ?? '';

        $subject =
            $form_name
            ? sprintf(
                'Ihre %s Dokumente sind fertig.',
                $form_name
            )
            : 'Ihre Unterlagen liegen bereit';

        $body =
            '<p>Hallo,</p>';

        $body .=
            '<p>Vielen Dank, dass Sie Ihren Onboarding-Prozess abgeschlossen haben.</p>';

        $body .=
            '<p>Ihre Dokumente wurden geprüft und sind dieser E-Mail beigefügt.</p>';

        if ($form_name) {
            $body .=
                '<p><strong>Form:</strong> ' .
                esc_html($form_name) .
                '</p>';
        }

        $body .=
            '<p>Bitte beachten Sie, dass dieser E-Mail Ihr bestätigter Vertrag beigefügt ist.</p>';

        $settings =
            Templates::get_settings(
                $submission
            );

        $company_name =
            sanitize_text_field(
                $settings['company_name']
                    ?? ''
            );

        if ($company_name === '') {
            $company_name =
                get_bloginfo('name');
        }

        $body .=
            '<p>Mit freundlichen Grüße,<br>' .
            esc_html($company_name) .
            '</p>';

        $attachments =
            self::get_contract_files(
                $submission
            );

        $headers =
            self::get_headers(
                $submission
            );

        $customer_sent =
            wp_mail(
                $email,
                $subject,
                $body,
                $headers,
                $attachments
            );

        $settings =
            Templates::get_settings(
                $submission
            );

        $backoffice_email =
            sanitize_email(
                $settings['admin_email'] ?? ''
            );

        $backoffice_sent = true;

        $backoffice_status =
            'not_configured';

        if (
            $backoffice_email &&
            is_email($backoffice_email)
        ) {
            $backoffice_sent =
                wp_mail(
                    $backoffice_email,
                    $subject,
                    $body,
                    $headers,
                    $attachments
                );

            $backoffice_status =
                $backoffice_sent
                ? 'success'
                : 'failed';
        }

        $lead_id =
            is_object($lead)
            ? absint(
                $lead->id ?? 0
            )
            : absint(
                $lead['id'] ?? 0
            );

        if ($lead_id) {
            $latest_lead =
                Lead::get(
                    $lead_id
                );

            if ($latest_lead) {
                $latest_submission =
                    self::get_submission(
                        $latest_lead
                    );

                if (
                    !isset(
                        $latest_submission['delivery']
                    ) ||
                    !is_array(
                        $latest_submission['delivery']
                    )
                ) {
                    $latest_submission['delivery'] =
                        [];
                }

                $existing_state =
                    $latest_submission['delivery']['backoffice_contract_email'] ?? [];

                if (
                    !is_array($existing_state)
                ) {
                    $existing_state = [];
                }

                if (
                    ($existing_state['status'] ?? '') !==
                    'success'
                ) {
                    $latest_submission['delivery']['backoffice_contract_email'] = [
                        'status' =>
                        $backoffice_status,

                        'recipient' =>
                        $backoffice_email,

                        'last_attempt_at' =>
                        current_time('mysql'),

                        'sent_at' =>
                        $backoffice_status === 'success'
                            ? current_time('mysql')
                            : '',
                    ];

                    if (
                        !Lead::update_submission(
                            $lead_id,
                            $latest_submission
                        )
                    ) {
                        Logger::error(
                            'mail',
                            'Back-office contract email delivery state could not be saved.',
                            [
                                'operation' =>
                                'send_verified_contracts',

                                'lead_id' =>
                                $lead_id,
                            ]
                        );
                    }
                }
            }
        }

        return $customer_sent &&
            $backoffice_sent;
    }

    private static function get_log_context(
        array $submission,
        string $operation
    ): array {
        return [
            'operation' =>
            $operation,

            'form_id' =>
            absint(
                $submission['form']['id']
                    ?? 0
            ),

            'lead_id' =>
            absint(
                $submission['lead_id']
                    ?? 0
            ),
        ];
    }

    private static function get_email(
        array $submission
    ): string {
        foreach (
            ($submission['values'] ?? [])
            as $field
        ) {
            if (
                ($field['type'] ?? '') !==
                'email'
            ) {
                continue;
            }

            $email =
                sanitize_email(
                    $field['value'] ?? ''
                );

            if (
                is_email($email)
            ) {
                return $email;
            }
        }

        return '';
    }

    private static function get_submission(
        $lead
    ): array {
        $submission = [];

        if (
            is_array($lead)
        ) {
            $submission =
                $lead['submission'] ??
                [];
        } elseif (
            is_object($lead)
        ) {
            $submission =
                $lead->submission ??
                [];
        }

        if (
            is_string($submission)
        ) {
            $submission =
                json_decode(
                    $submission,
                    true
                );
        }

        return is_array($submission)
            ? $submission
            : [];
    }

    private static function get_contract_files(
        array $submission
    ): array {
        $files = [];

        foreach (
            ($submission['contracts'] ?? [])
            as $contract
        ) {
            if (!is_array($contract)) {
                continue;
            }

            $attachment_id =
                absint(
                    $contract['attachment_id']
                        ?? $contract['id']
                        ?? 0
                );

            if (!$attachment_id) {
                continue;
            }

            $path =
                PdfManager::get_contract_file_path(
                    $attachment_id
                );

            if (
                $path &&
                is_file($path) &&
                is_readable($path)
            ) {
                $files[] = $path;
            }
        }

        return array_values(
            array_unique($files)
        );
    }
}
