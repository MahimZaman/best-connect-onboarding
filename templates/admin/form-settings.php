<?php

/**
 * Form Settings Template
 *
 * @package BestConnectOnboarding
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<div class="bcob-settings-wrap">
    <div class="bcob-settings-main">

        <!-- ==========================================
     Contract Rules
========================================== -->

        <div class="bcob-settings-card">

            <div class="bcob-settings-card-header">

                <h3><?php esc_html_e('Contract Rules', 'best-connect-onboarding'); ?></h3>

                <p>

                    <?php esc_html_e('Select the PDF contract that should be used when the following conditions are matched.', 'best-connect-onboarding'); ?>

                </p>

            </div>

            <div class="bcob-settings-card-body">

                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Match Conditions Using', 'best-connect-onboarding'); ?>

                    </label>

                    <select
                        id="bcob-contract-relation"
                        class="bcob-contract-relation">

                        <option value="AND">

                            <?php esc_html_e('AND (All conditions must match)', 'best-connect-onboarding'); ?>

                        </option>

                        <option value="OR">

                            <?php esc_html_e('OR (Any condition may match)', 'best-connect-onboarding'); ?>

                        </option>

                    </select>

                </div>

                <div id="bcob-contract-rules">

                    <div
                        class="bcob-contract-rule-template"
                        style="display:none;">

                        <div class="bcob-grid bcob-grid-2">

                            <div class="bcob-form-group">

                                <label>

                                    <?php esc_html_e('Field', 'best-connect-onboarding'); ?>

                                </label>

                                <select class="bcob-contract-field">

                                    <option value="">

                                        <?php esc_html_e('Select Field', 'best-connect-onboarding'); ?>

                                    </option>

                                    <!-- Populated by JavaScript -->

                                </select>

                            </div>

                            <div class="bcob-form-group">

                                <label>

                                    <?php esc_html_e('Condition', 'best-connect-onboarding'); ?>

                                </label>

                                <select class="bcob-contract-operator">

                                    <option value="=">Equals</option>

                                    <option value="!=">Not Equals</option>

                                    <option value=">">Greater Than</option>

                                    <option value=">=">Greater Than or Equal</option>

                                    <option value="<">Less Than</option>

                                    <option value="<=">Less Than or Equal</option>

                                    <option value="contains">Contains</option>

                                    <option value="not_contains">Does Not Contain</option>

                                    <option value="starts_with">Starts With</option>

                                    <option value="ends_with">Ends With</option>

                                    <option value="empty">Is Empty</option>

                                    <option value="not_empty">Is Not Empty</option>

                                </select>

                            </div>

                        </div>

                        <div class="bcob-form-group">

                            <label>

                                <?php esc_html_e('Value', 'best-connect-onboarding'); ?>

                            </label>

                            <input
                                type="text"
                                class="bcob-contract-value"
                                placeholder="<?php esc_attr_e('Enter comparison value...', 'best-connect-onboarding'); ?>">

                        </div>

                        <div class="bcob-form-group">

                            <label>

                                <?php esc_html_e('Contract PDF', 'best-connect-onboarding'); ?>

                            </label>

                            <div class="bcob-contract-pdf">

                                <input
                                    type="hidden"
                                    class="bcob-contract-pdf-id">

                                <div class="bcob-contract-pdf-info">

                                    <i class="ri-file-pdf-2-fill"></i>

                                    <div>

                                        <strong class="bcob-contract-pdf-name">
                                            No PDF Selected
                                        </strong>

                                        <small>
                                            PDF contracts only
                                        </small>

                                    </div>

                                </div>

                                <div class="bcob-contract-pdf-actions">

                                    <button
                                        type="button"
                                        class="button button-secondary bcob-select-contract-pdf">
                                        <i class="ri-refresh-line"></i>
                                        <?php esc_html_e('Change PDF', 'best-connect-onboarding'); ?>
                                    </button>

                                    <button
                                        type="button"
                                        class="button button-secondary bcob-map-contract-fields"
                                        title="<?php esc_attr_e('Map PDF Fields', 'best-connect-onboarding'); ?>"
                                        disabled>
                                        <i class="ri-links-line"></i>
                                    </button>

                                    <label class="bcob-contract-preview-toggle">
                                        <span class="bcob-contract-preview-label">
                                            <?php esc_html_e('PDF Preview', 'best-connect-onboarding'); ?>
                                        </span>

                                        <span class="bcob-switch">
                                            <input
                                                type="checkbox"
                                                class="bcob-contract-preview"
                                                checked>
                                            <span class="bcob-slider"></span>
                                        </span>
                                    </label>

                                </div>

                                <div class="bcob-contract-mapping-status">
                                    <span class="bcob-contract-mapping-count">
                                        <?php esc_html_e('No fields mapped', 'best-connect-onboarding'); ?>
                                    </span>
                                </div>

                            </div>

                        </div>

                        <div class="bcob-form-group">

                            <button
                                type="button"
                                class="bcob-remove-contract-rule"
                                title="Remove Condition">

                                <i class="ri-delete-bin-line"></i>

                            </button>

                        </div>

                        <hr class="bcob-settings-separator">

                    </div>

                    <div id="bcob-contract-rule-list"></div>

                </div>

                <button
                    type="button"
                    class="button button-secondary"
                    id="bcob-add-contract-rule">

                    <i class="ri-add-line"></i>

                    <?php esc_html_e('Add Condition', 'best-connect-onboarding'); ?>

                </button>

            </div>

        </div>

        <!-- ==========================================
		     General Settings
		========================================== -->

        <div class="bcob-settings-card">

            <div class="bcob-settings-card-header">
                <h3><?php esc_html_e('General Settings', 'best-connect-onboarding'); ?></h3>
                <p><?php esc_html_e('Configure general behaviour for this form.', 'best-connect-onboarding'); ?></p>
            </div>

            <div class="bcob-settings-card-body">

                <div class="bcob-setting-row">

                    <div class="bcob-setting-label">
                        <label for="bcob_enable_tracking">
                            <?php esc_html_e('Enable Analytics Tracking', 'best-connect-onboarding'); ?>
                        </label>

                        <span>
                            <?php esc_html_e('Track form progress and completion analytics.', 'best-connect-onboarding'); ?>
                        </span>
                    </div>

                    <div class="bcob-setting-control">

                        <label class="bcob-switch">

                            <input
                                type="checkbox"
                                id="bcob_enable_tracking"
                                class="bcob-setting"
                                data-setting="enable_tracking">

                            <span class="bcob-slider"></span>

                        </label>

                    </div>

                </div>

                <div class="bcob-setting-row" style="margin-top: 18px;">

                    <div class="bcob-setting-label">

                        <label for="bcob_form_type">
                            <?php esc_html_e('Form Type', 'best-connect-onboarding'); ?>
                        </label>

                        <span>
                            <?php esc_html_e('Select the customer type supported by this form.', 'best-connect-onboarding'); ?>
                        </span>

                    </div>

                    <div class="bcob-setting-control">

                        <select
                            id="bcob_form_type"
                            class="bcob-setting"
                            data-setting="form_type">

                            <option value="b2c">
                                <?php esc_html_e('B2C', 'best-connect-onboarding'); ?>
                            </option>

                            <option value="b2b">
                                <?php esc_html_e('B2B', 'best-connect-onboarding'); ?>
                            </option>

                        </select>

                    </div>

                </div>

                <div class="bcob-setting-row" style="margin-top: 18px;">

                    <div class="bcob-setting-label">

                        <label for="bcob_withdrawal_form_url">
                            <?php esc_html_e(
                                'Withdrawal Form URL',
                                'best-connect-onboarding'
                            ); ?>
                        </label>

                        <span>
                            <?php esc_html_e(
                                'URL opened when the customer clicks the withdrawal form button.',
                                'best-connect-onboarding'
                            ); ?>
                        </span>

                    </div>

                    <div class="bcob-setting-control">

                        <input
                            type="url"
                            id="bcob_withdrawal_form_url"
                            class="regular-text bcob-setting"
                            data-setting="withdrawal_form_url"
                            placeholder="https://example.com/withdrawal/">

                    </div>

                </div>

            </div>

        </div>

        <div class="bcob-settings-card">

            <div class="bcob-settings-card-header">

                <h3>
                    <?php esc_html_e(
                        'Company & Sender Identity',
                        'best-connect-onboarding'
                    ); ?>
                </h3>

                <p>
                    <?php esc_html_e(
                        'Configure the company and email sender identity for this form.',
                        'best-connect-onboarding'
                    ); ?>
                </p>

            </div>

            <div class="bcob-settings-card-body">

                <div class="bcob-form-group">

                    <label for="bcob_company_name">
                        <?php esc_html_e(
                            'Company Name',
                            'best-connect-onboarding'
                        ); ?>
                    </label>

                    <input
                        type="text"
                        id="bcob_company_name"
                        class="regular-text bcob-setting"
                        data-setting="company_name"
                        placeholder="<?php echo esc_attr(
                                            get_bloginfo('name')
                                        ); ?>">

                </div>

                <div class="bcob-form-group">

                    <label for="bcob_sender_name">
                        <?php esc_html_e(
                            'Sender Name',
                            'best-connect-onboarding'
                        ); ?>
                    </label>

                    <input
                        type="text"
                        id="bcob_sender_name"
                        class="regular-text bcob-setting"
                        data-setting="sender_name"
                        placeholder="<?php echo esc_attr(
                                            get_bloginfo('name')
                                        ); ?>">

                </div>

                <div class="bcob-form-group">

                    <label for="bcob_sender_email">
                        <?php esc_html_e(
                            'Sender Email',
                            'best-connect-onboarding'
                        ); ?>
                    </label>

                    <input
                        type="email"
                        id="bcob_sender_email"
                        class="regular-text bcob-setting"
                        data-setting="sender_email"
                        placeholder="<?php echo esc_attr(
                                            get_option('admin_email')
                                        ); ?>">

                </div>

            </div>

        </div>


        <!-- ==========================================
		     Admin Notification
		========================================== -->

        <div class="bcob-settings-card">

            <div class="bcob-settings-card-header">
                <h3><?php esc_html_e('Admin Notification', 'best-connect-onboarding'); ?></h3>
                <p><?php esc_html_e('Email sent to administrators after a successful submission.', 'best-connect-onboarding'); ?></p>
            </div>

            <div class="bcob-settings-card-body">

                <div class="bcob-form-group">

                    <label for="bcob_admin_email">
                        <?php esc_html_e('Recipient Email', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        type="email"
                        id="bcob_admin_email"
                        class="regular-text bcob-setting"
                        data-setting="admin_email"
                        placeholder="<?php esc_attr_e('admin@example.com', 'best-connect-onboarding'); ?>">

                </div>

                <div class="bcob-form-group">

                    <label for="bcob_admin_subject">
                        <?php esc_html_e('Email Subject', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        type="text"
                        id="bcob_admin_subject"
                        class="regular-text bcob-setting"
                        data-setting="admin_subject"
                        placeholder="<?php esc_attr_e('New Form Submission', 'best-connect-onboarding'); ?>">

                </div>

                <div class="bcob-form-group">

                    <label>
                        <?php esc_html_e('Email Content', 'best-connect-onboarding'); ?>
                    </label>

                    <?php
                    wp_editor(
                        '<h2>New Submission</h2>

<p>You have received a new submission.</p>

<p><strong>Name:</strong> {{first_name}}</p>
<p><strong>Email:</strong> {{email}}</p>',
                        'bcob_admin_email_template',
                        [
                            'textarea_name' => 'bcob_admin_email_template',
                            'textarea_rows' => 14,
                            'media_buttons' => false,
                            'teeny'         => false,
                        ]
                    );
                    ?>

                </div>

            </div>

        </div>


        <!-- ==========================================
		     Customer Confirmation
		========================================== -->

        <div class="bcob-settings-card">

            <div class="bcob-settings-card-header">
                <h3><?php esc_html_e('Customer Confirmation', 'best-connect-onboarding'); ?></h3>
                <p><?php esc_html_e('Email automatically sent to the customer after submission.', 'best-connect-onboarding'); ?></p>
            </div>

            <div class="bcob-settings-card-body">

                <div class="bcob-form-group">

                    <label for="bcob_customer_subject">
                        <?php esc_html_e('Email Subject', 'best-connect-onboarding'); ?>
                    </label>

                    <input
                        type="text"
                        id="bcob_customer_subject"
                        class="regular-text bcob-setting"
                        data-setting="customer_subject"
                        placeholder="<?php esc_attr_e('Thank you for your submission', 'best-connect-onboarding'); ?>">

                </div>

                <div class="bcob-form-group">

                    <label>
                        <?php esc_html_e('Email Content', 'best-connect-onboarding'); ?>
                    </label>

                    <?php
                    wp_editor(
                        '<h2>Hello {{first_name}},</h2>

<p>Thank you for submitting your application.</p>

<p>We have successfully received your information and our team will review it shortly.</p>

<p>Kind regards,<br>{{site_name}}</p>',
                        'bcob_customer_email_template',
                        [
                            'textarea_name' => 'bcob_customer_email_template',
                            'textarea_rows' => 16,
                            'media_buttons' => false,
                            'teeny'         => false,
                        ]
                    );
                    ?>

                </div>

            </div>

        </div>

    </div>


    <!-- ==========================================
	     Merge Tags Sidebar
	========================================== -->

    <aside class="bcob-settings-sidebar">

        <div class="bcob-settings-card bcob-sticky">

            <div class="bcob-settings-card-header">

                <h3><?php esc_html_e('Available Merge Tags', 'best-connect-onboarding'); ?></h3>

                <p><?php esc_html_e('Click a tag to insert it into the active editor.', 'best-connect-onboarding'); ?></p>

            </div>

            <div class="bcob-settings-card-body">

                <div class="bcob-form-group">

                    <input
                        type="search"
                        id="bcob-merge-search"
                        placeholder="<?php esc_attr_e('Search tags…', 'best-connect-onboarding'); ?>">

                </div>

                <div class="bcob-merge-section">

                    <h4><?php esc_html_e('System Tags', 'best-connect-onboarding'); ?></h4>

                    <div class="bcob-merge-tags">

                        <button type="button" class="bcob-merge-tag">{{site_name}}</button>
                        <button type="button" class="bcob-merge-tag">{{site_url}}</button>
                        <button type="button" class="bcob-merge-tag">{{form_name}}</button>
                        <button type="button" class="bcob-merge-tag">{{submission_date}}</button>
                        <button type="button" class="bcob-merge-tag">{{submission_time}}</button>
                        <button type="button" class="bcob-merge-tag">{{validation_url}}</button>

                    </div>

                </div>

                <div class="bcob-merge-section">

                    <h4><?php esc_html_e('Form Fields', 'best-connect-onboarding'); ?></h4>

                    <div
                        id="bcob-merge-tags"
                        class="bcob-merge-tags">

                        <!-- Populated dynamically from form schema -->

                    </div>

                </div>

            </div>

        </div>

    </aside>

</div>

<?php

$bcob_pdf_mapping = new \BestConnectOnboarding\PDFs\Mapping();
$bcob_pdf_mapping->render();

unset($bcob_pdf_mapping);

?>