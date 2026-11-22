<?php

defined('ABSPATH') || exit;

$field_width = !empty($field->width) ? absint($field->width) : 100;

$field_required = !empty($field->required);

$field_validation = !empty($field->validation)
    ? wp_json_encode($field->validation)
    : '[]';

?>

<div
    class="bcob-field bcob-validator width-<?php echo esc_attr($field_width); ?>"
    data-field-id="<?php echo esc_attr($field->id); ?>"
    data-field-type="<?php echo esc_attr($field->type); ?>"
    data-required="<?php echo $field_required ? 1 : 0; ?>"
    data-validation='<?php echo esc_attr($field_validation); ?>'>

    <?php

    switch ($field->type) {

        case 'text':
            (new \BestConnectOnboarding\Forms\Fields\TextField())->render($field);
            break;

        case 'email':
            (new \BestConnectOnboarding\Forms\Fields\EmailField())->render($field);
            break;

        case 'number':
            (new \BestConnectOnboarding\Forms\Fields\NumberField())->render($field);
            break;

        case 'phone':
            (new \BestConnectOnboarding\Forms\Fields\PhoneField())->render($field);
            break;

        case 'select':
            (new \BestConnectOnboarding\Forms\Fields\SelectField())->render($field);
            break;

        case 'radio':
            (new \BestConnectOnboarding\Forms\Fields\RadioField())->render($field);
            break;

        case 'advanced_radio':
            (new \BestConnectOnboarding\Forms\Fields\AdvancedRadioField())->render($field);
            break;

        case 'checkbox':
            (new \BestConnectOnboarding\Forms\Fields\CheckboxField())->render($field);
            break;

        case 'advanced_checkbox':
            (new \BestConnectOnboarding\Forms\Fields\AdvancedCheckboxField())->render($field);
            break;

        case 'file':
            (new \BestConnectOnboarding\Forms\Fields\FileUploadField())->render($field);
            break;

        case 'message':
            (new \BestConnectOnboarding\Forms\Fields\MessageField())->render($field);
            break;

        case 'html':
            (new \BestConnectOnboarding\Forms\Fields\HtmlField())->render($field);
            break;

        case 'step-editor':
            (new \BestConnectOnboarding\Forms\Fields\StepEditor())->render($field);
            break;

        case 'coupon':
            (new \BestConnectOnboarding\Forms\Fields\CouponField())->render($field);
            break;

        case 'order-review':
            (new \BestConnectOnboarding\Forms\Fields\OrderReview())->render($field);
            break;

        case 'order-total':
            (new \BestConnectOnboarding\Forms\Fields\OrderTotal())->render($field);
            break;

        case 'payment':
            (new \BestConnectOnboarding\Forms\Fields\PaymentField())->render($field);
            break;

        default:

            do_action('bcob_render_field_' . $field->type, $field);

            break;
    }

    ?>
    <div
        class="bcob-error"
        data-error-for="<?php echo esc_attr($field->id); ?>"></div>

</div>