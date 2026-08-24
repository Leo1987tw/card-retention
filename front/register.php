<style>
/* 1. 修正全域容器 */
.container {
    max-width: 420px;
    margin: 60px auto;
    padding: 30px 40px 40px 40px;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background-color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
    
    /* 核心修正：消除 fieldset 內建的左右預設內距，讓輸入框左右撐滿 */
    box-sizing: border-box; 
}

/* 2. 外框標題 */
.container legend {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    padding: 0 12px;
    letter-spacing: 1px;
}

/* 3. 輸入框區塊間距 */
.form-group {
    margin-top: 24px;
    display: flex;
    flex-direction: column;
    width: 100%; /* 確保區塊撐滿容器 */
}

/* 4. 欄位標籤 */
.form-group label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
    text-align: left; /* 確保標籤一律靠左對齊 */
}

/* 5. 輸入框本體 */
.form-group input {
    padding: 12px 16px;
    font-size: 1rem;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    color: #334155;
    background-color: #f8fafc;
    transition: all 0.25s ease;
    outline: none;
    width: 100%; /* 讓輸入框完美橫向拉長 */
}

.form-group input:focus {
    border-color: #3b82f6;
    background-color: #ffffff;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
}

/* 6. 按鈕群組排列（修正折行的關鍵） */
.button-group {
    margin-top: 36px;
    display: flex;
    gap: 16px;
    width: 100%; /* 確保按鈕群組水平撐滿 */
}

/* 按鈕通用基礎樣式 */
.button-group button {
    flex: 1;                /* 讓兩個按鈕均分剩餘寬度 */
    padding: 14px 0;        /* 稍微加高點擊高度 */
    font-size: 1rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    white-space: nowrap;    /* 核心修正：強制文字絕對不折行 */
}

/* 註冊主按鈕 */
.button-group button[type="submit"] {
    background-color: #2563eb;
    color: #ffffff;
    box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
}

.button-group button[type="submit"]:hover {
    background-color: #1d4ed8;
    transform: translateY(-1px);
}

/* 返回登入按鈕 */
.button-group button[type="button"] {
    background-color: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.button-group button[type="button"]:hover {
    background-color: #e2e8f0;
    color: #1e293b;
}
</style>

<fieldset class="container">
    <legend>學習者註冊</legend>
    <form action="./api/register.php" method="POST">
        <h3>註冊新帳號</h3>
        <div class="form-group">
            <label for="username">帳號：</label>
            <input type="text" name="username" id="username" required placeholder="請輸入您的帳號">
        </div>

        <div class="form-group">
            <label for="password">密碼：</label>
            <input type="password" name="password" id="password" required placeholder="請輸入您的密碼">
        </div>

        <div class="form-group">
            <label for="password2">再次確認密碼：</label>
            <input type="password" name="password2" id="password2" required placeholder="再次輸入您的密碼">
        </div>

        <div class="button-group">
            <button type="submit">註冊</button>
            <button type="button" onclick="location.href='../index.php?do=login';">返回登入</button>
        </div>
    </form>
</fieldset>