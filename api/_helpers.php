<?php

error_reporting(E_ALL & ~E_DEPRECATED);
ob_start();

require_once __DIR__ . '/_JotForm.php';

function cors_headers(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

function handle_options(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        cors_headers();
        http_response_code(204);
        exit;
    }
}

function json_response(mixed $data, int $status = 200): void {
    cors_headers();
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function error_response(string $message, int $status = 400): void {
    json_response(['error' => $message], $status);
}

function require_method(string ...$methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods)) {
        error_response('Method not allowed', 405);
    }
}

function get_client(): JotForm {
    $apiKey = getenv('JOTFORM_API_KEY');
    if (!$apiKey) {
        error_response('JOTFORM_API_KEY environment variable is not set', 500);
    }
    try {
        return new JotForm($apiKey);
    } catch (Exception $e) {
        error_response('Failed to initialize JotForm client: ' . $e->getMessage(), 500);
    }
}

function require_param(string $name): string {
    $value = $_GET[$name] ?? '';
    if ($value === '') {
        error_response("Missing required parameter: {$name}");
    }
    return $value;
}
