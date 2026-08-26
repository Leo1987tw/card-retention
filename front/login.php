<!-- 💡 智慧修正：最外層改為 form，承接系統莫蘭迪樣式 -->
<form action="./api/login.php" method="POST" class="container">
    <fieldset style="width: 100%; border: none; padding: 0; margin: 0;">
        <legend>學習者登入</legend>
        
        <div class="form-group">
            <label for="username">帳號：</label>
            <input type="text" name="username" id="username" required placeholder="請輸入您的帳號">
        </div>

        <div class="form-group">
            <label for="password">密碼：</label>
            <input type="password" name="password" id="password" required placeholder="請輸入您的密碼">
        </div>

        <div class="button-group">
            <button type="submit">登入</button>
            <button type="button" onclick="location.href='./index.php?do=register';">前往註冊</button>
        </div>
    </fieldset>
</form>