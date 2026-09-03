// ==================== 1. 全域變數宣告 ====================
let card = document.getElementById('card'); // 動態捕捉中央 3D 卡片 DOM 節點
let degree = 0;        // 記錄卡片目前的 3D 旋轉角度（0度正面、180度背面）
let currentId = -1;    // 儲存目前畫面上這張單字的資料庫流水號 (ID)，回報進度時必帶
let currentAudioUrl = ""; // 儲存當前單字的真人 MP3 發音網址

// 💡 頁面載入時自動初始化第一張卡片
window.onload = () => {
    // isTaskFinished 是由後端 PHP 渲染在 HTML 全域的變數，代表今天是否已完工
    if (typeof isTaskFinished !== 'undefined' && isTaskFinished) {
        // 情境 A：如果今天一登入就發現已經完工，立刻將按鈕渲染成特訓/盲刷樣式
        renderButtonFinishedStyle();
        // 完工狀態下同樣呼叫 null，代表「不要更新任何對錯進度，純粹抽第一張特訓字」
        drawCard(null, null);
    } else {
        // 情境 B：正常狀態下登入，同樣帶入 null，純粹「抽今天的第一張舊字庫卡片」
        drawCard(null, null);
    }
}

// ==================== 2. 核心 1：手動翻牌行為 (flipCard) ====================
function flipCard(event) {
    // 1. 防誤觸防線：如果點到的是發音按鈕、按鍵或輸入框，絕對禁止卡片翻轉
    if (event.target.closest('#audio') || event.target.tagName === 'BUTTON' || event.target.tagName === 'INPUT') {
        return;
    }

    // 2. 完工防線：如果完工祝賀畫面（finished-box）還在，阻擋翻牌並提示
    const finishedBox = document.querySelector('.finished-box');
    if (finishedBox) {
        alert("今日任務已達成！請點擊下方按鈕開始複習字庫。");
        return;
    }

    card = document.getElementById('card');
    if (!card) return;

    // 3. 智慧方向翻轉演算法
    card.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";
    const rectangle = card.getBoundingClientRect();
    const clickPosition = event.clientX - rectangle.left - rectangle.width / 2;

    // 點擊卡片右半邊 ➔ 向右正翻 (+180度)；點擊左半邊 ➔ 向左逆翻 (-180度)
    if (clickPosition >= 0) {
        degree += 180;
    } else {
        degree -= 180;
    }
    card.style.transform = `rotateY(${degree}deg)`;
}

// =========================================================================
// 💡 父監聽器防線：改綁在「永遠不會被摧毀」的父層容器 #card-board 上！
// =========================================================================
const cardBoardContainer = document.getElementById('card-board');
if (cardBoardContainer) {
    cardBoardContainer.addEventListener('transitionend', (event) => {
        // 確保引發動畫結束的是 #card 本身，而不是裡面的小元件
        if (event.target.id === 'card') {
            const currentCard = event.target;
            currentCard.style.transition = "none"; // 暫時關閉動畫，防止無限累加角度

            // 數學歸一：讓旋轉角度永遠保持在 0 ~ 360 度之間，防止數字過大造成翻轉卡頓
            degree %= 360;
            if (degree < 0) degree += 360;
            currentCard.style.transform = `rotateY(${degree}deg)`;
        }
    });
}

// ==================== 3. 核心 2：抽下一張牌 (drawCard) ====================
// 💡 裡外徹底一體化：action 可以傳 'correct'（記得）、'wrong'（不記得）或 null（純抽卡）
function drawCard(action, event) {
    if (event) event.stopPropagation(); // 防止點擊答題按鈕時順便把卡片翻面

    const urlParams = new URLSearchParams(window.location.search);
    const currentDo = urlParams.get('do') || 'main'; // 動態取得排堆主題

    let url = `./api/api.php?do=${encodeURIComponent(currentDo)}`;
    const fetchOptions = {};

    if (typeof isTaskFinished !== 'undefined' && isTaskFinished) {
        // 【完工特訓分支】
        if (action === 'correct') url += `&mode=pool_hard`;   // 點特訓生字
        else if (action === 'wrong') url += `&mode=pool_rand`;   // 點隨機盲刷
    } else {
        // 【一般學習分支】裡外完全一致，直接打包 &action=correct 或 wrong 傳給後端
        if (action !== null && currentId !== -1) {
            fetchOptions.method = 'POST';
            fetchOptions.headers = {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': window.csrfToken || ''
            };
            fetchOptions.body = new URLSearchParams({
                id: String(currentId),
                action
            });
        }
    }

    fetch(url, fetchOptions)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {

                // 情境：如果您「作答的當下」剛好清空舊字、學滿新字，觸發當下完工結算
                if (data.isFinished && (typeof isTaskFinished === 'undefined' || !isTaskFinished)) {
                    isTaskFinished = true;
                    renderButtonFinishedStyle(); // 下方按鈕立刻質變變色

                    // 中央卡片舞台直接抹除，替換為精美的完工結算畫面
                    document.getElementById('card-board').innerHTML = `
                        <div class="finished-box">
                            <h1 style="font-size: 4rem; margin-bottom: 20px;">🎉</h1>
                            <h2 style="color: #2a9d8f; margin-bottom: 15px;">您今天的任務結束！</h2>
                            <p style="color: #666; font-size: 1.1rem; line-height: 1.6;">
                                今日目標已全數達成。<br>請利用下方按鈕進入字庫強化模式！
                            </p>
                        </div>
                    `;
                    return;
                }

                // 情境：如果是完工特訓狀態下連續點擊，自動將「祝賀文字」還原回「3D卡片結構」
                restoreCardLayoutIfFinished();

                // 處理卡片翻轉回正與填入資料的時間差
                card = document.getElementById('card'); // 永遠撈取最新被生出來的卡片
                const isBack = (degree % 360 === 180);  // 檢查當前卡片是不是背面朝上

                if (isBack && card) {
                    // 如果在背面（中文朝上），先播旋轉動畫翻回正面（總耗時 0.4 秒）
                    card.style.transition = "transform 0.4s ease-out";
                    degree = 0;
                    card.style.transform = `rotateY(${degree}deg)`;

                    // 💡 關鍵時間差優化：等待 200 毫秒（卡片剛好轉到 90 度垂直、眼睛看不到字時）
                    // 默默在背景啟動 fillCardContent 把新文字塞進去，翻回正面時就是新單字，完美防止穿幫！
                    setTimeout(() => { fillCardContent(data); }, 200);
                } else {
                    // 如果本來就在正面，直接重新填入資料、無感知刷新
                    fillCardContent(data);
                }
            } else {
                const wordNode = document.getElementById('word');
                if (wordNode) wordNode.innerText = 'Fetch data fail.';
            }
        })
        .catch(error => console.error('Error: ', error));
}

// ==================== 4. 全功能輔助工具箱 ====================

/**
 * 輔助 1：當點擊特訓時，將「完工祝賀文字」無縫復原回「卡牌 3D 結構」
 */
function restoreCardLayoutIfFinished() {
    const cardBoard = document.getElementById('card-board');
    if (!cardBoard || document.getElementById('card')) return; // 如果已經是卡片結構，直接跳過

    // 重新用 innerHTML 把卡片結構長回來
    cardBoard.innerHTML = `
        <div class="card" id="card">
            <div class="face front">
                <!-- 💡 終極 Bug 修正（物理閹割法）：這裡徹底刪除原先寫死的 NEW 標籤 HTML！ -->
                <!-- 預設改放一個空容器，複習或特訓模式下這裡在物理上根本沒有 NEW，絕對 100% 拆除殘留！ -->
                <div class="new"></div> 
                <div class="learning-statement">
                    <span class="learning-level">記憶等級 LV <span id="current-level">--</span></span>
                    <span class="preview-count">此卡牌出現過 <span id="current-preview-count">--</span> 次</span>
                </div>
                <p class="word" id="word">載入中...</p>
                <div style="position: absolute; bottom: 80px; display: flex; justify-content: center; align-items: center;">
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
    `;
    card = document.getElementById('card'); // 重新將全域變數指派給最新長出來的節點
    cardBoard.onclick = (e) => { if (typeof flipCard === 'function') flipCard(e); }; // 補綁點擊翻翻事件
}

/**
 * 輔助 2：資料自動盲填填充功能 (fillCardContent)
 */
function fillCardContent(data) {
    currentId = data.id;
    currentAudioUrl = data.audio || ""; // 智慧通道：更換卡片時更新真人音檔網址

    // 建立一對一「乾淨映射表」：HTML ID 與後端 JSON 100% 同名，直接用迴圈盲填
    const elementMapping = {
        'word': data.word,
        'definition': data.definition,
        'translation': data.translation,
        'category1': data.category1,
        'category2': data.category2
    };

    // 安全遍歷填充所有欄位文字
    Object.keys(elementMapping).forEach(id => {
        const element = document.getElementById(id);
        if (element) element.innerText = elementMapping[id] || '';
    });

    // 渲染上方等級數據：生字特訓會如實亮出數據，隨機盲刷收到 null 則乾淨呈現 '--'
    const levelSpan = document.getElementById('current-level');
    if (levelSpan) levelSpan.innerText = (data.level !== null && data.level !== undefined) ? data.level : '--';

    // 渲染上方出現次數：生字特訓會如實亮出數據，隨機盲刷收到 null 則乾淨呈現 '--'
    const countSpan = document.getElementById('current-preview-count');
    if (countSpan) countSpan.innerText = (data.preview_count !== null && data.preview_count !== undefined) ? data.preview_count : '--';

    // =========================================================================
    // 💡 終極 Bug 修正（動態注入防線）：徹底根治 discreet 亮 NEW 的問題！
    // =========================================================================
    const container = document.querySelector('.new');
    if (container) {
        // 只有後端確認是新字，且今日任務尚未完工時才顯示標籤
        if ((typeof isTaskFinished === 'undefined' || !isTaskFinished) && 
            data.isNew === true) {
            
            container.innerText = 'NEW';
        } else {
            // 舊字複習、生字特訓 (pool_hard)、隨機盲刷 (pool_rand) 一律清空，物理上消滅 NEW！
            container.innerText = ''; 
        }
    }
}

/**
 * 輔助 3：今日完成後的按鈕樣式渲染與質變
 */
function renderButtonFinishedStyle() {
    const btnGroup = document.querySelectorAll('.true-false button');
    if (btnGroup.length >= 2) {
        btnGroup[0].innerText = "🎯 字庫生字特訓";
        btnGroup[0].style.backgroundColor = "#2a9d8f";
        btnGroup[0].style.color = "#ffffff";

        btnGroup[1].innerText = "🎲 字庫隨機盲刷";
        btnGroup[1].style.backgroundColor = "#e76f51";
        btnGroup[1].style.color = "#ffffff";
    }
}

/**
 * 輔助 4：語音發音 (pronounce) - 真人音檔優先與原生 TTS 機器音雙軌切換備援
 */
function pronounce(event) {
    if (event) event.stopPropagation(); // 防止點發音按鈕時不小心把卡片翻面
    const wordText = document.getElementById('word').innerText.trim();
    if (!wordText || ['Fetch data fail.', 'Connection fail.', '載入中...'].includes(wordText)) return;

    if (currentAudioUrl) {
        // 【軌道 A：資料庫裡有配真人音檔網址】
        let isTtsTriggered = false;
        const audio = new Audio(currentAudioUrl);

        // 建立一個 400 毫秒的緩衝防禦定時器
        const timeoutId = setTimeout(() => {
            if (!isTtsTriggered) {
                isTtsTriggered = true;
                audio.src = ''; audio.load(); // 立即掐斷原音檔的網路請求
                executeTTS(wordText); // 強制降級使用內建 TTS 發音
            }
        }, 400);

        // 監聽：如果在 400 毫秒內音檔能流暢播放，取消定時器，播放真人配音
        audio.addEventListener('canplaythrough', () => {
            clearTimeout(timeoutId);
            if (!isTtsTriggered) { audio.play().catch(err => { executeTTS(wordText); }); }
        }, { once: true });

        // 防禦：如果音檔元件回報檔案毀損 (HTTP 404 等)，直接秒切 TTS 備援
        audio.addEventListener('error', () => {
            clearTimeout(timeoutId);
            if (!isTtsTriggered) { isTtsTriggered = true; executeTTS(wordText); }
        }, { once: true });
    } else {
        // 【軌道 B：無音檔時，直接走 TTS 原生發音】
        executeTTS(wordText);
    }
}

/**
 * 輔助 5：原生網頁 TTS 發音引擎封裝
 */
function executeTTS(text) {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel(); // 💡 關鍵優化：連點發音時，立刻掐斷前一次聲音，防止語音堆疊嚴重延遲！
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-US';
        utterance.rate = 0.9; // 語速稍微放慢 10%，英文發音更清晰
        window.speechSynthesis.speak(utterance);
    }
}