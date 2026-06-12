<?php
/**
 * 字典資料批量補全腳本 (繞過 cURL 指紋封鎖版)
 * 安全性：絕不影響、不更新、不覆蓋現有的中文翻譯欄位。
 */

if (php_sapi_name() !== 'cli') {
    die("Error: This script can only be run from the command line (CLI).\n");
}

try {
    $config = require_once 'db_config.php';
    $dsn = "mysql:host={$config['host']};charset=utf8;dbname={$config['dbname']};";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $exception) {
    die("Database connection failed: " . $exception->getMessage() . "\n");
}

$batch_size = 50; 

echo "==================================================\n";
echo "Starting Batch Fetch via Dictionary API at " . date('Y-m-d H:i:s') . "\n";
echo "==================================================\n";

$sql = "SELECT * FROM `words` 
        WHERE `phonetic` = '' OR `phonetic` IS NULL 
           OR `definition` = '' OR `definition` IS NULL 
           OR `audio_url` = '' OR `audio_url` IS NULL 
        LIMIT :batch_size";

$statement = $pdo->prepare($sql);
$statement->bindValue(':batch_size', $batch_size, PDO::PARAM_INT);
$statement->execute();
$words_to_fetch = $statement->fetchAll();

if (empty($words_to_fetch)) {
    echo "Excellent! All words in the database are already fully processed.\n";
    exit;
}

echo "Found " . count($words_to_fetch) . " words need to be processed in this batch.\n\n";

$success_count = 0;

foreach ($words_to_fetch as $index => $word_data) {
    $current_num = $index + 1;
    $word_text = trim($word_data['word']); 
    echo "[{$current_num}/" . count($words_to_fetch) . "] Fetching API for: '{$word_text}'... ";

    // 每筆請求隨機等待 2 到 4 秒，模擬真人行為防鎖 IP
    $sleep_time = rand(2000000, 4000000);
    usleep($sleep_time);

    $word_encoded = urlencode($word_text);
    $api_url = "https://api.dictionaryapi.dev/api/v2/entries/en/" . $word_encoded;

    // 改用繞過 cURL 指紋的 Stream 流方法獲取 JSON
    $api_response = fetchJsonWithStream($api_url);

    if (empty($api_response)) {
        echo "FAILED (API returned 403 or word not found).\n";
        continue;
    }

    $eng_data = json_decode($api_response, true);
    
    // 檢查 API 回傳結構 (Dictionary API 第一層必定是陣列)
    if (!is_array($eng_data) || empty($eng_data) || !isset($eng_data[0])) {
        echo "FAILED (Invalid JSON structure).\n";
        continue;
    }

    $main_data = $eng_data[0]; 
    $need_update = false;

    // 1. 提取正確音標
    $fetched_phonetic = $main_data['phonetic'] ?? '';
    if (empty($fetched_phonetic) && !empty($main_data['phonetics'])) {
        foreach ($main_data['phonetics'] as $p) {
            if (!empty($p['text'])) {
                $fetched_phonetic = $p['text'];
                break;
            }
        }
    }
    if (!empty($fetched_phonetic) && empty($word_data['phonetic'])) {
        $word_data['phonetic'] = $fetched_phonetic;
        $need_update = true;
    }

    // 2. 匹配詞性提取定義
    $part_of_speech_map = [
        1 => "noun", 2 => "verb", 3 => "adjective", 
        4 => "adverb", 5 => "preposition", 6 => "conjunction"
    ];
    $target_part_of_speech = $part_of_speech_map[$word_data['part_of_speech']] ?? "";

    if (!empty($target_part_of_speech) && isset($main_data['meanings'])) {
        foreach ($main_data['meanings'] as $meaning) {
            if (strtolower($meaning['partOfSpeech']) === $target_part_of_speech) {
                if (!empty($meaning['definitions'][0]['definition']) && empty($word_data['definition'])) {
                    $word_data['definition'] = $meaning['definitions'][0]['definition'];
                    $need_update = true;
                    break;
                }
            }
        }
    }

    // 3. 若詞性不匹配，保底使用第一個定義
    if (empty($word_data['definition']) && !empty($main_data['meanings'][0]['definitions'][0]['definition'])) {
        $word_data['definition'] = $main_data['meanings'][0]['definitions'][0]['definition'];
        $need_update = true;
    }

    // 4. 提取發音音訊檔
    if (!empty($main_data['phonetics'])) {
        foreach ($main_data['phonetics'] as $p) {
            if (!empty($p['audio']) && empty($word_data['audio_url'])) {
                $word_data['audio_url'] = $p['audio'];
                $need_update = true;
                break;
            }
        }
    }

    // 【100% 安全更新】：完全不包含 translation 欄位，中文絕對安全
    if ($need_update === true) {
        $update_sql = "UPDATE `words` SET `phonetic` = ?, `definition` = ?, `audio_url` = ? WHERE `id` = ?;";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            $word_data['phonetic'] ?? "",
            $word_data['definition'] ?? "",
            $word_data['audio_url'] ?? null,
            $word_data['id']
        ]);
        echo "SUCCESS (Database updated).\n";
        $success_count++;
    } else {
        echo "SKIPPED (No new data field was updated).\n";
    }
}

echo "\n==================================================\n";
echo "Batch Completed! Successfully updated {$success_count} words.\n";
echo "==================================================\n";

/**
 * 使用內建 Stream 機制獲取網頁（完全不經過 cURL 指紋驗證）
 */
function fetchJsonWithStream($url) {
    // 建立極擬真的瀏覽器上下文標頭
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" .
                        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36\r\n" .
                        "Accept-Language: zh-TW,zh;q=0.9,en-US;q=0.8\r\n" .
                        "Connection: close\r\n",
            'timeout' => 8,
            'ignore_errors' => true // 允許讀取非 200 的伺服器錯誤回傳
        ],
        'ssl' => [
            'verify_peer' => false, // 忽略 Windows 本地端憑證缺失問題
            'verify_peer_name' => false
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    // 檢查 HTTP 回傳狀態碼是否為 200
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                // 如果回傳包含 200 OK 以外的錯誤碼則放棄
                if (strpos($header, '200') === false) {
                    return null;
                }
                break;
            }
        }
    }
    
    return $response !== false ? $response : null;
}