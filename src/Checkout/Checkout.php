<?php

namespace BestConnectOnboarding\Checkout;

if (! defined('ABSPATH')) {
    exit;
}

class Checkout
{

    /**
     * Checkout page URL.
     *
     * @var string
     */
    protected $checkout_url = '';

    /**
     * Success redirect.
     *
     * @var string
     */
    protected $success_redirect = '';

    /**
     * Error redirect.
     *
     * @var string
     */
    protected $error_redirect = '';

    /**
     * Popup ID or slug.
     *
     * @var string
     */
    protected $popup = '';

    public function __construct()
    {

        $this->checkout_url     = apply_filters('bcob_checkout_url', '');
        $this->success_redirect = apply_filters('bcob_checkout_success_redirect', '');
        $this->error_redirect   = apply_filters('bcob_checkout_error_redirect', '');
        $this->popup            = apply_filters('bcob_checkout_popup', '');

        do_action('bcob_checkout_init', $this);
    }

    /**
     * Main checkout handler.
     *
     * @param array $data
     *
     * @return array
     */
    public function process(array $data = [])
    {

        $result = $this->validate($data);

        if (! $result['success']) {

            do_action('bcob_checkout_failed', $data, $result);

            return $result;
        }

        /**
         * External systems should create orders,
         * process payments, etc.
         */
        $result = apply_filters(
            'bcob_checkout_process',
            [
                'success' => true,
                'message' => '',
                'data'    => [],
            ],
            $data,
            $this
        );

        do_action('bcob_checkout_completed', $result, $data);

        return $result;
    }

    /**
     * Validate checkout.
     *
     * @param array $data
     *
     * @return array
     */
    public function validate(array $data = [])
    {

        $result = [
            'success' => true,
            'message' => '',
            'errors'  => [],
        ];

        $result = apply_filters(
            'bcob_checkout_validate',
            $result,
            $data,
            $this
        );

        if (! empty($result['errors'])) {
            $result['success'] = false;
        }

        return $result;
    }

    /**
     * Add an error.
     *
     * @param array  $result
     * @param string $message
     *
     * @return array
     */
    public function add_error(array $result, $message)
    {

        $result['success'] = false;
        $result['errors'][] = $message;
        $result['message'] = $message;

        return $result;
    }

    /**
     * Redirect after checkout.
     *
     * @param bool $success
     */
    public function redirect($success = true)
    {

        $url = $success
            ? $this->success_redirect
            : $this->error_redirect;

        $url = apply_filters(
            'bcob_checkout_redirect',
            $url,
            $success,
            $this
        );

        if (empty($url)) {
            return;
        }

        wp_safe_redirect($url);
        exit;
    }

    /**
     * Return popup information.
     *
     * @param bool $success
     *
     * @return array
     */
    public function popup($success = true)
    {

        return apply_filters(
            'bcob_checkout_popup_response',
            [
                'success' => $success,
                'popup'   => $this->popup,
            ],
            $success,
            $this
        );
    }

    public function get_checkout_url()
    {
        return $this->checkout_url;
    }

    public function set_checkout_url($url)
    {
        $this->checkout_url = $url;
        return $this;
    }

    public function set_success_redirect($url)
    {
        $this->success_redirect = $url;
        return $this;
    }

    public function set_error_redirect($url)
    {
        $this->error_redirect = $url;
        return $this;
    }

    public function set_popup($popup)
    {
        $this->popup = $popup;
        return $this;
    }
}
