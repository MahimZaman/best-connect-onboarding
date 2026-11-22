<?php

namespace BestConnectOnboarding\Membership;

if (!defined('ABSPATH')) {
    exit;
}

class Membership
{
    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);

        add_action('add_meta_boxes', [$this, 'register_metabox']);
        add_action('save_post_bcob_membership', [$this, 'save_metabox']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter(
            'manage_bcob_membership_posts_columns',
            [$this, 'admin_columns']
        );

        add_action(
            'manage_bcob_membership_posts_custom_column',
            [$this, 'admin_column_content'],
            10,
            2
        );

        add_action(
            'admin_footer',
            [$this, 'copy_shortcode_script']
        );
    }

    /**
     * Admin columns
     */
    public function admin_columns(array $columns): array
    {
        return [
            'cb'         => $columns['cb'],
            'title'      => __('Name', 'best-connect-onboarding'),
            'versions'   => __('Versions', 'best-connect-onboarding'),
            'shortcode'  => __('Shortcode', 'best-connect-onboarding'),
            'date'       => $columns['date'],
        ];
    }

    /**
     * Column content
     */
    public function admin_column_content(string $column, int $post_id): void
    {
        switch ($column) {

            case 'versions':

                $versions = get_post_meta(
                    $post_id,
                    '_bcob_versions',
                    true
                );

                if (empty($versions) || !is_array($versions)) {
                    echo '&mdash;';
                    return;
                }

                $titles = [];

                foreach ($versions as $version) {

                    if (!empty($version['title'])) {
                        $titles[] = $version['title'];
                    }
                }

                echo esc_html(
                    implode(', ', $titles)
                );

                break;

            case 'shortcode':

                $shortcode = '[onboarding_portal id="' . $post_id . '"]';

?>

                <code
                    class="bcob-copy-shortcode"
                    data-shortcode="<?php echo esc_attr($shortcode); ?>"
                    style="cursor:pointer;">

                    <?php echo esc_html($shortcode); ?>

                </code>

        <?php

                break;
        }
    }


    /**
     * Register Version CPT
     */
    public function register_post_type(): void
    {
        register_post_type('bcob_membership', [
            'labels' => [
                'name'               => __('Versionen', 'best-connect-onboarding'),
                'singular_name'      => __('Version', 'best-connect-onboarding'),
                'add_new_item'       => __('Add Version', 'best-connect-onboarding'),
                'edit_item'          => __('Edit Version', 'best-connect-onboarding'),
                'new_item'           => __('New Version', 'best-connect-onboarding'),
                'view_item'          => __('View Version', 'best-connect-onboarding'),
                'search_items'       => __('Search Versionen', 'best-connect-onboarding'),
                'not_found'          => __('No Versionen Found', 'best-connect-onboarding'),
                'menu_name'          => __('Versionen', 'best-connect-onboarding'),
            ],

            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'supports'            => ['title'],
            'show_in_rest'        => false,
            'rewrite'             => false,
            'query_var'           => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
        ]);
    }

    /**
     * Load assets
     */
    public function enqueue_assets($hook): void
    {
        global $post;

        if (
            !isset($post) ||
            $post->post_type !== 'bcob_membership'
        ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('bcob-remixicon', 'https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css', [], BCOB_VER);

        add_action('admin_footer', [$this, 'admin_footer_js']);
    }

    /**
     * Register metabox
     */
    public function register_metabox(): void
    {
        add_meta_box(
            'bcob-membership-details',
            __('Membership Details', 'best-connect-onboarding'),
            [$this, 'render_metabox'],
            'bcob_membership',
            'normal',
            'high'
        );
    }

    /**
     * Render metabox
     */
    public function render_metabox($post): void
    {
        wp_nonce_field(
            'bcob_membership_save',
            'bcob_membership_nonce'
        );

        global $wpdb;

        $table = $wpdb->prefix . 'bcob_forms';

        $forms = $wpdb->get_results("
        SELECT id, name
        FROM {$table}
        ORDER BY name ASC
    ");

        $form_ids =
            array_map(
                'absint',
                wp_list_pluck(
                    $forms,
                    'id'
                )
            );

        $validation =
            get_transient(
                $this->validation_key(
                    (int) $post->ID
                )
            );

        $validation_errors = [];

        $versions =
            get_post_meta(
                $post->ID,
                '_bcob_versions',
                true
            );

        if (is_array($validation)) {
            if (
                isset($validation['versions']) &&
                is_array($validation['versions'])
            ) {
                $versions =
                    $validation['versions'];
            }

            if (
                isset($validation['errors']) &&
                is_array($validation['errors'])
            ) {
                $validation_errors =
                    $validation['errors'];
            }
        }

        if (!is_array($versions) || empty($versions)) {
            $versions = [
                [
                    'icon' => '',
                    'title' => '',
                    'description' => '',
                    'form' => '',
                    'items' => [],
                ]
            ];
        }

        ?>

        <?php if ($validation_errors) : ?>

            <div class="notice notice-error inline">

                <p>
                    <strong>
                        <?php
                        esc_html_e(
                            'The membership offer was not saved because it contains invalid data.',
                            'best-connect-onboarding'
                        );
                        ?>
                    </strong>
                </p>

                <ul style="list-style:disc;margin-left:20px;">

                    <?php foreach ($validation_errors as $error) : ?>

                        <li>
                            <?php echo esc_html($error); ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>

        <table class="form-table">

            <tbody>

                <tr>

                    <td>

                        <div id="bcob-versions">

                            <?php foreach ($versions as $v_index => $version) :

                                $icon_id  = absint($version['icon'] ?? 0);
                                $icon_url = $icon_id ? wp_get_attachment_url($icon_id) : '';

                            ?>

                                <div class="bcob-version" style="border:1px solid #ddd;padding:20px;margin-bottom:25px;background:#fff;">

                                    <p style="text-align:right;">
                                        <button
                                            type="button"
                                            class="button button-link-delete bcob-remove-version">
                                            Remove Version
                                        </button>
                                    </p>

                                    <table class="form-table">

                                        <tr>

                                            <th style="width:180px;">Icon</th>

                                            <td>

                                                <div class="bcob-icon-preview">

                                                    <?php if ($icon_url) : ?>

                                                        <img src="<?php echo esc_url($icon_url); ?>" style="max-width:80px;display:block;margin-bottom:10px;">

                                                    <?php endif; ?>

                                                </div>

                                                <input
                                                    type="hidden"
                                                    class="bcob-icon"
                                                    name="bcob_versions[<?php echo $v_index; ?>][icon]"
                                                    value="<?php echo esc_attr($icon_id); ?>">

                                                <button
                                                    type="button"
                                                    class="button bcob-upload-icon">
                                                    Select Icon
                                                </button>

                                                <button
                                                    type="button"
                                                    class="button bcob-remove-icon">
                                                    Remove
                                                </button>

                                            </td>

                                        </tr>

                                        <tr>

                                            <th>Title</th>

                                            <td>

                                                <input
                                                    type="text"
                                                    class="widefat"
                                                    name="bcob_versions[<?php echo $v_index; ?>][title]"
                                                    value="<?php echo esc_attr($version['title'] ?? ''); ?>">

                                            </td>

                                        </tr>

                                        <tr>

                                            <th>Description</th>

                                            <td>

                                                <?php
                                                wp_editor(
                                                    $version['description'] ?? '',
                                                    'bcob_description_' . $v_index,
                                                    [
                                                        'textarea_name' => 'bcob_versions[' . $v_index . '][description]',
                                                        'textarea_rows' => 6,
                                                        'media_buttons' => false,
                                                        'teeny' => true,
                                                    ]
                                                );
                                                ?>

                                            </td>

                                        </tr>

                                        <tr>

                                            <th>Form</th>

                                            <td>

                                                <select
                                                    name="bcob_versions[<?php echo $v_index; ?>][form]">

                                                    <option value="">-- Select Form --</option>

                                                    <?php
                                                    $selected_form_id =
                                                        absint(
                                                            $version['form'] ?? 0
                                                        );
                                                    ?>

                                                    <?php if (
                                                        $selected_form_id &&
                                                        !in_array(
                                                            $selected_form_id,
                                                            $form_ids,
                                                            true
                                                        )
                                                    ) : ?>

                                                        <option
                                                            value="<?php echo esc_attr($selected_form_id); ?>"
                                                            selected>

                                                            <?php
                                                            echo esc_html(
                                                                sprintf(
                                                                    __(
                                                                        'Deleted form #%d — select another form',
                                                                        'best-connect-onboarding'
                                                                    ),
                                                                    $selected_form_id
                                                                )
                                                            );
                                                            ?>

                                                        </option>

                                                    <?php endif; ?>

                                                    <?php foreach ($forms as $form) : ?>

                                                        <option
                                                            value="<?php echo esc_attr($form->id); ?>"
                                                            <?php selected($version['form'] ?? '', $form->id); ?>>

                                                            <?php echo esc_html($form->name); ?>

                                                        </option>

                                                    <?php endforeach; ?>

                                                </select>

                                            </td>

                                        </tr>

                                        <tr>

                                            <th style="vertical-align:top;">
                                                Pricing Items
                                            </th>

                                            <td>

                                                <table class="widefat bcob-items">

                                                    <thead>

                                                        <tr>

                                                            <th style="padding-left: 15px; width:50%">Product Name</th>

                                                            <th style="padding-left: 15px;width:50%">Price</th>

                                                            <th style="max-width:50px"></th>

                                                        </tr>

                                                    </thead>

                                                    <tbody>

                                                        <?php

                                                        $items = $version['items'] ?? [];

                                                        foreach ($items as $i => $item) :

                                                        ?>

                                                            <tr>

                                                                <td>

                                                                    <input
                                                                        class="widefat"
                                                                        type="text"
                                                                        name="bcob_versions[<?php echo $v_index; ?>][items][<?php echo $i; ?>][name]"
                                                                        value="<?php echo esc_attr($item['name'] ?? ''); ?>">

                                                                </td>

                                                                <td>

                                                                    <input
                                                                        class="widefat"
                                                                        type="number"
                                                                        step="0.01"
                                                                        name="bcob_versions[<?php echo $v_index; ?>][items][<?php echo $i; ?>][price]"
                                                                        value="<?php echo esc_attr($item['price'] ?? ''); ?>">

                                                                </td>

                                                                <td>

                                                                    <button
                                                                        type="button"
                                                                        class="button bcob-remove-row">
                                                                        <i class="ri-delete-bin-line"></i>
                                                                    </button>

                                                                </td>

                                                            </tr>

                                                        <?php endforeach; ?>

                                                    </tbody>

                                                </table>

                                                <p>

                                                    <button
                                                        type="button"
                                                        class="button button-primary bcob-add-item">

                                                        Add Item

                                                    </button>

                                                </p>

                                            </td>

                                        </tr>

                                    </table>

                                </div>

                            <?php endforeach; ?>

                        </div>

                        <p>

                            <button
                                type="button"
                                class="button button-primary"
                                id="bcob-add-version">

                                Add Version

                            </button>

                        </p>
                        <script type="text/template" id="bcob-version-template">

                            <div class="bcob-version" style="border:1px solid #ddd;padding:20px;margin-bottom:25px;background:#fff;">

                        <p style="text-align:right;">

                            <button
                                type="button"
                                class="button button-link-delete bcob-remove-version">
                                Remove Version
                            </button>

                        </p>

                        <table class="form-table">

                            <tr>

                                <th style="width:180px;">Icon</th>

                                <td>

                                    <div class="bcob-icon-preview"></div>

                                    <input
                                        type="hidden"
                                        class="bcob-icon"
                                        name="bcob_versions[__v__][icon]">

                                    <button
                                        type="button"
                                        class="button bcob-upload-icon">
                                        Select Icon
                                    </button>

                                    <button
                                        type="button"
                                        class="button bcob-remove-icon">
                                        Remove
                                    </button>

                                </td>

                            </tr>

                            <tr>

                                <th>Title</th>

                                <td>

                                    <input
                                        type="text"
                                        class="widefat"
                                        name="bcob_versions[__v__][title]">

                                </td>

                            </tr>

                            <tr>

                                <th>Description</th>

                                <td>

                                    <textarea
                                        class="widefat"
                                        rows="6"
                                        name="bcob_versions[__v__][description]"></textarea>

                                </td>

                            </tr>

                            <tr>

                                <th>Form</th>

                                <td>

                                    <select
                                        name="bcob_versions[__v__][form]">

                                        <option value="">-- Select Form --</option>

                                        <?php foreach ($forms as $form) : ?>

                                            <option value="<?php echo esc_attr($form->id); ?>">
                                                <?php echo esc_html($form->name); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </td>

                            </tr>

                            <tr>

                                <th style="vertical-align:top;">
                                    Pricing Items
                                </th>

                                <td>

                                    <table class="widefat bcob-items">

                                        <thead>

                                            <tr>

                                                <th style="padding-left: 15px; width:50%">Product Name</th>

                                                <th style="padding-left: 15px;width:50%">Price</th>

                                                <th style="max-width:50px"></th>

                                            </tr>

                                        </thead>

                                        <tbody></tbody>

                                    </table>

                                    <p>

                                        <button
                                            type="button"
                                            class="button button-primary bcob-add-item">
                                            Add Item
                                        </button>

                                    </p>

                                </td>

                            </tr>

                        </table>

                    </div>

                </script>

                        <script type="text/template" id="bcob-item-template">

                            <tr>

                        <td>

                            <input
                                type="text"
                                class="widefat"
                                name="bcob_versions[__v__][items][__i__][name]">

                        </td>

                        <td>

                            <input
                                type="number"
                                class="widefat"
                                step="0.01"
                                name="bcob_versions[__v__][items][__i__][price]">

                        </td>

                        <td>

                            <button
                                type="button"
                                class="button bcob-remove-row">

                                <i class="ri-delete-bin-line"></i>

                            </button>

                        </td>

                    </tr>

                </script>

                    </td>

                </tr>

            </tbody>

        </table>

    <?php
    }

    private function validation_key(
        int $post_id
    ): string {
        return
            'bcob_membership_validation_' .
            $post_id .
            '_' .
            get_current_user_id();
    }

    /**
     * Save Membership Meta
     */
    /**
     * Save Version Meta
     */
    public function save_metabox($post_id): void
    {
        if (
            !isset($_POST['bcob_membership_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['bcob_membership_nonce']
                    )
                ),
                'bcob_membership_save'
            )
        ) {
            return;
        }

        if (
            defined('DOING_AUTOSAVE') &&
            DOING_AUTOSAVE
        ) {
            return;
        }

        if (
            !current_user_can(
                'edit_post',
                $post_id
            )
        ) {
            return;
        }

        $versions = [];
        $errors = [];

        $posted_versions =
            isset($_POST['bcob_versions']) &&
            is_array($_POST['bcob_versions'])
            ? wp_unslash(
                $_POST['bcob_versions']
            )
            : [];

        global $wpdb;

        $forms_table =
            $wpdb->prefix .
            'bcob_forms';

        foreach (
            $posted_versions
            as $version_index =>
            $version
        ) {
            if (!is_array($version)) {
                continue;
            }

            $version_number =
                absint($version_index) + 1;

            $title =
                sanitize_text_field(
                    $version['title'] ?? ''
                );

            $description =
                wp_kses_post(
                    $version['description'] ?? ''
                );

            $form =
                absint(
                    $version['form'] ?? 0
                );

            $icon =
                absint(
                    $version['icon'] ?? 0
                );

            $items = [];

            if (
                isset($version['items']) &&
                is_array($version['items'])
            ) {
                foreach (
                    $version['items']
                    as $item_index =>
                    $item
                ) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $item_number =
                        absint($item_index) + 1;

                    $name =
                        sanitize_text_field(
                            $item['name'] ?? ''
                        );

                    $raw_price =
                        sanitize_text_field(
                            $item['price'] ?? ''
                        );

                    if (
                        $name === '' &&
                        $raw_price === ''
                    ) {
                        continue;
                    }

                    if ($name === '') {
                        $errors[] =
                            sprintf(
                                __(
                                    'Version %1$d, pricing item %2$d: enter a product name.',
                                    'best-connect-onboarding'
                                ),
                                $version_number,
                                $item_number
                            );
                    }

                    if ($raw_price === '') {
                        $errors[] =
                            sprintf(
                                __(
                                    'Version %1$d, pricing item %2$d: enter a price.',
                                    'best-connect-onboarding'
                                ),
                                $version_number,
                                $item_number
                            );

                        $price = '';
                    } elseif (
                        !is_numeric(
                            $raw_price
                        ) ||
                        (float) $raw_price < 0
                    ) {
                        $errors[] =
                            sprintf(
                                __(
                                    'Version %1$d, pricing item %2$d: price must be a valid non-negative number.',
                                    'best-connect-onboarding'
                                ),
                                $version_number,
                                $item_number
                            );

                        $price =
                            $raw_price;
                    } else {
                        $price =
                            number_format(
                                (float) $raw_price,
                                2,
                                '.',
                                ''
                            );
                    }

                    $items[] = [
                        'name' =>
                        $name,

                        'price' =>
                        $price,
                    ];
                }
            }

            if ($form) {
                $form_exists =
                    $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id
                        FROM {$forms_table}
                        WHERE id = %d
                        LIMIT 1",
                            $form
                        )
                    );

                if (!$form_exists) {
                    $errors[] =
                        sprintf(
                            __(
                                'Version %1$d: assigned form #%2$d no longer exists. Select another form.',
                                'best-connect-onboarding'
                            ),
                            $version_number,
                            $form
                        );
                }
            }

            if (
                $title === '' &&
                $description === '' &&
                !$form &&
                !$icon &&
                empty($items)
            ) {
                continue;
            }

            $versions[] = [
                'icon' =>
                $icon,

                'title' =>
                $title,

                'description' =>
                $description,

                'form' =>
                $form,

                'items' =>
                $items,
            ];
        }

        $validation_key =
            $this->validation_key(
                (int) $post_id
            );

        if ($errors) {
            set_transient(
                $validation_key,
                [
                    'versions' =>
                    $versions,

                    'errors' =>
                    array_values(
                        array_unique(
                            $errors
                        )
                    ),
                ],
                HOUR_IN_SECONDS
            );

            return;
        }

        delete_transient(
            $validation_key
        );

        update_post_meta(
            $post_id,
            '_bcob_versions',
            $versions
        );

        delete_post_meta(
            $post_id,
            '_bcob_icon'
        );

        delete_post_meta(
            $post_id,
            '_bcob_title'
        );

        delete_post_meta(
            $post_id,
            '_bcob_description'
        );

        delete_post_meta(
            $post_id,
            '_bcob_form'
        );

        delete_post_meta(
            $post_id,
            '_bcob_items'
        );
    }

    /**
     * Admin Footer JS
     */
    /**
     * Admin Footer JS
     */
    public function admin_footer_js(): void
    {
    ?>
        <script>
            jQuery(function($) {

                function openMedia($wrapper) {

                    const frame = wp.media({
                        title: 'Select Icon',
                        button: {
                            text: 'Use Icon'
                        },
                        multiple: false
                    });

                    frame.on('select', function() {

                        const attachment = frame.state().get('selection').first().toJSON();

                        $wrapper.find('.bcob-icon').val(attachment.id);

                        $wrapper.find('.bcob-icon-preview').html(
                            '<img src="' + attachment.url + '" style="max-width:80px;display:block;margin-bottom:10px;">'
                        );

                    });

                    frame.open();
                }

                $(document).on('click', '.bcob-upload-icon', function(e) {

                    e.preventDefault();

                    openMedia($(this).closest('.bcob-version'));

                });

                $(document).on('click', '.bcob-remove-icon', function() {

                    const $version = $(this).closest('.bcob-version');

                    $version.find('.bcob-icon').val('');

                    $version.find('.bcob-icon-preview').html('');

                });

                $('#bcob-add-version').on('click', function() {

                    let index = $('#bcob-versions .bcob-version').length;

                    let html = $('#bcob-version-template')
                        .html()
                        .replace(/__v__/g, index);

                    $('#bcob-versions').append(html);

                });

                $(document).on('click', '.bcob-remove-version', function() {

                    $(this)
                        .closest('.bcob-version')
                        .remove();

                    reindexVersions();

                });

                $(document).on('click', '.bcob-add-item', function() {

                    const $version = $(this).closest('.bcob-version');

                    const v = $('#bcob-versions .bcob-version').index($version);

                    const i = $version.find('.bcob-items tbody tr').length;

                    let html = $('#bcob-item-template').html();

                    html = html
                        .replace(/__v__/g, v)
                        .replace(/__i__/g, i);

                    $version.find('.bcob-items tbody').append(html);

                });

                $(document).on('click', '.bcob-remove-row', function() {

                    const $version = $(this).closest('.bcob-version');

                    $(this).closest('tr').remove();

                    reindexItems($version);

                });

                function reindexVersions() {

                    $('#bcob-versions .bcob-version').each(function(v) {

                        $(this)
                            .find('[name]')
                            .each(function() {

                                $(this).attr(
                                    'name',
                                    $(this)
                                    .attr('name')
                                    .replace(/bcob_versions\[\d+\]/, 'bcob_versions[' + v + ']')
                                );

                            });

                        reindexItems($(this));

                    });

                }

                function reindexItems($version) {

                    const v = $('#bcob-versions .bcob-version').index($version);

                    $version.find('.bcob-items tbody tr').each(function(i) {

                        $(this)
                            .find('[name]')
                            .each(function() {

                                let name = $(this).attr('name');

                                name = name.replace(/bcob_versions\[\d+\]/, 'bcob_versions[' + v + ']');

                                name = name.replace(/\[items\]\[\d+\]/, '[items][' + i + ']');

                                $(this).attr('name', name);

                            });

                    });

                }

            });
        </script>
    <?php
    }

    /**
     * Copy shortcode script
     */
    public function copy_shortcode_script(): void
    {
        global $typenow;

        if ($typenow !== 'bcob_membership') {
            return;
        }

    ?>

        <script>
            jQuery(function($) {

                $(document).on('click', '.bcob-copy-shortcode', function() {

                    const shortcode = $(this).data('shortcode');

                    navigator.clipboard.writeText(shortcode);

                    const $el = $(this);

                    const original = $el.text();

                    $el.text('Copied!');

                    setTimeout(function() {

                        $el.text(original);

                    }, 1200);

                });

            });
        </script>

<?php
    }
}
