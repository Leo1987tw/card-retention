<?php

include_once "../db.php"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password']) && isset($_POST['password2'])) {
    
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $password2 = trim($_POST['password2']);

    if ($username === '' || $password === '' || $password2 === '') {
        echo "<script>alert('所有欄位皆為必填！'); history.back();</script>";
        exit();
    }

    if ($password !== $password2) {
        echo "<script>alert('兩次輸入的密碼不一致，請重新確認！'); history.back();</script>";
        exit();
    }

    if (strlen($password) < 6) {
        echo "<script>alert('為了您的帳號安全，密碼長度至少需要 6 個字元！'); history.back();</script>";
        exit();
    }

    $exists = $Learner->find(['username' => $username]);

    if ($exists) {
        echo "<script>alert('此帳號已被註冊，請換一個帳號。'); history.back();</script>";
        exit();
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $data = [
            'username' => $username,
            'password' => $hashed_password
        ];

        try {
            $Learner->save($data);
            
            echo "<script>";
            echo "alert('註冊成功！請使用新帳號登入。');";
            echo "location.href='../index.php?do=login';";
            echo "</script>";
            exit();
            
        } catch (PDOException $e) {
            echo "<script>alert('系統寫入錯誤，請稍後再試。'); history.back();</script>";
            exit();
        }
    }

} else {
    to("../index.php?do=login");
    exit();
}
?>
