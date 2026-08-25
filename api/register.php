<?php

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

        // 準備寫入資料庫的陣列（欄位名稱請依據您資料表實際名稱調整，例如：username, password）
        $data = [
            'username' => $username,
            'password' => $hashed_password
        ];

        try {
            // 6. 執行儲存
            $Learner->save($data);

            // 7. 提示成功並跳轉至登入頁面
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
    // 非正常 POST 訪問，直接導回登入頁
    to("../index.php?do=login");
    exit();
}
