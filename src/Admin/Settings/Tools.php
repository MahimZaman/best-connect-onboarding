<?php

namespace BestConnectOnboarding\Admin\Settings;

defined('ABSPATH') || exit;

class Tools
{
    public function __construct()
    {
        add_action(
            'bcob_settings_tab_tools',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $settings = get_option('bcob_settings', []);

?>
        <div class="bcob-settings-section">
            <div class="bcob-settings-divider">

                <h3>

                    <i class="ri-bank-card-line"></i>

                    <?php esc_html_e('DateV Rest API', 'best-connect-onboarding'); ?>

                </h3>

                <p>

                    <?php esc_html_e('Rest API setting to connect to REST API and send form submission data and then sync to DateV', 'best-connect-onboarding'); ?>

                </p>

            </div>
            <div class="bcob-grid bcob-grid-2">
                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('API Key', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        type="text"
                        name="settings[bcob_datev_api_key]"
                        value="<?php echo esc_attr($settings['bcob_datev_api_key'] ?? ''); ?>">

                </div>
                <div class="bcob-form-group">

                    <label>

                        <?php esc_html_e('Server URL', 'best-connect-onboarding'); ?>

                    </label>

                    <input
                        type="text"
                        name="settings[bcob_datev_url]"
                        value="<?php echo esc_attr($settings['bcob_datev_url'] ?? ''); ?>">

                </div>
            </div>
            <div class="bcob-datev-help">

                <div class="bcob-datev-help__header">
                    <h2>DATEV Middleware Field Mapping</h2>

                    <p>
                        Use the CSS classes below on form fields to tell the system
                        which submitted value should be used in the DATEV middleware payload.
                    </p>
                </div>

                <div class="bcob-datev-help__notice">
                    <strong>How it works</strong>

                    <p>
                        Add one or more DATEV classes to a form field.
                        The submitted value of that field will automatically be placed
                        into the corresponding DATEV payload value.
                    </p>

                    <p>
                        A field can have more than one class if the same value needs to
                        be used in multiple places.
                    </p>
                </div>

                <h3>Customer</h3>

                <div class="bcob-datev-help__table-wrap">
                    <table class="bcob-datev-help__table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Payload value</th>
                                <th>Example</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>
                                    <code>datev-customer-number</code>
                                </td>
                                <td>
                                    <code>customer.customerNumber</code>
                                </td>
                                <td>8170</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-name</code>
                                </td>
                                <td>
                                    <code>customer.name</code>
                                </td>
                                <td>Testkunde GmbH</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-email</code>
                                </td>
                                <td>
                                    <code>customer.email</code>
                                </td>
                                <td>kunde@example.com</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-street</code>
                                </td>
                                <td>
                                    <code>customer.street</code>
                                </td>
                                <td>Musterweg 1</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-zip</code>
                                </td>
                                <td>
                                    <code>customer.zip</code>
                                </td>
                                <td>9020</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-city</code>
                                </td>
                                <td>
                                    <code>customer.city</code>
                                </td>
                                <td>Klagenfurt</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-country-code</code>
                                </td>
                                <td>
                                    <code>customer.countryCode</code>
                                </td>
                                <td>AT</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3>Invoice</h3>

                <div class="bcob-datev-help__table-wrap">
                    <table class="bcob-datev-help__table">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Payload value</th>
                                <th>Example</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>
                                    <code>datev-invoice-date</code>
                                </td>
                                <td>
                                    <code>invoice.invoiceDate</code>
                                </td>
                                <td>2026-07-01</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-period-from</code>
                                </td>
                                <td>
                                    <code>invoice.periodFrom</code>
                                </td>
                                <td>2026-07-01</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-period-to</code>
                                </td>
                                <td>
                                    <code>invoice.periodTo</code>
                                </td>
                                <td>2026-07-31</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-due-date</code>
                                </td>
                                <td>
                                    <code>invoice.dueDate</code>
                                </td>
                                <td>2026-07-15</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-total-net</code>
                                </td>
                                <td>
                                    <code>invoice.totalNetAmount</code>
                                </td>
                                <td>112.19</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-total-tax</code>
                                </td>
                                <td>
                                    <code>invoice.totalTaxAmount</code>
                                </td>
                                <td>22.44</td>
                            </tr>

                            <tr>
                                <td>
                                    <code>datev-text</code>
                                </td>
                                <td>
                                    <code>invoice.text</code>
                                </td>
                                <td>Online Zahlung</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3>Automatically handled values</h3>

                <div class="bcob-datev-help__auto">

                    <div class="bcob-datev-help__auto-item">
                        <strong>Language</strong>
                        <code>DE</code>
                    </div>

                    <div class="bcob-datev-help__auto-item">
                        <strong>Invoice period</strong>
                        <span>
                            Defaults to the current month's first and last day.
                        </span>
                    </div>

                    <div class="bcob-datev-help__auto-item">
                        <strong>Total gross amount</strong>
                        <span>
                            Taken directly from the payment amount.
                        </span>
                    </div>

                    <div class="bcob-datev-help__auto-item">
                        <strong>Currency</strong>
                        <span>
                            Taken from the payment/submission currency.
                        </span>
                    </div>

                    <div class="bcob-datev-help__auto-item">
                        <strong>SEPA direct debit</strong>
                        <code>false</code>
                    </div>

                    <div class="bcob-datev-help__auto-item">
                        <strong>Invoice text</strong>
                        <code>Online Zahlung</code>
                        <span class="bcob-datev-help__muted">
                            Used when no DATEV text field overrides it.
                        </span>
                    </div>

                </div>

                <div class="bcob-datev-help__notice bcob-datev-help__notice--example">
                    <strong>Example</strong>

                    <p>
                        If your form has an Email field, add:
                    </p>

                    <code>datev-email</code>

                    <p>
                        The submitted email will then be sent as:
                    </p>

                    <code>customer.email</code>
                </div>

                <div class="bcob-datev-help__notice">
                    <strong>Important</strong>

                    <p>
                        The class must be added to the actual form field that contains
                        the value you want to send. Hidden or unrelated fields should
                        not be used unless they intentionally contain the DATEV value.
                    </p>

                    <p>
                        If a DATEV class is not assigned to a field, the corresponding
                        value remains at its configured/default value.
                    </p>
                </div>

            </div>
        </div>
<?php
    }
}
