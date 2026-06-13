<?php
// 1. 基本安全設定：不限執行時間，並開啟即時畫面輸出
set_time_limit(0);
ob_implicit_flush(true);
if (ob_get_level() == 0) ob_start();

header('Content-Type: text/html; charset=utf-8;');

try {
    $config = require_once 'db_config.php';
    $dsn = "mysql:host={$config['host']}; charset=utf8; dbname={$config['dbname']};";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $exception) {
    echo "資料庫連線失敗: " . $exception->getMessage();
    exit;
}

// 2. 初始化進度記錄表（如果不存在就自動建立）
$pdo->exec("CREATE TABLE IF NOT EXISTS `fetch_progress` (
    `id` INT PRIMARY KEY,
    `last_word_id` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

$checkProgress = $pdo->query("SELECT COUNT(*) FROM `fetch_progress` WHERE `id` = 1")->fetchColumn();
if ($checkProgress == 0) {
    $pdo->exec("INSERT INTO `fetch_progress` (`id`, `last_word_id`) VALUES (1, 0);");
}

// 讀取上次更新到哪一個 word_id
$last_word_id = $pdo->query("SELECT `last_word_id` FROM `fetch_progress` WHERE `id` = 1")->fetchColumn();

echo "<h2>📚 單字庫高速批次補全系統 🚀</h2>";
echo "💾 上次進度：將從單字序號 <strong>> {$last_word_id}</strong> 開始掃描。<br>";

// 3. 核心優化：直接在 SQL 篩選出大於上次進度，且「真正需要去抓 API」的單字，只拿 50 個
// 🌟 這樣做，那些已經有資料的單字在資料庫端就直接被過濾（跳過）了，不需要在 PHP 裡等待！
$sql = "SELECT `id`, `word`, `part_of_speech`, `definition`, `phonetic` 
        FROM `words` 
        WHERE `id` > ? 
        AND (`definition` = '' OR `definition` IS NULL OR `phonetic` = '' OR `phonetic` IS NULL)
        ORDER BY `id` ASC 
        LIMIT 50;"; // 🌟 每次只精準抓取 50 個需要連網的單字

$stmt = $pdo->prepare($sql);
$stmt->execute([$last_word_id]);
$all_words = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_tasks = count($all_words);
echo "🎯 本批次偵測到需要聯網擷取的單字共：<strong>{$total_tasks}</strong> 個。<br><br>";

if ($total_tasks == 0) {
    echo "🎉 之後的所有單字都已補全完畢，或者本批次沒有需要更新的單字！";
    exit;
}

echo "開始慢速安全擷取...<br><br><hr>";
ob_flush();
flush();

$success_count = 0;
$fail_count = 0;

// 4. 開始循環擷取（這裹面抓的每一筆都是確定需要連網的）
foreach ($all_words as $word_data) {
    $current_id = $word_data['id'];
    $word = urlencode($word_data['word']);
    $need_update = false;
    
    // 連接 API
    $eng_url = "https://api.dictionaryapi.dev/api/v2/entries/en/" . $word;
    $options = array('http' => array('timeout' => 5, 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'));
    $context = stream_context_create($options);
    $eng_response = @file_get_contents($eng_url, false, $context);

    if ($eng_response !== false) {
        $eng_data = json_decode($eng_response, true);
        
        $part_of_speech_map = [
            1 => "noun", 2 => "verb", 3 => "adjective", 
            4 => "adverb", 5 => "preposition", 6 => "conjunction"
        ];
        $target_part_of_speech = isset($part_of_speech_map[$word_data['part_of_speech']]) ? $part_of_speech_map[$word_data['part_of_speech']] : "";

        // 精準詞性比對
        if (!empty($target_part_of_speech) && isset($eng_data[0]['meanings'])) {
            foreach ($eng_data[0]['meanings'] as $meaning) {
                if (strtolower($meaning['partOfSpeech']) === $target_part_of_speech) {
                    if (!empty($meaning['definitions'][0]['definition'])) {
                        $word_data['definition'] = $meaning['definitions'][0]['definition'];
                        $word_data['phonetic'] = isset($eng_data[0]['phonetic']) ? $eng_data[0]['phonetic'] : '';
                        break;
                    }
                }
            }
        }
            
        // 備用大眾定義
        if (empty($word_data['definition']) && !empty($eng_data[0]['meanings'][0]['definitions'][0]['definition'])) {
            $word_data['definition'] = $eng_data[0]['meanings'][0]['definitions'][0]['definition'];
            $word_data['phonetic'] = isset($eng_data[0]['phonetic']) ? $eng_data[0]['phonetic'] : '';
        }

        // 拿音檔
        if (!empty($eng_data[0]['phonetics'])) {
            foreach ($eng_data[0]['phonetics'] as $p) {
                if (!empty($p['audio'])) {
                    $word_data['audio_url'] = $p['audio'];
                    break;
                }
            }
        }
        $need_update = true;
    }

    // 5. 寫入單字資料，並即時更新進度表
    if ($need_update === true && !empty($word_data['definition'])) {
        $update_sql = "UPDATE `words` SET `phonetic` = ?, `definition` = ?, `audio_url` = ? WHERE `id` = ?;";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            $word_data['phonetic'] ?? "", 
            $word_data['definition'] ?? "", 
            $word_data['audio_url'] ?? "https://dictionaryapi.dev/" . $word, 
            $current_id
        ]);
        $success_count++;
        echo "✅ [成功] 序號 {$current_id}: <strong>{$word_data['word']}</strong> 已連網補全。<br>";
    } else {
        $fail_count++;
        echo "❌ [失敗] 序號 {$current_id}: <strong>{$word_data['word']}</strong> API 查無此字。<br>";
    }

    // 🌟 核心記憶功能：每跑完一個，不論成功或無資料，都記錄目前進度
    $progress_sql = "UPDATE `fetch_progress` SET `last_word_id` = ? WHERE `id` = 1;";
    $progress_stmt = $pdo->prepare($progress_sql);
    $progress_stmt->execute([$current_id]);

    // 🌟 核心等待功能：只有在真正發送完 API 後，才執行隨機 5 ~ 10 秒的防封鎖暫停
    $sleep_seconds = rand(5, 10);
    echo "<span style='color: #888;'>[安全機制] 已同步進度。隨機等待 {$sleep_seconds} 秒後執行下一個...</span><br><br>";
    
    ob_flush();
    flush();
    sleep($sleep_seconds);
}

echo "<hr><h3>🏁 本批次 50 個單字擷取任務已結束！</h3>";
echo "成功補全: {$success_count} 筆，失敗: {$fail_count} 筆。<br>";
echo "💡 想要繼續抓下 50 個，只要「重新整理網頁（F5）」即可！";
?>