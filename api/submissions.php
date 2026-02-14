<?php

require_once __DIR__ . '/_helpers.php';

handle_options();
require_method('GET');

$formId = require_param('form_id');
$offset = (int) ($_GET['offset'] ?? 0);
$limit = (int) ($_GET['limit'] ?? 20);

$client = get_client();

try {
    $submissions = $client->getFormSubmissions($formId, $offset, $limit);
    json_response([
        'form_id' => $formId,
        'offset' => $offset,
        'limit' => $limit,
        'count' => count($submissions),
        'submissions' => $submissions,
    ]);
} catch (Exception $e) {
    error_response($e->getMessage(), $e->getCode() ?: 500);
}
