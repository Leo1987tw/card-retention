const card = document.getElementById('card');
let degree = 0;
let currentId = -1;
let currentAudioUrl = ""; // 💡 全新配置：儲存當前卡片從資料庫撈出的真人音檔網址

// 頁面載入時自動初始化第一張卡片
window.onload = () => {
    if (isTaskFinished) {
        // 1. 如果今天已經完成任務，一登入立刻把按鈕渲染完畢（完全不閃爍）
        renderButtonFinishedStyle();
        // 2. 完工狀態下，第一次進入直接去撈取 200 字庫內完全隨機的卡片，供後續翻牌
        getInitialFinishedCard();
    } else {
        // 正常狀態：抓取今天的第一張普通卡片
        nextCard(null, null);
    }
}

/**
 * 特殊輔助：任務完成後重新登入，初次靜默加載字庫單字
 */
function getInitialFinishedCard() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentDo = urlParams.get('do') || 'main'; // 預設對接單一入口路由

    fetch(`./api/api.php?do=${encodeURIComponent(currentDo)}&mode=pool_rand`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                currentId = data.id;
                currentAudioUrl = data.audio || ""; // 緩存發音網址
            }
        })
        .catch(error => console.error('初始字庫載入失敗: ', error));
}

/**
 * 核心 1：翻牌行為 (flipCard)
 */
function flipCard(event) {
    // 1. 防誤觸：點擊到發音、按鈕或輸入框時，禁止翻牌
    if (event.target.closest('#audio') || event.target.tagName === 'BUTTON' || event.target.tagName === 'INPUT') {
        return;
    }

    // 2. 💡 修正特訓防線：只有在卡片「沒被重建」（即畫面上真的還顯示著 finished-box）時才阻擋
    // 點擊特訓按鈕後，新卡片產生了，此時不應該被阻擋
    const finishedBox = document.querySelector('.finished-box');
    if (finishedBox) {
        alert("今日任務已達成！請點擊下方按鈕開始複習字庫。");
        return;
    }

    // 3. 💡 關鍵修正：每次點擊，即時獲取最新重建的 #card 節點
    const currentCard = document.getElementById('card');
    if (!currentCard) return;

    // 將最新節點同步回全域變數，確保其他連動腳本不會出錯
    window.card = currentCard;

    // 4. 執行翻轉邏輯
    currentCard.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";
    const rectangle = currentCard.getBoundingClientRect();
    const clickPosition = event.clientX - rectangle.left - rectangle.width / 2;

    if (clickPosition >= 0) { 
        degree += 180; 
    } else { 
        degree -= 180; 
    }
    currentCard.style.transform = `rotateY(${degree}deg)`;
}

// =========================================================================
// 💡 關鍵修正：不要把 transitionend 綁在會被摧毀的 card 上！
// 改綁在永遠不滅的父層容器 #card-board 上，一勞永逸！
// =========================================================================
const cardBoardContainer = document.getElementById('card-board');
if (cardBoardContainer) {
    cardBoardContainer.addEventListener('transitionend', (event) => {
        // 確保引發動畫結束的是 #card 本身，而不是它裡面的其他元素
        if (event.target.id === 'card') {
            const currentCard = event.target;
            currentCard.style.transition = "none";
            
            degree %= 360;
            if (degree < 0) degree += 360;
            currentCard.style.transform = `rotateY(${degree}deg)`;
        }
    });
}

/**
 * 核心 2：抽下一張牌 (nextCard)
 */
function nextCard(isCorrect, event) {
    if (event) event.stopPropagation();

    const urlParams = new URLSearchParams(window.location.search);
    const currentDo = urlParams.get('do') || 'main';

    let url = `./api/api.php?do=${encodeURIComponent(currentDo)}`;

    // 如果今天任務已經完成了，按鈕功能全面質變
    if (isTaskFinished) {
        if (isCorrect === true) {
            url += `&mode=pool_hard`;   // 點擊特訓 ➔ 200 字庫內的最低 LV 生字
        } else if (isCorrect === false) {
            url += `&mode=pool_rand`;   // 點擊盲刷 ➔ 200 字庫內完全隨機，不紀錄
        }
    } else {
        if (isCorrect !== null && currentId !== -1) {
            url += `&id=${currentId}&isCorrect=${isCorrect ? 'true' : 'false'}`;
        }
    }

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {

                // 情境：一般學習過程中，「作答的當下」剛好清空舊字並觸發完工
                if (data.isFinished && !isTaskFinished) {
                    isTaskFinished = true;
                    renderButtonFinishedStyle(); // 渲染按鈕質變樣式

                    // 動態將卡牌區塊替換為完工結算畫面
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

                // 情境：如果是「完工後連續點擊按鈕複習字庫」，將卡牌結構復原
                restoreCardLayoutIfFinished();

                const isBack = (degree % 360 === 180);
                if (isBack) {
                    card.style.transition = "transform 0.5s ease-out";
                    degree = 0;
                    card.style.transform = `rotateY(${degree}deg)`;
                    setTimeout(() => { fillCardContent(data); }, 200);
                } else {
                    fillCardContent(data);
                }
            } else {
                const wordNode = document.getElementById('word');
                if (wordNode) wordNode.innerText = 'Fetch data fail.';
            }
        })
        .catch(error => {
            console.error('Error: ', error);
        });
}

/**
 * 輔助 1：當點擊特訓/盲刷時，將「任務結束」文字替換回「卡牌 3D 結構」
 */
function restoreCardLayoutIfFinished() {
    const finishedBox = document.querySelector('.finished-box');
    if (finishedBox) {
        document.getElementById('card-board').innerHTML = `
            <div class="card" id="card">
                <div class="face front">
                    <div class="new">NEW</div>
                    <div class="learning-statement">
                        <span class="learning-level">記憶等級 LV <span id="current-level">--</span></span>
                        <span class="preview-count">此卡牌出現過 <span id="current-preview-count">--</span> 次</span>
                    </div>
                    <p class="word" id="word">載入中...</p>
                    <div style="position: absolute; bottom: 80px; display: flex; justify-content: center; align-items: center;">
                        <!-- 💡 核心修正：動態重建的結構也同步更名為簡潔的 category1 與 category2 ID -->
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
        // 重新獲取剛生成的 card DOM 物件，確保全域變數可以正常對接動畫與旋轉
        window.card = document.getElementById('card');
        
        // 3. 💡 核心修正：強制重新綁定點擊翻轉事件，確保特訓完功能不遺失
        // 如果原本的 flipCard 屬性消失了，這行能完美補救
        cardBoard.onclick = function(event) {
            if (typeof flipCard === 'function') {
                flipCard(event);
            }
        };
    }
}

/**
 * 輔助 2：資料填入功能 (fillCardContent)
 * 💡 極致優化：因為 HTML ID 與後端通道 100% 同名，直接進行一對一「乾淨映射」，省去所有 switch/if 判斷分流！
 */
function fillCardContent(data) {
    currentId = data.id;
    currentAudioUrl = data.audio || ""; // 智慧通道：每次更換卡片，即更新真人發音網址

    // 建立萬用欄位盲填對照映射表
    const elementMapping = {
        'word': data.word,
        'definition': data.definition,
        'translation': data.translation,
        'category1': data.category1, // 後端主分類數據 ➔ 填入 id="category1" 的 HTML
        'category2': data.category2  // 後端次分類數據 ➔ 填入 id="category2" 的 HTML
    };

    // 安全遍歷渲染所有對應欄位
    Object.keys(elementMapping).forEach(id => {
        const element = document.getElementById(id);
        if (element) { 
            element.innerText = elementMapping[id] || ''; 
        }
    });

    // 【指標數據欄位渲染】
    const levelSpan = document.getElementById('current-level');
    if (levelSpan) {
        levelSpan.innerText = (data.level !== null && data.level !== undefined) ? data.level : '--';
    }

    const countSpan = document.getElementById('current-preview-count');
    if (countSpan) {
        countSpan.innerText = (data.preview_count !== null && data.preview_count !== undefined) ? data.preview_count : '--';
    }

    // 控制 NEW 標籤是否顯示
    const newTag = document.querySelector('.new');
    if (newTag) {
        newTag.style.display = (Number(data.preview_count) === 1) ? 'block' : 'none';
    }
}

/**
 * 輔助 3：今日完成後的按鈕渲染與質變樣式
 */
function renderButtonFinishedStyle() {
    const btnGroup = document.querySelectorAll('.true-false button');
    if (btnGroup.length >= 2) {
        const correctBtn = btnGroup[0]; // 答對了 按鈕
        const wrongBtn = btnGroup[1];   // 答錯了 按鈕

        correctBtn.innerText = "🎯 字庫生字特訓";
        correctBtn.style.backgroundColor = "#2a9d8f";
        correctBtn.style.color = "#ffffff";
        correctBtn.title = "鎖定在您已建立的200字庫中，專門抽取低等級生字強化複習！";

        wrongBtn.innerText = "🎲 字庫隨機盲刷";
        wrongBtn.style.backgroundColor = "#e76f51";
        wrongBtn.style.color = "#ffffff";
        wrongBtn.title = "從200字庫中完全隨機抽選，且此模式點擊對錯不會記錄、不扣分！";
    }
}

/**
 * 輔助 4：語音發音 (pronounce) - 真人音檔優先與原生機器音備援雙軌切換
 */
function pronounce(event) {
    if (event) event.stopPropagation();
    const wordText = document.getElementById('word').innerText.trim();
    if (!wordText || ['Fetch data fail.', 'Connection fail.', '載入中...'].includes(wordText)) return;

    // 軌道 A：如果當前卡片帶有真實 mp3 網址，走最高品質真人語音播放
    if (currentAudioUrl) {
        const audio = new Audio(currentAudioUrl);
        audio.play().catch(err => {
            console.warn("真人音檔播放失敗，自動轉為 TTS 備援發音:", err);
            executeTTS(wordText); // 音檔失效或不支援時，無縫啟動防禦機器音
        });
    } else {
        // 軌道 B：無音檔時（如 HTML/CSS 專業術語庫），直接走 TTS 發音
        executeTTS(wordText);
    }
}

/**
 * 原生網頁 TTS 發音引擎封裝
 */
function executeTTS(text) {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel(); // 停止先前的聲音，防止連點時語音嚴重延遲堆疊
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-US';
        utterance.rate = 0.9;
        window.speechSynthesis.speak(utterance);
    }
}