<?php

defined('ABSPATH') || exit;

?>

<div class="bcob-progress">

    <div class="bcob-progress-title">
        <?php echo esc_html($formatted_schema->title); ?>
    </div>

    <ul
        class="bcob-step-list"
        aria-label="<?php esc_attr_e(
                        'Onboarding steps',
                        'best-connect-onboarding'
                    ); ?>">

        <?php foreach ($steps as $index => $item) : ?>

            <li
                class="bcob-step-item <?php echo ($index + 1) === 1 ? 'is-active' : ''; ?>"
                data-step="<?php echo esc_attr($index + 1); ?>"
                role="button"
                tabindex="0"
                aria-current="<?php echo ($index + 1) === 1 ? 'step' : 'false'; ?>"
                aria-label="<?php echo esc_attr(
                                sprintf(
                                    __(
                                        'Step %1$d of %2$d: %3$s',
                                        'best-connect-onboarding'
                                    ),
                                    $index + 1,
                                    $total_steps,
                                    $item->title
                                )
                            ); ?>">

                <span class="bcob-step-number">
                    <?php echo $index + 1; ?>
                </span>

                <div class="bcob-step-text">

                    <div class="bcob-step-title">
                        <?php echo esc_html($item->title); ?>
                    </div>

                    <?php if (!empty($item->description)) : ?>

                        <div class="bcob-step-description">
                            <?php echo esc_html($item->description); ?>
                        </div>

                    <?php endif; ?>

                </div>

            </li>

        <?php endforeach; ?>

    </ul>

</div>