<?php

/**
 * Pricing Box Widget
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Widgets;

if (! defined('ABSPATH')) {
    exit;
}

class PricingBoxWidget extends WidgetBase
{

    /**
     * Widget type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'pricing_box';
    }

    /**
     * Widget title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Pricing Box', 'best-connect-onboarding');
    }

    /**
     * Widget icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-price-tag-3-line';
    }

    /**
     * Default widget schema.
     *
     * @return array
     */
    public function get_schema(): array
    {
        return [
            'type'     => $this->get_type(),
            'settings' => [
                'label'                => '',
                'rating'               => '',
                'price_heading'        => '',
                'unit'                 => '',
                'description_1'        => '',
                'description_2'        => '',
                'container_background' => '',
            ],
        ];
    }

    public function get_fields(): array
    {
        return [
            [
                'type'  => 'text',
                'key'   => 'label',
                'label' => 'Label',
            ],
            [
                'type'  => 'number',
                'key'   => 'rating',
                'label' => 'Rating',
            ],
            [
                'type'  => 'text',
                'key'   => 'price_heading',
                'label' => 'Price / Heading',
            ],
            [
                'type'  => 'text',
                'key'   => 'unit',
                'label' => 'Unit',
            ],
            [
                'type'  => 'textarea',
                'key'   => 'description_1',
                'label' => 'Description 1',
            ],
            [
                'type'  => 'textarea',
                'key'   => 'description_2',
                'label' => 'Description 2',
            ],
            [
                'type'  => 'text',
                'key'   => 'container_background',
                'label' => 'Container Background',
            ],
        ];
    }
}
