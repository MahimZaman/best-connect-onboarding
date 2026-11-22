<?php

defined('ABSPATH') || exit;

foreach ($steps as $index => $step) :

?>

    <div
        class="bcob-sidebar-step <?php echo ($index + 1) === 1 ? 'is-active' : ''; ?>"
        data-step="<?php echo esc_attr($index + 1); ?>">

        <?php

        if (
            !empty($step->sidebar) &&
            !empty($step->sidebar->widgets)
        ) :

            foreach ($step->sidebar->widgets as $widget) :
                switch ($widget->type) {
                    case 'author_box':
                        require BCOB_PATH . 'templates/frontend/widgets/author-box.php';
                        break;
                    case 'listing_box':
                        require BCOB_PATH . 'templates/frontend/widgets/listing-box.php';
                        break;
                    case 'pricing_box':
                        require BCOB_PATH . 'templates/frontend/widgets/pricing-box.php';
                        break;
                    default:
                        do_action('bcob_render_widget_' . $widget->type, $widget);
                        break;
                }

            endforeach;

        endif;

        ?>

    </div>

<?php endforeach; ?>