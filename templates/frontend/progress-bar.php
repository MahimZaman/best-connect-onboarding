<?php

defined('ABSPATH') || exit;

$current_step = 1;
$total_steps  = count($steps);
$percent      = round(($current_step / $total_steps) * 100);

?>

<div
    class="bcob-form-progress"
    role="progressbar"
    aria-label="<?php esc_attr_e(
                    'Onboarding progress',
                    'best-connect-onboarding'
                ); ?>"
    aria-valuemin="1"
    aria-valuemax="<?php echo esc_attr($total_steps); ?>"
    aria-valuenow="<?php echo esc_attr($current_step); ?>"
    aria-valuetext="<?php echo esc_attr(
                        sprintf(
                            __(
                                'Step %1$d of %2$d',
                                'best-connect-onboarding'
                            ),
                            $current_step,
                            $total_steps
                        )
                    ); ?>">

    <div class="bcob-form-progress-top">

        <div class="bcob-form-progress-title">
            SCHRITT
            <span class="bcob-current-step"><?php echo $current_step; ?></span>
            VON
            <span class="bcob-total-steps"><?php echo $total_steps; ?></span>
        </div>

        <div
            class="bcob-form-progress-percent"
            aria-hidden="true">
            <?php echo $percent; ?> %
        </div>

    </div>

    <div class="bcob-form-progress-bar">

        <div
            class="bcob-form-progress-fill"
            style="width:<?php echo esc_attr($percent); ?>%;"></div>

    </div>

</div>