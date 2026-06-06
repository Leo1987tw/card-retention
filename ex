<?php
// === 1. PHP 後端邏輯處理 ===
// 檢查這是否為前端發出的抽牌請求
if (isset($_GET['action']) && $_GET['action'] === 'draw') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        // 修正：移除 mysql: 後的空格，並修正資料庫拼字為 vocabulary
        $dsn = "mysql:host=localhost;charset=utf8;dbname=vocabulary";
        $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // 隨機從資料庫抽出一個單字 (可以根據需求改成按順序)
        $stmt = $pdo->query("SELECT * FROM words ORDER BY RAND() LIMIT 1");
        $word_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$word_data) {
            echo json_encode(["status" => "error", "message" => "資料庫沒有單字"]);
            exit;
        }

        // 如果音標或定義是空的，立刻向網路 API 抓取補齊
        if (empty($word_data['phonetic']) || empty($word_data['definition'])) {
            $api_url = "https://dictionaryapi.dev" . urlencode($word_data['word']);
            $options = array('http' => array('timeout' => 3));
            $context = stream_context_create($options);
            $api_response = @file_get_contents($api_url, false, $context);

            if ($api_response !== false) {
                $api_data = json_decode($api_response, true);
                
                // 解析音標與定義
                $fetched_phonetic = isset($api_data[0]['phonetic']) ? $api_data[0]['phonetic'] : '';
                $fetched_definition = '';
                if (!empty($api_data[0]['meanings'][0]['definitions'][0]['definition'])) {
                    $fetched_definition = $api_data[0]['meanings'][0]['definitions'][0]['definition'];
                }
                
                // 解析發音音檔網址
                $fetched_audio = '';
                if (!empty($api_data[0]['phonetics'])) {
                    foreach ($api_data[0]['phonetics'] as $p) {
                        if (!empty($p['audio'])) {
                            $fetched_audio = $p['audio'];
                            break;
                        }
                    }
                }

                // 寫回資料庫補齊 (永久儲存)
                $update_stmt = $pdo->prepare("UPDATE words SET phonetic = ?, definition = ? WHERE id = ?");
                $update_stmt->execute([$fetched_phonetic, $fetched_definition, $word_data['id']]);

                $word_data['phonetic'] = $fetched_phonetic;
                $word_data['definition'] = $fetched_definition;
                $word_data['audio_url'] = $fetched_audio;
            }
        } else {
            // 如果原本就有定義，動態產生發音 API 連結
            $word_data['audio_url'] = "https://dictionaryapi.dev" . urlencode($word_data['word']);
        }

        // 回傳給前端
        echo json_encode([
            "status" => "success",
            "word" => $word_data['word'],
            "phonetic" => $word_data['phonetic'] ? $word_data['phonetic'] : "/暫無音標/",
            "definition" => $word_data['definition'] ? $word_data['definition'] : "No definition found.",
            "audio" => isset($word_data['audio_url']) ? $word_data['audio_url'] : ""
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit; // 結束 API 請求，不渲染下方的 HTML
}
?>

<!-- === 2. HTML / CSS 前端介面 === -->
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>大考 7000 單字卡</title>
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
            perspective: 800px; /* 增加透視度，翻轉效果更立體 */
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
            border: 1px solid #ccc;
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
            border-radius: 38px;
            backface-visibility: hidden;
            text-align: center;
        }

        .front {
            background-color: #ff8fa3;
            font-size: 3rem;
            font-weight: bold;
        }

        .back {
            background-color: #4a90e2;
            font-size: 1.2rem; /* 背面要放定義，字體調小避免溢出 */
            transform: rotateY(180deg);
            justify-content: space-evenly; /* 均勻分配空間 */
        }

        .phonetic {
            font-size: 1.5rem;
            color: #e0e0e0;
        }

        .definition {
            font-style: italic;
            max-height: 150px;
            overflow-y: auto; /* 若定義太長可滾動 */
        }

        .audio-btn {
            background: white;
            color: #4a90e2;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 1rem;
            cursor: pointer;
            font-weight: bold;
        }

        .draw-btn {
            width: 400px;
            height: 60px;
            border: 2px solid #333;
            border-radius: 40px;
            background-color: #333;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: 0.2s;
        }
        .draw-btn:hover {
            background-color: #555;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- 修正：Id 拼字修正 -->
    <div class="card-board" id="card-board" onclick="flipCard(event)">
        <div class="card" id="card">
            <!-- 正面顯示單字 -->
            <div class="face front" id="text-front">點擊下方抽牌</div>
            <!-- 背面顯示詳細資料 -->
            <div class="face back">
                <div class="phonetic" id="text-phonetic">/音標/</div>
                <div class="definition" id="text-definition">英英定義</div>
                <button class="audio-btn" id="audio-btn" onclick="playAudio(event)">🔊 發音</button>
            </div>
        </div>
    </div>

    <button class="draw-btn" onclick="drawCard()">🃏 隨機抽單字</button>
</div>

<!-- === 3. JavaScript 前端控制 === -->
<script>
    const card = document.getElementById('card');
    let degree = 0;
    let currentAudioUrl = ""; // 儲存目前單字的音檔網址

    // 翻牌邏輯
    function flipCard(event){
        card.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";
        const rectangle = card.getBoundingClientRect();
        const clickPosition = event.clientX - rectangle.left - rectangle.width / 2;

        if(clickPosition >= 0){
            degree += 180;
        } else {
            degree -= 180;
        }
        card.style.transform = `rotateY(${degree}deg)`;
    }

    card.addEventListener('transitionend', () => {
        card.style.transition = "none";
        degree %= 360;
        card.style.transform = `rotateY(${degree}deg)`;
    });

    // 獨立出來的發音按鈕事件，防止冒泡觸發翻牌
    function playAudio(event) {
        event.stopPropagation(); // 阻止點擊按鈕時卡片又翻轉
        if (currentAudioUrl) {
            // 如果從 API 拿到的是真實 mp3
            if(currentAudioUrl.includes('.mp3')) {
                const audio = new Audio(currentAudioUrl);
                audio.play();
            } else {
                // 否則退回到線上查詢
                fetch(currentAudioUrl)
                    .then(res => res.json())
                    .then(data => {
                        if(data[0]?.phonetics?.[0]?.audio) {
                            new Audio(data[0].phonetics[0].audio).play();
                        } else {
                            alert("暫無此單字語音檔案");
                        }
                    });
            }
        }
    }

    // 抽牌邏輯：透過 Fetch 向本支 PHP 檔案索取 JSON 資料
    function drawCard(){
        // 1. 先將卡片平滑翻回正面 (角度歸零)
        card.style.transition = "transform 0.4s ease";
        degree = 0;
        card.style.transform = `rotateY(${degree}deg)`;

        // 2. 向後端索取新單字
        fetch('?action=draw')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // 3. 把新單字寫入網頁
                    document.getElementById('text-front').innerText = data.word;
                    document.getElementById('text-phonetic').innerText = data.phonetic;
                    document.getElementById('text-definition').innerText = data.definition;
                    
                    // 4. 暫存發音網址
                    currentAudioUrl = data.audio;
                } else {
                    document.getElementById('text-front').innerText = "抽牌失敗";
                }
            })
            .catch(err => {
                console.error("發生錯誤:", err);
                document.getElementById('text-front').innerText = "連線錯誤";
            });
    }
</script>

</body>
</html>
