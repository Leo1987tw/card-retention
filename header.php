<header>
    <div class="left">
        <a class="button" href="./index.php">home</a>
        <div class="dropdown-block">
            <a class="dropdown-title button" id="dropdown-title" href="./index.php">words</a>
            <div class="dropdown-content" id="dropdown-menu">
                <a class="button" href="./index.php">words</a>
                <a class="button" href="./index.php?do=html">html</a>
                <a class="button" href="./index.php?do=css">css</a>
            </div>
        </div>
    </div>

    <div class="right">
        <?php

        if (!isset($_SESSION['user_id'])) {
            echo "<a class=\"button\" href=\"?do=register\">register</a>";
            echo "<a class=\"button\" href=\"?do=login\">login</a>";
        } else {
            echo "<a class=\"button\" href=\"?do=console\">console</a>";
            echo "<a class=\"button\" href=\"?do=logout\">logout</a>";
        }

        ?>
    </div>
</header>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const dropdownTitle = document.getElementById('dropdown-title');
        const dropdownMenu = document.getElementById('dropdown-menu');

        // 【步驟 A】初始化標題（維持原樣）
        const urlParams = new URLSearchParams(window.location.search);
        const currentDo = urlParams.get('do');
        const validTitles = ['words', 'html', 'css'];

        if (currentDo && validTitles.includes(currentDo)) {
            dropdownTitle.textContent = currentDo;
        } else {
            dropdownTitle.textContent = 'words';
        }

        // 【步驟 B】💡 改成用點的
        if (dropdownTitle && dropdownMenu) {
            // 1. 點擊主標題：切換選單的顯示與隱藏
            dropdownTitle.addEventListener('click', function(event) {
                event.preventDefault(); // 阻止 <a> 標籤預設的跳轉行為
                event.stopPropagation(); // 💡 阻止事件冒泡，防止被下方的 window 點擊事件秒收回
                dropdownMenu.classList.toggle('show');
            });

            // 2. 點擊選單內的選項：換字
            dropdownMenu.addEventListener('click', function(event) {
                if (event.target.classList.contains('button')) {
                    dropdownTitle.textContent = event.target.textContent;
                    // 選完字之後，自動把選單收起來
                    dropdownMenu.classList.remove('show');
                }
            });
        }

        // 3. 💡 貼心防呆：當選單開著時，點擊網頁「任何其他地方」就自動收起選單
        window.addEventListener('click', function() {
            if (dropdownMenu && dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
            }
        });
    });
</script>