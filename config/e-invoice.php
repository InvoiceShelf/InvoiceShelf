<?php

return [
    /*
    |--------------------------------------------------------------------------
    | E-Invoice Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for European e-invoice generation and compliance
    |
    */

    'default_format' => env('E_INVOICE_DEFAULT_FORMAT', 'UBL'),

    'supported_formats' => [
        'UBL' => [
            'name' => 'Universal Business Language',
            'description' => 'XML format compliant with EN16931',
            'xml_only' => true,
        ],
        'CII' => [
            'name' => 'Cross Industry Invoice',
            'description' => 'XML format compliant with EN16931',
            'xml_only' => true,
        ],
        'Factur-X' => [
            'name' => 'Factur-X',
            'description' => 'PDF with embedded XML (French standard)',
            'xml_only' => false,
        ],
        'ZUGFeRD' => [
            'name' => 'ZUGFeRD',
            'description' => 'PDF with embedded XML (German standard)',
            'xml_only' => false,
        ],
    ],

    'storage' => [
        'disk' => env('E_INVOICE_STORAGE_DISK', 'local'),
        'path' => 'e-invoices',
    ],

    'validation' => [
        'required_fields' => [
            'invoice_number',
            'company_name',
            'customer_name',
            'items',
        ],
        'facturx_required_fields' => [
            'company_vat_id',
            'company_tax_id',
        ],
    ],

    'generation' => [
        'async_by_default' => env('E_INVOICE_ASYNC_DEFAULT', true),
        'queue' => env('E_INVOICE_QUEUE', 'default'),
        'timeout' => env('E_INVOICE_TIMEOUT', 300), // 5 minutes
    ],

    'compliance' => [
        'standard' => 'EN16931',
        'directive' => '2014/55/EU',
        'version' => '1.0',
    ],
];
