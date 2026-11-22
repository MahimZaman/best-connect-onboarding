<?php

namespace BestConnectOnboarding\Admin;

use BestConnectOnboarding\Helpers\Logger;

defined('ABSPATH') || exit;

class Logs
{
    public static function render(): void
    {
        if (
            !current_user_can(
                'manage_options'
            )
        ) {
            wp_die(
                esc_html__(
                    'Permission denied.',
                    'best-connect-onboarding'
                )
            );
        }

        Logger::ensure_table();

        if (
            isset(
                $_POST['bcob_clear_logs']
            )
        ) {
            check_admin_referer(
                'bcob_clear_logs'
            );

            Logger::clear();

            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__(
                    'Logs cleared.',
                    'best-connect-onboarding'
                ) .
                '</p></div>';
        }

        $level =
            sanitize_key(
                wp_unslash(
                    $_GET['level'] ?? ''
                )
            );

        $channel =
            sanitize_key(
                wp_unslash(
                    $_GET['channel'] ?? ''
                )
            );

        $per_page = 25;

        $total =
            Logger::count_logs(
                [
                    'level' =>
                    $level,

                    'channel' =>
                    $channel,
                ]
            );

        $total_pages =
            max(
                1,
                (int) ceil(
                    $total /
                        $per_page
                )
            );

        $current_page =
            max(
                1,
                absint(
                    $_GET['paged'] ?? 1
                )
            );

        $current_page =
            min(
                $current_page,
                $total_pages
            );

        $logs =
            Logger::get_logs(
                [
                    'level' =>
                    $level,

                    'channel' =>
                    $channel,

                    'limit' =>
                    $per_page,

                    'offset' => (
                        $current_page -
                        1
                    ) *
                        $per_page,
                ]
            );

        $channels =
            Logger::get_channels();
?>

        <div class="wrap bcob-logs-page">

            <h1>
                <?php
                esc_html_e(
                    'System Logs',
                    'best-connect-onboarding'
                );
                ?>
            </h1>

            <p>
                <?php
                esc_html_e(
                    'Technical operational logs only. Customer form values and personal data are not stored here.',
                    'best-connect-onboarding'
                );
                ?>
            </p>

            <div
                style="
                    display:flex;
                    align-items:flex-end;
                    justify-content:space-between;
                    gap:16px;
                    margin:20px 0;
                    flex-wrap:wrap;
                ">

                <form
                    method="get"
                    style="
                        display:flex;
                        gap:10px;
                        align-items:flex-end;
                        flex-wrap:wrap;
                    ">

                    <input
                        type="hidden"
                        name="page"
                        value="bcob-logs">

                    <div>

                        <label
                            for="bcob-log-level"
                            style="display:block;margin-bottom:5px;">
                            <?php
                            esc_html_e(
                                'Level',
                                'best-connect-onboarding'
                            );
                            ?>
                        </label>

                        <select
                            id="bcob-log-level"
                            name="level">

                            <option value="">
                                <?php
                                esc_html_e(
                                    'All levels',
                                    'best-connect-onboarding'
                                );
                                ?>
                            </option>

                            <option
                                value="error"
                                <?php selected($level, 'error'); ?>>
                                Error
                            </option>

                            <option
                                value="warning"
                                <?php selected($level, 'warning'); ?>>
                                Warning
                            </option>

                            <option
                                value="info"
                                <?php selected($level, 'info'); ?>>
                                Info
                            </option>

                        </select>

                    </div>

                    <div>

                        <label
                            for="bcob-log-channel"
                            style="display:block;margin-bottom:5px;">
                            <?php
                            esc_html_e(
                                'Channel',
                                'best-connect-onboarding'
                            );
                            ?>
                        </label>

                        <select
                            id="bcob-log-channel"
                            name="channel">

                            <option value="">
                                <?php
                                esc_html_e(
                                    'All channels',
                                    'best-connect-onboarding'
                                );
                                ?>
                            </option>

                            <?php
                            foreach (
                                $channels
                                as $log_channel
                            ) :
                            ?>

                                <option
                                    value="<?php echo esc_attr($log_channel); ?>"
                                    <?php selected($channel, $log_channel); ?>>

                                    <?php
                                    echo esc_html(
                                        ucfirst(
                                            $log_channel
                                        )
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <button
                        type="submit"
                        class="button">

                        <?php
                        esc_html_e(
                            'Filter',
                            'best-connect-onboarding'
                        );
                        ?>

                    </button>

                    <a
                        href="<?php echo esc_url(
                                    admin_url(
                                        'admin.php?page=bcob-logs'
                                    )
                                ); ?>"
                        class="button">

                        <?php
                        esc_html_e(
                            'Reset',
                            'best-connect-onboarding'
                        );
                        ?>

                    </a>

                </form>

                <form method="post">

                    <?php
                    wp_nonce_field(
                        'bcob_clear_logs'
                    );
                    ?>

                    <button
                        type="submit"
                        name="bcob_clear_logs"
                        value="1"
                        class="button"
                        onclick="return confirm('<?php echo esc_js(
                                                        __(
                                                            'Clear all system logs?',
                                                            'best-connect-onboarding'
                                                        )
                                                    ); ?>');">

                        <?php
                        esc_html_e(
                            'Clear Logs',
                            'best-connect-onboarding'
                        );
                        ?>

                    </button>

                </form>

            </div>

            <div style="overflow-x:auto;">

                <table class="widefat striped">

                    <thead>

                        <tr>
                            <th>Time</th>
                            <th>Level</th>
                            <th>Channel</th>
                            <th>Operation</th>
                            <th>Operation ID</th>
                            <th>Form</th>
                            <th>Lead</th>
                            <th>Payment</th>
                            <th>Message</th>
                            <th>Context</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (!$logs) : ?>

                            <tr>

                                <td colspan="10">
                                    <?php
                                    esc_html_e(
                                        'No log entries found.',
                                        'best-connect-onboarding'
                                    );
                                    ?>
                                </td>

                            </tr>

                        <?php else : ?>

                            <?php foreach ($logs as $log) : ?>

                                <?php
                                $context =
                                    json_decode(
                                        $log->context ?? '',
                                        true
                                    );

                                if (!is_array($context)) {
                                    $context = [];
                                }
                                ?>

                                <tr>

                                    <td>
                                        <?php echo esc_html($log->created_at); ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?php echo esc_html(
                                                strtoupper(
                                                    $log->level
                                                )
                                            ); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php echo esc_html($log->channel); ?>
                                    </td>

                                    <td>
                                        <?php echo esc_html($log->operation); ?>
                                    </td>

                                    <td>
                                        <code>
                                            <?php echo esc_html($log->operation_id); ?>
                                        </code>
                                    </td>

                                    <td>
                                        <?php echo esc_html($log->form_id ?: '—'); ?>
                                    </td>

                                    <td>
                                        <?php echo esc_html($log->lead_id ?: '—'); ?>
                                    </td>

                                    <td>
                                        <?php echo esc_html($log->payment_id ?: '—'); ?>
                                    </td>

                                    <td>
                                        <?php echo esc_html($log->message); ?>
                                    </td>

                                    <td>

                                        <?php if ($context) : ?>

                                            <code style="white-space:pre-wrap;">
                                                <?php
                                                echo esc_html(
                                                    wp_json_encode(
                                                        $context,
                                                        JSON_PRETTY_PRINT |
                                                            JSON_UNESCAPED_SLASHES
                                                    )
                                                );
                                                ?>
                                            </code>

                                        <?php else : ?>

                                            —

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <?php if ($total_pages > 1) : ?>

                <div
                    class="tablenav bottom"
                    style="margin-top:16px;">

                    <div class="tablenav-pages">

                        <?php
                        echo wp_kses_post(
                            paginate_links(
                                [
                                    'base' =>
                                    add_query_arg(
                                        [
                                            'page' =>
                                            'bcob-logs',

                                            'level' =>
                                            $level,

                                            'channel' =>
                                            $channel,

                                            'paged' =>
                                            '%#%',
                                        ],
                                        admin_url(
                                            'admin.php'
                                        )
                                    ),

                                    'current' =>
                                    $current_page,

                                    'total' =>
                                    $total_pages,

                                    'prev_text' =>
                                    '&lsaquo;',

                                    'next_text' =>
                                    '&rsaquo;',
                                ]
                            )
                        );
                        ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>

<?php
    }
}
