<?php

defined('ABSPATH') || exit;

global $bcob_form_schema;

if (empty($form_data)) {
    echo esc_html__('Form doesn\'t exist', 'best-connect-onboarding');
    return;
}

$form_schema = $form_data->form_schema;

if (empty($form_schema)) {
    echo esc_html__('Invalid form schema.', 'best-connect-onboarding');
    return;
}

$formatted_schema = json_decode($form_schema);

if (!$formatted_schema) {
    echo esc_html__('Unable to parse form schema.', 'best-connect-onboarding');
    return;
}

$form_title = $formatted_schema->title ?? '';

$settings = $formatted_schema->settings ?? new stdClass();

$conditions = $formatted_schema->conditions ?? [];

$steps = $formatted_schema->steps ?? [];

$total_steps = count($steps);

if (!$total_steps) {
    echo esc_html__('No steps found.', 'best-connect-onboarding');
    return;
}

$form_schema_assoc = json_decode(
    $form_schema,
    true
);

$bcob_form_schema['steps'] =
    $form_schema_assoc['steps'] ?? [];

$frontend_form = [
    'id' =>
    (int) $form_data->id,

    'title' =>
    sanitize_text_field(
        $form_schema_assoc['title'] ?? ''
    ),

    'settings' =>
    [],

    'conditions' =>
    [],

    'steps' =>
    [],
];

$form_settings =
    is_array(
        $form_schema_assoc['settings'] ?? null
    )
    ? $form_schema_assoc['settings']
    : [];

if (
    array_key_exists(
        'form_type',
        $form_settings
    )
) {
    $frontend_form['settings']['form_type'] =
        sanitize_key(
            $form_settings['form_type']
        );
}

if (
    array_key_exists(
        'enable_tracking',
        $form_settings
    )
) {
    $frontend_form['settings']['enable_tracking'] =
        (bool) $form_settings['enable_tracking'];
}

foreach (
    ($form_schema_assoc['conditions'] ?? [])
    as $condition
) {
    if (!is_array($condition)) {
        continue;
    }

    $rules = [];

    foreach (
        ($condition['rules'] ?? [])
        as $rule
    ) {
        if (!is_array($rule)) {
            continue;
        }

        $rules[] = [
            'field' =>
            sanitize_text_field(
                $rule['field'] ?? ''
            ),

            'operator' =>
            sanitize_key(
                $rule['operator'] ?? ''
            ),

            'value' =>
            $rule['value'] ?? '',
        ];
    }

    $frontend_form['conditions'][] = [
        'relation' =>
        sanitize_key(
            $condition['relation'] ?? 'and'
        ),

        'action' =>
        sanitize_key(
            $condition['action'] ?? ''
        ),

        'target' =>
        sanitize_text_field(
            $condition['target'] ?? ''
        ),

        'rules' =>
        $rules,
    ];
}

foreach (
    ($form_schema_assoc['steps'] ?? [])
    as $step
) {
    if (!is_array($step)) {
        continue;
    }

    $frontend_step = [
        'id' =>
        sanitize_text_field(
            $step['id'] ?? ''
        ),

        'title' =>
        sanitize_text_field(
            $step['title'] ?? ''
        ),

        'fields' =>
        [],
    ];

    foreach (
        ($step['fields'] ?? [])
        as $field
    ) {
        if (!is_array($field)) {
            continue;
        }

        $frontend_step['fields'][] = [
            'id' =>
            sanitize_text_field(
                $field['id'] ?? ''
            ),

            'key' =>
            sanitize_text_field(
                $field['key'] ?? ''
            ),

            'type' =>
            sanitize_key(
                $field['type'] ?? ''
            ),

            'label' =>
            sanitize_text_field(
                $field['label'] ?? ''
            ),

            'required' =>
            !empty($field['required']),

            'placeholder' =>
            sanitize_text_field(
                $field['placeholder'] ?? ''
            ),

            'options' =>
            is_array(
                $field['options'] ?? null
            )
                ? $field['options']
                : [],
        ];
    }

    $frontend_form['steps'][] =
        $frontend_step;
}

?>

<div
    id="bcob-form"
    class="bcob-form"
    data-form-id="<?php echo esc_attr($form_data->id); ?>"
    data-total-steps="<?php echo esc_attr($total_steps); ?>">

    <aside class="bcob-left-column">

        <?php
        require BCOB_PATH . 'templates/frontend/progress.php';
        ?>

    </aside>

    <main class="bcob-center-column">
        <?php require BCOB_PATH . 'templates/frontend/progress-bar.php'; ?>

        <form
            id="bcob-form-element"
            method="post"
            enctype="multipart/form-data"
            novalidate>

            <?php

            foreach ($steps as $index => $step) {

                $current_step = $index + 1;

                require BCOB_PATH . 'templates/frontend/step.php';
            }

            require BCOB_PATH . 'templates/frontend/navigation.php';

            ?>

        </form>

    </main>

    <aside class="bcob-right-column">

        <?php
        require BCOB_PATH . 'templates/frontend/sidebar.php';
        ?>

    </aside>

</div>

<script>
    window.BCOB = window.BCOB || {};

    window.BCOB.form =
        <?php echo wp_json_encode($frontend_form); ?>;

    <?php if (!empty($membership_context)) : ?>

        window.BCOB.membership =
            <?php echo wp_json_encode(
                $membership_context
            ); ?>;

    <?php elseif (!isset($membership_context)) : ?>

        window.BCOB.membership =
            window.BCOB.membership || {};

    <?php endif; ?>

    window.BCOB.validation = {};

    <?php foreach ($steps as $step) :

        if (empty($step->fields)) {
            continue;
        }

        foreach ($step->fields as $field) :

    ?>

            window.BCOB.validation["<?php echo esc_js($field->id); ?>"] = {
                required: <?php echo !empty($field->required) ? 'true' : 'false'; ?>,
                rules: <?php echo wp_json_encode($field->validation ?? []); ?>
            };

    <?php

        endforeach;

    endforeach;

    ?>
</script>