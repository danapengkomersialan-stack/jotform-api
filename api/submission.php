<?php

require_once __DIR__ . '/_helpers.php';

handle_options();
require_method('GET', 'POST');

$id = require_param('id');
$client = get_client();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $submission = $client->getSubmission($id);
        json_response(['submission' => $submission]);
    } else {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || !is_array($body)) {
            error_response('Request body must be a JSON object with field_id: value pairs');
        }
        $result = $client->editSubmission($id, $body);
        json_response(['result' => $result]);
    }
} catch (Exception $e) {
    error_response($e->getMessage(), $e->getCode() ?: 500);
}
