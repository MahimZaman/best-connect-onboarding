<?php
$settings = $widget->settings;
?>

<div
    class="bcob-author-box"
    style="background: <?php echo esc_attr($settings->background_color ?: '#009FDF'); ?>;">

    <div class="bcob-author-box__header">

        <?php if (!empty($settings->author_image_url)) : ?>
            <div class="bcob-author-box__image">
                <img
                    src="<?php echo esc_url($settings->author_image_url); ?>"
                    alt="<?php echo esc_attr($settings->author_name); ?>">
            </div>
        <?php endif; ?>

        <div class="bcob-author-box__content">

            <?php if (!empty($settings->author_name)) : ?>
                <h3 class="bcob-author-box__name">
                    <?php echo esc_html($settings->author_name); ?>
                </h3>
            <?php endif; ?>

            <?php if (!empty($settings->author_job)) : ?>
                <div class="bcob-author-box__job">
                    <?php echo esc_html($settings->author_job); ?>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <?php if (!empty($settings->description)) : ?>

        <div class="bcob-author-box__description">
            <?php echo wpautop(esc_html($settings->description)); ?>
        </div>

    <?php endif; ?>

</div>