<?php
// 1. 基本安全設定（移除對免費主機無效的 flush 設定）
header('Content-Type: text/html; charset=utf-8;');

try {
    $config = require_once 'db_config.php';
    $dsn = "mysql:host={$config['host']}; charset=utf8; dbname={$config['dbname']};";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $exception) {
    echo "資料庫連線失敗: " . $exception->getMessage();
    exit;
}

// 2. 初始化進度記錄表（保留您的進度表功能）
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

echo "<h2>📚 單字庫高速批次補全系統（安全輪詢版） 🚀</h2>";
echo "💾 上次進度：將從單字序號 <strong>> {$last_word_id}</strong> 開始掃描。<br><br>";

// 3. 核心改動：每次「只拿 1 個」真正需要去抓 API 的單字，避免迴圈卡死主機
$sql = "SELECT `id`, `word`, `part_of_speech`, `definition`, `phonetic` 
        FROM `words` 
        WHERE `id` > ? 
        AND (`definition` = '' OR `definition` IS NULL OR `phonetic` = '' OR `phonetic` IS NULL)
        ORDER BY `id` ASC 
        LIMIT 1;"; 

$stmt = $pdo->prepare($sql);
$stmt->execute([$last_word_id]);
$word_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$word_data) {
    echo "🎉 之後的所有單字都已補全完畢！任務結束。";
    exit;
}

// 4. 處理這唯一的一筆單字資料
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

// 5. 寫入單字資料
if ($need_update === true && !empty($word_data['definition'])) {
    $update_sql = "UPDATE `words` SET `phonetic` = ?, `definition` = ?, `audio_url` = ? WHERE `id` = ?;";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([
        $word_data['phonetic'] ?? "", 
        $word_data['definition'] ?? "", 
        $word_data['audio_url'] ?? "https://dictionaryapi.dev/" . $word, 
        $current_id
    ]);
    echo "<div style='color: green; font-size: 16px;'>✅ [成功] 序號 {$current_id}: <strong>{$word_data['word']}</strong> 已連網補全。</div>";
} else {
    // 🌟 修正邏輯漏洞：如果查無此字，塞入 'N/A' 標記它已被處理過，否則進度會卡死在這個字
    $update_sql = "UPDATE `words` SET `definition` = 'N/A' WHERE `id` = ?;";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$current_id]);
    echo "<div style='color: red; font-size: 16px;'>❌ [失敗] 序號 {$current_id}: <strong>{$word_data['word']}</strong> API 查無此字（已標記跳過）。</div>";
}

// 🌟 無論成功或失敗，都把進度表往前推，確保下次不重複抓
$progress_sql = "UPDATE `fetch_progress` SET `last_word_id` = ? WHERE `id` = 1;";
$progress_stmt = $pdo->prepare($progress_sql);
$progress_stmt->execute([$current_id]);

// 設定安全的隨機等待秒數 (為了防鎖，建議設定 8 ~ 15 秒之間)
$sleep_seconds = rand(8, 15);
?>

<!-- 6. 核心前端防鎖線：利用 JavaScript 執行真正的畫面倒數計時 -->
<hr>
<div style="background: #f4f4f4; padding: 15px; border-radius: 5px; display: inline-block; margin-top: 15px;">
    <span style="color: #666;">[安全機制] 已同步進度到序號 <?php echo $current_id; ?>。</span><br>
    ⏳ <span id="timer" style="font-weight: bold; color: blue; font-size: 22px;"><?php echo $sleep_seconds; ?></span> 秒後將自動重新整理並擷取下一個...
</div>

<script>
let timeLeft = <?php echo $sleep_seconds; ?>;
const timerDisplay = document.getElementById('timer');

const countdown = setInterval(function() {
    timeLeft--;
    timerDisplay.textContent = timeLeft;
    
    if (timeLeft <= 0) {
        clearInterval(countdown);
        // 倒數結束，自動重新整理網頁執行下一個單字
        location.reload();
    }
}, 1000);
</script>