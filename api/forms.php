<?php

require_once __DIR__ . '/_helpers.php';

handle_options();
require_method('GET');

$client = get_client();

try {
    $forms = $client->getForms();
    $result = array_map(function ($form) {
        return [
            'id' => $form['id'],
            'title' => $form['title'] ?? '',
            'count' => $form['count'] ?? 0,
            'created_at' => $form['created_at'] ?? '',
            'status' => $form['status'] ?? '',
        ];
    }, $forms);
    json_response(['forms' => $result]);
} catch (Exception $e) {
    error_response($e->getMessage(), $e->getCode() ?: 500);
}
