<?php

include_once "./db.php";

// 檢查是否收到登入表單的帳密
if (isset($_POST['username']) && isset($_POST['password'])) {

    // 移除前後空白字元
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // 1. 修正查詢語法：比照 register.php 使用陣列型態傳入欄位與條件
    $learner = $Learner->find(['username' => $username]);

    // 2. 驗證帳號是否存在，並使用 password_verify 比對資料庫內雜湊後的密碼
    if ($learner && password_verify($password, $learner['password'])) {

        // 3. 登入成功，將帳號（或 ID）存入 Session
        $_SESSION['login'] = $learner['username'];

        // 4. 成功後導向至學習卡片板面（card_board）
        header("Location: ../index.php?do=card_board");
        exit();
    } else {
        // 5. 登入失敗：提示帳號或密碼錯誤，並導回登入頁
        echo "<script>";
        echo "alert('帳號或密碼錯誤，請重新輸入。');";
        echo "location.href='../index.php?do=login';";
        echo "</script>";
        exit();
    }
} else {
    // 6. 若非透過表單正常提交，直接攔截並導回登入頁
    header("Location: ../index.php?do=login");
    exit();
}

?>


{"last_date":"2026-08-23","sets":{"words":{"total":3,"wrong":1,"new_word_count":3,"pool_size":3},"html":{"total":0,"wrong":0,"new_word_count":0,"pool_size":0},"css":{"total":0,"wrong":0,"new_word_count":0,"pool_size":0}}}