<?php

/**
 * Field Registration
 *
 * Registers all built-in fields.
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms;

defined('ABSPATH') || exit;

use BestConnectOnboarding\Forms\Fields\TextField;
use BestConnectOnboarding\Forms\Fields\StepEditor;
use BestConnectOnboarding\Forms\Fields\EmailField;
use BestConnectOnboarding\Forms\Fields\NumberField;
use BestConnectOnboarding\Forms\Fields\PhoneField;
use BestConnectOnboarding\Forms\Fields\MessageField;
use BestConnectOnboarding\Forms\Fields\SelectField;
use BestConnectOnboarding\Forms\Fields\CheckboxField;
use BestConnectOnboarding\Forms\Fields\RadioField;
use BestConnectOnboarding\Forms\Fields\AdvancedCheckboxField;
use BestConnectOnboarding\Forms\Fields\AdvancedRadioField;
use BestConnectOnboarding\Forms\Fields\FileUploadField;
use BestConnectOnboarding\Forms\Fields\HtmlField;
use BestConnectOnboarding\Forms\Fields\CouponField;
use BestConnectOnboarding\Forms\Fields\OrderReview;
use BestConnectOnboarding\Forms\Fields\OrderTotal;
use BestConnectOnboarding\Forms\Fields\PaymentField;

class FieldRegistration
{

    /**
     * Constructor.
     */
    public function __construct()
    {

        $this->register();
    }

    /**
     * Register built-in fields.
     *
     * @return void
     */
    private function register(): void
    {

        add_filter(
            'bcob_registered_fields',
            [TextField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [StepEditor::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [EmailField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [PhoneField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [NumberField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [MessageField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [SelectField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [CheckboxField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [RadioField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [AdvancedCheckboxField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [AdvancedRadioField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [FileUploadField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [HtmlField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [CouponField::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [OrderReview::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [OrderTotal::class, 'register']
        );

        add_filter(
            'bcob_registered_fields',
            [PaymentField::class, 'register']
        );
    }
}
