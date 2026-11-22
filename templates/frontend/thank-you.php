<?php

defined('ABSPATH') || exit;

$verified =
    !empty($verified);

$verification_error =
    !empty($verification_error);

$submission = [];

if (
    is_object($lead) &&
    !empty($lead->submission)
) {
    $submission =
        is_string($lead->submission)
        ? json_decode(
            $lead->submission,
            true
        )
        : $lead->submission;
}

$email = '';
$first_name = '';

foreach (
    ($submission['values'] ?? [])
    as $field
) {
    if (
        ($field['type'] ?? '') === 'email'
    ) {
        $email =
            sanitize_email(
                $field['value'] ?? ''
            );
    }

    if (
        ($field['type'] ?? '') === 'text' &&
        !$first_name &&
        (str_contains(
            strtolower(
                $field['label'] ?? ''
            ),
            'first'
        ) || str_contains(
            strtolower(
                $field['label'] ?? ''
            ),
            'vorname'
        ))
    ) {
        $first_name =
            sanitize_text_field(
                $field['value'] ?? ''
            );
    }
}

$form_settings =
    \BestConnectOnboarding\Emails\Templates::get_settings(
        is_array($submission)
            ? $submission
            : []
    );

$company_name =
    sanitize_text_field(
        $form_settings['company_name']
            ?? ''
    );

$sender_name =
    sanitize_text_field(
        $form_settings['sender_name']
            ?? ''
    );

$sender_email =
    sanitize_email(
        $form_settings['sender_email']
            ?? ''
    );

if ($company_name === '') {
    $company_name =
        sanitize_text_field(
            get_bloginfo('name')
        );
}

if ($sender_name === '') {
    $sender_name =
        $company_name;
}

if (!is_email($sender_email)) {
    $sender_email =
        sanitize_email(
            get_option('admin_email')
        );
}

$withdrawal_form_url =
    esc_url(
        $form_settings['withdrawal_form_url']
            ?? ''
    );


$payment_record = null;

if (
    is_object($lead) &&
    !empty($lead->id)
) {
    $payment_record =
        \BestConnectOnboarding\Payments\Payment::get_latest_for_lead(
            (int) $lead->id
        );
}

$created = '-';

if (
    $payment_record &&
    !empty($payment_record->created_at)
) {
    $created_timestamp =
        strtotime(
            $payment_record->created_at
        );

    if ($created_timestamp) {
        $created =
            wp_date(
                'd M, Y',
                $created_timestamp
            );
    }
}

$subscription_end = 0;

if (
    $payment_record &&
    $payment_record->payment_type === 'recurring' &&
    !empty($payment_record->subscription_id)
) {
    $subscription_end =
        \BestConnectOnboarding\Payments\Stripe::get_subscription_period_end(
            (string) $payment_record->subscription_id
        );
}

$end =
    $subscription_end
    ? wp_date(
        'd M, Y',
        $subscription_end
    )
    : '-';
$amount = isset($submission['amount']) ? bcob_price($submission['amount']) : bcob_price(0);
$products = isset($submission['products']) ? $submission['products'] : [];

?>
<script>
    $ = jQuery;
    let offset = 0;

    $("header:visible").each(function() {
        offset = Math.max(offset, $(this).outerHeight());
    });

    $("#wpadminbar").each(function() {
        offset += $(this).outerHeight();
    });

    $(".bcob-form-overlay").css({
        top: offset,
        height: "calc(100vh - " + offset + "px)",
    });

    $(".bcob-form-overlay").addClass("active");

    $("html, body").css("overflow", "hidden");
</script>

<?php if (!$verified): ?>

    <div class="bcob-confirmation-email">
        <div class="bcob-confirmation">

            <div class="bcob-confirmation__topbar"></div>

            <div class="bcob-confirmation__inner">

                <!-- Mail Icon -->
                <div class="bcob-confirmation__icon">
                    <svg width="25" height="19" viewBox="0 0 25 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22.6562 0C23.9258 0 25 1.07422 25 2.34375C25 3.125 24.6094 3.80859 24.0234 4.24805L13.4277 12.207C12.8418 12.6465 12.1094 12.6465 11.5234 12.207L0.927734 4.24805C0.341797 3.80859 0 3.125 0 2.34375C0 1.07422 1.02539 0 2.34375 0L22.6562 0ZM10.5957 13.4766C11.7188 14.3066 13.2324 14.3066 14.3555 13.4766L25 5.46875V15.625C25 17.3828 23.584 18.75 21.875 18.75H3.125C1.36719 18.75 0 17.3828 0 15.625L0 5.46875L10.5957 13.4766Z" fill="#009FDF" />
                    </svg>

                </div>

                <!-- Heading -->
                <h1 class="bcob-confirmation__title">
                    BESTÄTIGUNGS-LINK VERSCHICKT
                </h1>

                <p class="bcob-confirmation__description">
                    Wir haben eine E-Mail mit Ihrem Vertrag und einem Magic-Link an
                    <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a>
                    gesendet.
                    <strong>
                        Mit einem Klick darauf bestätigen Sie Ihre Mitgliedschaft rechtsverbindlich.
                    </strong>
                </p>

                <!-- Email Preview -->
                <div class="bcob-email-preview">

                    <div class="bcob-email-preview__caption">
                        Bestätigen Sie diese E-Mail in Ihrem Posteingang:
                    </div>

                    <div class="bcob-email-preview__content">

                        <div class="bcob-email-preview__watermark">
                            CONFIRMATION<br>EMAIL
                        </div>

                        <div class="bcob-email-header">

                            <div class="bcob-email-header__icon">
                                <svg width="23" height="17" viewBox="0 0 23 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14.3683 10.6934L12.7443 12.0597C12.1851 12.6621 11.8302 13.2753 11.8302 14.1359C11.8302 15.5022 12.8734 16.5242 14.2177 16.5242C15.1641 16.5242 15.8202 15.9002 16.3794 15.2332L22.5311 8.2621L16.3041 1.18337C15.7772 0.559413 15.1211 0 14.25 0C12.8626 0 11.8409 1.10807 11.8409 2.49584C11.8409 3.23814 12.4109 3.94817 12.9164 4.44303L14.3898 5.83081H2.92528C2.1617 5.83081 1.32283 5.90611 0.763583 6.46553C0.290376 6.93888 0 7.57359 0 8.25134C0 8.9291 0.344151 9.72518 0.924905 10.177C1.50566 10.6181 2.31226 10.6719 3.02207 10.6719H14.379L14.3683 10.6934Z" fill="white" />
                                </svg>

                            </div>

                            <div class="bcob-email-header__info">

                                <div class="bcob-email-header__name">
                                    <?php echo esc_html($sender_name); ?>
                                </div>

                                <div class="bcob-email-header__mail">
                                    <?php echo esc_html($sender_email); ?>
                                </div>

                            </div>

                        </div>

                        <h3 class="bcob-email-title">
                            Bestätigen Sie Ihre Mitgliedschaft
                        </h3>

                        <p class="bcob-email-text">
                            Hallo <?php echo $first_name; ?>, Ihre Anmeldung ist bei uns eingelangt.
                            Klicken Sie auf den Button, um Ihren Vertrag
                            rechtsverbindlich zu bestätigen.
                        </p>

                        <p class="bcob-email-text">
                            Der Link ist 24 Stunden gültig.
                        </p>

                        <a href="#" class="bcob-email-button">
                            Mitgliedschaft jetzt bestätigen
                            <span><i class="ri-arrow-right-line"></i></span>
                        </a>

                    </div>

                </div>

                <!-- Missing Mail -->
                <div class="bcob-box">

                    <h3>
                        Sie haben keine E-Mail erhalten?
                    </h3>

                    <p>
                        Prüfen Sie zuerst Ihren Spam-Ordner.
                        Ist der Link dort nicht zu finden,
                        können Sie ihn erneut anfordern
                        oder Ihre E-Mail-Adresse korrigieren.
                    </p>

                    <div class="bcob-box__buttons">

                        <a href="#" class="bcob-outline-button bcob-resend-confirmation">
                            E-Mail erneut senden
                        </a>

                        <a href="#" class="bcob-outline-button">
                            E-Mail Adresse ändern
                        </a>

                    </div>

                </div>

                <!-- Cancellation -->
                <div class="bcob-box">

                    <h3>
                        14 Tage Widerrufsrecht
                    </h3>

                    <p>
                        Sie können Ihre Mitgliedschaft innerhalb von
                        14 Tagen nach Abschluss ohne Angabe von Gründen
                        widerrufen.
                        Wir erstatten den Betrag binnen
                        14 Tagen vollständig zurück.
                    </p>

                    <?php if ($withdrawal_form_url !== '') : ?>

                        <a
                            href="<?php echo esc_url($withdrawal_form_url); ?>"
                            class="bcob-outline-button bcob-outline-button--single">

                            <?php esc_html_e(
                                'Widerrufs-Formular öffnen',
                                'best-connect-onboarding'
                            ); ?>

                        </a>

                    <?php endif; ?>

                </div>

                <!-- Footer -->
                <div class="bcob-footer">

                    <a href="#" class="bcob-outline-button bcob-footer__back">
                        <i class="ri-arrow-left-line"></i> Zurück
                    </a>

                    <a href="#" class="bcob-footer__save">
                        Speichern
                    </a>

                </div>

            </div>

        </div>
    </div>

<?php endif; ?>

<?php if ($verified): ?>

    <div class="bcob-confirm-membership">
        <div class="bcob-success">

            <div class="bcob-success__topbar"></div>

            <div class="bcob-success__body">

                <!-- Success Icon -->
                <div class="bcob-success__icon">
                    <svg width="27" height="25" viewBox="0 0 27 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.0859 25C8.59375 25 4.49219 22.6562 2.24609 18.75C0 14.8926 0 10.1562 2.24609 6.25C4.49219 2.39258 8.59375 0 13.0859 0C17.5293 0 21.6309 2.39258 23.877 6.25C26.123 10.1562 26.123 14.8926 23.877 18.75C21.6309 22.6562 17.5293 25 13.0859 25ZM18.6035 10.2051H18.5547C19.043 9.76562 19.043 9.0332 18.5547 8.54492C18.1152 8.10547 17.3828 8.10547 16.9434 8.54492L11.5234 14.0137L9.22852 11.7188C8.74023 11.2305 8.00781 11.2305 7.56836 11.7188C7.08008 12.1582 7.08008 12.8906 7.56836 13.3301L10.6934 16.4551C11.1328 16.9434 11.8652 16.9434 12.3535 16.4551L18.6035 10.2051Z" fill="#009FDF" />
                    </svg>

                </div>

                <!-- Heading -->

                <h1 class="bcob-success__title">
                    MITGLIEDSCHAFT BESTÄTIGT!
                </h1>

                <p class="bcob-success__subtitle">
                    <?php if ($first_name) : ?>

                        <?php
                        printf(
                            esc_html__(
                                'Vielen Dank, %s. Ihre Mitgliedschaft ist rechtsverbindlich aktiv.',
                                'best-connect-onboarding'
                            ),
                            esc_html($first_name)
                        );
                        ?>

                    <?php else : ?>

                        <?php
                        esc_html_e(
                            'Vielen Dank. Ihre Mitgliedschaft ist rechtsverbindlich aktiv.',
                            'best-connect-onboarding'
                        );
                        ?>

                    <?php endif; ?>

                    <?php
                    esc_html_e(
                        ' Eine Vertragskopie haben wir Ihnen per E-Mail zugesandt.',
                        'best-connect-onboarding'
                    );
                    ?>
                </p>

                <!-- Membership Summary -->

                <div class="bcob-summary">

                    <div class="bcob-summary__row">
                        <div class="bcob-summary__label">
                            Mitglieds-Nr.
                        </div>

                        <div class="bcob-summary__value">
                            <?php echo esc_html($lead->membership_number ?? '-'); ?>
                        </div>
                    </div>

                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <div class="bcob-summary__row">
                                <div class="bcob-summary__label">
                                    Produkt(e)
                                </div>

                                <div class="bcob-summary__value">
                                    <?php echo $product['name'] ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="bcob-summary__row">
                        <div class="bcob-summary__label">
                            Bezahlt
                        </div>

                        <div class="bcob-summary__value">
                            <?php echo $amount; ?> · Kreditkarte
                        </div>
                    </div>

                    <div class="bcob-summary__row">
                        <div class="bcob-summary__label">
                            Aktiviert am
                        </div>

                        <div class="bcob-summary__value">
                            <?php echo $created; ?>
                        </div>
                    </div>

                    <div class="bcob-summary__row">
                        <div class="bcob-summary__label">
                            Nächste Verlängerung
                        </div>

                        <div class="bcob-summary__value">
                            <?php echo $end; ?>
                        </div>
                    </div>

                </div>

                <!-- Information -->

                <div class="blue-notice">

                    <div class="bcob-info__title">
                        Was jetzt passiert:
                    </div>

                    <ol class="bcob-info__list">

                        <li>
                            Wir prüfen Ihre Daten und kontaktieren Sie binnen
                            3 Werktagen mit Ihrem persönlichen Tarifvergleich.
                        </li>

                        <li>
                            Sie wählen ggf. einen neuen Lieferanten —
                            wir übernehmen den Anbieterwechsel.
                        </li>

                        <li>
                            Sie erhalten alle Unterlagen digital und einen
                            Account zur Selbstverwaltung Ihrer Mitgliedschaft.
                        </li>

                    </ol>

                </div>

                <!-- Button -->

                <div class="bcob-success__actions" style="margin-bottom:52px;">
                    <a href="#" class="bcob-outline-button bcob-btn-back">
                        <i class="ri-arrow-left-line"></i> Zur Startseite
                    </a>
                </div>

            </div>

        </div>
    </div>

<?php endif; ?>