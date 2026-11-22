<?php

defined('ABSPATH') || exit;

?>

<section
    class="bcob-step <?php echo $current_step === 1 ? 'is-active' : ''; ?>"
    id="<?php echo esc_attr($step->id); ?>"
    data-step="<?php echo esc_attr($current_step); ?>">

    <div class="bcob-step-inner">

        <?php

        if (!empty($step->fields)) :

            foreach ($step->fields as $field) :

                require BCOB_PATH . 'templates/frontend/field-wrapper.php';

            endforeach;

        endif;

        ?>

    </div>

</section>