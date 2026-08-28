<?php

include_once "./db.php"; 
header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'error', 'message' => '未知錯誤'];

/* =========================================================================
   [前端參數安全擷取與動態排堆路由] 
   ========================================================================= */
$do        = isset($_GET['do'])        ? trim($_GET['do']) : 'main'; // 預設對接單一入口主路由
$id        = isset($_GET['id'])        ? intval($_GET['id']) : 0;
$isCorrect = isset($_GET['isCorrect']) ? $_GET['isCorrect'] : null;
$mode      = isset($_GET['mode'])      ? trim($_GET['mode']) : ''; 

// 智慧別名路由表：對應不同的 ?do= 指派正確的底層主資料表名稱、快取鍵名與專屬 DB 物件
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
   第一部分：儲存作答進度 (活用 $LearningRecord->find 與 save 方法)
   ========================================================================= */
if ($isLoggedIn && $learner_id > 0 && $id > 0 && $isCorrect !== null && $mode !== 'pool_rand') {
    
    $record = $LearningRecord->find(['learner_id' => $learner_id, 'word_id' => $id, 'type' => $do]);

    if (!$record) {
        // =========================================================================
        // ✨ 全新字初次見面防線：今天剛認識，不論點什麼，一律先留在 LV 1
        // =========================================================================
        $new_level = 1; 
        $new_preview_count = 1;
        $is_actual_wrong = false;       // 新字第一天不扣正確率
        $is_new_word_graduation = true; // 標記為今日新學到的字（新字額度 +1）
    } else {
        // =========================================================================
        // 舊單字複習邏輯：隔天以後再次見面，正式啟動間隔重複升降機制
        // =========================================================================
        $is_new_word_graduation = false;
        
        if ($isCorrect === 'true') {
            // 舊字點認得：等級往上加（最高 LV 5）
            $new_level = min(5, intval($record['learning_level']) + 1); 
            $new_preview_count = intval($record['preview_count']) + 1;
            $is_actual_wrong = false;
        } else {
            // 舊字點不認得：打回原形降至 LV 1
            $new_level = 1;
            $new_preview_count = intval($record['preview_count']) + 1; 
            $is_actual_wrong = true; // 真正的舊字複習答錯，會拉低正確率並影響新字額度
        }
    }

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
   第二部分：動態完工審查與下一張卡片抽取路由
   ========================================================================= */
$word_data = null;
$isFinishedSignal = false;

// 💡 智慧動態 SQL 構造器：對接資料庫欄位不一的現況，並做好雙表 JOIN 的別名綁定
if ($do === 'main' || $do === 'card_board') {
    $col_word = 'word';
    $col_def  = 'definition';
    
    // 一般字庫：JOIN 詞性表，並直接將 phonetic 欄位封裝為 secondary_name 通道
    $sql_fields = "t.*, c1.display_name AS category_name, t.phonetic AS secondary_name";
    $sql_join   = "LEFT JOIN `categories` c1 ON t.part_of_speech_id = c1.id";
} else {
    $col_word = 'term_name';
    $col_def  = 'description';
    
    // 術語庫（HTML/CSS）：採用雙表 JOIN，同時將主分類、次分類的真實中文名稱解碼出來！
    $sql_fields = "t.*, c1.display_name AS category_name, c2.display_name AS secondary_name";
    $sql_join   = "LEFT JOIN `categories` c1 ON t.category1_id = c1.id 
                   LEFT JOIN `categories` c2 ON t.category2_id = c2.id";
}

if (!$isLoggedIn) {
    /* ----- 【A. 未登入 / 訪客模式】 ----- */
    $res = $currentDB->q("SELECT $sql_fields FROM `$table` t $sql_join ORDER BY RAND() LIMIT 1");
    $word_data = !empty($res) ? $res[0] : null;
} else {
    /* ----- 【B. 已登入 / 會員模式】 ----- */
    $current_date = date('Y-m-d');
    
    $progress   = $_SESSION['daily_progress']['sets'][$setKey] ?? ['total'=>0, 'wrong'=>0, 'new_word_count'=>0, 'pool_size'=>0, 'is_finished'=>false];
    $total      = intval($progress['total']);
    $wrong      = intval($progress['wrong']);
    $new_count  = intval($progress['new_word_count']);
    $pool_size  = intval($progress['pool_size']);

    $accuracy = ($total === 0) ? 100 : (($total - $wrong) / $total) * 100;
    if ($accuracy >= 85)     $new_limit = 20;
    elseif ($accuracy >= 70) $new_limit = 15;
    elseif ($accuracy >= 60) $new_limit = 10;
    elseif ($accuracy >= 50) $new_limit = 5;
    else                     $new_limit = 0; 

    $due_where = "WHERE `learner_id` = '$learner_id' AND `type` = '$do' AND `next_review_date` <= '$current_date'";
    $has_due_words = ($LearningRecord->count($due_where) > 0);

    $isTodayTaskDone = (!$has_due_words && ($new_count >= $new_limit || $pool_size >= 200));

    if ($isTodayTaskDone && $_SESSION['daily_progress']['sets'][$setKey]['is_finished'] === false) {
        $_SESSION['daily_progress']['sets'][$setKey]['is_finished'] = true;
        $Learner->save(['id' => $learner_id, 'task_finished' => $current_date]);
    }

    $updated_json = json_encode($_SESSION['daily_progress'], JSON_UNESCAPED_UNICODE);
    $Learner->save(['id' => $learner_id, 'daily_progress' => $updated_json]);

    if ($_SESSION['daily_progress']['sets'][$setKey]['is_finished'] === true || $mode !== '') {
        $isFinishedSignal = true;

        if ($mode === 'pool_hard') {
            $res = $currentDB->q("SELECT lr.*, $sql_fields FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id $sql_join WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' ORDER BY lr.learning_level ASC, RAND() LIMIT 1");
        } else {
            $res = $currentDB->q("SELECT lr.*, $sql_fields FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id $sql_join WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' ORDER BY RAND() LIMIT 1");
        }
        $word_data = !empty($res) ? $res[0] : null;
    } else {
        if ($has_due_words) {
            $res = $currentDB->q("SELECT lr.*, $sql_fields FROM `learning_records` lr JOIN `$table` t ON lr.word_id = t.id $sql_join WHERE lr.learner_id = '$learner_id' AND lr.type = '$do' AND lr.next_review_date <= '$current_date' ORDER BY RAND() LIMIT 1");
            $word_data = !empty($res) ? $res[0] : null;
        } else {
            $res = $currentDB->q("SELECT $sql_fields FROM `$table` t $sql_join WHERE t.id NOT IN (SELECT word_id FROM `learning_records` WHERE learner_id = '$learner_id' AND type = '$do') ORDER BY RAND() LIMIT 1");
            $word_data = !empty($res) ? $res[0] : null;
            
            if ($word_data) {
                $word_data['learning_level'] = 1;
                $word_data['preview_count'] = 1; 
            }
        }
    }
}

if (!$word_data) {
    echo json_encode(["status" => "empty", "message" => "當前排堆已無任何可用卡片"], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================================
   第三部分：[智慧數據抽象化封裝與 JSON 輸出]
   ========================================================================= */
$final_category1 = $word_data['category_name']  ?? "";
$final_category2 = $word_data['secondary_name'] ?? ""; // 智慧解碼：英文庫輸出音標字串，術語庫輸出次分類中文名稱

echo json_encode([
    "status"        => "success",
    "id"            => $word_data['word_id']     ?? $word_data['id'], 
    "word"          => $word_data[$col_word]     ?? "", // 動態適應 word / term_name
    "category1"     => $final_category1,                // 乾淨的 category1 通道 ➔ 前端直填 id="category1"
    "category2"     => $final_category2,                // 乾淨的 category2 通道 ➔ 前端直填 id="category2"
    "definition"    => $word_data[$col_def]      ?? "", // 動態適應 definition / description
    "translation"   => $word_data['translation'] ?? "",
    "audio"         => $word_data['audio_url']   ?? "", // 真人 MP3 發音網址通道
    "level"         => ($mode === 'pool_rand') ? null : ($word_data['learning_level'] ?? null),
    "preview_count" => ($mode === 'pool_rand') ? null : ($word_data['preview_count'] ?? null),
    "isFinished"    => $isFinishedSignal
], JSON_UNESCAPED_UNICODE);

exit;

?>