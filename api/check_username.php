<?php

header('Content-Type: application/json; charset=utf-8');
include_once __DIR__ . '/db.php';

// 1. 初始化回傳結果（預設為不存在）
$response = ['exists' => false];

// 2. 取得前端傳過來的 username 參數
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

// 3. 如果帳號不為空，則進入資料庫查詢
if ($username !== '') {
    try {
        if ($Learner->find(['username' => $username])) {
            $response['exists'] = true;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        $response['error'] = 'Database error';
    }
}

// 6. 將結果轉成 JSON 格式回傳給前端的 fetch
echo json_encode($response);
exit;
