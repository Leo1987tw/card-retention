<?php
// 開啟 Session，確保登入狀態與每日進度快取正常運作
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once "./db.php"; 
header('Content-Type: application/json; charset=utf-8');

// 強制統一伺服器時區，確保跨天判斷與複習排程計算精準無誤
date_default_timezone_set('Asia/Taipei'); 

$response = ['status' => 'error', 'message' => '未知錯誤'];
$current_date = date('Y-m-d');

/* =========================================================================
   [前端參數安全擷取與動態排堆路由] 
   ========================================================================= */
$do     = isset($_GET['do'])     ? trim($_GET['do']) : 'main'; 
$id     = (isset($_GET['id']) && $_GET['id'] !== 'null') ? intval($_GET['id']) : 0;
$action = (isset($_GET['action']) && $_GET['action'] !== 'null') ? trim($_GET['action']) : null; 
$mode   = isset($_GET['mode'])   ? trim($_GET['mode']) : ''; // 💡 完美相容舊版：精準擷取 mode 參數

// 智慧別名路由表：對接三個不同的排堆（words、html_terms、css_terms）
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

// 智慧動態 SQL 欄位構造器
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

/* =========================================================================
   最高鐵律防線一：[未登入 / 訪客模式大門口攔截]
   ========================================================================= */
$isLoggedIn = isset($_SESSION['username']);
$learner_id = 0;

if (!$isLoggedIn) {
    // 訪客沒有任何負擔，直接隨機抽卡，絕對不進行任何資料庫與 Session 的寫入
    $res = $currentDB->q("SELECT $sql_fields FROM `$table` t $sql_join ORDER BY RAND() LIMIT 1");
    $word_data = !empty($res) ? $res[0] : null; // 💡 終極修正：直接抓取第 0 筆物件，防止欄位空無一物！

    if (!$word_data) {
        echo json_encode(["status" => "empty", "message" => "當前排堆已無任何可用卡片。"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        "status"        => "success",
        "id"            => $word_data['id'], 
        "word"          => $word_data[$col_word]     ?? "", 
        "category1"     => $word_data['category_name']  ?? "",                
        "category2"     => $word_data['secondary_name'] ?? "",                
        "definition"    => $word_data[$col_def]      ?? "", 
        "translation"   => $word_data['translation'] ?? "",
        "audio"         => $word_data['audio_url']   ?? "", 
        "level"         => null,
        "preview_count" => null,
        "isFinished"    => false
    ], JSON_UNESCAPED_UNICODE);
    exit; // 訪客模式在此處直接斬斷，絕不往下執行
}

/* -------------------------------------------------------------------------
   【B. 已登入 / 會員模式】
   ------------------------------------------------------------------------- */
$user = $Learner->find(['username' => $_SESSION['username']]);
$learner_id = $user ? intval($user['id']) : 0;

if ($learner_id <= 0) {
    echo json_encode(["status" => "error", "message" => "無效的使用者帳號。"], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================================
   核心修改 2：[daily_progress 三組獨立計數與跨天自動重置機制]
   ========================================================================= */
$db_progress = [];
if (!empty($user['daily_progress'])) {
    $db_progress = json_decode($user['daily_progress'], true);
}

// 跨天檢查：若欄位不存在，或日期不是今天
if (!isset($db_progress['date']) || $db_progress['date'] !== $current_date) {
    
    // 初始化新的一天 JSON 基礎架構
    $new_progress = [
        'date' => $current_date,
        'sets' => []
    ];

    // 強制重置與定義三組排堆（words, html, css）的數據
    $keys_to_init = ['words', 'html', 'css'];
    foreach ($keys_to_init as $k) {
        // 動態轉換路由別名對接資料庫的 type 欄位值
        $db_type = ($k === 'words') ? 'main' : $k;

        // 💡 極致效能優化：只有在跨天重置時，才精準執行一次 COUNT 統計當前排堆未滿 5 級的單字總數
        $count_res = $LearningRecord->count("WHERE `learner_id` = '$learner_id' AND `type` = '$db_type' AND `learning_level` < 5");
        
        $new_progress['sets'][$k] = [
            'total'           => 0, 
            'wrong'           => 0, 
            'new_word_count'  => 0, 
            'pool_size'       => 0, 
            'under_lv5_count' => intval($count_res), // 三組排堆完全獨立分組快取計數
            'is_finished'     => false
        ];
    }
    
    $db_progress = $new_progress;

    // 將初始化與精算好的快取數據存回資料庫
    $Learner->save([
        'id'             => $learner_id, 
        'daily_progress' => json_encode($db_progress, JSON_UNESCAPED_UNICODE)
    ]);
}

// 💡 核心注入：將最新的進度數據（不論是否跨天）同步寫入 SESSION 快取中
$_SESSION['daily_progress'] = $db_progress;

// 確保當前操作排堆的快捷節點存在，防呆補位
if (!isset($_SESSION['daily_progress']['sets'][$setKey])) {
    $_SESSION['daily_progress']['sets'][$setKey] = ['total'=>0, 'wrong'=>0, 'new_word_count'=>0, 'pool_size'=>0, 'under_lv5_count'=>0, 'is_finished'=>false];
}
$is_finished_now = $_SESSION['daily_progress']['sets'][$setKey]['is_finished'];


/* =========================================================================
   核心修改 5：[完美相容舊前端：任務結束後或手動傳入 mode 的純特訓零污染處理]
   ========================================================================= */
// 💡 對接舊版 Script：不論是今日已完工，還是前端有主動傳入特訓模式參數，一律進入本攔截區
if ($is_finished_now === true || $mode !== '') {
    
    $word_data = null;
    
    // 精准辨識舊前端傳遞的 mode 分支
    if ($mode === 'pool_hard') {
        // 生字特訓：專門抽取該使用者字庫中 Level 最低的生字進行突擊
        $res = $currentDB->q("SELECT lr.*, $sql_fields FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id $sql_join WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' ORDER BY lr.learning_level ASC, RAND() LIMIT 1");
        $word_data = !empty($res) ? $res[0] : null; // 👑 終極修正：精準取得第 0 筆物件，徹底消除全空 Bug
    } else {
        // 隨機盲刷（含 pool_rand）：在已建立的字庫記錄中完全隨機盲抽
        $res = $currentDB->q("SELECT lr.*, $sql_fields FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id $sql_join WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' ORDER BY RAND() LIMIT 1");
        $word_data = !empty($res) ? $res[0] : null; // 👑 終極修正：精準取得第 0 筆物件，徹底消除全空 Bug
    }

    if (!$word_data) {
        echo json_encode(["status" => "empty", "message" => "特訓字庫已無可用卡片，請確認字庫庫存是否充足。"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 💡 遵照最高鐵律：完工特訓/盲刷雙軌分流之下，全盤封鎖並直接跳過所有資料庫與 Session 的修改更新！
    // 💡 同時相容舊前端：隨機盲刷下 level 與 preview_count 回傳 null
    echo json_encode([
        "status"        => "success",
        "id"            => $word_data['word_id']     ?? $word_data['id'], 
        "word"          => $word_data[$col_word]     ?? "", 
        "category1"     => $word_data['category_name']  ?? "",                
        "category2"     => $word_data['secondary_name'] ?? "",                
        "definition"    => $word_data[$col_def]      ?? "", 
        "translation"   => $word_data['translation'] ?? "",
        "audio"         => $word_data['audio_url']   ?? "", 
        "level"         => ($mode === 'pool_rand') ? null : ($word_data['learning_level'] ?? 1),
        "preview_count" => ($mode === 'pool_rand') ? null : ($word_data['preview_count']  ?? 1),
        "isFinished"    => true
    ], JSON_UNESCAPED_UNICODE);
    exit; // 完工特訓在此直接中斷，零污染
}

/* =========================================================================
   核心修改 3：[學習進行中階段（未完工）：歷史作答紀錄與 under_lv5_count 快取更新]
   ========================================================================= */
$is_this_turn_new = false; // 用來標記下一張抽出的字在本次撈取中是否為全新字

if ($id > 0 && $action !== null) {
    
    $record = $LearningRecord->find(['learner_id' => $learner_id, 'word_id' => $id, 'type' => $do]);

    if (!$record) {
        /* ----- 全新字初次見面防線 ----- */
        // 💡 鐵律：新字第一次出現，不論點 correct 或 wrong，記憶等級無條件維持 1 級
        $new_level = 1; 
        $new_preview_count = 1; // 看過次數初始化為 1
        
        // 💡 聯動更新：舊字庫新增一個未熟新成員，該組 under_lv5_count 快取計數動態 +1
        $_SESSION['daily_progress']['sets'][$setKey]['under_lv5_count'] += 1;
        
        // 累加今日新字學過總量與暫存池
        $_SESSION['daily_progress']['sets'][$setKey]['new_word_count'] += 1;
        $_SESSION['daily_progress']['sets'][$setKey]['pool_size'] += 1;
    } else {
        /* ----- 舊單字複習邏輯 ----- */
        $old_level = intval($record['learning_level']);
        $new_preview_count = intval($record['preview_count']) + 1; // 💡 鐵律：看過次數無條件往上累加 (+1)

        if ($action === 'wrong') {
            // 💡 答錯最高防線：不論原本幾級，只要答錯記憶等級強制降回 1 級
            $new_level = 1;
            
            // 💡 聯動更新：如果該舊字原本是背熟的 5 級，因為答錯降級，造成未滿 5 級的單字總數 +1
            if ($old_level === 5) {
                $_SESSION['daily_progress']['sets'][$setKey]['under_lv5_count'] += 1;
            }
            
            $_SESSION['daily_progress']['sets'][$setKey]['total'] += 1;
            $_SESSION['daily_progress']['sets'][$setKey]['wrong'] += 1;
        } else {
            // 點擊 correct：舊字等級逐步 +1（最高到 5）
            $new_level = min(5, $old_level + 1);
            
            // 💡 聯動更新：如果這一手點擊成功讓舊字爬升上 5 級，代表被背熟了，未滿 5 級的計數快取 -1
            if ($old_level < 5 && $new_level === 5) {
                $_SESSION['daily_progress']['sets'][$setKey]['under_lv5_count'] = max(0, $_SESSION['daily_progress']['sets'][$setKey]['under_lv5_count'] - 1);
            }
            
            $_SESSION['daily_progress']['sets'][$setKey]['total'] += 1;
        }
    }

    // 推算下一次間隔重複複習日期
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
        'learner_id'       => $learner_id,
        'word_id'          => $id,
        'type'             => $do,
        'learning_level'   => $new_level,
        'preview_count'    => $new_preview_count,
        'last_review_at'   => date('Y-m-d H:i:s'),
        'next_review_date' => $next_review_date
    ];

    if ($record) {
        $save_data['id'] = $record['id'];
    } else {
        $save_data['is_new_word'] = 1;
        $save_data['created_at']  = date('Y-m-d H:i:s');
    }
    
    // 儲存、更新歷史作答進度
    $LearningRecord->save($save_data);
}


/* =========================================================================
   核心修改 4：[學習進行中階段（未完工）：下一張卡片智慧抽字調度與任務完工審查]
   ========================================================================= */
$progress   = $_SESSION['daily_progress']['sets'][$setKey];
$total      = intval($progress['total']);
$wrong      = intval($progress['wrong']);
$new_count  = intval($progress['new_word_count']);
$pool_size  = intval($progress['pool_size']);
$under_lv5  = intval($progress['under_lv5_count']); // 直接讀取 Session 快取，免去重複 COUNT 撈庫效能損耗

// 1. 優先檢查舊字庫中是否有今天到期或過期的舊字
$due_where = "WHERE `learner_id` = '$learner_id' AND `type` = '$do' AND `next_review_date` <= '$current_date'";
$has_due_words = ($LearningRecord->count($due_where) > 0);

$isTodayTaskDone = false;

if ($has_due_words) {
    // 【消滅舊字軌道】只要還有到期舊字，就必須全力按完，此時絕對不判定完工
    $isTodayTaskDone = false;
} else {
    // 【舊字已清空，進入決策】
    
    // 💡 字庫容載上限防護線：直接檢查快取中未達 5 級的總字數是否已超過或等於 200 字
    if ($under_lv5 >= 200) {
        // 超過 200 字代表尚未熟記的字負擔過重，直接全面封鎖新字，今日任務宣告完工結束！
        $isTodayTaskDone = true;
        $new_limit = 0;
    } else {
        // 未滿 200 字，放行結算今日舊字複習正確率，以此動態核發今日新字上限額度
        $accuracy = ($total === 0) ? 100 : (($total - $wrong) / $total) * 100;
        
        if ($accuracy >= 85)     $new_limit = 20;
        elseif ($accuracy >= 70) $new_limit = 15;
        elseif ($accuracy >= 60) $new_limit = 10;
        elseif ($accuracy >= 50) $new_limit = 5;
        else                     $new_limit = 0; // 錯太多，今天不發新字

        if ($new_count >= $new_limit || $pool_size >= 200) {
            $isTodayTaskDone = true;
        }
    }
}

// 2. 完工狀態同步與持久化寫入資料庫
if ($isTodayTaskDone) {
    $_SESSION['daily_progress']['sets'][$setKey]['is_finished'] = true;
    $is_finished_now = true;
}

// 即時將本輪動作的所有進度數據（含最新增減後的 under_lv5_count 快取）同步回存到資料庫 learners 表
$updated_json = json_encode($_SESSION['daily_progress'], JSON_UNESCAPED_UNICODE);
$Learner->save(['id' => $learner_id, 'daily_progress' => $updated_json]);


// 3. 正式抽取卡片分流
$word_data = null;

if ($is_finished_now === true) {
    // 剛好在這一手作答點擊完達到完工線（即刻拋出完工訊號給前端引導慶祝畫面）
    echo json_encode([
        "status"     => "success",
        "message"    => "今日任務完工！",
        "isFinished" => true
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($has_due_words) {
    /* ----- 優先軌道：隨機抽取今天到期舊字 ----- */
    $res = $currentDB->q("SELECT lr.*, $sql_fields FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id $sql_join WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' AND lr.next_review_date <= '$current_date' ORDER BY RAND() LIMIT 1");
    // 💡 關鍵修正：從陣列中解鎖、直接取得第 0 筆關聯資料，確保轉為單一物件輸出
    $word_data = !empty($res) ? $res[0] : null; 
} else {
    /* ----- 遞補軌道：抽全新字（智慧防重複防防線） ----- */
    // 💡 利用 NOT IN 子查詢，確保隨機抽出的新字絕對不可能跟舊字庫重複
    if ($new_count < $new_limit) {
        $res = $currentDB->q("SELECT $sql_fields FROM `$table` t $sql_join WHERE t.id NOT IN (SELECT word_id FROM `learning_records` WHERE learner_id = '$learner_id' AND type = '$do') ORDER BY RAND() LIMIT 1");
        // 💡 關鍵修正：從陣列中解鎖、直接取得第 0 筆關聯資料，確保轉為單一物件輸出
        $word_data = !empty($res) ? $res[0] : null; 
        
        if ($word_data) {
            $word_data['learning_level'] = 1;
            // 💡 完美對接舊前端：新字還沒有被點擊作答（即未被儲存進歷史紀錄）前
            // 此處刻意傳回數字 1，完美驅動舊前端的 data.preview_count === 1 觸發 NEW 徽章注入！
            $word_data['preview_count'] = 1; 
        }
    }
}

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
    "level"         => $word_data['learning_level'] ?? null,
    "preview_count" => $word_data['preview_count']  ?? null,
    "isFinished"    => false // 進行中
], JSON_UNESCAPED_UNICODE);

exit;
?>