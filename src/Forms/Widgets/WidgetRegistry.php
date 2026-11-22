<?php

namespace BestConnectOnboarding\Forms\Widgets;

use BestConnectOnboarding\Forms\Widgets\AuthorBoxWidget;

defined('ABSPATH') || exit;

class WidgetRegistry
{
    /**
     * Registered widgets.
     *
     * @var array
     */
    private array $widgets = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->register(new AuthorBoxWidget());
        $this->register(new PricingBoxWidget());
        $this->register(new ListingBoxWidget());

        do_action('bcob_register_widgets', $this);
    }

    /**
     * Register widget.
     *
     * @param WidgetBase $widget Widget.
     *
     * @return void
     */
    public function register(WidgetBase $widget): void
    {
        $this->widgets[$widget->get_type()] = $widget;
    }

    /**
     * Get widgets.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->widgets;
    }

    /**
     * Get widget.
     *
     * @param string $type Widget type.
     *
     * @return WidgetBase|null
     */
    public function get(string $type): ?WidgetBase
    {
        return $this->widgets[$type] ?? null;
    }

    /**
     * Export schemas for JS.
     *
     * @return array
     */
    public function get_schemas(): array
    {
        $widgets = [];

        foreach ($this->widgets as $widget) {
            $widgets[$widget->get_type()] = [
                'schema' => $widget->get_schema(),
                'fields' => $widget->get_fields(),
            ];
        }

        return $widgets;
    }

    public function exists(string $type): bool
    {
        return isset($this->widgets[$type]);
    }
}
