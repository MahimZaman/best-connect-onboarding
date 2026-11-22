<?php

defined('ABSPATH') || exit;

if (!function_exists('bcob_price')) {
    /**
     * Format a price.
     *
     * @param float|string $price
     * @param array        $args
     * @return string
     */
    function bcob_price($price, array $args = []): string
    {
        $settings = get_option('bcob_settings', []);

        $symbols = bcob_currency_symbols();

        $currency = $settings['currency'] ?? 'USD';

        $defaults = [
            'currency'             => $symbols[$currency] ?? $currency,
            'currency_position'    => $settings['currency_position'] ?? 'left',
            'decimals'             => (int) ($settings['number_of_decimals'] ?? 2),
            'decimal_separator'    => $settings['decimal_separator'] ?? '.',
            'thousand_separator'   => $settings['thousand_separator'] ?? ',',
            'trim_zeros'           => false,
            'html'                 => true,
        ];

        $args = wp_parse_args($args, $defaults);

        $formatted = number_format(
            (float) $price,
            $args['decimals'],
            $args['decimal_separator'],
            $args['thousand_separator']
        );

        if ($args['trim_zeros']) {
            $formatted = rtrim(rtrim($formatted, '0'), $args['decimal_separator']);
        }

        switch ($args['currency_position']) {

            case 'right':
                $price_string = $formatted . $args['currency'];
                break;

            case 'left_space':
                $price_string = $args['currency'] . ' ' . $formatted;
                break;

            case 'right_space':
                $price_string = $formatted . ' ' . $args['currency'];
                break;

            default:
                $price_string = $args['currency'] . $formatted;
                break;
        }

        if (!$args['html']) {
            return $price_string;
        }

        return sprintf(
            '<span class="bcob-price">%s</span>',
            esc_html($price_string)
        );
    }
}

if (!function_exists('bcob_currency_symbols')) {
    function bcob_currency_symbols()
    {
        return [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AUD' => '$',
            'BDT' => '৳',
            'CAD' => '$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'DKK' => 'kr.',
            'HKD' => '$',
            'INR' => '₹',
            'JPY' => '¥',
            'KRW' => '₩',
            'MYR' => 'RM',
            'NOK' => 'kr',
            'NZD' => '$',
            'PKR' => '₨',
            'RUB' => '₽',
            'SAR' => 'ر.س',
            'SEK' => 'kr',
            'SGD' => '$',
            'THB' => '฿',
            'TRY' => '₺',
            'AED' => 'د.إ',
            'VND' => '₫',
            'ZAR' => 'R',
        ];
    }
}
