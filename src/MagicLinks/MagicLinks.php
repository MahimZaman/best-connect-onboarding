<?php

namespace BestConnectOnboarding\MagicLinks;

use BestConnectOnboarding\Emails\Mailer;
use BestConnectOnboarding\Leads\Lead;
use BestConnectOnboarding\Membership\MembershipNumber;
use BestConnectOnboarding\Middleware\Middleware;
use BestConnectOnboarding\Middleware\Payload;
use BestConnectOnboarding\PDFs\PdfManager;
use BestConnectOnboarding\Helpers\Logger;

defined('ABSPATH') || exit;

class MagicLinks
{
    private const ACTION = 'bcob_verify_member';

    public function __construct()
    {
        add_action(
            'template_redirect',
            [$this, 'handle_request']
        );
    }

    public function generate(
        int $lead_id,
        string $page_url
    ): string {
        if (!$lead_id) {
            throw new \InvalidArgumentException(
                'Lead ID is required.'
            );
        }

        $token = wp_generate_password(
            64,
            false,
            false
        );

        MagicLinkRepository::create(
            $lead_id,
            $token,
            wp_date(
                'Y-m-d H:i:s',
                time() + DAY_IN_SECONDS
            )
        );

        return $token;
    }

    public function url(
        string $token,
        string $page_url
    ): string {
        return add_query_arg(
            [
                'bcob_action' => self::ACTION,
                'token' => rawurlencode($token),
            ],
            $page_url ?: home_url('/')
        );
    }

    public function handle_request(): void
    {
        if (
            ($_GET['bcob_action'] ?? '') !==
            self::ACTION
        ) {
            return;
        }

        $token = sanitize_text_field(
            wp_unslash(
                $_GET['token'] ?? ''
            )
        );

        $result = $this->verify($token);

        $redirect = $result['url'] ?? home_url('/');

        if (!empty($result['success'])) {
            $redirect = add_query_arg(
                [
                    'success' => $result['lead_token'],
                    'verified' => 1,
                ],
                $redirect
            );
        } else {
            $redirect = add_query_arg(
                [
                    'verification_error' => 1,
                    'success' => $result['lead_token'] ?? '',
                ],
                $redirect
            );
        }

        wp_safe_redirect($redirect);

        exit;
    }

    public function verify(string $token): array
    {
        if ($token === '') {
            return [
                'success' => false,
                'url' => home_url('/'),
            ];
        }

        $magic_link =
            MagicLinkRepository::get_by_token($token);

        if (!$magic_link) {
            return [
                'success' => false,
                'url' => home_url('/'),
            ];
        }

        $lead =
            Lead::get(
                (int) $magic_link->lead_id
            );

        if (!$lead) {
            return [
                'success' => false,
                'url' => home_url('/'),
            ];
        }

        $submission = json_decode(
            $lead->submission ?? '',
            true
        );

        if (!is_array($submission)) {
            return [
                'success' => false,
                'url' => home_url('/'),
            ];
        }

        $page_url = esc_url_raw(
            $submission['page_url'] ?? home_url('/')
        );

        if ((int) $magic_link->is_used === 1) {
            Logger::warning(
                'magic_link',
                'Magic Link verification rejected because the link was already used.',
                [
                    'operation' =>
                    'verify_magic_link',

                    'lead_id' =>
                    (int) $lead->id,

                    'magic_link_id' =>
                    (int) $magic_link->id,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            return [
                'success' => false,
                'url' => $page_url,
                'lead_token' => $lead->token,
            ];
        }

        if (
            empty($magic_link->expires_at) ||
            current_time('timestamp') >
            strtotime($magic_link->expires_at)
        ) {
            Logger::warning(
                'magic_link',
                'Magic Link verification rejected because the link expired.',
                [
                    'operation' =>
                    'verify_magic_link',

                    'lead_id' =>
                    (int) $lead->id,

                    'magic_link_id' =>
                    (int) $magic_link->id,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            return [
                'success' => false,
                'url' => $page_url,
                'lead_token' => $lead->token,
            ];
        }

        if (
            !in_array(
                $lead->status,
                [
                    'paid_pending_verification',
                ],
                true
            )
        ) {
            Logger::warning(
                'magic_link',
                'Magic Link verification rejected because the lead status is not eligible.',
                [
                    'operation' =>
                    'verify_magic_link',

                    'lead_id' =>
                    (int) $lead->id,

                    'magic_link_id' =>
                    (int) $magic_link->id,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),

                    'status' =>
                    sanitize_key(
                        (string) $lead->status
                    ),
                ]
            );

            return [
                'success' => false,
                'url' => $page_url,
                'lead_token' => $lead->token,
            ];
        }

        $signature_ip =
            sanitize_text_field(
                $_SERVER['REMOTE_ADDR'] ?? ''
            );

        $signature_datetime =
            current_time('mysql');

        try {
            $signed_contract =
                PdfManager::finalize_magic_link_signature(
                    (int) $lead->id,
                    $signature_ip,
                    $signature_datetime,
                    (int) $magic_link->id
                );

            if (!$signed_contract) {
                throw new \RuntimeException(
                    'Unable to finalize signed contract.'
                );
            }

            $lead =
                Lead::get(
                    (int) $lead->id
                );

            if (!$lead) {
                throw new \RuntimeException(
                    'Lead not found after signing.'
                );
            }

            $submission =
                json_decode(
                    $lead->submission ?? '',
                    true
                );

            if (!is_array($submission)) {
                throw new \RuntimeException(
                    'Invalid lead submission.'
                );
            }

            Mailer::send_verified_contracts(
                $lead
            );

            Middleware::post(
                Payload::build(
                    $submission
                ),
                (int) $lead->id
            );

            MembershipNumber::allocate(
                (int) $lead->id
            );

            if (
                !Lead::update_status(
                    (int) $lead->id,
                    'completed'
                )
            ) {
                throw new \RuntimeException(
                    'Unable to complete lead.'
                );
            }

            if (
                !MagicLinkRepository::mark_used(
                    (int) $magic_link->id,
                    $signature_ip,
                    $signature_datetime
                )
            ) {
                throw new \RuntimeException(
                    'Magic Link could not be marked as used.'
                );
            }

            do_action(
                'bcob_submission_completed',
                (int) $lead->id,
                $submission
            );

            Logger::info(
                'magic_link',
                'Membership verification completed.',
                [
                    'operation' =>
                    'verify_magic_link',

                    'lead_id' =>
                    (int) $lead->id,

                    'magic_link_id' =>
                    (int) $magic_link->id,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            return [
                'success' => true,
                'url' => $page_url,
                'lead_token' => $lead->token,
            ];
        } catch (\Throwable $e) {
            Logger::error(
                'magic_link',
                'Membership verification failed.',
                [
                    'operation' =>
                    'verify_magic_link',

                    'lead_id' =>
                    (int) $lead->id,

                    'magic_link_id' =>
                    (int) $magic_link->id,

                    'form_id' =>
                    absint(
                        $submission['form']['id']
                            ?? 0
                    ),
                ]
            );

            return [
                'success' => false,
                'url' => $page_url,
                'lead_token' => $lead->token,
            ];
        }
    }
}
