<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 💡 安全增強：在使用者登入前，強制清空現有的舊快取，避免與前一個使用者的紀錄產生殘留干擾
$_SESSION = [];

// 確保時間戳精確契合台灣時間
date_default_timezone_set("Asia/Taipei");

// 💡 根據您的結構，login.php 與 db.php 都在 api 資料夾內，所以使用 ./db.php
include_once "./db.php";

// 檢查是否收到登入表單的帳密
if (isset($_POST['username']) && isset($_POST['password'])) {

    // 移除前後空白字元
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // 使用安全陣列傳參查詢使用者
    $learner = $Learner->find(['username' => $username]);

    // 驗證帳號是否存在，並比對雜湊密碼
    if ($learner && password_verify($password, $learner['password'])) {

        // 核心對齊：存入全域登入識別 Session
        $_SESSION['username'] = $learner['username'];

        /* =========================================================================
           [關鍵補強] 提取、修復並注入目前的登入日期與精確時間戳 (daily_progress)
           ========================================================================= */
        $today_date = date('Y-m-d');
        $now_time   = date('Y-m-d H:i:s'); // 獲取精確的當前登入時間
        
        // 讀取資料庫欄位 (對齊您截圖中的真實欄位名 daily_progress)
        $db_progress_json = $learner['daily_progress'] ?? '';

        // 將進度 JSON 字串轉成 PHP 陣列
        $progress_data = json_decode($db_progress_json, true);

        // 🌟 防空與跨日重置機制：如果資料庫是 NULL 或新一天，立刻初始化乾淨的結構
        if (empty($progress_data) || !isset($progress_data['last_date']) || $progress_data['last_date'] !== $today_date) {
            $progress_data = [
                'last_date' => $today_date,
                'sets' => [
                    'words' => ['total' => 0, 'wrong' => 0, 'new_word_count' => 0, 'pool_size' => 0, 'is_finished' => false],
                    'html'  => ['total' => 0, 'wrong' => 0, 'new_word_count' => 0, 'pool_size' => 0, 'is_finished' => false],
                    'css'   => ['total' => 0, 'wrong' => 0, 'new_word_count' => 0, 'pool_size' => 0, 'is_finished' => false]
                ]
            ];
        }

        // 🎯 核心實作：不論是舊進度還是新初始化結構，都在外層強行灌入本次的學習登入時間
        $progress_data['login_at'] = $now_time;

        // 將最新帶有時間戳的進度包，重新打包並回寫更新到使用者的資料庫主表中
        $updated_json = json_encode($progress_data, JSON_UNESCAPED_UNICODE);
        $Learner->save([
            'id'             => $learner['id'],
            'daily_progress' => $updated_json // 回寫更新使用者的 daily_progress 欄位
        ]);

        // 同步灌入全域 Session 快取，讓 api.php 在抽卡時能夠抓到最新的數據
        $_SESSION['daily_progress'] = $progress_data;

        // 💡 修正跳轉：因為檔案在 api/ 資料夾內，必須往上跳一層 (../) 才能找到根目錄的 index.php
        header("Location: ../index.php?do=card_board");
        exit();

    } else {
        // 💡 修正跳轉：失敗時正確往上跳一層，導回根目錄 index.php 的登入頁面
        echo "<script>";
        echo "alert('帳號或密碼錯誤，請重新輸入。');";
        echo "location.href='../index.php?do=login';";
        echo "</script>";
        exit();
    }
} else {
    // 💡 修正跳轉：非正常提交時安全攔截並往上跳一層
    header("Location: ../index.php?do=login");
    exit();
}