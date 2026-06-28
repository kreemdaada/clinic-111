<?php

/**
 * Supported ISO 4217 currencies (ADR-034).
 *
 * Single source of truth for codes, symbols, precision, and display labels.
 * Controllers and views must read from CurrencyCatalog — never hardcode here.
 */
return [

    'supported' => [
        'AED' => [
            'name' => 'UAE Dirham',
            'symbol' => 'AED ',
            'precision' => 2,
            'format' => 'code_amount',
        ],
        'EUR' => [
            'name' => 'Euro',
            'symbol' => '€',
            'precision' => 2,
            'format' => 'symbol_amount',
        ],
        'USD' => [
            'name' => 'US Dollar',
            'symbol' => '$',
            'precision' => 2,
            'format' => 'symbol_amount',
        ],
        'SAR' => [
            'name' => 'Saudi Riyal',
            'symbol' => 'SAR ',
            'precision' => 2,
            'format' => 'code_amount',
        ],
        'GBP' => [
            'name' => 'British Pound',
            'symbol' => '£',
            'precision' => 2,
            'format' => 'symbol_amount',
        ],
    ],

];
