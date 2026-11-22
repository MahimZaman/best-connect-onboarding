<?php

namespace BestConnectOnboarding\Core;

defined('ABSPATH') || exit;

class Admin
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('upload_mimes', [$this, 'register_mimes']);
        add_action('wp_check_filetype_and_ext', [$this, 'check_filetype'], 10, 4);
    }

    public function register_mimes($mimes)
    {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    public function check_filetype($data, $file, $filename, $mimes)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext === 'svg' || $ext === 'svgz') {
            $data['ext']  = $ext;
            $data['type'] = 'image/svg+xml';
        }

        return $data;
    }

    public function register_menus(): void
    {
        $capability = 'manage_options';
        $slug       = 'bcob-dashboard';

        add_menu_page(
            __('Best Connect', 'best-connect-onboarding'),
            __('Best Connect', 'best-connect-onboarding'),
            $capability,
            $slug,
            '',
            'dashicons-feedback',
            56
        );

        add_submenu_page(
            $slug,
            __('Dashboard', 'best-connect-onboarding'),
            __('Dashboard', 'best-connect-onboarding'),
            $capability,
            $slug,
            [\BestConnectOnboarding\Admin\Dashboard::class, 'render']
        );

        add_submenu_page(
            $slug,
            __('Versionen', 'best-connect-onboarding'),
            __('Versionen', 'best-connect-onboarding'),
            $capability,
            admin_url('edit.php?post_type=bcob_membership')
        );

        add_submenu_page(
            $slug,
            __('Forms', 'best-connect-onboarding'),
            __('Forms', 'best-connect-onboarding'),
            $capability,
            'bcob-forms',
            [\BestConnectOnboarding\Admin\Forms::class, 'render']
        );

        add_submenu_page(
            null,
            __('Edit Form', 'best-connect-onboarding'),
            __('Edit Form', 'best-connect-onboarding'),
            $capability,
            'bcob-form-edit',
            [\BestConnectOnboarding\Admin\FormEditor::class, 'render']
        );

        add_submenu_page(
            $slug,
            __('Leads', 'best-connect-onboarding'),
            __('Leads', 'best-connect-onboarding'),
            $capability,
            'bcob-leads',
            [\BestConnectOnboarding\Admin\Leads::class, 'render']
        );

        add_submenu_page(
            $slug,
            __('Payments', 'best-connect-onboarding'),
            __('Payments', 'best-connect-onboarding'),
            $capability,
            'bcob-payments',
            [\BestConnectOnboarding\Payments\Payment::class, 'render']
        );

        add_submenu_page(
            $slug,
            __('Prefill Links', 'best-connect-onboarding'),
            __('Prefill Links', 'best-connect-onboarding'),
            $capability,
            'bcob-prefill-links',
            [\BestConnectOnboarding\Prefill\Prefill::class, 'render']
        );

        add_submenu_page(
            $slug,
            __('Coupon', 'best-connect-onboarding'),
            __('Coupon', 'best-connect-onboarding'),
            $capability,
            admin_url('edit.php?post_type=bcob_coupon')
        );

        add_submenu_page(
            $slug,
            __('Logs', 'best-connect-onboarding'),
            __('Logs', 'best-connect-onboarding'),
            $capability,
            'bcob-logs',
            [\BestConnectOnboarding\Admin\Logs::class, 'render']
        );

        add_submenu_page(
            $slug,
            __('Settings', 'best-connect-onboarding'),
            __('Settings', 'best-connect-onboarding'),
            $capability,
            'bcob-settings',
            [\BestConnectOnboarding\Admin\Settings::class, 'render']
        );
    }

    public function enqueue_assets(string $hook): void
    {
        if (strpos($hook, 'bcob') === false) {
            return;
        }
        wp_enqueue_style('bcob-remixicon', 'https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css', [], BCOB_VER);
        wp_enqueue_style('bcob-admin', BCOB_URL . 'assets/css/admin.css', [], BCOB_VER);
        wp_enqueue_style('bcob-admin-settings', BCOB_URL . 'assets/css/settings.css', [], BCOB_VER);
        wp_enqueue_style('bcob-admin-leads', BCOB_URL . 'assets/css/admin-leads.css', [], BCOB_VER);
        wp_enqueue_style('bcob-form-settings', BCOB_URL . 'assets/css/form-settings.css', [], BCOB_VER);
        wp_enqueue_style('bcob-form-editor', BCOB_URL . 'assets/css/form-editor.css', [], BCOB_VER);
        wp_enqueue_style('bcob-condition-builder', BCOB_URL . 'assets/css/condition-builder.css', [], BCOB_VER);

        wp_enqueue_editor();
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('bcob-form-builder', BCOB_URL . 'assets/js/admin/form-builder.js', ['jquery'], BCOB_VER, true);
        wp_enqueue_script('bcob-remixicon-tooltip', BCOB_URL . 'assets/js/admin/remixicon-tooltip.js', ['jquery'], BCOB_VER, true);
        wp_enqueue_script('bcob-admin-leads', BCOB_URL . 'assets/js/admin/admin-leads.js', ['jquery'], BCOB_VER, true);
        wp_enqueue_script('bcob-form-settings', BCOB_URL . 'assets/js/admin/form-settings.js', ['jquery'], BCOB_VER, true);
        wp_enqueue_script('bcob-admin-settings', BCOB_URL . 'assets/js/admin/settings.js', ['jquery'], BCOB_VER, true);
        wp_enqueue_script('bcob-advanced-options-builder', BCOB_URL . 'assets/js/admin/advanced-options-builder.js', ['jquery'], BCOB_VER, true);
        wp_enqueue_script('bcob-widget-builder', BCOB_URL . 'assets/js/admin/widget-builder.js', ['jquery'], BCOB_VER, true);
        wp_enqueue_script('bcob-condition-builder', BCOB_URL . 'assets/js/admin/condition-builder.js', ['jquery'], BCOB_VER, true);
        wp_enqueue_script(
            'bcob-import-export',
            BCOB_URL . 'assets/js/admin/import-export.js',
            [],
            BCOB_VER,
            true
        );
        $form_id = absint($_GET['form_id'] ?? 0);

        $repository = new \BestConnectOnboarding\Forms\FormRepository();

        $form = $form_id ? $repository->get($form_id) : null;

        $form_schema = [];

        if ($form && ! empty($form->form_schema)) {
            $form_schema = json_decode($form->form_schema, true);
        }
        wp_localize_script(
            'bcob-form-builder',
            'bcob',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('bcob_form'),
                'form_id'  => $form_id,
                'form'     => $form_schema,
            ]
        );

        $widget_registry = new \BestConnectOnboarding\Forms\Widgets\WidgetRegistry();

        wp_localize_script(
            'bcob-widget-builder',
            'bcobWidgets',
            $widget_registry->get_schemas()
        );
        wp_localize_script(
            'bcob-admin',
            'bcobAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('bcob_admin'),
            ]
        );
    }
}
