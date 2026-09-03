<?php
// 開啟 Session，確保登入狀態與每日進度快取正常運作
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once "./db.php"; 
header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'error', 'message' => '未知錯誤'];

/* =========================================================================
   [前端參數安全擷取與動態排堆路由] 
   ========================================================================= */
$do     = isset($_GET['do'])     ? trim($_GET['do']) : 'main'; // 預設對接單一入口主路由
$id     = (isset($_GET['id']) && $_GET['id'] !== 'null') ? intval($_GET['id']) : 0;
$action = (isset($_GET['action']) && $_GET['action'] !== 'null') ? trim($_GET['action']) : null; // 💡 裡外一體化：全面正名改用 action
$mode   = isset($_GET['mode'])   ? trim($_GET['mode']) : ''; 

// 智慧別名路由表：對接多個不同的排堆（words、html_terms、css_terms）
$route_map = [
    'main'       => ['table' => 'words',      'key' => 'words', 'db' => $Word],
    'card_board' => ['table' => 'words',      'key' => 'words', 'db' => $Word],
    'html'       => ['table' => 'html_terms', 'key' => 'html',  'db' => $HTML],
    'css'        => ['table' => 'css_terms',  'key' => 'css',   'db' => $CSS]
];

if (array_key_exists($do, $route_map)) {
    $table  = $route_map[$do]['table'];
    $setKey = $route_map[$do]['key'];
    $currentDB = $route_map[$do]['db']; 
} else {
    // 安全防線
    $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $do);
    $setKey = $do;
    $currentDB = new DB($table);
}

// 判斷使用者是否登入
$isLoggedIn = isset($_SESSION['username']);
$learner_id = 0;

if ($isLoggedIn) {
    $user = $Learner->find(['username' => $_SESSION['username']]);
    $learner_id = $user ? intval($user['id']) : 0;
}

/* =========================================================================
   第一部分：儲存作答進度 (徹底封鎖特訓污染，採用全新 action 語意判斷)
   ========================================================================= */
// 💡 關鍵安全防線：一登入時（action 爲 null），或者進入特訓/盲刷（mode 有值）時，一律被本防線攔截！「只抽卡、不改寫原有的記憶等級與複習時間」
if ($isLoggedIn && $learner_id > 0 && $id > 0 && $action !== null && $mode !== 'pool_rand' && $mode !== 'pool_hard') {
    
    $record = $LearningRecord->find(['learner_id' => $learner_id, 'word_id' => $id, 'type' => $do]);

    if (!$record) {
        // ✨ 全新字初次見面防線：今天第一天認識，不論點什麼，一律先留在 LV 1（新字第一天不扣正確率）
        $new_level = 1; 
        $new_preview_count = 1;
    } else {
        // 舊單字複習邏輯：隔天以後再次見面，正式啟動間隔重複升降機制
        if ($action === 'correct') {
            // 💡 對接全新 action：點擊記得，記憶等級往上加（最高 LV 5）
            $new_level = min(5, intval($record['learning_level']) + 1); 
            $new_preview_count = intval($record['preview_count']) + 1;
        } else {
            // 💡 對接全新 action：點擊不記得，直接打回原形降至 LV 1
            $new_level = 1;
            $new_preview_count = intval($record['preview_count']) + 1; 
        }
    }

    // 間隔記憶法排程時間：根據最新的記憶等級，精密計算下一次複習的日期
    switch ($new_level) {
        case 1:  $days = '+1 day';   break;
        case 2:  $days = '+3 days';  break;
        case 3:  $days = '+7 days';  break;
        case 4:  $days = '+14 days'; break;
        case 5:  $days = '+30 days'; break;
        default: $days = '+1 day';   break;
    }
    $next_review_date = date('Y-m-d', strtotime($days));

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
        $save_data['id'] = $record['id'];
    } else {
        $save_data['is_new_word'] = 1;
        $save_data['created_at']  = date('Y-m-d H:i:s');
    }

    $LearningRecord->save($save_data);

    // 建立進度快取緩衝結構（給 console.php 數據儀表板即時抓取）
    if (!isset($_SESSION['daily_progress']['sets'][$setKey])) {
        $_SESSION['daily_progress']['sets'][$setKey] = ['total'=>0, 'wrong'=>0, 'new_word_count'=>0, 'pool_size'=>0, 'is_finished'=>false];
    }

    // 💡 正確率分母防污染優化：只有在複習「舊單字（$record 存在）」時，才累計正確率分子分母
    if ($record) {
        $_SESSION['daily_progress']['sets'][$setKey]['total'] += 1;
        if ($action === 'wrong') {
            $_SESSION['daily_progress']['sets'][$setKey]['wrong'] += 1;
        }
    } else {
        // 新字作答則單純累計今日已學新字量，絕對不稀釋舊字的錯誤率！
        $_SESSION['daily_progress']['sets'][$setKey]['new_word_count'] += 1;
        $_SESSION['daily_progress']['sets'][$setKey]['pool_size'] += 1;
    }
}

/* =========================================================================
   第二部分：動態完工審查與下一張卡片抽取路由 (完全符合 drawCard 動態適應邏輯)
   ========================================================================= */
$word_data = null;
$isFinishedSignal = false;

// 智慧動態 SQL 構造器：自動對接資料表欄位（如英文庫的 word 與術語庫的 term_name），並做好 JOIN 綁定
if ($do === 'main' || $do === 'card_board') {
    $col_word = 'word';
    $col_def  = 'definition';
    $sql_fields = "t.*, c1.display_name AS category_name, t.phonetic AS secondary_name";
    $sql_join   = "LEFT JOIN `categories` c1 ON t.part_of_speech_id = c1.id";
} else {
    $col_word = 'term_name';
    $col_def  = 'description';
    $sql_fields = "t.*, c1.display_name AS category_name, c2.display_name AS secondary_name";
    $sql_join   = "LEFT JOIN `categories` c1 ON t.category1_id = c1.id 
                   LEFT JOIN `categories` c2 ON t.category2_id = c2.id";
}

if (!$isLoggedIn || $learner_id <= 0) {
    /* ----- 【A. 未登入 / 訪客模式】 ----- */
    // 訪客沒有任何負擔，直接從大資料庫 ORDER BY RAND() 盲抽一張卡片回傳
    $res = $currentDB->q("SELECT $sql_fields FROM `$table` t $sql_join ORDER BY RAND() LIMIT 1");
    $word_data = !empty($res) ? $res[0] : null;

} else {
    /* ----- 【B. 已登入 / 會員模式 - 您的核心核心規則調度樹】 ----- */
    $current_date = date('Y-m-d');
    
    if (!isset($_SESSION['daily_progress']['sets'][$setKey])) {
        $_SESSION['daily_progress']['sets'][$setKey] = ['total'=>0, 'wrong'=>0, 'new_word_count'=>0, 'pool_size'=>0, 'is_finished'=>false];
    }

    $progress   = $_SESSION['daily_progress']['sets'][$setKey];
    $total      = intval($progress['total']);
    $wrong      = intval($progress['wrong']);
    $new_count  = intval($progress['new_word_count']);
    $pool_size  = intval($progress['pool_size']);

    // 1. 嚴格檢查：今天是否還有「到期、且尚未複習完成」的舊單字
    $due_where = "WHERE `learner_id` = '$learner_id' AND `type` = '$do' AND `next_review_date` <= '$current_date'";
    $has_due_words = ($LearningRecord->count($due_where) > 0);

    // 2. 智慧動態決策流程
    $isTodayTaskDone = false;

    if ($has_due_words) {
        // 【波段 1：舊字還在】➔ 全力消滅舊字！此時「絕對不結算正確率」，新字大門全面深鎖
        $isTodayTaskDone = false;
    } else {
        // 【波段 2：舊字清空】➔ 舊字剛好歸零的這千分之一秒，後端首度結算今日舊字正確率，當場決定新字上限！
        $accuracy = ($total === 0) ? 100 : (($total - $wrong) / $total) * 100;
        
        if ($accuracy >= 85)     $new_limit = 20;
        elseif ($accuracy >= 70) $new_limit = 15;
        elseif ($accuracy >= 60) $new_limit = 10;
        elseif ($accuracy >= 50) $new_limit = 5;
        else                     $new_limit = 0; // 舊字錯太多，今天不發放新字，強制學員專心對付舊字

        // 檢查今天加的新字數量是否達到了剛計算出來的上限
        if ($new_count >= $new_limit || $pool_size >= 200) {
            $isTodayTaskDone = true;
        }
    }

    // 3. 完工旗標同步：若判定完工且 Session 尚未標記，立即寫入雲端與快取
    if ($isTodayTaskDone && $_SESSION['daily_progress']['sets'][$setKey]['is_finished'] === false) {
        $_SESSION['daily_progress']['sets'][$setKey]['is_finished'] = true;
        $Learner->save(['id' => $learner_id, 'task_finished' => $current_date]);
    }

    // 將最新進度儲存回 Learner 表，供 console.php 數據仪表板渲染
    $updated_json = json_encode($_SESSION['daily_progress'], JSON_UNESCAPED_UNICODE);
    $Learner->save(['id' => $learner_id, 'daily_progress' => $updated_json]);

    // 4. 下一張卡片抽取與分流調度
    if ($_SESSION['daily_progress']['sets'][$setKey]['is_finished'] === true || $mode !== '') {
        // ➔ 【完工階段】：功能全盤質變，依前端按鈕給予特訓或盲刷
        $isFinishedSignal = true;

        if ($mode === 'pool_hard') {
            // 生字特訓：專門抽取該使用者字庫中 Level 最低的生字進行突擊
            $res = $currentDB->q("SELECT lr.*, $sql_fields FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id $sql_join WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' ORDER BY lr.learning_level ASC, RAND() LIMIT 1");
        } else {
            // 隨機盲刷：在已建立的 200 字庫記錄中完全隨機盲抽
            $res = $currentDB->q("SELECT lr.*, $sql_fields FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id $sql_join WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' ORDER BY RAND() LIMIT 1");
        }
        $word_data = !empty($res) ? $res[0] : null;
    } else {
        // ➔ 【學習進行中階段】：嚴格落實「先舊後新」分流
        if ($has_due_words) {
            // 優先軌道：舊字堆。只要這堆字不按完，新字連碰都碰不到
            $res = $currentDB->q("SELECT lr.*, $sql_fields FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id $sql_join WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' AND lr.next_review_date <= '$current_date' ORDER BY RAND() LIMIT 1");
            $word_data = !empty($res) ? $res[0] : null;
        } else {
            // 遞補軌道：新字堆。舊字清空了且還有上限額度，放行加載從未背過的新字，並依 ID 順序發放
            $res = $currentDB->q("SELECT $sql_fields FROM `$table` t $sql_join WHERE t.id NOT IN (SELECT word_id FROM `learning_records` WHERE learner_id = '$learner_id' AND type = '$do') ORDER BY RAND() LIMIT 1");
            $word_data = !empty($res) ? $res[0] : null;
            
            if ($word_data) {
                // 賦予新字第一天初始 Level
                $word_data['learning_level'] = 1;
                $word_data['preview_count'] = 1; 
            }
        }
    }
}

// 基礎防禦
if (!$word_data) {
    echo json_encode(["status" => "empty", "message" => "當前排堆已無任何可用卡片，請確認字庫庫存是否充足。"], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================================
   第三部分：[智慧數據抽象化封裝與 JSON 輸出]
   ========================================================================= */
$final_category1 = $word_data['category_name']  ?? "";
$final_category2 = $word_data['secondary_name'] ?? ""; 

echo json_encode([
    "status"        => "success",
    "id"            => $word_data['word_id']     ?? $word_data['id'], 
    "word"          => $word_data[$col_word]     ?? "", 
    "category1"     => $final_category1,                
    "category2"     => $final_category2,                
    "definition"    => $word_data[$col_def]      ?? "", 
    "translation"   => $word_data['translation'] ?? "",
    "audio"         => $word_data['audio_url']   ?? "", 
    "level"         => ($mode === 'pool_rand') ? null : ($word_data['learning_level'] ?? null),
    "preview_count" => ($mode === 'pool_rand') ? null : ($word_data['preview_count'] ?? null),
    "isFinished"    => $isFinishedSignal
], JSON_UNESCAPED_UNICODE);

exit;
?>