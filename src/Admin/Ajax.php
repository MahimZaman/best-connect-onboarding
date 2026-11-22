<?php

namespace BestConnectOnboarding\Admin;

use BestConnectOnboarding\Forms\FormRepository;

defined('ABSPATH') || exit;

class Ajax
{

    /**
     * @var FormRepository
     */
    private static $forms;

    public static function init(): void
    {

        self::$forms = new FormRepository();

        add_action('wp_ajax_bcob_save_form', [__CLASS__, 'save_form']);
        add_action('wp_ajax_bcob_delete_form', [__CLASS__, 'delete_form']);
        add_action('wp_ajax_bcob_duplicate_form', [__CLASS__, 'duplicate_form']);
        add_action('wp_ajax_bcob_export_form', [__CLASS__, 'export_form']);
        add_action('wp_ajax_bcob_import_form', [__CLASS__, 'import_form']);
    }

    public static function export_form(): void
    {
        check_ajax_referer('bcob_form', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'best-connect-onboarding'));
        }

        $form_id = absint($_POST['form_id'] ?? 0);

        if (! $form_id) {
            wp_die(__('Invalid form.', 'best-connect-onboarding'));
        }

        $form = self::$forms->get($form_id);

        if (! $form) {
            wp_die(__('Form not found.', 'best-connect-onboarding'));
        }

        $schema = json_decode($form->form_schema, true);

        $export = [
            'version' => '1.0',
            'plugin'  => 'Best Connect Onboarding',
            'form'    => [
                'name'   => $form->name,
                'status' => $form->status,
                'schema' => $schema,
            ],
        ];

        $filename = sanitize_title($form->name) . '.json';

        nocache_headers();

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo wp_json_encode($export, JSON_PRETTY_PRINT);

        exit;
    }

    public static function import_form(): void
    {
        check_ajax_referer('bcob_form', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'best-connect-onboarding'));
        }

        if (empty($_FILES['file']['tmp_name'])) {
            wp_send_json_error(__('No file uploaded.', 'best-connect-onboarding'));
        }

        $json = file_get_contents($_FILES['file']['tmp_name']);

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid JSON file.', 'best-connect-onboarding'));
        }

        if (
            empty($data['form']) ||
            empty($data['form']['schema'])
        ) {
            wp_send_json_error(__('Invalid export file.', 'best-connect-onboarding'));
        }

        $new_id = self::$forms->insert([
            'name'        => sanitize_text_field($data['form']['name'] ?? __('Imported Form', 'best-connect-onboarding')),
            'status'      => sanitize_key($data['form']['status'] ?? 'draft'),
            'form_schema' => $data['form']['schema'],
        ]);

        if (!$new_id) {
            wp_send_json_error(
                self::repository_error(
                    __(
                        'Unable to import form.',
                        'best-connect-onboarding'
                    )
                )
            );
        }

        wp_send_json_success([
            'message' => __('Form imported successfully.', 'best-connect-onboarding'),
            'form_id' => $new_id,
        ]);
    }

    public static function duplicate_form(): void
    {

        check_ajax_referer('bcob_form', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'best-connect-onboarding'));
        }

        $form_id = absint($_POST['form_id'] ?? 0);

        if (! $form_id) {
            wp_send_json_error(__('Invalid form.', 'best-connect-onboarding'));
        }

        $new_id = self::$forms->duplicate($form_id);

        if (!$new_id) {
            wp_send_json_error(
                self::repository_error(
                    __(
                        'Unable to duplicate form.',
                        'best-connect-onboarding'
                    )
                )
            );
        }

        wp_send_json_success(
            [
                'message' => __('Form duplicated.', 'best-connect-onboarding'),
            ]
        );
    }

    public static function delete_form(): void
    {
        check_ajax_referer('bcob_form', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'best-connect-onboarding'));
        }

        $form_id = absint($_POST['form_id'] ?? 0);

        if (! $form_id) {
            wp_send_json_error(__('Invalid form.', 'best-connect-onboarding'));
        }

        if (! self::$forms->exists($form_id)) {
            wp_send_json_error(__('Form not found.', 'best-connect-onboarding'));
        }

        if (! self::$forms->delete($form_id)) {
            wp_send_json_error(__('Unable to delete form.', 'best-connect-onboarding'));
        }

        wp_send_json_success(
            [
                'message' => __('Form deleted successfully.', 'best-connect-onboarding'),
            ]
        );
    }

    private static function repository_error(
        string $fallback
    ): string {
        $errors =
            self::$forms
            ->get_validation_errors();

        if (!$errors) {
            return $fallback;
        }

        return implode(
            "\n",
            $errors
        );
    }

    public static function save_form(): void
    {

        check_ajax_referer('bcob_form', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'best-connect-onboarding'));
        }

        $form_id = absint($_POST['form_id'] ?? 0);

        $schema = json_decode(
            wp_unslash($_POST['schema'] ?? '{}'),
            true
        );

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid form schema.', 'best-connect-onboarding'));
        }

        $form_name = sanitize_text_field(
            $schema['title'] ?? __('Untitled Form', 'best-connect-onboarding')
        );

        $data = [
            'name'        => $form_name,
            'form_schema' => $schema,
            'status'      => 'draft',
        ];

        /*
		|--------------------------------------------------------------------------
		| Create New Form
		|--------------------------------------------------------------------------
		*/

        if (empty($form_id)) {

            $form_id = self::$forms->insert($data);

            if (!$form_id) {
                wp_send_json_error(
                    self::repository_error(
                        __(
                            'Unable to create form.',
                            'best-connect-onboarding'
                        )
                    )
                );
            }

            wp_send_json_success(
                [
                    'message'  => __('Form created successfully.', 'best-connect-onboarding'),
                    'form_id'  => $form_id,
                    'redirect' => admin_url(
                        'admin.php?page=bcob-form-edit&form_id=' . $form_id
                    ),
                ]
            );
        }

        /*
		|--------------------------------------------------------------------------
		| Update Existing Form
		|--------------------------------------------------------------------------
		*/

        if (! self::$forms->exists($form_id)) {
            wp_send_json_error(__('Form not found.', 'best-connect-onboarding'));
        }

        $updated = self::$forms->update(
            $form_id,
            $data
        );

        if (!$updated) {
            wp_send_json_error(
                self::repository_error(
                    __(
                        'Unable to save form.',
                        'best-connect-onboarding'
                    )
                )
            );
        }

        wp_send_json_success(
            [
                'message' => __('Form saved successfully.', 'best-connect-onboarding'),
                'form_id' => $form_id,
            ]
        );
    }
}
