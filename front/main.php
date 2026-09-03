<?php
// 1. 獲取當前功能排堆 do (例如：card_board)
$currentDo = isset($_GET['do']) ? $_GET['do'] : 'card_board';

// 轉換為您的日常進度快取 sets 內對應的鍵名 (例如 card_board 對應 words)
$setKey = ($currentDo === 'card_board' || $currentDo === 'main') ? 'words' : $currentDo;

// 2. 核心時間防線：從 Session 中抓取當天的完工標記
$php_is_finished = false;
if (isset($_SESSION['daily_progress']['sets'][$setKey]['is_finished'])) {
    $php_is_finished = (bool)$_SESSION['daily_progress']['sets'][$setKey]['is_finished'];
}
?>

<div class="container main-layout">
    <!-- =========================================================================
       【左側/上方：核心遊戲互動區】
       ========================================================================= -->
    <div class="game-zone">
        <div class="card-board" id="card-board" onclick="flipCard(event)">
            <?php if ($php_is_finished): ?>
                <!-- 【完工防線】如果今天任務已經結束，登入或重新整理一進來第一眼直接看見這個畫面 -->
                <div class="finished-box">
                    <h1>🎉</h1>
                    <h2>您今天的任務結束！</h2>
                    <p>今日目標已全數達成。<br>請利用下方按鈕進入字庫強化模式！</p>
                </div>
            <?php else: ?>
                <!-- 【正常學習】呈現標準的單字 3D 卡牌結構 -->
                <div class="card" id="card">
                    <div class="face front">
                        <div class="new"></div>
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
                            <!-- 💡 欄位優化：正式更名為標準的 category1 與 category2 ID，實現資料盲填通道 -->
                            <p class="category1" id="category1">--</p>
                            <p class="category2" id="category2">--</p>
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
            <button onclick="drawCard('correct', event)">我認得</button>
            <button onclick="drawCard('wrong', event)">不認得</button>
        </div>
    </div>

    <!-- =========================================================================
       【右側/下方：遊戲規則說明與圖例面板】
       ========================================================================= -->
    <div class="rules-sidebar" style="float: right;">
        <h3>💡 核心學習規則</h3>
        
        <!-- 規則 1 -->
        <div class="rules-section">
            <h4>📈 1. 記憶等級與複習機制</h4>
            <p>本系統採用<strong>間隔記憶法（Spaced Repetition）</strong>：</p>
            <ul>
                <li><strong>點擊【我認得】</strong>：全新單字直升 LV 2；舊單字等級 <strong>+1</strong>（最高 LV 5）。等級越高，單字再次出現的間隔時間就越長。</li>
                <li><strong>點擊【不認得】</strong>：單字直接<strong>打回原形降至 LV 1</strong>，並排定於 <strong>1 天後</strong>強制重新複習。</li>
            </ul>
            <div class="level-badge-container">
                <span class="badge">LV 1: 1天</span>
                <span class="badge">LV 2: 3天</span>
                <span class="badge">LV 3: 7天</span>
                <span class="badge">LV 4: 14天</span>
                <span class="badge">LV 5: 30天</span>
            </div>
        </div>

        <!-- 規則 2 -->
        <div class="rules-section">
            <h4>🎯 2. 每日新字額度</h4>
            <p>系統根據您今日複習舊字的<strong>正確率</strong>，動態調整今日新字上限：</p>
            <ul>
                <li>正確率 ≥ 85% ➔ <strong>20 字</strong></li>
                <li>正確率 ≥ 70% ➔ <strong>15 字</strong></li>
                <li>正確率 ≥ 60% ➔ <strong>10 字</strong></li>
                <li>正確率 ≥ 50% ➔ <strong>5 字</strong></li>
                <li>正確率 &lt; 50% ➔ <strong>0 字</strong>（強制專心複習舊字，不發新字）</li>
            </ul>
        </div>

        <!-- 規則 3 -->
        <div class="rules-section">
            <h4>🏁 3. 今日任務完工</h4>
            <p>當<strong>「今日到期舊字清空」</strong>且<strong>「新字達上限」</strong>（或總刷題達 200 字）時，即觸發任務完工，下方按鈕將轉化為<strong>字庫強化練習模式</strong>（點擊不計分）。</p>
        </div>
    </div>
</div>

<script>
    // 【核心同步】使用 PHP 直接將當日完工真理印給 JavaScript 全域變數
    let isTaskFinished = <?php echo $php_is_finished ? 'true' : 'false'; ?>;
</script>