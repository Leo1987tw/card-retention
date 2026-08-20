<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $config = require __DIR__ . "/../db_config/vocabulary/db_config.php";
    $dsn = "{$config['driver']}:host={$config['host']}; dbname={$config['database']}";

    if ($config['driver'] == "mysql") {
        $dsn .= "; charset=utf8";
    }

    $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $exception) {
    echo json_encode([
        "status" => "error",
        "message" => $exception->getMessage()
    ]);
    exit;
}

$table = isset($_GET['set']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['set']) : 'words';

// 洗牌
if (isset($_GET['action']) && $_GET['action'] == 'shuffle') {
    if (isset($_SESSION['username'])) {
        $sql = "SELECT `id` FROM `$table` WHERE `id` NOT IN (SELECT `word_id` FROM `learning_record` WHERE `learner_id`= ? AND `is_learned`='1');";
        $statement = $pdo->prepare($sql);
        $statement->execute([$_SESSION['username']]);
        $queue = $statement->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $sql = "SELECT `id` FROM `$table`";
        $queue = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    shuffle($queue);
    $_SESSION['word_queue'] = $queue;
}

// 抽牌
if ((isset($_GET['action']) && $_GET['action'] == 'draw') || (isset($_GET['action']) && $_GET['action'] == 'shuffle')) {
    header('Content-Type: application/json; charset=utf8;');

    // 當牌堆沒牌時進行洗牌
    if (!isset($_SESSION['word_queue'])) {
        if (isset($_SESSION['username'])) {
            $sql = "SELECT `id` FROM `$table` WHERE `id` NOT IN (SELECT `word_id` FROM `learning_record` WHERE `learner_id`= ? AND `is_learned`='1');";
            $statement = $pdo->prepare($sql);
            $statement->execute([$_SESSION['username']]);
            $queue = $statement->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $sql = "SELECT `id` FROM `$table`";
            $queue = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        }

        shuffle($queue);
        $_SESSION['word_queue'] = $queue;
    }

    if (empty($_SESSION['word_queue'])) {
        echo json_encode(["status" => "empty", "message" => "好棒棒，你把單字都學完了！"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $draw_word_id = array_shift($_SESSION['word_queue']);

    $sql = "SELECT * FROM `$table` WHERE `id`= ?";
    $statement = $pdo->prepare($sql);
    $statement->execute([$draw_word_id]);
    $word_data = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$word_data) {
        echo json_encode(["status" => "error", "message" => "資料庫裡沒有這個單字"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $word = urlencode($word_data['word']);
    $need_update = false;

    if (empty($word_data['phonetic']) || empty($word_data['definition'])) {
        $eng_url = "https://api.dictionaryapi.dev/api/v2/entries/en/" . $word;
        $options = array('http' => array('timeout' => 3, 'user_agent' => 'Mozilla/5.0'));
        $context = stream_context_create($options);
        $eng_response = @file_get_contents($eng_url, false, $context);

        if ($eng_response !== false) {
            $eng_data = json_decode($eng_response, true);
            $part_of_speech_map = [
                1 => "noun",
                2 => "verb",
                3 => "adjective",
                4 => "adverb",
                5 => "preposition",
                6 => "conjunction"
            ];
            $target_part_of_speech = isset($part_of_speech_map[$word_data['part_of_speech']]) ? $part_of_speech_map[$word_data['part_of_speech']] : "";

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

            if (empty($word_data['definition']) && !empty($eng_data[0]['meanings'][0]['definitions'][0]['definition'])) {
                $word_data['definition'] = $eng_data[0]['meanings'][0]['definitions'][0]['definition'];
                $word_data['phonetic'] = isset($eng_data[0]['phonetic']) ? $eng_data[0]['phonetic'] : '';
            }

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
    }

    if ($need_update === true) {
        $sql = "UPDATE `words` SET `phonetic` = ?, `definition` = ?, `translation` = ?, `audio_url` = ? WHERE `id` = ?;";
        $statement = $pdo->prepare($sql);
        $statement->execute([

            $word_data['phonetic'] ?? "",
            $word_data['definition'] ?? "",
            $word_data['translation'] ?? "",
            $word_data['audio_url'] ?? "",
            $word_data['id']
        ]);
    }

    if (!isset($word_data['audio_url'])) {
        $word_data['audio_url'] = "https://dictionaryapi.dev/" . $word;
    }

    $part_of_speech_map = [
        1 => 'noun',
        2 => 'verb',
        3 => 'adjective',
        4 => 'adverb',
        5 => 'preposition',
        6 => 'conjunction',
        7 => 'proper noun'
    ];

    echo json_encode([
        "status" => "success",
        "word" => $word_data['word'],
        "part_of_speech" => isset($word_data['part_of_speech']) ? $part_of_speech_map[$word_data['part_of_speech']] : "",
        "phonetic" => $word_data['phonetic'] ? $word_data['phonetic'] : "",
        "definition" => $word_data['definition'] ? $word_data['definition'] : "",
        "translation" => $word_data['translation'] ? $word_data['translation'] : "",
        "audio" => $word_data['audio_url'] ? $word_data['audio_url'] : ""
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// 確認已經學習過的牌卡
if(isset($_GET['action']) && $_GET['action'] == 'learned'){
    header('Content-Type: application/json; charset=utf8;');

    if(!isset($_SESSION['username'])){
        echo json_encode(['status' => 'error', 'message' => 'please login first']);
        exit;
    }

    $word_id = intval($_GET['id'] ?? 0);
    if($word_id <= 0){
        echo json_encode(['status' => 'error', 'message' => 'fail word id']);
        exit;
    }

    $sql = "INSERT INTO `learning_record`(`learner_id`, `word_id`, `is_learned`) VALUES (?, ?, '1') ON DUPLICATE KEY UPDATE `is_learned` = '1';";

    $statement = $pdo->prepare($sql);
    $success = $statement->execute([$_SESSION['username'], $word_id]);

    if($success){
        if(isset($_SESSION['word_queue']) && is_array($_SESSION['word_queue'])){
            $_SESSION['word_queue'] = array_values(array_diff($_SESSION['word_queue'], [$word_id]));
        }

        echo json_encode(['status' => 'success', 'message' => 'you have learned this word.']);
    }else {
        echo json_encode(['status' => 'error', 'message' => 'learning_record update fail.']);
    }

    exit;
}

// 取消已經學習過的牌卡
if(isset($_GET['action']) && $_GET['action'] == 'forgot'){
    header('Content-Type: application/json; charset=utf8;');

    if(!isset($_SESSION['username'])){
        echo json_encode(['status' => 'error', 'message' => 'please login first']);
        exit;
    }

    $word_id = intval($_GET['id'] ?? 0);
    if($word_id <= 0){
        echo json_encode(['status' => 'error', 'message' => 'fail word id']);
        exit;
    }

    $sql = "SELECT `id` FROM `learning_record` WHERE `learner_id`= ? AND `word_id`= ?;";
    $statement = $pdo->prepare($sql);
    $statement->execute([$_SESSION['username'], $word_id]);
    $has_record = $statement->fetch();

    if($has_record){
        $sql = "UPDATE `learning_record` SET `is_learned`='0' WHERE `learner_id`= ? AND `word_id`= ?;";

        $statement = $pdo->prepare($sql);
        $success = $statement->execute([$_SESSION['username'], $word_id]);
    }else {
        $sql = "INSERT INTO `learning_record`(`learner_id`, `word_id`, `is_learned`) VALUES (?, ?, '0');";

        $statement = $pdo->prepare($sql);
        $success = $statement->execute([$_SESSION['username'], $word_id]);
    }
    
    if($success){
        if(isset($_SESSION['word_queue']) && is_array($_SESSION['word_queue'])){
            if(!in_array($word_id, $_SESSION['word_queue'])){
                $_SESSION['word_queue'][] = $word_id;
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'you have forgetten this word.']);
    }else {
        echo json_encode(['status' => 'error', 'message' => 'learning_record update fail.']);
    }

    exit;
}