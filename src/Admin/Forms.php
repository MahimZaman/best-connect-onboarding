<?php

namespace BestConnectOnboarding\Admin;

defined('ABSPATH') || exit;

class Forms
{

    public static function render(): void
    {
        $repository =
            new \BestConnectOnboarding\Forms\FormRepository();

        $per_page = 20;

        $total_forms =
            $repository->get_count();

        $total_pages =
            max(
                1,
                (int) ceil(
                    $total_forms /
                        $per_page
                )
            );

        $current_page =
            max(
                1,
                absint(
                    $_GET['paged'] ?? 1
                )
            );

        $current_page =
            min(
                $current_page,
                $total_pages
            );

        $offset =
            ($current_page - 1) *
            $per_page;

        $forms =
            $repository->get_all(
                [
                    'limit' =>
                    $per_page,

                    'offset' =>
                    $offset,
                ]
            );

        $export_forms =
            $total_forms > 0
            ? $repository->get_all(
                [
                    'limit' =>
                    $total_forms,

                    'offset' =>
                    0,
                ]
            )
            : [];
?>

        <div class="wrap bcob-forms-page">

            <div class="bcob-page-header">

                <h1 class="wp-heading-inline">Forms</h1>

                <div class="bcob-page-actions">

                    <div class="bcob-export">

                        <button type="button" class="button" id="bcob-export-toggle">
                            <i class="ri-download-line"></i>
                            Export
                        </button>

                        <div class="bcob-export-menu" id="bcob-export-menu">

                            <?php foreach ($export_forms as $form) : ?>

                                <a href="#"
                                    class="bcob-export-form"
                                    data-id="<?php echo esc_attr($form->id); ?>">

                                    <?php echo esc_html($form->name); ?>

                                </a>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <button
                        type="button"
                        class="button"
                        id="bcob-import-btn">

                        <i class="ri-upload-line"></i>
                        Import

                    </button>

                    <input
                        type="file"
                        id="bcob-import-file"
                        accept=".json"
                        hidden>

                    <a href="<?php echo admin_url('admin.php?page=bcob-form-edit'); ?>"
                        class="page-title-action">

                        <i class="ri-add-line"></i>
                        Add New

                    </a>

                </div>

            </div>

            <div class="bcob-table-wrap">

                <table class="bcob-table">

                    <thead>

                        <tr>
                            <th>Name</th>
                            <th>Steps</th>
                            <th>Updated</th>
                            <th>Shortcode</th>
                            <th class="bcob-actions-column">Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($forms)) : ?>

                            <tr>

                                <td colspan="5">
                                    No forms found.
                                </td>

                            </tr>

                        <?php else : ?>

                            <?php foreach ($forms as $form) :

                                $schema = json_decode($form->form_schema, true);

                                $steps = ! empty($schema['steps']) ? count($schema['steps']) : 0;

                                $edit_url = admin_url(
                                    'admin.php?page=bcob-form-edit&form_id=' . $form->id
                                );

                            ?>

                                <tr>

                                    <td class="bcob-name">

                                        <a href="<?php echo esc_url($edit_url); ?>">

                                            <?php echo esc_html($form->name); ?>

                                        </a>

                                    </td>

                                    <td>

                                        <?php echo esc_html($steps); ?>

                                    </td>

                                    <td>

                                        <?php echo esc_html(wp_date(get_option('date_format'), strtotime($form->updated_at))); ?>

                                    </td>

                                    <td>

                                        <span class="bcob-shortcode">
                                            <i class="ri-file-copy-line"></i>
                                            <?php echo esc_html('[bcob_form id="' . $form->id . '"]'); ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div class="bcob-actions">

                                            <a href="<?php echo esc_url($edit_url); ?>" title="Edit">

                                                <i class="ri-edit-line"></i>

                                            </a>

                                            <a href="#"
                                                class="bcob-duplicate-form"
                                                data-id="<?php echo esc_attr($form->id); ?>"
                                                title="Duplicate">

                                                <i class="ri-file-copy-line"></i>

                                            </a>

                                            <a href="#"
                                                class="bcob-delete-form"
                                                data-id="<?php echo esc_attr($form->id); ?>"
                                                title="Delete">

                                                <i class="ri-delete-bin-line"></i>

                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <?php if ($total_pages > 1) : ?>

                <div class="tablenav bottom">

                    <div class="tablenav-pages">

                        <?php
                        echo wp_kses_post(
                            paginate_links(
                                [
                                    'base' =>
                                    add_query_arg(
                                        'paged',
                                        '%#%'
                                    ),

                                    'format' =>
                                    '',

                                    'current' =>
                                    $current_page,

                                    'total' =>
                                    $total_pages,

                                    'prev_text' =>
                                    '&lsaquo;',

                                    'next_text' =>
                                    '&rsaquo;',

                                    'type' =>
                                    'plain',
                                ]
                            )
                        );
                        ?>

                    </div>

                </div>

            <?php endif; ?>

            <script>
                window.BCOB_IMPORT = {
                    ajax: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                    nonce: "<?php echo wp_create_nonce('bcob_form'); ?>"
                };
            </script>

        </div>

<?php
    }
}
