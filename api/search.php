<?php

require_once __DIR__ . '/_helpers.php';

handle_options();
require_method('GET');

$formId = require_param('form_id');
$applicationId = require_param('application_id');

$client = get_client();

try {
    // Fetch all submissions (paginate through all results)
    $allSubmissions = [];
    $offset = 0;
    $limit = 100;

    do {
        $batch = $client->getFormSubmissions($formId, $offset, $limit);
        $allSubmissions = array_merge($allSubmissions, $batch);
        $offset += $limit;
    } while (count($batch) === $limit);

    // Filter by Application ID field value
    $matches = array_filter($allSubmissions, function ($submission) use ($applicationId) {
        if (!isset($submission['answers'])) {
            return false;
        }
        foreach ($submission['answers'] as $answer) {
            $answerValue = $answer['answer'] ?? '';
            if (is_string($answerValue) && strcasecmp($answerValue, $applicationId) === 0) {
                return true;
            }
            if (is_array($answerValue)) {
                foreach ($answerValue as $v) {
                    if (is_string($v) && strcasecmp($v, $applicationId) === 0) {
                        return true;
                    }
                }
            }
        }
        return false;
    });

    json_response([
        'form_id' => $formId,
        'application_id' => $applicationId,
        'total_searched' => count($allSubmissions),
        'count' => count($matches),
        'submissions' => array_values($matches),
    ]);
} catch (Exception $e) {
    error_response($e->getMessage(), $e->getCode() ?: 500);
}
