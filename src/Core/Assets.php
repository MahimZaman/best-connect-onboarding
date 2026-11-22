<?php

namespace BestConnectOnboarding\Core;

defined('ABSPATH') || exit;

class Assets
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'frontend']);
    }

    public function frontend()
    {
        wp_register_style(
            'bcob-remixicon',
            'https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css',
            [],
            BCOB_VER
        );

        wp_register_style(
            'bcob-frontend',
            BCOB_URL . 'assets/css/frontend/frontend.css',
            [],
            BCOB_VER
        );

        wp_register_style(
            'bcob-pdf-review',
            BCOB_URL . 'assets/css/frontend/pdf-review.css',
            [],
            BCOB_VER
        );

        wp_register_style(
            'bcob-stepper',
            BCOB_URL . 'assets/css/frontend/stepper.css',
            ['bcob-frontend'],
            BCOB_VER
        );

        wp_register_style(
            'bcob-fields',
            BCOB_URL . 'assets/css/frontend/fields.css',
            ['bcob-frontend'],
            BCOB_VER
        );

        wp_register_style(
            'bcob-sidebar',
            BCOB_URL . 'assets/css/frontend/sidebar.css',
            ['bcob-frontend'],
            BCOB_VER
        );

        wp_register_style(
            'bcob-buttons',
            BCOB_URL . 'assets/css/frontend/buttons.css',
            ['bcob-frontend'],
            BCOB_VER
        );

        wp_register_style(
            'bcob-upload',
            BCOB_URL . 'assets/css/frontend/upload.css',
            ['bcob-frontend'],
            BCOB_VER
        );

        wp_register_style(
            'bcob-animations',
            BCOB_URL . 'assets/css/frontend/animations.css',
            ['bcob-frontend'],
            BCOB_VER
        );

        wp_register_style(
            'bcob-thankyou',
            BCOB_URL . 'assets/css/frontend/thankyou.css',
            ['bcob-frontend'],
            BCOB_VER
        );

        wp_register_style(
            'bcob-responsive',
            BCOB_URL . 'assets/css/frontend/frontend-responsive.css',
            [
                'bcob-frontend',
                'bcob-stepper',
                'bcob-fields',
                'bcob-sidebar',
                'bcob-buttons',
                'bcob-upload',
                'bcob-animations',
            ],
            BCOB_VER
        );

        wp_register_script(
            'bcob-google-tags',
            BCOB_URL . 'assets/js/frontend/google-tags.js',
            ['jquery'],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-renderer',
            BCOB_URL . 'assets/js/frontend/renderer.js',
            ['jquery'],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-validator',
            BCOB_URL . 'assets/js/frontend/validator.js',
            ['jquery', 'bcob-renderer'],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-uploader',
            BCOB_URL . 'assets/js/frontend/uploader.js',
            ['jquery', 'bcob-renderer', 'bcob-google-tags'],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-sidebar',
            BCOB_URL . 'assets/js/frontend/sidebar.js',
            ['jquery'],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-progress',
            BCOB_URL . 'assets/js/frontend/progress.js',
            ['jquery'],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-storage',
            BCOB_URL . 'assets/js/frontend/storage.js',
            ['jquery'],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-step-manager',
            BCOB_URL . 'assets/js/frontend/step-manager.js',
            [
                'jquery',
                'bcob-google-tags',
                'bcob-validator',
                'bcob-progress',
                'bcob-sidebar',
                'bcob-storage',
                'bcob-uploader',
            ],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-frontend',
            BCOB_URL . 'assets/js/frontend/frontend.js',
            [
                'jquery',
                'bcob-step-manager',
            ],
            BCOB_VER,
            true
        );

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

        wp_register_script(
            'bcob-pdf-review',
            BCOB_URL . 'assets/js/frontend/pdf-review.js',
            [
                'jquery',
                'bcob-pdfjs-loader',
            ],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-step-editor',
            BCOB_URL . 'assets/js/frontend/step-editor.js',
            [
                'jquery',
            ],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-stripe-cdn',
            'https://js.stripe.com/v3/',
            [],
            null,
            true
        );

        wp_register_script(
            'bcob-payment',
            BCOB_URL . 'assets/js/frontend/payment.js',
            ['bcob-stripe-cdn', 'bcob-google-tags'],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-submission',
            BCOB_URL . 'assets/js/frontend/submission.js',
            ['bcob-google-tags'],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-order-review',
            BCOB_URL . 'assets/js/frontend/order-review.js',
            [],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-coupon',
            BCOB_URL . 'assets/js/frontend/coupon.js',
            ['bcob-google-tags'],
            BCOB_VER,
            true
        );

        wp_register_script(
            'bcob-resend-confirmation',
            BCOB_URL . 'assets/js/frontend/resend-confirmation.js',
            ['jquery'],
            BCOB_VER,
            true
        );



        if (
            !is_admin() &&
            $this->should_load_frontend_assets()
        ) {

            wp_enqueue_style('bcob-remixicon');
            wp_enqueue_style('bcob-frontend');
            wp_enqueue_style('bcob-pdf-review');
            wp_enqueue_style('bcob-stepper');
            wp_enqueue_style('bcob-fields');
            wp_enqueue_style('bcob-sidebar');
            wp_enqueue_style('bcob-buttons');
            wp_enqueue_style('bcob-upload');
            wp_enqueue_style('bcob-animations');
            wp_enqueue_style('bcob-thankyou');
            wp_enqueue_style('bcob-responsive');

            wp_enqueue_script('bcob-google-tags');
            wp_enqueue_script('bcob-renderer');
            wp_enqueue_script('bcob-validator');
            wp_enqueue_script('bcob-uploader');
            wp_enqueue_script('bcob-sidebar');
            wp_enqueue_script('bcob-progress');
            wp_enqueue_script('bcob-storage');
            wp_enqueue_script('bcob-payment');
            wp_enqueue_script('bcob-coupon');
            wp_enqueue_script('bcob-order-review');
            wp_enqueue_script('bcob-submission');
            wp_enqueue_script('bcob-resend-confirmation');
            wp_enqueue_script('bcob-step-manager');
            wp_enqueue_script('bcob-frontend');
            wp_enqueue_script('bcob-pdfjs-loader');
            wp_enqueue_script('bcob-pdf-review');
            wp_enqueue_script('bcob-step-editor');

            $settings = get_option('bcob_settings', []);
            $symbols = bcob_currency_symbols();
            $currency = $settings['currency'] ?? 'USD';

            wp_localize_script(
                'bcob-google-tags',
                'BCOBTracking',
                [
                    'enabled'            => !empty($settings['bcob_ga4_measurement_id']) || !empty($settings['bcob_gtm_container_id']),
                    'ga4_measurement_id' => sanitize_text_field($settings['bcob_ga4_measurement_id'] ?? ''),
                    'gtm_container_id'   => sanitize_text_field($settings['bcob_gtm_container_id'] ?? ''),
                    'consent_gating'     => ($settings['bcob_tracking_consent_gating'] ?? '1') === '1',
                    'consent_tool'       => sanitize_key($settings['bcob_tracking_consent_tool'] ?? 'borlabs_cookie'),
                    'consent_condition' => sanitize_text_field(
                        $settings['bcob_tracking_consent_condition']
                            ?? "window.BorlabsCookie?.Consents?.hasConsentForServiceGroup('statistics') === true"
                    ),
                ]
            );

            wp_localize_script('bcob-uploader', 'BCOBUploader', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('bcob_nonce'),
            ]);

            wp_localize_script(
                'bcob-resend-confirmation',
                'BCOBResendConfirmation',
                [
                    'ajax_url' =>
                    admin_url(
                        'admin-ajax.php'
                    ),

                    'nonce' =>
                    wp_create_nonce(
                        'bcob_nonce'
                    ),
                ]
            );

            wp_localize_script(
                'bcob-frontend',
                'BCOB',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('bcob_nonce'),
                    'price_settings' => [
                        'currency'           => $symbols[$currency] ?? $currency,
                        'currency_code'      => $currency ?? 'USD',
                        'currency_position'  => $settings['currency_position'] ?? 'left',
                        'decimals'           => (int) ($settings['number_of_decimals'] ?? 2),
                        'decimal_separator'  => $settings['decimal_separator'] ?? '.',
                        'thousand_separator' => $settings['thousand_separator'] ?? ',',
                        'trim_zeros'         => false,
                        'html'               => true,
                    ],
                ]
            );

            wp_localize_script(
                'bcob-coupon',
                'BCOBCouponLocals',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('bcob_nonce'),
                ]
            );

            wp_localize_script(
                'bcob-pdf-review',
                'BCOBPdfReview',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('bcob_nonce'),
                ]
            );

            wp_localize_script(
                'bcob-payment',
                'BCOBStripe',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('bcob_frontend'),
                ]
            );
        }
    }

    private function should_load_frontend_assets(): bool
    {
        $post =
            is_singular()
            ? get_queried_object()
            : null;

        $should_load = false;

        if ($post instanceof \WP_Post) {
            $should_load =
                $this->contains_bcob_shortcode(
                    $post->post_content
                ) ||
                $this->contains_bcob_shortcode(
                    get_post_meta(
                        $post->ID
                    )
                );
        }

        return (bool) apply_filters(
            'bcob_should_load_frontend_assets',
            $should_load,
            $post
        );
    }

    private function contains_bcob_shortcode(
        mixed $value
    ): bool {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (
                    $this->contains_bcob_shortcode(
                        $item
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        if (is_object($value)) {
            return $this->contains_bcob_shortcode(
                get_object_vars(
                    $value
                )
            );
        }

        if (!is_string($value)) {
            return false;
        }

        $unserialized =
            maybe_unserialize(
                $value
            );

        if ($unserialized !== $value) {
            return $this->contains_bcob_shortcode(
                $unserialized
            );
        }

        return
            has_shortcode(
                $value,
                'bcob_form'
            ) ||
            has_shortcode(
                $value,
                'onboarding_portal'
            );
    }
}
