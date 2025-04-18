<?php
// api/router.php

// Load configuration and autoloader (configuration files and classes must be in the proper locations)
require_once __DIR__ . "/../includes.php";
require_once __DIR__ . '/method/BaseMethod.php';
// Allow CORS preflight and actual requests

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Access-Token');
    exit;
}

// Для всех остальных ответов
header('Access-Control-Allow-Origin: *');
// Get the method from the GET parameter
$method = isset($_GET['method']) ? $_GET['method'] : '';
if (empty($method)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Method not specified']);
    exit;
}

// Determine the path to the method file (e.g., api/method/receive.php)
$methodFile = __DIR__ . '/method/' . $method . '.php';
if (!file_exists($methodFile)) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => 'Method file not found']);
    exit;
}

// Include the file with the method implementation
require_once $methodFile;

// Convention: the class name is ucfirst(method) + "Method". For example, "receive" becomes "ReceiveMethod"
$methodClass = ucfirst($method) . 'Method';
if (!class_exists($methodClass)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Method class not found']);
    exit;
}

// Create an instance of the method class and call its process method
$apiMethod = new $methodClass();
$apiMethod->process();
