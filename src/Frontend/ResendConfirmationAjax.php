<?php

namespace BestConnectOnboarding\Frontend;

use BestConnectOnboarding\Emails\Mailer;
use BestConnectOnboarding\Leads\Lead;
use BestConnectOnboarding\MagicLinks\MagicLinks;
use BestConnectOnboarding\Helpers\Logger;

defined('ABSPATH') || exit;

class ResendConfirmationAjax
{
    public function __construct()
    {
        add_action(
            'wp_ajax_bcob_resend_confirmation',
            [$this, 'resend']
        );

        add_action(
            'wp_ajax_nopriv_bcob_resend_confirmation',
            [$this, 'resend']
        );
    }

    public function resend(): void
    {
        check_ajax_referer(
            'bcob_nonce',
            'nonce'
        );

        try {
            $lead_token =
                sanitize_text_field(
                    wp_unslash(
                        $_POST['lead_token'] ?? ''
                    )
                );

            if (!$lead_token) {
                throw new \Exception(
                    __(
                        'Lead token is missing.',
                        'best-connect-onboarding'
                    )
                );
            }

            $lead =
                Lead::get_by_token(
                    $lead_token
                );

            if (!$lead) {
                throw new \Exception(
                    __(
                        'Lead not found.',
                        'best-connect-onboarding'
                    )
                );
            }

            if ($lead->status === 'completed') {
                throw new \Exception(
                    __(
                        'This membership has already been confirmed.',
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

            $magic_links =
                new MagicLinks();

            $verification_token =
                $magic_links->generate(
                    (int) $lead->id,
                    $page_url
                );

            $verification_url =
                $magic_links->url(
                    $verification_token,
                    $page_url
                );

            // Enable this code incase you are not getting links in your E-mail this will allow you debug the urls. 

            // error_log(
            //     '[BCOB Magic Link] Verification URL: ' .
            //         $verification_url
            // );

            if (
                !Mailer::send_confirmation(
                    $submission,
                    $verification_url
                )
            ) {
                throw new \Exception(
                    __(
                        'The confirmation email could not be sent.',
                        'best-connect-onboarding'
                    )
                );
            }

            Logger::info(
                'mail',
                'Confirmation email resent.',
                [
                    'operation' =>
                    'resend_confirmation',

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),

                    'lead_id' =>
                    (int) $lead->id,
                ]
            );

            wp_send_json_success(
                [
                    'message' =>
                    __(
                        'Confirmation email sent successfully.',
                        'best-connect-onboarding'
                    ),
                ]
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
}
