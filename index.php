<?php

include_once "./api/db.php";

?>

<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>天天20-卡牌學習記憶系統</title>
    <link rel="stylesheet" href="./css/style.css">
    <script src="./js/script.js" defer></script>
</head>

<body>
    <?php include_once "./header.php"; ?>
    <main>
        <?php
        
        $page_map = [
            'main' => 'main',
            'card_board' => 'main',
            'html' => 'main',
            'css' => 'main',
            'login' => 'login',
            'register' => 'register',
            'console' => 'console',
            'logout' => 'logout'
        ];
        $do = $_GET['do'] ?? 'main';
        $page = $page_map[$do] ?? 'main';
        $file = "./front/$page.php";

        include_once $file;
        
        ?>
    </main>
    <?php include_once "./footer.php"; ?>
</body>

</html>