<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

try{
    $config = require 'db_config.php';
    $dsn = "mysql:host={$config['host']}; charset=utf8; dbname={$config['dbname']};";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}catch(Exception $exception){
    echo json_encode([
        "status" => "error", 
        "message" => $exception->getMessage()
        ]);
    exit;
}

if(isset($_GET['action']) && $_GET['action'] == 'draw'){
    header('Content-Type: application/json; charset=utf8;');

    if(!isset($_SESSION['word_queue']) || empty($_SESSION['word_queue'])){
        if(isset($_POST['learner'])){
            $sql = "SELECT `word_id` FROM `learning_records` WHERE `learner_id`= ? AND `is_learned`='0' ORDER BY RAND();";
            $statement = $pdo->prepare($sql);
            $statement->execute([$_POST['learner']]);
            $_SESSION['word_queue'] = $statement->fetchAll(PDO::FETCH_COLUMN);
        }else {
            $sql = "SELECT `id` FROM `words` ORDER BY RAND()";
            $_SESSION['word_queue'] = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        }
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

        if(empty($word_data['translation'])){
            $tw_url = "http://dict.e.opac.vip/dict.php?sw=" . $word;
            $options = array('http' => array('timeout' => 3, 'user_agent' => 'Mozilla/5.0'));
            $context = stream_context_create($options);
            $tw_response = @file_get_contents($tw_url, false, $context);
            
            if($tw_response !== false){
                $tw_data = json_decode($tw_response, true);

                if(isset($tw_data['translation'])){
                    $raw_translation = $tw_data['translation'];

                    $part_of_speech_map_tw = [
                        1 => "n", 
                        2 => "v", 
                        3 => "adj", 
                        4 => "adv", 
                        5 => "prep", 
                        6 => "conj", 
                        7 => "prop"
                    ];

                    $part_of_speech_tag = isset($part_of_speech_map_tw[$word_data['part_of_speech']]) ? $part_of_speech_map_tw[$word_data['part_of_speech']] : "";
                    $filtered_translation = "";

                    if(!empty($part_of_speech_tag)){
                        $pattern = "/(" . $part_of_speech_tag . "\.)(.*?)(?=[a-zA-Z]+\.|\n|$)/";

                        if(preg_match($pattern, $raw_translation, $matches)){
                            $filtered_translation = trim($matches[2]);
                            $filtered_translation = trim(str_replace("\n", "", $filtered_translation));
                        }
                    }

                    if(empty($filtered_translation)){
                        $filtered_translation = str_replace("\n", "; ", $raw_translation);
                    }
                    
                    $word_data['translation'] = $filtered_translation;
                    $need_update = true;
                }
            }
        }

        if($need_update === true){
            $sql = "UPDATE `words` SET `phonetic` = ?, `definition` = ?, `translation` = ?, `audio_url` = ? WHERE `id` = ?;";
            $statement = $pdo->prepare($sql);
            $statement->execute([
                $word_data['phonetic'] ? $word_data['phonetic'] : "", 
                $word_data['definition'] ? $word_data['definition'] : "", 
                $word_data['translation'] ? $word_data['translation'] : "", 
                $word_data['audio_url'] ? $word_data['audio_url'] : "", 
                $word_data['id']
                ]);
        }

        if(!isset($word_data['audio_url'])){
            $word_data['audio_url'] = "https://dictionaryapi.dev" . $word;
        }

        echo json_encode([
            "status" => "success", 
            "word" => $word_data['word'], 
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
        justify-content: flex-start;
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

    button {
        position: absolute;
        bottom: 40px;
        width: 400px;
        height: 100px;
        border: 1px solid black;
        border-radius: 40px;
    }

    button#audio {
        width: 200px;
        height: 50px;
    }

    .word {
        top: 40px;
    }

    .phonetic {
        top: 100px;
    }

    .definition {
        height: 40%;
        text-align: center;
        font-size: 0.6em;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .translation {
        height: 40%;
        text-align: center;
        font-size: 0.8em;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<div class="container">
    <div class="card-board" id="card-board" onclick="flipCard(event)">
        <div class="card" id="card">
            <div class="face front">
                <p class="word">word</p>
                <p class="part-of-speech">part of speech</p>
                <p class="phonetic">phonetic</p>
                <button class="" id=audio onclick="playAudio()"></button>
            </div>
            <div class="face back">
                <p class="definition">Lorem ipsum dolor, sit amet consectetur adipisicing elit. Cum dolor facere perspiciatis beatae, veritatis soluta temporibus, autem quibusdam nostrum velit laboriosam praesentium possimus voluptas vero, aliquam exercitationem ipsam tempora deleniti.</p>
                <p class="translation">Lorem ipsum dolor sit amet consectetur, adipisicing elit. Nulla tempora doloribus delectus nihil nesciunt sequi veniam obcaecati numquam sed voluptatum neque hic, accusamus nobis tenetur aut expedita culpa amet quia?</p>
            </div>
        </div>
    </div>

    <div>
        <button onclick="drawCard()"></button>
        <button onclick="random()"></button>
    </div>
</div>

<script>
    const card = document.getElementById('card');

    let degree = 0;

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
    }

    card.addEventListener('transitionend', () => {
        card.style.transition = "none";

        degree %= 360;
        card.style.transform = `rotateY(${degree}deg)`;
    })

    function drawCard(){
        card.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";

        degree = 0;
        card.style.transform = `rotateY(${degree}deg)`;

        fetch('?action=draw').then(response => response.json()).then(data => {
            if(data.status === 'success'){
                document.getElementById('text-front').innerText = data.word;
                document.getElementById('text-phonetic').innerText = data.phonetic;
                document.getElementById('text-definition').innerText = data.definition;

                currentAudioUrl = data.audio;
            }else {
                document.getElementById('text-front').innerText = "draw fail.";
            }
        }).catch(error => {
            console.error("error", error);
            document.getElementById('text-front').innerText = "wrong connection";
        });
    }

    function playAudio(event){
        event.stopPropagation();
        if(cuurentAudioUrl){
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
    }
</script>