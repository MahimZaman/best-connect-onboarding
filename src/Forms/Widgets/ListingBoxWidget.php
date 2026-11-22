<?php

/**
 * Listing Box Widget
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Widgets;

if (! defined('ABSPATH')) {
    exit;
}

class ListingBoxWidget extends WidgetBase
{

    /**
     * Widget type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'listing_box';
    }

    /**
     * Widget title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Listing Box', 'best-connect-onboarding');
    }

    /**
     * Widget icon.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-list-check-3';
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
                'items'                => [],
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
                'type'   => 'repeater',
                'key'    => 'items',
                'label'  => 'Items',
                'fields' => [
                    [
                        'type'  => 'text',
                        'key'   => 'icon',
                        'label' => 'Remix Icon',
                    ],
                    [
                        'type'  => 'text',
                        'key'   => 'label',
                        'label' => 'Label',
                    ],
                    [
                        'type'  => 'text',
                        'key'   => 'value',
                        'label' => 'Value',
                    ],
                ],
            ],
            [
                'type'  => 'text',
                'key'   => 'container_background',
                'label' => 'Container Background',
            ],
        ];
    }
}
