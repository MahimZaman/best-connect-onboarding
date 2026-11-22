<?php

namespace BestConnectOnboarding\Admin\Settings;

defined('ABSPATH') || exit;

class Styles
{
    public function __construct()
    {
        add_action(
            'bcob_settings_tab_styles',
            [$this, 'render']
        );
    }

    public function render(): void
    {
?>
        <div class="bcob-style-library">

            <div class="bcob-style-library-intro">

                <h3>
                    <?php esc_html_e(
                        'Pre-designed HTML Classes',
                        'best-connect-onboarding'
                    ); ?>
                </h3>

                <p>
                    <?php esc_html_e(
                        'Use the classes below in your HTML content to create the pre-designed elements shown in the examples.',
                        'best-connect-onboarding'
                    ); ?>
                </p>

            </div>


            <!-- Blue Notice -->

            <div class="bcob-style-library-item">

                <div class="bcob-style-library-heading">

                    <h4>
                        <?php esc_html_e(
                            'Blue Notice',
                            'best-connect-onboarding'
                        ); ?>
                    </h4>

                    <code>blue-notice</code>

                </div>

                <div class="bcob-style-library-preview">

                    <p class="blue-notice">
                        <strong>Use the <code>blue-notice</code> class</strong>
                        on a paragraph to display an informational notice
                        with a soft blue background and highlighted left border.
                    </p>

                </div>

                <div class="bcob-style-library-instruction">

                    <strong>How to use:</strong>

                    <code>
                        &lt;p class="blue-notice"&gt;Your notice text here.&lt;/p&gt;
                    </code>

                </div>

            </div>


            <!-- Hero Section -->

            <div class="bcob-style-library-item">

                <div class="bcob-style-library-heading">

                    <h4>
                        <?php esc_html_e(
                            'Hero Section',
                            'best-connect-onboarding'
                        ); ?>
                    </h4>

                    <code>hero-section</code>

                </div>

                <div class="bcob-style-library-preview">

                    <section class="hero-section">

                        <div class="hero-badge">

                            <i class="ri-arrow-right-line"></i>

                            <?php esc_html_e(
                                'zahlung',
                                'best-connect-onboarding'
                            ); ?>

                        </div>

                        <h1 class="hero-title">

                            <?php esc_html_e(
                                'MITGLIEDSBEITRAG BEGLEICHEN',
                                'best-connect-onboarding'
                            ); ?>

                        </h1>

                        <p class="hero-description">

                            <?php esc_html_e(
                                'Erste Zahlung jetzt, danach jährlich automatisch, jederzeit online kündbar.',
                                'best-connect-onboarding'
                            ); ?>

                        </p>

                    </section>

                </div>

                <div class="bcob-style-library-instruction">

                    <strong>How to use:</strong>

                    <p>
                        <?php esc_html_e(
                            'Wrap the hero content inside the hero-section class. Use hero-badge for the small badge, hero-title for the main heading, and hero-description for the supporting text.',
                            'best-connect-onboarding'
                        ); ?>
                    </p>

                    <pre><code>&lt;section class="hero-section"&gt;

    &lt;div class="hero-badge"&gt;
        &lt;i class="ri-arrow-right-line"&gt;&lt;/i&gt;
        zahlung
    &lt;/div&gt;

    &lt;h1 class="hero-title"&gt;
        MITGLIEDSBEITRAG BEGLEICHEN
    &lt;/h1&gt;

    &lt;p class="hero-description"&gt;
        Erste Zahlung jetzt, danach jährlich automatisch,
        jederzeit online kündbar.
    &lt;/p&gt;

&lt;/section&gt;</code></pre>

                </div>

            </div>


            <!-- Group Label -->

            <div class="bcob-style-library-item">

                <div class="bcob-style-library-heading">

                    <h4>
                        <?php esc_html_e(
                            'Group Label',
                            'best-connect-onboarding'
                        ); ?>
                    </h4>

                    <code>bcob-group-label</code>

                </div>

                <div class="bcob-style-library-preview">

                    <p>
                        <label class="bcob-group-label">
                            KONTAKT
                        </label>
                    </p>

                </div>

                <div class="bcob-style-library-instruction">

                    <strong>How to use:</strong>

                    <p>
                        <?php esc_html_e(
                            'Use the bcob-group-label class on a label to create a section heading with a divider above it.',
                            'best-connect-onboarding'
                        ); ?>
                    </p>

                    <code>
                        &lt;p&gt;&lt;label class="bcob-group-label"&gt;KONTAKT&lt;/label&gt;&lt;/p&gt;
                    </code>

                </div>

            </div>


            <!-- Stats Card -->

            <div class="bcob-style-library-item">

                <div class="bcob-style-library-heading">

                    <h4>
                        <?php esc_html_e(
                            'Stats Card',
                            'best-connect-onboarding'
                        ); ?>
                    </h4>

                    <code>stats-card</code>

                </div>

                <div class="bcob-style-library-preview">

                    <div class="stats-card">

                        <div class="stats-left">

                            <div class="stats-label">
                                BUNDESLAND / HINWEIS
                            </div>

                            <div class="stats-number">
                                XXXXXXX
                            </div>

                            <div class="stats-subtitle">
                                Personen sind schon dabei
                            </div>

                        </div>

                        <div class="stats-right">

                            <p>
                                Lorem ipsum dolor sit amet, consectetur
                                adipiscing elit, sed diam nonumy eirmod
                                tempor invidunt ut labore et dolore magna
                                aliquyam erat, sed diam voluptua.
                            </p>

                        </div>

                    </div>

                </div>

                <div class="bcob-style-library-instruction">

                    <strong>How to use:</strong>

                    <p>
                        <?php esc_html_e(
                            'Use stats-card as the main container. Inside it, use stats-left for the statistics area and stats-right for the description. The stats-left section uses stats-label, stats-number and stats-subtitle for its three text levels.',
                            'best-connect-onboarding'
                        ); ?>
                    </p>

                    <pre><code>&lt;div class="stats-card"&gt;

    &lt;div class="stats-left"&gt;

        &lt;div class="stats-label"&gt;
            BUNDESLAND / HINWEIS
        &lt;/div&gt;

        &lt;div class="stats-number"&gt;
            XXXXXXX
        &lt;/div&gt;

        &lt;div class="stats-subtitle"&gt;
            Personen sind schon dabei
        &lt;/div&gt;

    &lt;/div&gt;

    &lt;div class="stats-right"&gt;

        &lt;p&gt;
            Your description here.
        &lt;/p&gt;

    &lt;/div&gt;

&lt;/div&gt;</code></pre>

                </div>

            </div>

        </div>
<?php
    }
}
