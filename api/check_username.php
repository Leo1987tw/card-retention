<?php

header('Content-Type: application/json; charset=utf-8');

// 1. 初始化回傳結果（預設為不存在）
$response = ['exists' => false];

// 2. 取得前端傳過來的 username 參數
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

// 3. 如果帳號不為空，則進入資料庫查詢
if ($username !== '') {

    // --- [資料庫連線設定] 請根據您的資料庫欄位修改以下資訊 ---
    $db_host = 'localhost';
    $db_name = '您的資料庫名稱';
    $db_user = '您的資料庫帳號';
    $db_pass = '您的資料庫密碼';

    try {
        // 建立 PDO 連線
        $pdo = new PDO(
            "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        // 4. 使用預備陳述式 (Prepared Statement) 查詢帳號，完全杜絕 SQL 注入攻擊
        // 請將 `users` 與 `username` 替換成您實際的資料表與欄位名稱
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = :username");
        $stmt->execute([':username' => $username]);

        // 取得符合的筆數
        $count = $stmt->fetchColumn();

        // 5. 如果筆數大於 0，代表帳號已被註冊
        if ($count > 0) {
            $response['exists'] = true;
        }
    } catch (PDOException $e) {
        // 如果資料庫連線或查詢失敗，回傳錯誤訊息（開發除錯用）
        $response['error'] = 'Database error: ' . $e->getMessage();
    }
}

// 6. 將結果轉成 JSON 格式回傳給前端的 fetch
echo json_encode($response);
exit;
