<?php

namespace BestConnectOnboarding\AI;

if (! defined('ABSPATH')) {
    exit;
}

class File
{

    protected string $api_key = '';

    protected int $timeout = 150;

    protected ?string $last_error = null;

    public function __construct()
    {

        $settings = get_option('bcob_settings', []);

        $this->api_key = trim($settings['bcob_openai_api_key'] ?? '');
    }

    private static function is_local_environment(): bool
    {
        $host = wp_parse_url(
            home_url('/'),
            PHP_URL_HOST
        );

        if (!$host) {
            return false;
        }

        $host = strtolower($host);

        return in_array(
            $host,
            [
                'localhost',
                '127.0.0.1',
                '::1',
            ],
            true
        );
    }

    public function upload(string $file): string
    {

        if (! file_exists($file)) {

            $this->last_error = __('File not found.', 'best-connect-onboarding');

            return '';
        }

        $ch = curl_init();

        curl_setopt_array(
            $ch,
            [
                CURLOPT_URL            => 'https://api.openai.com/v1/files',
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER =>
                !self::is_local_environment(),

                CURLOPT_SSL_VERIFYHOST =>
                self::is_local_environment()
                    ? 0
                    : 2,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->api_key,
                ],
                CURLOPT_POSTFIELDS     => [
                    'purpose' => 'user_data',
                    'file'    => new \CURLFile(
                        $file,
                        mime_content_type($file),
                        basename($file)
                    ),
                ],
            ]
        );

        $response = curl_exec($ch);

        if (curl_errno($ch)) {

            $this->last_error = curl_error($ch);

            curl_close($ch);

            return '';
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $body = json_decode($response, true);

        if ($status >= 400) {

            $this->last_error = $body['error']['message'] ?? __('OpenAI upload failed.', 'best-connect-onboarding');

            return '';
        }

        return $body['id'] ?? '';
    }

    public function retrieve(string $file_id): array
    {

        $response = wp_remote_get(
            'https://api.openai.com/v1/files/' . rawurlencode($file_id),
            [
                'timeout' => $this->timeout,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
            ]
        );

        if (is_wp_error($response)) {

            $this->last_error = $response->get_error_message();

            return [];
        }

        return json_decode(
            wp_remote_retrieve_body($response),
            true
        );
    }

    public function delete(string $file_id): bool
    {

        $response = wp_remote_request(
            'https://api.openai.com/v1/files/' . rawurlencode($file_id),
            [
                'method'  => 'DELETE',
                'timeout' => $this->timeout,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
            ]
        );

        if (is_wp_error($response)) {

            $this->last_error = $response->get_error_message();

            return false;
        }

        $body = json_decode(
            wp_remote_retrieve_body($response),
            true
        );

        return ! empty($body['deleted']);
    }

    public function get_last_error(): ?string
    {

        return $this->last_error;
    }
}
