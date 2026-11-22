<?php
$settings = $widget->settings;
?>

<div
    class="bcob-listing-box"
    <?php if (!empty($settings->container_background)) : ?>
    style="background: <?php echo esc_attr($settings->container_background); ?>;"
    <?php endif; ?>>

    <?php if (!empty($settings->label)) : ?>
        <div class="bcob-listing-box__title">
            <?php echo esc_html($settings->label); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($settings->items)) : ?>

        <div class="bcob-listing-box__items">

            <?php foreach ($settings->items as $item) : ?>

                <div class="bcob-listing-box__item">

                    <div class="bcob-listing-box__left">

                        <div class="bcob-listing-box__icon">
                            <i class="<?php echo esc_attr($item->icon); ?>"></i>
                        </div>

                        <div class="bcob-listing-box__label">
                            <?php echo esc_html($item->label); ?>
                        </div>

                    </div>

                    <div class="bcob-listing-box__value">
                        <?php echo esc_html($item->value); ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>