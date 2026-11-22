<?php

/**
 * Author Box Widget
 *
 * @package BestConnectOnboarding
 */

namespace BestConnectOnboarding\Forms\Widgets;

if (! defined('ABSPATH')) {
    exit;
}

class AuthorBoxWidget extends WidgetBase
{

    /**
     * Widget type.
     *
     * @return string
     */
    public function get_type(): string
    {
        return 'author_box';
    }

    /**
     * Widget title.
     *
     * @return string
     */
    public function get_title(): string
    {
        return __('Author Box', 'best-connect-onboarding');
    }

    /**
     * Widget icon.
     *
     * Dashicons class.
     *
     * @return string
     */
    public function get_icon(): string
    {
        return 'ri-user-3-line';
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
                'author_image'    => 0,
                'author_name'     => '',
                'author_job'      => '',
                'description'     => '',
                'background_color' => '',
            ],
        ];
    }

    public function get_fields(): array
    {
        return [
            [
                'type'  => 'media',
                'key'   => 'author_image',
                'label' => 'Author Image',
            ],
            [
                'type'  => 'text',
                'key'   => 'author_name',
                'label' => 'Author Name',
            ],
            [
                'type'  => 'text',
                'key'   => 'author_job',
                'label' => 'Author Job',
            ],
            [
                'type'  => 'textarea',
                'key'   => 'description',
                'label' => 'Description',
            ],
            [
                'type'  => 'text',
                'key'   => 'background_color',
                'label' => 'Background Color',
            ],
        ];
    }
}
