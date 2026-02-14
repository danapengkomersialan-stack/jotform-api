<?php

require_once __DIR__ . '/_helpers.php';

handle_options();
require_method('GET');

$results = [
    'status' => 'ok',
    'php_version' => PHP_VERSION,
    'timestamp' => date('c'),
    'env' => [
        'JOTFORM_API_KEY' => getenv('JOTFORM_API_KEY') ? 'set (' . strlen(getenv('JOTFORM_API_KEY')) . ' chars)' : 'NOT SET',
    ],
    'extensions' => [
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json'),
    ],
];

// If API key is set, test the connection
if (getenv('JOTFORM_API_KEY')) {
    try {
        $client = get_client();
        $user = $client->getUser();
        $results['jotform'] = [
            'connected' => true,
            'username' => $user['username'] ?? '',
            'email' => $user['email'] ?? '',
            'account_type' => $user['account_type'] ?? '',
        ];
    } catch (Exception $e) {
        $results['jotform'] = [
            'connected' => false,
            'error' => $e->getMessage(),
        ];
    }
}

json_response($results);
