<?php

namespace BestConnectOnboarding\Forms\Widgets;

defined('ABSPATH') || exit;

abstract class WidgetBase
{
    /**
     * Widget type.
     *
     * @return string
     */
    abstract public function get_type(): string;

    /**
     * Widget title.
     *
     * @return string
     */
    abstract public function get_title(): string;

    /**
     * Widget icon.
     *
     * @return string
     */
    abstract public function get_icon(): string;

    /**
     * Default widget schema.
     *
     * @return array
     */
    public function get_schema(): array
    {
        return [
            'type' => $this->get_type(),
            'settings' => [],
        ];
    }

    abstract public function get_fields(): array;
}
