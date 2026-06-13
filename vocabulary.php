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

<style>
    * {
        box-sizing: border-box;
        margin: 0px;
        padding: 0px;
    }

    .container {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        margin: 20px auto;
        padding: 20px;
    }

    .card-board {
        perspective: 400px;
        width: 400px;
        height: 400px;
        margin: 30px;
        cursor: pointer;
    }

    .card {
        position: relative;
        width: 400px;
        height: 400px;
        box-shadow: 0px 10px 25px -5px rgba(0, 0, 0, 0.1), 0px 8px 10px -6px rgba(0, 0, 0, 0.1);
        transform-style: preserve-3d;
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid black;
        border-radius: 40px;
    }

    .face {
        position: absolute;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: 100%;
        height: 100%;
        padding: 24px;
        color: white;
        font-size: 3rem;
        border-radius: 40px;
        backface-visibility: hidden;
    }

    .front {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background-color: pink;
    }
    
    .back {
        display: flex;
        flex-direction: column;
        justify-content: space-around;
        align-items: center;
        background-color: blue;
        transform: rotateY(180deg);
    }

    .button {
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        top: 20px;
        width: 400px;
        height: 100px;
        border: 1px solid black;
        border-radius: 40px;
    }

    button {
        cursor: pointer;
    }

    .button>button {
        width: 150px;
        height: 50px;
        margin: 10px;
        font-size: 1.6rem;
        border: 1px solid black;
        border-radius: 40px;
    }

    button#audio {
        width: 40px;
        height: 40px;
        margin: 10px;
        vertical-align: middle;
        font-size: 1rem;
        border: 1px solid black;
        border-radius: 10px;
    }

    .word {
        position: relative;
        bottom: 40px;
        font-size: 4rem;
    }

    .part-of-speech {
        margin: 20px;
        font-size: 1.6rem;
    }

    .phonetic {
        font-size: 1.6rem;
        font-family: "Lucida Sans Unicode", "Arial Unicode MS", "Segoe UI", sans-serif;
    }

    .definition {
        height: 40%;
        text-align: justify;
        font-size: 1.4rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .translation {
        display: block;
        text-align: justify;
        font-size: 1.4rem;
    }
</style>

<!-- <button onclick="myFetch()">automatically fetch vocabulary</button> -->

<div class="container">
    <div class="card-board" id="card-board" onclick="flipCard(event)">
        <div class="card" id="card">
            <div class="face front">
                <p class="word" id="word">word</p>
                <div style="position: absolute; bottom: 80px; display: flex; justify-content: center; align-items :center;">
                    <p class="part-of-speech" id="part-of-speech">part of speech</p>
                    <p class="phonetic" id="phonetic">phonetic</p>
                    <button class="audio" id="audio" onclick="playAudio(event)">發音</button>
                </div>
            </div>
            <div class="face back">
                <p class="translation" id="translation">中文翻譯</p>
                <p class="definition" id="definition">english definition</p>
            </div>
        </div>
    </div>

    <div class="button">
        <button onclick="shuffleCard()">洗牌</button>
        <button id="drawCard" onclick="drawCard()">抽牌</button>
    </div>
</div>

<script>
    const card = document.getElementById('card');

    let degree = 0;
    let currentAudioUrl = "";

    function flipCard(event){
        card.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";

        const rectangle = card.getBoundingClientRect();
        const clickPosition = event.clientX - rectangle.left - rectangle.width / 2;

        if(clickPosition >= 0){
            degree += 180;
        }else {
            degree -= 180;
        }

        card.style.transform = `rotateY(${degree}deg)`;
    };

    card.addEventListener('transitionend', () => {
        card.style.transition = "none";

        degree %= 360;
        card.style.transform = `rotateY(${degree}deg)`;
    });

    function drawCard(){
        card.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";

        degree = 0;
        card.style.transform = `rotateY(${degree}deg)`;

        fetch('?action=draw').then(response => response.json()).then(data => {
            if(data.status === 'success'){
                document.getElementById('word').innerText = data.word;
                document.getElementById('part-of-speech').innerText = data.part_of_speech;
                document.getElementById('phonetic').innerText = data.phonetic;
                document.getElementById('definition').innerText = data.definition;
                document.getElementById('translation').innerText = data.translation;

                currentAudioUrl = data.audio;
            }else {
                document.getElementById('word').innerText = "draw fail.";
            }
        }).catch(error => {
            console.error("error", error);
            document.getElementById('word').innerText = "wrong connection";
        });
    };

    function shuffleCard(){
        card.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";

        degree = 0;
        card.style.transform = `rotateY(${degree}deg)`;

        fetch('?action=shuffle').then(response => response.json()).then(data => {
            if(data.status === 'success'){
                document.getElementById('word').innerText = data.word;
                document.getElementById('part-of-speech').innerText = data.part_of_speech;
                document.getElementById('phonetic').innerText = data.phonetic;
                document.getElementById('definition').innerText = data.definition;
                document.getElementById('translation').innerText = data.translation;

                currentAudioUrl = data.audio;
            }else {
                document.getElementById('word').innerText = "draw fail.";
            }
        }).catch(error => {
            console.error("error", error);
            document.getElementById('word').innerText = "wrong connection";
        });
    };

    function playAudio(event){
        event.stopPropagation();
        if(currentAudioUrl){
            if(currentAudioUrl.includes('.mp3')){
                const audio = new Audio(currentAudioUrl);
                audio.play();
            }else {
                fetch(currentAudioUrl).then(response => response.json()).then(data => {
                    if(data[0]?.phonetics?.[0]?.audio){
                        new Audio(data[0].phonetics[0].audio).play();
                    }else {
                        alert("this word does not have phonetic data");
                    }
                });
            }
        }
    };

    // const randomSleep = () => {
    //     let ms = 1000 + 1000 * Math.random();
    //     return new Promise(resolve => setTimeout(resolve, ms));
    // };

    // async function myFetch(){
    //     let i;
    //     let drawCardvar = document.getElementById('drawCard');
    //     for(i = 0; i < 10000 ; i++){
    //         drawCardvar.click();
    //         await randomSleep();
    //     }
    // };
</script>