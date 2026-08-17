<header>
    <div class="left">
        <a href="./index.php">home</a>
    </div>

    <div class="right">
        <a href="./register.php" target="main">register</a>
        <?php

        if (!isset($_SESSION['user_id'])) {
            echo "<a href=\"./login.php\" target=\"main\">login</a>";
        } else {
            echo "<a href=\"./logout.php\" target=\"main\">logout</a>";
        }

        ?>
    </div>
</header>