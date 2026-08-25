<fieldset class="container">
    <legend>學習者登出</legend>
    <form action="./api/logout.php" method="POST">
        <div style="margin-top: 36px;">
            <h3>請問您確定要登出嗎？</h3>
        </div>

        <div class="button-group">
            <button type="submit">登出</button>
            <button type="button" onclick="location.href='./index.php';">返回首頁</button>
        </div>
    </form>
</fieldset>