<?php

namespace BestConnectOnboarding\Frontend;

use BestConnectOnboarding\Tracking\GoogleTags;
use BestConnectOnboarding\Payments\Stripe;

if (!defined('ABSPATH')) {
    exit;
}

class MembershipShortcode
{
    public function __construct()
    {
        add_shortcode('onboarding_portal', [$this, 'render']);

        add_action('wp_enqueue_scripts', [$this, 'assets']);

        add_action('wp_ajax_bcob_load_form', [$this, 'load_form']);
        add_action('wp_ajax_nopriv_bcob_load_form', [$this, 'load_form']);
    }

    public function assets(): void
    {
        $post =
            is_singular()
            ? get_queried_object()
            : null;

        $should_load = false;

        if ($post instanceof \WP_Post) {
            $should_load =
                $this->contains_portal_shortcode(
                    $post->post_content
                ) ||
                $this->contains_portal_shortcode(
                    get_post_meta(
                        $post->ID
                    )
                );
        }

        $should_load =
            (bool) apply_filters(
                'bcob_should_load_frontend_assets',
                $should_load,
                $post
            );

        if (!$should_load) {
            return;
        }

        wp_enqueue_style(
            'bcob-memberships',
            BCOB_URL . 'assets/css/frontend/memberships.css',
            [],
            BCOB_VER
        );

        wp_enqueue_script(
            'bcob-memberships',
            BCOB_URL . 'assets/js/frontend/memberships.js',
            ['jquery'],
            BCOB_VER,
            true
        );

        wp_localize_script(
            'bcob-memberships',
            'BCOBMembership',
            [
                'ajax' =>
                admin_url(
                    'admin-ajax.php'
                ),
            ]
        );
    }

    private function contains_portal_shortcode(
        mixed $value
    ): bool {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (
                    $this->contains_portal_shortcode(
                        $item
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        if (is_object($value)) {
            return $this->contains_portal_shortcode(
                get_object_vars(
                    $value
                )
            );
        }

        if (!is_string($value)) {
            return false;
        }

        $unserialized =
            maybe_unserialize(
                $value
            );

        if ($unserialized !== $value) {
            return $this->contains_portal_shortcode(
                $unserialized
            );
        }

        return has_shortcode(
            $value,
            'onboarding_portal'
        );
    }

    public function render($atts): string
    {
        $attr = shortcode_atts([
            'id' => null
        ], $atts);

        $portal_id = $attr['id'];

        if ($portal_id == null) {
            return __('No portals found', 'best-connect-onboarding');
        }

        $lead = false;

        $verified =
            !empty($_GET['verified']);

        $verification_error =
            !empty($_GET['verification_error']);

        if (!empty($_GET['success'])) {
            $lead =
                \BestConnectOnboarding\Leads\Lead::get_by_token(
                    sanitize_text_field(
                        wp_unslash(
                            $_GET['success']
                        )
                    )
                );
        }

        $query = new \WP_Query([
            'post_type'      => 'bcob_membership',
            'post_status'    => 'publish',
            'post__in'       => [$portal_id]
        ]);



        ob_start();

        if (
            !$query->have_posts() &&
            !$lead
        ) {
            return '';
        }

?>

        <div class="bcob-memberships">

            <?php

            while ($query->have_posts()) :

                $query->the_post();

                $id = get_the_ID();

                $versions = get_post_meta($id, '_bcob_versions', true);

            ?>

                <?php if (!empty($versions)): ?>
                    <?php foreach ($versions as $index => $version):

                        $icon = $version['icon'];

                        $title = $version['title'];

                        $description = $version['description'];

                        $form = $version['form'];

                        $offer_signature =
                            Stripe::create_offer_signature(
                                (int) $id,
                                (int) $index,
                                absint($form)
                            );
                    ?>
                        <label class="bcob-membership-card">

                            <input
                                type="radio"
                                name="bcob_membership"
                                value="<?php echo esc_attr($id); ?>"
                                data-form="<?php echo esc_attr($form); ?>"
                                data-portal="<?php echo esc_attr($index); ?>"
                                data-offer-signature="<?php echo esc_attr($offer_signature); ?>">

                            <span class="bcob-membership-check"></span>

                            <span class="bcob-membership-inner">

                                <span class="bcob-membership-icon">

                                    <?php
                                    echo wp_get_attachment_image(
                                        $icon,
                                        'thumbnail'
                                    );
                                    ?>

                                </span>

                                <span class="bcob-membership-title">

                                    <?php echo esc_html($title); ?>

                                </span>

                                <span class="bcob-membership-description">

                                    <?php echo wp_kses_post($description); ?>

                                </span>

                            </span>

                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php endwhile; ?>

        </div>

        <div
            class="bcob-form-overlay"
            role="dialog"
            aria-modal="true"
            aria-label="<?php esc_attr_e(
                            'Onboarding form',
                            'best-connect-onboarding'
                        ); ?>"
            aria-hidden="true"
            tabindex="-1">

            <div class="bcob-form-container">

                <?php
                if ($lead) {
                    include BCOB_PATH . 'templates/frontend/thank-you.php';
                }
                ?>

            </div>

        </div>

<?php

        wp_reset_postdata();

        return ob_get_clean();
    }

    public function load_form(): void
    {
        check_ajax_referer(
            'bcob_load_form',
            'nonce'
        );

        $membership =
            absint(
                $_POST['membership'] ?? 0
            );

        $portal_index =
            isset($_POST['portal_index'])
            ? absint($_POST['portal_index'])
            : -1;

        $offer_signature =
            sanitize_text_field(
                wp_unslash(
                    $_POST['offer_signature']
                        ?? ''
                )
            );

        if (
            !$membership ||
            $portal_index < 0
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'Invalid membership selection.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $membership_post =
            get_post($membership);

        if (
            !$membership_post ||
            $membership_post->post_type !== 'bcob_membership' ||
            $membership_post->post_status !== 'publish'
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'Membership not found.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $versions =
            get_post_meta(
                $membership,
                '_bcob_versions',
                true
            );

        if (
            !is_array($versions) ||
            !array_key_exists(
                $portal_index,
                $versions
            ) ||
            !is_array(
                $versions[$portal_index]
            )
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'Membership version not found.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        $portal =
            $versions[$portal_index];

        $form =
            absint(
                $portal['form'] ?? 0
            );

        if (!$form) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'No form is assigned to this membership.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        if (
            !Stripe::verify_offer_signature(
                $membership,
                $portal_index,
                $form,
                $offer_signature
            )
        ) {
            wp_send_json_error(
                [
                    'message' =>
                    __(
                        'Invalid membership selection.',
                        'best-connect-onboarding'
                    ),
                ]
            );
        }

        wp_send_json_success(
            [
                'form' =>
                do_shortcode(
                    '[bcob_form id="' .
                        $form .
                        '" membership="' .
                        $membership .
                        '" portal_index="' .
                        $portal_index .
                        '"]'
                ),

                'membership' => [
                    'id' =>
                    $membership,

                    'icon' =>
                    absint(
                        $portal['icon'] ?? 0
                    ),

                    'title' =>
                    sanitize_text_field(
                        $portal['title'] ?? ''
                    ),

                    'description' =>
                    wp_kses_post(
                        $portal['description'] ?? ''
                    ),

                    'items' =>
                    is_array(
                        $portal['items'] ?? null
                    )
                        ? $portal['items']
                        : [],

                    'form' =>
                    $form,

                    'portal_index' =>
                    $portal_index,

                    'context_signature' =>
                    Stripe::create_offer_signature(
                        $membership,
                        $portal_index,
                        $form
                    ),
                ],

                'tracking' => [
                    'event' =>
                    'portal_selection',

                    'form_id' =>
                    $form,

                    'membership_id' =>
                    $membership,

                    'portal_index' =>
                    $portal_index,

                    'status' =>
                    'success',
                ],
            ]
        );
    }
}
