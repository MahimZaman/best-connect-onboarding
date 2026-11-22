<?php

namespace BestConnectOnboarding\Tracking;

defined('ABSPATH') || exit;

class GoogleTags
{
    /**
     * Singleton instance.
     *
     * @var GoogleTags|null
     */
    protected static ?GoogleTags $instance = null;

    /**
     * Get instance.
     */
    public static function instance(): GoogleTags
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {}

    /**
     * Initialize.
     */
    public function init(): void
    {
        add_action(
            'wp_enqueue_scripts',
            [$this, 'enqueue_scripts'],
            100
        );
    }

    /**
     * Enqueue the frontend tracking helper and pass tracking settings to JavaScript.
     */
    public function enqueue_scripts(): void
    {
        $settings = (array) get_option('bcob_settings', []);

        $ga4_measurement_id = isset($settings['bcob_ga4_measurement_id'])
            ? trim((string) $settings['bcob_ga4_measurement_id'])
            : '';

        $gtm_container_id = isset($settings['bcob_gtm_container_id'])
            ? trim((string) $settings['bcob_gtm_container_id'])
            : '';

        $consent_gating = isset($settings['bcob_tracking_consent_gating'])
            ? $settings['bcob_tracking_consent_gating'] === '1'
            : true;

        $consent_condition = isset($settings['bcob_tracking_consent_condition'])
            ? trim((string) $settings['bcob_tracking_consent_condition'])
            : 'window.consent.statistics === true';

        wp_register_script(
            'bcob-google-tags',
            BCOB_URL . 'assets/js/frontend/google-tags.js',
            ['jquery'],
            BCOB_VER,
            true
        );

        wp_localize_script(
            'bcob-google-tags',
            'BCOBTracking',
            [
                'enabled'            => $ga4_measurement_id !== '' || $gtm_container_id !== '',
                'ga4_measurement_id' => $ga4_measurement_id,
                'gtm_container_id'   => $gtm_container_id,
                'consent_gating'     => $consent_gating,
                'consent_tool'       => sanitize_key(
                    $settings['bcob_tracking_consent_tool'] ?? 'borlabs_cookie'
                ),
                'consent_condition'  => $consent_condition,
            ]
        );

        wp_enqueue_script('bcob-google-tags');
    }

    /**
     * Add an inline tracking event.
     *
     * @param string $event Event name.
     * @param array  $data Event data.
     */
    public function trigger(string $event, array $data = []): void
    {
        $payload = array_merge(
            [
                'event' => $event,
            ],
            $data
        );

        if (wp_script_is('bcob-google-tags', 'enqueued')) {
            wp_add_inline_script(
                'bcob-google-tags',
                'window.BCOBGoogleTags.push(' . wp_json_encode($payload) . ');',
                'after'
            );
        }
    }
}
