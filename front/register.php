<fieldset class="container">
    <legend>學習者註冊</legend>
    <form action="./api/register.php" method="POST" id="regForm" onsubmit="return validateForm()">
        <div class="form-group">
            <label for="username">帳號：</label>
            <input type="text" name="username" id="username" required placeholder="請輸入您的帳號" onblur="checkUsername()">
            <span id="username-msg" style="display:block; font-size:14px; margin-top:5px;"></span>
        </div>

        <div class="form-group">
            <label for="password">密碼：</label>
            <!-- 當第一組密碼改變時，也要同步觸發檢查（防止使用者先打完確認密碼，才回頭改第一組密碼） -->
            <input type="password" name="password" id="password" required placeholder="請輸入您的密碼" oninput="checkPasswordMatch()">
        </div>

        <div class="form-group">
            <label for="password2">再次確認密碼：</label>
            <!-- 綁定 oninput 事件，每輸入一個字就即時比對 -->
            <input type="password" name="password2" id="password2" required placeholder="再次輸入您的密碼" oninput="checkPasswordMatch()">
            <!-- 新增密碼檢查提示區塊 -->
            <span id="password-msg" style="display:block; font-size:14px; margin-top:5px;"></span>
        </div>

        <div class="button-group">
            <button type="submit" id="submitBtn">註冊</button>
            <button type="button" onclick="location.href='../index.php?do=login';">返回登入</button>
        </div>
    </form>
</fieldset>

<script>
    let isUsernameValid = false;
    let isPasswordValid = false;

    // 1. 檢查帳號是否重複 (AJAX)
    function checkUsername() {
        const username = document.getElementById('username').value.trim();
        const msgSpan = document.getElementById('username-msg');

        if (username === '') {
            msgSpan.innerHTML = '';
            isUsernameValid = false;
            toggleSubmitButton();
            return;
        }

        fetch(`./api/check_username.php?username=${encodeURIComponent(username)}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    msgSpan.style.color = 'red';
                    msgSpan.innerHTML = '❌ 此帳號已被註冊';
                    isUsernameValid = false;
                } else {
                    msgSpan.style.color = 'green';
                    msgSpan.innerHTML = '   此帳號可以使用';
                    isUsernameValid = true;
                }
                toggleSubmitButton(); // 每次檢查完更新按鈕狀態
            })
            .catch(err => {
                console.error('檢查失敗:', err);
            });
    }

    // 2. 即時檢查密碼是否一致
    function checkPasswordMatch() {
        const p1 = document.getElementById('password').value;
        const p2 = document.getElementById('password2').value;
        const msgSpan = document.getElementById('password-msg');

        // 如果確認密碼欄位還是空的，不顯示錯誤提示
        if (p2 === '') {
            msgSpan.innerHTML = '';
            isPasswordValid = false;
            toggleSubmitButton();
            return;
        }

        if (p1 === p2) {
            msgSpan.style.color = 'green';
            msgSpan.innerHTML = '   密碼輸入一致';
            isPasswordValid = true;
        } else {
            msgSpan.style.color = 'red';
            msgSpan.innerHTML = '❌ 兩次輸入的密碼不符';
            isPasswordValid = false;
        }
        toggleSubmitButton(); // 每次檢查完更新按鈕狀態
    }

    // 3. 控制註冊按鈕的啟用與停用
    function toggleSubmitButton() {
        const submitBtn = document.getElementById('submitBtn');
        // 必須帳號可用 且 密碼一致，按鈕才會解鎖
        if (isUsernameValid && isPasswordValid) {
            submitBtn.disabled = false;
        } else {
            submitBtn.disabled = true;
        }
    }

    // 4. 表單送出前的最終防線
    function validateForm() {
        if (!isUsernameValid) {
            alert('請先輸入有效的帳號！');
            return false;
        }
        if (!isPasswordValid) {
            alert('請確認密碼輸入是否正確！');
            return false;
        }
        return true;
    }
</script>