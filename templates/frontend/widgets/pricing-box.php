<?php
$settings = $widget->settings;
?>

<div
    class="bcob-pricing-box"
    <?php if (!empty($settings->container_background)) : ?>
    style="background: <?php echo esc_attr($settings->container_background); ?>;"
    <?php endif; ?>>

    <?php if (!empty($settings->label)) : ?>
        <div class="bcob-pricing-box__label">
            <?php echo esc_html($settings->label); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($settings->rating)) : ?>
        <div class="bcob-pricing-box__rating">

            <?php for ($i = 1; $i <= (int) $settings->rating; $i++) : ?>
                <i class="ri-star-fill"></i>
            <?php endfor; ?>

        </div>
    <?php endif; ?>

    <div class="bcob-pricing-box__price">

        <?php if (!empty($settings->price_heading)) : ?>
            <span class="bcob-pricing-box__heading">
                <?php echo esc_html($settings->price_heading); ?>
            </span>
        <?php endif; ?>

        <?php if (!empty($settings->unit)) : ?>
            <span class="bcob-pricing-box__unit">
                <?php echo esc_html($settings->unit); ?>
            </span>
        <?php endif; ?>

    </div>

    <?php if (!empty($settings->description_1)) : ?>
        <div class="bcob-pricing-box__description">
            <?php echo nl2br(esc_html($settings->description_1)); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($settings->description_2)) : ?>
        <div class="bcob-pricing-box__description">
            <?php echo nl2br(esc_html($settings->description_2)); ?>
        </div>
    <?php endif; ?>

</div>