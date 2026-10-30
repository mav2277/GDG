<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require_admin();

header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'This action is not allowed.']);
    exit;
}

try {
    $accounts = (new User(database_connection()))->allAccounts();
    $accounts = array_map(static function (array $account): array {
        return [
            'id' => (int) $account['id'],
            'displayName' => (string) $account['display_name'],
            'username' => (string) $account['username'],
            'email' => $account['email'],
            'role' => (string) $account['role'],
            'status' => (string) $account['account_status'],
            'createdAt' => (string) $account['created_at'],
        ];
    }, $accounts);

    echo json_encode(['success' => true, 'accounts' => $accounts], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('Admin account list could not be loaded: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Accounts could not be loaded. Refresh and try again.']);
}
