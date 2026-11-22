<?php

namespace BestConnectOnboarding\Core;

use BestConnectOnboarding\Abandonment\Abandonment;
use BestConnectOnboarding\Forms\FieldRegistration;
use BestConnectOnboarding\Admin\Ajax;
use BestConnectOnboarding\Admin\Settings;
use BestConnectOnboarding\Forms\Widgets\WidgetRegistry;
use BestConnectOnboarding\Frontend\Shortcode;
use BestConnectOnboarding\Coupon\Coupon;
use BestConnectOnboarding\Membership\Membership;
use BestConnectOnboarding\Checkout\Checkout;
use BestConnectOnboarding\Frontend\Ajax as FrontendAjax;
use BestConnectOnboarding\Frontend\MembershipShortcode;
use BestConnectOnboarding\Payments\Stripe;
use BestConnectOnboarding\Tracking\GoogleTags;
use BestConnectOnboarding\PDFs\Mapping;
use BestConnectOnboarding\Prefill\Prefill;
use BestConnectOnboarding\Middleware\Middleware;
use BestConnectOnboarding\GDPR\GDPR;

defined('ABSPATH') || exit;

class Plugin
{

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        $this->load_installer();
    }

    private function load_installer(): void
    {
        if (class_exists(Installer::class)) {
            new Installer();
        }

        new Admin();
        new FieldRegistration();
        Ajax::init();
        new WidgetRegistry();
        (new Mapping())->init();
        new Settings();

        // Systems
        (new Coupon())->init();
        new Membership();
        new Stripe();
        new Checkout();
        new Prefill();
        new Abandonment();
        new Middleware();
        new GDPR();

        // FrontEnd;
        new Assets();
        Shortcode::init();
        new MembershipShortcode();
        new FrontendAjax();

        // Tracking
        GoogleTags::instance()->init();
    }
}
