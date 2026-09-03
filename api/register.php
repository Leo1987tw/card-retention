<?php

// 💡 根據 VS Code 目錄結構，register.php 與 db.php 都在 api 資料夾內，使用 ./db.php
include_once "./db.php";

// 檢查是否為 POST 請求，且必要欄位皆有傳遞
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password']) && isset($_POST['password2'])) {

    // 移除前後空白字元
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $password2 = trim($_POST['password2']);

    // 1. 後端後防線：檢查欄位是否為空
    if ($username === '' || $password === '' || $password2 === '') {
        echo "<script>alert('所有欄位皆為必填！'); history.back();</script>";
        exit();
    }

    // 2. 後端後防線：檢查兩次密碼是否一致
    if ($password !== $password2) {
        echo "<script>alert('兩次輸入的密碼不一致，請重新確認！'); history.back();</script>";
        exit();
    }

    // 3. 安全性檢查：密碼長度驗證
    if (strlen($password) < 6) {
        echo "<script>alert('為了您的帳號安全，密碼長度至少需要 6 個字元！'); history.back();</script>";
        exit();
    }

    // 4. 利用您的 $Learner 物件檢查帳號是否重複
    $exists = $Learner->find(['username' => $username]);

    if ($exists) {
        echo "<script>alert('此帳號已被註冊，請換一個帳號。'); history.back();</script>";
        exit();
    } else {
        // 5. 使用安全雜湊演算法加密密碼
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 🌟 智慧補強：幫全新註冊的使用者，直接初始化一份乾淨的每日學習進度 JSON 大底座！
        $today_date = date('Y-m-d');
        $init_progress = [
            'date'      => $today_date,
            'login_at'  => date('Y-m-d H:i:s'),
            'sets' => [
                'words' => ['total' => 0, 'wrong' => 0, 'new_word_count' => 0, 'pool_size' => 0, 'under_lv5_count' => 0, 'is_finished' => false],
                'html'  => ['total' => 0, 'wrong' => 0, 'new_word_count' => 0, 'pool_size' => 0, 'under_lv5_count' => 0, 'is_finished' => false],
                'css'   => ['total' => 0, 'wrong' => 0, 'new_word_count' => 0, 'pool_size' => 0, 'under_lv5_count' => 0, 'is_finished' => false]
            ]
        ];
        $init_progress_json = json_encode($init_progress, JSON_UNESCAPED_UNICODE);

        // 準備寫入資料庫的陣列（包含加密後的密碼與初始進度包）
        $data = [
            'username'       => $username,
            'password'       => $hashed_password,
            'daily_progress' => $init_progress_json // 🌟 讓新帳號註冊一落地就自帶完美結構
        ];

        try {
            // 6. 執行儲存
            $Learner->save($data);

            // 7. 提示成功並正确往上跳一層 (../) 導回根目錄 index.php 的登入頁面
            echo "<script>";
            echo "alert('註冊成功！請使用新帳號登入。');";
            echo "location.href='../index.php?do=login';";
            echo "</script>";
            exit();
        } catch (Throwable $e) {
            // 捕捉所有潛在錯誤（包含 PDOException 與一般 Exception），避免無回應
            echo "<script>alert('系統寫入錯誤，請稍後再試。'); history.back();</script>";
            exit();
        }
    }
} else {
    // 💡 修正跳轉：非正常 POST 訪問時，正確往上跳一層導回登入頁
    header("Location: ../index.php?do=login");
    exit();
}