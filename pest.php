<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Test Paths
    |--------------------------------------------------------------------------
    |
    | Configure which directories Pest should scan for tests. By default, Pest
    | will look for tests in the "tests" directory.
    |
    */
    'paths' => [
        'tests',
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Extensions
    |--------------------------------------------------------------------------
    |
    | Pest extensions can be enabled here.
    |
    */
    'bootstrap' => [
        'tests/Pest.php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Coverage Settings
    |--------------------------------------------------------------------------
    |
    | Configure code coverage reporting.
    |
    */
    'coverage' => [
        'driver' => 'pcov',
        'include' => [
            'src',
        ],
        'exclude' => [
            'src/Exceptions',
            'src/Facades',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Setup/Teardown
    |--------------------------------------------------------------------------
    |
    | Hooks that run before/after the entire test suite.
    |
    */
    'setup' => static function () {
        // Setup code here
    },

    'teardown' => static function () {
        // Teardown code here
    },
];
