<?php

namespace BestConnectOnboarding\Frontend;

defined('ABSPATH') || exit;

use BestConnectOnboarding\Forms\FormRepository;
use BestConnectOnboarding\Payments\Stripe;

class Shortcode
{
    private static $form_repo;
    public static function init()
    {
        self::$form_repo = new FormRepository();
        add_shortcode('bcob_form', [__CLASS__, 'render']);
    }

    public static function render($atts)
    {
        $atts =
            shortcode_atts(
                [
                    'id' =>
                    0,

                    'membership' =>
                    0,

                    'portal_index' =>
                    '',
                ],
                $atts
            );

        $form_id =
            absint(
                $atts['id']
            );

        if (!$form_id) {
            return __(
                'No form found.',
                'best-connect-onboarding'
            );
        }

        $buffer_level =
            ob_get_level();

        try {
            $form =
                self::$form_repo->get(
                    $form_id
                );

            if (!$form) {
                return __(
                    'Form not found.',
                    'best-connect-onboarding'
                );
            }

            $schema =
                json_decode(
                    (string) $form->form_schema,
                    true
                );

            $requires_payment = false;

            if (is_array($schema)) {
                foreach (
                    ($schema['steps'] ?? [])
                    as $step
                ) {
                    if (!is_array($step)) {
                        continue;
                    }

                    foreach (
                        ($step['fields'] ?? [])
                        as $field
                    ) {
                        if (
                            is_array($field) &&
                            sanitize_key(
                                $field['type'] ?? ''
                            ) === 'payment'
                        ) {
                            $requires_payment = true;
                            break 2;
                        }
                    }
                }
            }

            $membership_context =
                null;

            $membership_id =
                absint(
                    $atts['membership']
                );

            $portal_index =
                $atts['portal_index'] !== ''
                ? absint(
                    $atts['portal_index']
                )
                : null;

            if (
                $requires_payment &&
                (
                    !$membership_id ||
                    $portal_index === null
                )
            ) {
                return __(
                    'Payment context is missing for this form.',
                    'best-connect-onboarding'
                );
            }

            if ($membership_id) {
                if ($portal_index === null) {
                    return __(
                        'Membership version is required.',
                        'best-connect-onboarding'
                    );
                }

                $membership =
                    get_post(
                        $membership_id
                    );

                if (
                    !$membership ||
                    $membership->post_type !==
                    'bcob_membership' ||
                    $membership->post_status !==
                    'publish'
                ) {
                    return __(
                        'Invalid membership.',
                        'best-connect-onboarding'
                    );
                }

                $versions =
                    get_post_meta(
                        $membership_id,
                        '_bcob_versions',
                        true
                    );

                if (
                    !is_array($versions) ||
                    !array_key_exists(
                        $portal_index,
                        $versions
                    ) ||
                    !is_array(
                        $versions[$portal_index]
                    )
                ) {
                    return __(
                        'Invalid membership version.',
                        'best-connect-onboarding'
                    );
                }

                $version =
                    $versions[$portal_index];

                if (
                    absint(
                        $version['form']
                            ?? 0
                    ) !== $form_id
                ) {
                    return __(
                        'This form is not assigned to the selected membership.',
                        'best-connect-onboarding'
                    );
                }

                $membership_context = [
                    'id' =>
                    $membership_id,

                    'portal_index' =>
                    $portal_index,

                    'form' =>
                    $form_id,

                    'context_signature' =>
                    Stripe::create_offer_signature(
                        $membership_id,
                        $portal_index,
                        $form_id
                    ),

                    'icon' =>
                    absint(
                        $version['icon']
                            ?? 0
                    ),

                    'title' =>
                    sanitize_text_field(
                        $version['title']
                            ?? ''
                    ),

                    'description' =>
                    wp_kses_post(
                        $version['description']
                            ?? ''
                    ),

                    'items' =>
                    is_array(
                        $version['items']
                            ?? null
                    )
                        ? $version['items']
                        : [],
                ];
            }

            ob_start();

            self::load_template(
                'frontend/form',
                [
                    'form_data' =>
                    $form,

                    'membership_context' =>
                    $membership_context,
                ]
            );

            return ob_get_clean();
        } catch (\Throwable $e) {
            while (
                ob_get_level() >
                $buffer_level
            ) {
                ob_end_clean();
            }

            return esc_html(
                $e->getMessage()
            );
        }
    }

    protected static function load_template($template, array $vars = [])
    {
        extract($vars, EXTR_SKIP);

        require BCOB_PATH . 'templates/' . $template . '.php';
    }
}
