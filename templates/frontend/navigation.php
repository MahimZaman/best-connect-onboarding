<?php

defined('ABSPATH') || exit;

?>

<div class="bcob-navigation">

    <button
        type="button"
        class="bcob-btn bcob-prev"
        style="display:none;"
        aria-label="<?php esc_attr_e(
                        'Go to previous step',
                        'best-connect-onboarding'
                    ); ?>">
        <i class="ri-arrow-left-line"></i> Zurück
    </button>

    <button
        type="button"
        class="bcob-btn bcob-save">
        Speichern
    </button>

    <button
        type="button"
        class="bcob-btn bcob-next"
        aria-label="<?php esc_attr_e(
                        'Go to next step',
                        'best-connect-onboarding'
                    ); ?>">
        Weiter <i class="ri-arrow-right-line"></i>
    </button>

    <button
        type="submit"
        class="bcob-btn bcob-submit"
        style="display:none;"
        aria-label="<?php esc_attr_e(
                        'Submit onboarding form',
                        'best-connect-onboarding'
                    ); ?>">
        Absenden
    </button>

</div>