<!-- 💡 智慧修正：最外層改為 form，完美承接 style.css 的極致莫蘭迪外觀 -->
<form action="./api/register.php" method="POST" id="regForm" onsubmit="return validateForm()" class="container">
    <fieldset style="width: 100%; border: none; padding: 0; margin: 0;">
        <legend>學習者註冊</legend>
        
        <div class="form-group">
            <label for="username">帳號：</label>
            <input type="text" name="username" id="username" required placeholder="請輸入您的帳號" onblur="checkUsername()">
            <span id="username-msg" style="display:block; font-size:14px; margin-top:5px; font-weight: 600;"></span>
        </div>

        <div class="form-group">
            <label for="password">密碼：</label>
            <input type="password" name="password" id="password" required placeholder="密碼長度至少 6 個字元" oninput="checkPasswordMatch()">
        </div>

        <div class="form-group">
            <label for="password2">再次確認密碼：</label>
            <input type="password" name="password2" id="password2" required placeholder="再次輸入您的密碼" oninput="checkPasswordMatch()">
            <span id="password-msg" style="display:block; font-size:14px; margin-top:5px; font-weight: 600;"></span>
        </div>

        <div class="button-group">
            <!-- 💡 新增樣式過渡控制，讓按鈕在 disabled 變灰時擁有高級的透明度視覺 -->
            <button type="submit" id="submitBtn" style="transition: all 0.3s ease;">註冊</button>
            <button type="button" onclick="location.href='./index.php?do=login';">返回登入</button>
        </div>
    </fieldset>
</form>

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
                    msgSpan.style.color = '#e74c3c'; // 莫蘭迪紅
                    msgSpan.innerHTML = '❌ 此帳號已被註冊，請換一個';
                    isUsernameValid = false;
                } else {
                    msgSpan.style.color = '#2a9d8f'; // 莫蘭迪綠
                    msgSpan.innerHTML = '✓ 此帳號可以使用';
                    isUsernameValid = true;
                }
                toggleSubmitButton(); 
            })
            .catch(err => {
                console.error('檢查失敗:', err);
            });
    }

    // 2. 即時檢查密碼是否一致、且是否滿 6 碼 (智慧對齊後端)
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

        // 🌟 智慧防禦增強：加入 6 碼長度檢查，不讓弱密碼直接過關
        if (p1.length < 6) {
            msgSpan.style.color = '#e74c3c';
            msgSpan.innerHTML = '❌ 安全防線：密碼長度至少需要 6 個字元！';
            isPasswordValid = false;
            toggleSubmitButton();
            return;
        }

        if (p1 === p2) {
            msgSpan.style.color = '#2a9d8f';
            msgSpan.innerHTML = '✓ 密碼輸入一致';
            isPasswordValid = true;
        } else {
            msgSpan.style.color = '#e74c3c';
            msgSpan.innerHTML = '❌ 兩次輸入的密碼不符';
            isPasswordValid = false;
        }
        toggleSubmitButton(); 
    }

    // 3. 控制註冊按鈕的啟用與停用（外觀高階調教）
    function toggleSubmitButton() {
        const submitBtn = document.getElementById('submitBtn');
        if (isUsernameValid && isPasswordValid) {
            submitBtn.disabled = false;
            submitBtn.style.backgroundColor = '#2563eb'; // 回復原來的深藍色
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
        } else {
            submitBtn.disabled = true;
            submitBtn.style.backgroundColor = '#94a3b8'; // 精緻高級灰
            submitBtn.style.opacity = '0.6';
            submitBtn.style.cursor = 'not-allowed'; // 滑鼠變換為禁止符號
        }
    }

    // 4. 表單送出前的最終防線
    function validateForm() {
        const p1 = document.getElementById('password').value;
        if (!isUsernameValid) {
            alert('請先輸入有效的帳號！');
            return false;
        }
        if (p1.length < 6) {
            alert('密碼長度至少需要 6 個字元！');
            return false;
        }
        if (!isPasswordValid) {
            alert('請確認密碼輸入是否正確！');
            return false;
        }
        return true;
    }

    // 💡 頁面載入時，先全自動執行一次按鈕狀態初始化（讓註冊按鈕預設為精緻灰色鎖定）
    document.addEventListener("DOMContentLoaded", function() {
        toggleSubmitButton();
    });
</script>