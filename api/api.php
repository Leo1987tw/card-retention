<?php

include_once "./db.php"; 
header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'error', 'message' => '未知錯誤'];

/* =========================================================================
   [前端參數安全擷取與動態排堆路由] 
   ========================================================================= */
$do        = isset($_GET['do'])        ? trim($_GET['do']) : 'card_board'; 
$id        = isset($_GET['id'])        ? intval($_GET['id']) : 0;
$isCorrect = isset($_GET['isCorrect']) ? $_GET['isCorrect'] : null;
$mode      = isset($_GET['mode'])      ? trim($_GET['mode']) : ''; 

// 智慧別名路由表：對應不同的 ?do= 指派正確的底層主資料表名稱、快取鍵名與專屬 DB 物件
$route_map = [
    'card_board' => ['table' => 'words',      'key' => 'words', 'db' => $Word],
    'html'       => ['table' => 'html_terms', 'key' => 'html',  'db' => $HTML],
    'css'        => ['table' => 'css_terms',  'key' => 'css',   'db' => $CSS]
];

if (array_key_exists($do, $route_map)) {
    $table  = $route_map[$do]['table'];
    $setKey = $route_map[$do]['key'];
    $currentDB = $route_map[$do]['db']; // 當前作用中的排堆 DB 物件
} else {
    // 安全防線
    $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $do);
    $setKey = $do;
    $currentDB = new DB($table);
}

// 判斷使用者是否登入（對接您的 $_SESSION['username']）
$isLoggedIn = isset($_SESSION['username']);
$learner_id = 0;

if ($isLoggedIn) {
    // 直接調用 $Learner 物件的 find 方法查詢使用者流水號 ID
    $user = $Learner->find(['username' => $_SESSION['username']]);
    $learner_id = $user ? intval($user['id']) : 0;
}

/* =========================================================================
   第一部分：儲存作答進度 (活用 $LearningRecord->find 與 save 方法)
   ========================================================================= */
if ($isLoggedIn && $learner_id > 0 && $id > 0 && $isCorrect !== null && $mode !== 'pool_rand') {
    
    // 活用 DB 類別查詢現有紀錄
    $record = $LearningRecord->find(['learner_id' => $learner_id, 'word_id' => $id, 'type' => $do]);

    if ($isCorrect === 'true') {
        // 【答對了】
        if ($record) {
            $new_level = min(5, intval($record['learning_level']) + 1); // 記憶等級最高限制到 5
            $new_preview_count = intval($record['preview_count']) + 1;
        } else {
            $new_level = 2; // 全新卡片第一次答對直升 LV 2
            $new_preview_count = 1;
        }
    } else {
        // 【答錯了】打回原形 LV 1
        $new_level = 1;
        $new_preview_count = 1; 
    }

    // 依據記憶等級推算下一次複習日期
    switch ($new_level) {
        case 1:  $days = '+1 day';   break;
        case 2:  $days = '+3 days';  break;
        case 3:  $days = '+7 days';  break;
        case 4:  $days = '+14 days'; break;
        case 5:  $days = '+30 days'; break;
        default: $days = '+1 day';   break;
    }
    $next_review_date = date('Y-m-d', strtotime($days));

    // 封裝準備寫入/更新的關聯陣列
    $save_data = [
        'learner_id'     => $learner_id,
        'word_id'        => $id,
        'type'           => $do,
        'learning_level' => $new_level,
        'preview_count'  => $new_preview_count,
        'last_review_at' => date('Y-m-d H:i:s'),
        'next_review_date'=> $next_review_date
    ];

    if ($record) {
        // 帶有 id 的陣列傳給 save() ➔ 自動執行物件內部的 UPDATE 語法
        $save_data['id'] = $record['id'];
    } else {
        // 沒有 id 的陣列傳給 save() ➔ 自動執行物件內部的 INSERT 語法
        $save_data['is_new_word'] = 1;
        $save_data['created_at']  = date('Y-m-d H:i:s');
    }

    // 執行儲存
    $LearningRecord->save($save_data);

    // 【同步更新 Session 快取計數器】
    if (isset($_SESSION['daily_progress']['sets'][$setKey])) {
        if ($record) {
            $_SESSION['daily_progress']['sets'][$setKey]['total'] += 1;
            if ($isCorrect === 'false') {
                $_SESSION['daily_progress']['sets'][$setKey]['wrong'] += 1;
            }
        } else {
            $_SESSION['daily_progress']['sets'][$setKey]['new_word_count'] += 1;
            $_SESSION['daily_progress']['sets'][$setKey]['pool_size'] += 1;
        }
    }
}

/* =========================================================================
   第二部分：動態完工審查與下一張卡片抽取路由 (活用 $currentDB->q)
   ========================================================================= */
$word_data = null;
$isFinishedSignal = false;

if (!$isLoggedIn) {
    /* ----- 【A. 未登入 / 訪客模式】 ----- */
    // 活用 q() 方法，動態多表 JOIN categories 表
    $res = $currentDB->q("SELECT t.*, c.name AS category_name FROM `$table` t LEFT JOIN `categories` c ON t.category_id = c.id ORDER BY RAND() LIMIT 1");
    $word_data = !empty($res) ? $res[0] : null;
} else {
    /* ----- 【B. 已登入 / 會員模式】 ----- */
    $current_date = date('Y-m-d');
    
    // 安全載入 Session 快取計數器
    $progress   = $_SESSION['daily_progress']['sets'][$setKey] ?? ['total'=>0, 'wrong'=>0, 'new_word_count'=>0, 'pool_size'=>0, 'is_finished'=>false];
    $total      = intval($progress['total']);
    $wrong      = intval($progress['wrong']);
    $new_count  = intval($progress['new_word_count']);
    $pool_size  = intval($progress['pool_size']);

    // 1. 動態計算今日舊字複習正確率並配對限制額度
    $accuracy = ($total === 0) ? 100 : (($total - $wrong) / $total) * 100;
    if ($accuracy >= 85)     $new_limit = 20;
    elseif ($accuracy >= 70) $new_limit = 15;
    elseif ($accuracy >= 60) $new_limit = 10;
    elseif ($accuracy >= 50) $new_limit = 5;
    else                     $new_limit = 0; 

    // 2. 活用 count() 方法快速檢查今天是否有尚未複習的「到期舊字」
    $due_where = "WHERE `learner_id` = '$learner_id' AND `type` = '$do' AND `next_review_date` <= '$current_date'";
    $has_due_words = ($LearningRecord->count($due_where) > 0);

    // 3. 判定今日的正規任務是否已經完工
    $isTodayTaskDone = (!$has_due_words && ($new_count >= $new_limit || $pool_size >= 200));

    // 4. 當判定完工時，同步更新 Session 印記與資料庫獨立時間戳防線 (task_finished)
    if ($isTodayTaskDone && $_SESSION['daily_progress']['sets'][$setKey]['is_finished'] === false) {
        $_SESSION['daily_progress']['sets'][$setKey]['is_finished'] = true;
        
        // 直接使用 $Learner 物件進行欄位日期覆蓋更新
        $Learner->save(['id' => $learner_id, 'task_finished' => $current_date]);
    }

    // 將最新進度字串同步回寫至使用者主表
    $updated_json = json_encode($_SESSION['daily_progress'], JSON_UNESCAPED_UNICODE);
    $Learner->save(['id' => $learner_id, 'daily_progress' => $updated_json]);

    // 5. 判斷是否拋出完工訊號與處理進階卡庫分流 
    if ($_SESSION['daily_progress']['sets'][$setKey]['is_finished'] === true || $mode !== '') {
        $isFinishedSignal = true;

        if ($mode === 'pool_hard') {
            // 【分流軌道 1】特訓鈕 ➔ 抽取 200 字庫內最低 LV 的生字 (JOIN categories)
            $res = $currentDB->q("SELECT lr.*, t.*, c.name AS category_name FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id LEFT JOIN `categories` c ON t.category_id = c.id WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' ORDER BY lr.learning_level ASC, RAND() LIMIT 1");
        } else {
            // 【分流軌道 2】盲刷鈕 或 剛完工第一瞬間 ➔ 200 字庫內完全隨機盲刷 (JOIN categories)
            $res = $currentDB->q("SELECT lr.*, t.*, c.name AS category_name FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id LEFT JOIN `categories` c ON t.category_id = c.id WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' ORDER BY RAND() LIMIT 1");
        }
        $word_data = !empty($res) ? $res[0] : null;
    } else {
        // 【正常學習進行中】
        if ($has_due_words) {
            // [先舊後新] 強制優先抽已到期的舊字 (JOIN categories)
            $res = $currentDB->q("SELECT lr.*, t.*, c.name AS category_name FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id LEFT JOIN `categories` c ON t.category_id = c.id WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' AND lr.next_review_date <= '$current_date' ORDER BY RAND() LIMIT 1");
            $word_data = !empty($res) ? $res[0] : null;
        } else {
            // 舊字已清空，發放全新單字 (受限額與總容量阻攔)
            $res = $currentDB->q("SELECT t.*, c.name AS category_name FROM `$table` t LEFT JOIN `categories` c ON t.category_id = c.id WHERE t.id NOT IN (SELECT word_id FROM `learning_records` WHERE learner_id = '$learner_id' AND type = '$do') ORDER BY t.id ASC LIMIT 1");
            $word_data = !empty($res) ? $res[0] : null;
            
            if ($word_data) {
                $word_data['learning_level'] = 1;
                $word_data['preview_count'] = 1; // 亮起前端 NEW 標籤
            }
        }
    }
}

if (!$word_data) {
    echo json_encode(["status" => "empty", "message" => "當前排堆已無任何可用卡片"], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================================
   第三部分：[高度擴充化] 分類名稱與音標數據抽象化映射
   ========================================================================= */
$final_category1 = $word_data['category_name'] ?? "";
$final_category2 = $word_data['phonetic']      ?? ($word_data['category2'] ?? "");

echo json_encode([
    "status"        => "success",
    "id"            => $word_data['word_id'] ?? $word_data['id'], // 確保能正確拿到原始卡片流水號
    "word"          => $word_data['word']        ?? "",
    "category1"     => $final_category1, 
    "category2"     => $final_category2, 
    "definition"    => $word_data['definition']  ?? "",
    "translation"   => $word_data['translation'] ?? "",
    // 如果是隨機盲刷模式 (pool_rand)，回傳 null 讓前端指標欄位呈現 '--' 隱藏不計分外觀
    "level"         => ($mode === 'pool_rand') ? null : ($word_data['learning_level'] ?? null),
    "preview_count" => ($mode === 'pool_rand') ? null : ($word_data['preview_count'] ?? null),
    "isFinished"    => $isFinishedSignal
], JSON_UNESCAPED_UNICODE);

exit;

?>