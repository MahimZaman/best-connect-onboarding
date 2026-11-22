<?php

namespace BestConnectOnboarding\Admin;

defined('ABSPATH') || exit;

class FormEditor
{
    /**
     * Render Form Editor
     *
     * @return void
     */
    public static function render(): void
    {
        $form_id = absint($_GET['form_id'] ?? 0);

?>
        <div class="wrap bcob-form-editor-wrap">

            <form
                id="bcob-form-editor"
                class="bcob-form-editor"
                method="post"
                action="">

                <?php wp_nonce_field('bcob_save_form', 'bcob_nonce'); ?>

                <input
                    type="hidden"
                    name="form_id"
                    value="<?php echo esc_attr($form_id); ?>">

                <!-- =========================================================
                 Header
                ========================================================== -->

                <header class="bcob-editor-header">

                    <div class="bcob-header-left">

                        <a
                            href="<?php echo esc_url(admin_url('admin.php?page=bcob-forms')); ?>"
                            class="bcob-back-button">
                            <i class="ri-arrow-left-line"></i>
                        </a>

                        <div class="bcob-header-title">

                            <input
                                type="text"
                                name="form_title"
                                class="bcob-form-title-input"
                                value="Untitled Form"
                                placeholder="<?php esc_attr_e('Form title', 'best-connect-onboarding'); ?>">

                        </div>

                    </div>

                    <div class="bcob-header-right">

                        <nav class="bcob-editor-tabs">

                            <button
                                type="button"
                                class="bcob-tab active"
                                data-screen="builder">
                                <i class="ri-layout-grid-line"></i>

                                <?php esc_html_e('Build', 'best-connect-onboarding'); ?>

                            </button>

                            <button
                                type="button"
                                class="bcob-tab"
                                data-screen="conditions">
                                <i class="ri-git-branch-line"></i>

                                <?php esc_html_e('Conditions', 'best-connect-onboarding'); ?>

                            </button>

                            <button
                                type="button"
                                class="bcob-tab"
                                data-screen="settings">
                                <i class="ri-settings-3-line"></i>

                                <?php esc_html_e('Settings', 'best-connect-onboarding'); ?>

                            </button>

                        </nav>

                        <span class="bcob-save-status">

                            <i class="ri-checkbox-circle-line"></i>

                            <?php esc_html_e('Saved', 'best-connect-onboarding'); ?>

                        </span>

                        <button
                            type="submit"
                            class="button button-primary bcob-save-form">
                            <i class="ri-save-line"></i>

                            <?php esc_html_e('Save', 'best-connect-onboarding'); ?>

                        </button>

                    </div>

                </header>


                <div class="bcob-editor-layout">

                    <aside class="bcob-step-sidebar">

                        <div class="bcob-sidebar-heading">

                            <span>

                                <?php esc_html_e('Steps', 'best-connect-onboarding'); ?>

                            </span>

                            <button
                                type="button"
                                class="bcob-add-step">
                                <i class="ri-add-line"></i>
                            </button>

                        </div>



                        <div class="bcob-step-list">

                        </div>

                    </aside>


                    <main class="bcob-editor-content">

                        <section
                            class="bcob-screen active"
                            data-screen="builder">

                            <div class="bcob-panel">

                                <div class="bcob-panel-body">

                                    <div class="bcob-grid bcob-grid-2">

                                        <div class="bcob-form-group">

                                            <label>

                                                <?php esc_html_e('Step Title', 'best-connect-onboarding'); ?>

                                            </label>

                                            <input
                                                type="text"
                                                id="bcob-step-title"
                                                value="Step 1">

                                        </div>

                                        <div class="bcob-form-group">

                                            <label>

                                                <?php esc_html_e('Step Description', 'best-connect-onboarding'); ?>

                                            </label>

                                            <input
                                                type="text"
                                                id="bcob-step-description"
                                                placeholder="Description">

                                        </div>

                                    </div>

                                    <label class="bcob-checkbox">

                                        <input
                                            type="checkbox"
                                            id="bcob-step-sidebar"
                                            checked>

                                        <span>

                                            <?php esc_html_e('Enable widget sidebar for this step', 'best-connect-onboarding'); ?>

                                        </span>

                                    </label>

                                </div>

                            </div>



                            <!-- Dynamic Fields -->

                            <div id="bcob-step-fields" class="bcob-step-fields"></div>



                            <!-- Field Library -->

                            <div class="bcob-panel">

                                <div class="bcob-panel-header">

                                    <strong>

                                        <?php esc_html_e('Add Field', 'best-connect-onboarding'); ?>

                                    </strong>

                                </div>

                                <div class="bcob-panel-body">

                                    <div class="bcob-field-library">

                                        <?php

                                        $fields = apply_filters(
                                            'bcob_registered_fields',
                                            []
                                        );

                                        ?>

                                        <div id="bcob-field-library">
                                            <?php foreach ($fields as $field) : ?>

                                                <button
                                                    type="button"
                                                    class="bcob-add-field"
                                                    data-type="<?php echo esc_attr($field->get_type()); ?>">

                                                    <i class="<?php echo esc_attr($field->get_icon()); ?>"></i>

                                                    <?php echo esc_html($field->get_title()); ?>

                                                </button>

                                                <script
                                                    type="text/template"
                                                    id="bcob-settings-<?php echo esc_attr($field->get_type()); ?>">

                                                    <?php $field->render_builder_settings(); ?>

		</script>

                                                <script>
                                                    window.bcobFields = window.bcobFields || {};

                                                    window.bcobFields["<?php echo esc_js($field->get_type()); ?>"] = <?php echo wp_json_encode(
                                                                                                                            $field->get_defaults()
                                                                                                                        ); ?>;
                                                </script>

                                            <?php endforeach; ?>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </section>



                        <!-- Conditions Screen -->

                        <section
                            class="bcob-screen"
                            data-screen="conditions">

                            <div class="bcob-conditions">

                                <div class="bcob-condition-builder">

                                    <div class="bcob-condition-grid">

                                        <div class="bcob-form-group">

                                            <label>Target</label>

                                            <select class="bcob-condition-target">

                                                <option value="">
                                                    Select Step / Field
                                                </option>

                                            </select>

                                        </div>

                                        <div class="bcob-form-group">

                                            <label>Action</label>

                                            <select class="bcob-condition-action">

                                                <option value="show">Show</option>
                                                <option value="hide">Hide</option>

                                            </select>

                                        </div>

                                        <div class="bcob-form-group">

                                            <label>Rule Relation</label>

                                            <select class="bcob-condition-relation">

                                                <option value="and">AND</option>
                                                <option value="or">OR</option>

                                            </select>

                                        </div>

                                    </div>

                                    <div class="bcob-condition-rules">

                                        <div class="bcob-condition-rule">

                                            <div class="bcob-condition-rule-grid">

                                                <div class="bcob-form-group">

                                                    <label>Field</label>

                                                    <select class="bcob-rule-field">

                                                        <option value="">
                                                            Select Field
                                                        </option>

                                                    </select>

                                                </div>

                                                <div class="bcob-form-group">

                                                    <label>Operator</label>

                                                    <select class="bcob-rule-operator">

                                                        <option value="==">Equals</option>
                                                        <option value="!=">Not Equals</option>
                                                        <option value=">">Greater Than</option>
                                                        <option value="<">Less Than</option>
                                                        <option value=">=">Greater Than Or Equal</option>
                                                        <option value="<=">Less Than Or Equal</option>
                                                        <option value="contains">Contains</option>
                                                        <option value="starts_with">Starts With</option>
                                                        <option value="ends_with">Ends With</option>
                                                        <option value="empty">Is Empty</option>
                                                        <option value="not_empty">Is Not Empty</option>

                                                    </select>

                                                </div>

                                                <div class="bcob-form-group">

                                                    <label>Value</label>

                                                    <input
                                                        type="text"
                                                        class="bcob-rule-value">

                                                </div>

                                                <button
                                                    type="button"
                                                    class="bcob-rule-delete">

                                                    <i class="ri-delete-bin-line"></i>

                                                </button>

                                            </div>

                                        </div>

                                    </div>

                                    <div class="bcob-condition-actions">

                                        <button
                                            type="button"
                                            class="button bcob-add-rule">

                                            <i class="ri-add-line"></i>

                                            Add Rule

                                        </button>

                                        <button
                                            type="button"
                                            class="button button-primary bcob-add-condition">

                                            <i class="ri-add-line"></i>

                                            Add Condition

                                        </button>

                                    </div>

                                </div>

                                <div class="bcob-current-conditions"></div>

                            </div>

                        </section>



                        <!-- Settings Screen -->

                        <section
                            class="bcob-screen"
                            data-screen="settings">

                            <?php require_once BCOB_PATH . 'templates/admin/form-settings.php' ?>

                        </section>

                    </main>


                    <aside class="bcob-widget-sidebar">

                        <div class="bcob-sidebar-heading">

                            <?php esc_html_e('Step Sidebar', 'best-connect-onboarding'); ?>

                        </div>



                        <div id="bcob-sidebar-widgets" class="bcob-sidebar-widgets"></div>


                        <div class="bcob-widget-library">

                            <div class="bcob-widget-library-title">

                                <?php esc_html_e('Add Widget', 'best-connect-onboarding'); ?>

                            </div>

                            <?php
                            $widget_registry = new \BestConnectOnboarding\Forms\Widgets\WidgetRegistry();
                            $widgets         = $widget_registry->all();
                            ?>

                            <div class="bcob-widget-grid">
                                <?php foreach ($widgets as $widget) : ?>
                                    <button
                                        type="button"
                                        class="bcob-widget-card"
                                        data-widget="<?php echo esc_attr($widget->get_type()); ?>">
                                        <i class="<?php echo esc_attr($widget->get_icon()); ?>"></i>

                                        <span>
                                            <?php echo esc_html($widget->get_title()); ?>
                                        </span>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                        </div>

                    </aside>

                </div>

            </form>

        </div>
<?php
    }
}
