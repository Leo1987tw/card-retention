<!-- 💡 智慧修正：最外層改為 form，承接系統莫蘭迪樣式，寬度自動完美置中 -->
<form action="./api/logout.php" method="POST" class="container">
    <fieldset style="width: 100%; border: none; padding: 0; margin: 0;">
        <legend>學習者登出</legend>
        
        <div style="margin-top: 36px; margin-bottom: 24px; text-align: center;">
            <h3 style="color: #2c3e50; font-weight: 600;">請問您確定要登出嗎？</h3>
            <p style="color: #7f8c8d; font-size: 0.9rem; margin-top: 8px;">登出時系統將自動同步您今日的最新學習進度。</p>
        </div>

        <div class="button-group">
            <!-- 紅色莫蘭迪色調調配，強化「登出」動作的警示感 -->
            <button type="submit" style="background-color: #e74c3c;">登出</button>
            <button type="button" onclick="location.href='./index.php';">返回首頁</button>
        </div>
    </fieldset>
</form>