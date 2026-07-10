<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

try{
    $config = require_once 'db_config.php';
    $dsn = "mysql:host={$config['host']}; charset=utf8; dbname={$config['dbname']};";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}catch(Exception $exception){
    echo json_encode([
        "status" => "error", 
        "message" => $exception->getMessage()
        ]);
    exit;
}

if(isset($_GET['action']) && $_GET['action'] == 'shuffle'){
    if(isset($_POST['learner'])){
        $sql = "SELECT `word_id` FROM `learning_records` WHERE `learner_id`= ? AND `is_learned`='0';";
        $statement = $pdo->prepare($sql);
        $statement->execute([$_POST['learner']]);
        $queue = $statement->fetchAll(PDO::FETCH_COLUMN);
    }else {
        $sql = "SELECT `id` FROM `words`";
        $queue = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    shuffle($queue);
    $_SESSION['word_queue'] = $queue;
}

if((isset($_GET['action']) && $_GET['action'] == 'draw') || (isset($_GET['action']) && $_GET['action'] == 'shuffle')){
    header('Content-Type: application/json; charset=utf8;');

    if(!isset($_SESSION['word_queue']) || empty($_SESSION['word_queue'])){
        if(isset($_POST['learner'])){
            $sql = "SELECT `word_id` FROM `learning_records` WHERE `learner_id`= ? AND `is_learned`='0';";
            $statement = $pdo->prepare($sql);
            $statement->execute([$_POST['learner']]);
            $queue = $statement->fetchAll(PDO::FETCH_COLUMN);
        }else {
            $sql = "SELECT `id` FROM `words`";
            $queue = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        }

        shuffle($queue);
        $_SESSION['word_queue'] = $queue;
    }

    if(empty($_SESSION['word_queue'])){
        echo json_encode(["status" => "empty", "message" => "好棒棒，你把單字都學完了！"], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $draw_word_id = array_shift($_SESSION['word_queue']);

    $sql = "SELECT * FROM `words` WHERE `id`= ?";
    $statement = $pdo->prepare($sql);
    $statement->execute([$draw_word_id]);
    $word_data = $statement->fetch(PDO::FETCH_ASSOC);
    
    if(!$word_data){
        echo json_encode(["status" => "error", "message" => "資料庫裡沒有這個單字"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $word = urlencode($word_data['word']);
    $need_update = false;

    if(empty($word_data['phonetic']) || empty($word_data['definition'])){
        $eng_url = "https://api.dictionaryapi.dev/api/v2/entries/en/" . $word;
        $options = array('http' => array('timeout' => 3, 'user_agent' => 'Mozilla/5.0'));
        $context = stream_context_create($options);
        $eng_response = @file_get_contents($eng_url, false, $context);

        if($eng_response !== false){
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

            if(!empty($target_part_of_speech) && isset($eng_data[0]['meanings'])){
                foreach($eng_data[0]['meanings'] as $meaning){
                    if(strtolower($meaning['partOfSpeech']) === $target_part_of_speech){
                        if(!empty($meaning['definitions'][0]['definition'])){
                            $word_data['definition'] = $meaning['definitions'][0]['definition'];
                            $word_data['phonetic'] = isset($eng_data[0]['phonetic']) ? $eng_data[0]['phonetic'] : '';
                            break;
                        }
                    }
                }
            }
                
            if(empty($word_data['definition']) && !empty($eng_data[0]['meanings'][0]['definitions'][0]['definition'])){
                $word_data['definition'] = $eng_data[0]['meanings'][0]['definitions'][0]['definition'];
                $word_data['phonetic'] = isset($eng_data[0]['phonetic']) ? $eng_data[0]['phonetic'] : '';
            }

            if(!empty($eng_data[0]['phonetics'])){
                foreach($eng_data[0]['phonetics'] as $p){
                    if(!empty($p['audio'])){
                        $word_data['audio_url'] = $p['audio'];
                        break;
                    }
                }
            }
            $need_update = true;
        }
    }

    if($need_update === true){
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

    if(!isset($word_data['audio_url'])){
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

?>