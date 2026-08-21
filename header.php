<header>
    <div class="left">
        <a href="./index.php">home</a>
        <select name="vocabulary-category" id="vocabulary-category" onchange="changeVocabularySet(this.value)">
            <option value="words">words</option>
            <option value="html">html</option>
            <option value="css">css</option>
        </select>
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
        <a href="./back.php">console</a>
    </div>
</header>