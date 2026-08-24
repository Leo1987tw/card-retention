<?php
// 1. 獲取當前功能排堆 do (例如：card_board)
$currentDo = isset($_GET['do']) ? $_GET['do'] : 'card_board';

// 轉換為您的日常進度快取 sets 內對應的鍵名 (例如 card_board 對應 words)
$setKey = ($currentDo === 'card_board') ? 'words' : $currentDo;

// 2. 核心時間防線：從 Session 中抓取當天的完工標記
$php_is_finished = false;
if (isset($_SESSION['daily_progress']['sets'][$setKey]['is_finished'])) {
    $php_is_finished = (bool)$_SESSION['daily_progress']['sets'][$setKey]['is_finished'];
}
?>

<!-- <a href="./batch_fetch.php" style="position: fixed; right: 0; bottom: 10px; width: 240px; height: 120px; font-size: 3rem;">prefetch</a> -->

<div class="container">
    <div class="card-board" id="card-board" onclick="flipCard(event)">
        <?php if ($php_is_finished): ?>
            <!-- 【完工防線】如果今天任務已經結束，登入或重新整理一進來第一眼直接看見這個畫面 -->
            <div class="finished-box" style="text-align: center; padding: 40px 20px;">
                <h1 style="font-size: 4rem; margin-bottom: 20px;">🎉</h1>
                <h2 style="color: #2a9d8f; margin-bottom: 15px;">您今天的任務結束！</h2>
                <p style="color: #666; font-size: 1.1rem; line-height: 1.6;">
                    今日目標已全數達成。<br>請利用下方按鈕進入字庫強化模式！
                </p>
            </div>
        <?php else: ?>
            <!-- 【正常學習】呈現標準的單字 3D 卡牌結構 -->
            <div class="card" id="card">
                <div class="face front">
                    <div class="new">NEW</div>
                    <div class="learning-statement">
                        <span class="learning-level">
                            記憶等級 LV <span id="current-level">--</span>
                        </span>
                        <span class="preview-count">
                            此卡牌出現過 <span id="current-preview-count">--</span> 次
                        </span>
                    </div>
                    <p class="word" id="word">載入中...</p>
                    <div style="position: absolute; bottom: 80px; display: flex; justify-content: center; align-items: center;">
                        <p class="category1" id="part-of-speech">--</p>
                        <p class="category2" id="phonetic">--</p>
                        <button class="audio" id="audio" onclick="pronounce(event)">發音</button>
                    </div>
                </div>
                <div class="face back">
                    <p class="translation" id="translation">中文翻譯</p>
                    <p class="definition" id="definition">english definition</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="true-false">
        <button onclick="nextCard(true, event)">答對了</button>
        <button onclick="nextCard(false, event)">答錯了</button>
    </div>
</div>

<script>
    // 【核心同步】使用 PHP 直接將當日完工真理印給 JavaScript 全域變數
    // 這樣 script.js 一載入就能立刻依據此變數決定按鈕外觀，達到零延遲無縫渲染
    let isTaskFinished = <?php echo $php_is_finished ? 'true' : 'false'; ?>;
</script>