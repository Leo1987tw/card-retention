<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 確保時間戳精確契合台灣時間
date_default_timezone_set("Asia/Taipei");

include_once "./db.php";

/* =========================================================================
   [進度最後防線] 在摧毀 Session 離開前，強制將當前快取回寫資料庫，防止進度遺失
   ========================================================================= */
if (isset($_SESSION['username']) && isset($_SESSION['daily_progress'])) {

    // 1. 重新向資料庫查出目前該使用者的真實流水號 ID
    $user = $Learner->find(['username' => $_SESSION['username']]);

    if ($user) {
        $learner_id = intval($user['id']);

        // 2. 將全域 Session 中的最新計數進度包，在登出瞬間做最後一次 JSON 打包
        $final_progress_json = json_encode($_SESSION['daily_progress'], JSON_UNESCAPED_UNICODE);

        // 3. 準備回寫的長效主表欄位
        $logout_save_data = [
            'id'             => $learner_id,
            'daily_progress' => $final_progress_json // 🌟 確保最新的進度完好無缺存入主表
        ];

        // 4. 智慧安全同步：如果您任何一個排堆今天已經完成了，在登出時再次鞏固獨立時間戳防線
        $current_date = date('Y-m-d');
        foreach ($_SESSION['daily_progress']['sets'] as $set) {
            if (isset($set['is_finished']) && $set['is_finished'] === true) {
                $logout_save_data['task_finished'] = $current_date; // 覆蓋更新 task_finished 日期
                break;
            }
        }

        // 5. 執行安全回寫儲存
        $Learner->save($logout_save_data);
    }
}

/* =========================================================================
   核心清理：徹底摧毀與解綁瀏覽器端的登入 Session 印記
   ========================================================================= */
// 1. 清空所有記憶體中的 Session 變數值
$_SESSION = array();

// 2. 徹底銷毀瀏覽器對應此 Session 的實體 Cookie 密鑰，防止連線被劫持重複利用
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. 關閉並摧毀伺服器端的 Session 本體
session_destroy();

/* =========================================================================
   安全重導向：跳轉返回系統首頁
   ========================================================================= */
// 由於此檔案放在 api/ 資料夾下，使用 ../index.php 優雅跳轉回最外層的主框架首頁
header("Location: ../index.php");
exit();

?>