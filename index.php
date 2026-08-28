<?php

include_once "./api/db.php";

?>

<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>卡牌學習記憶系統</title>
    <link rel="stylesheet" href="./css/style.css">
    <script src="./js/script.js" defer></script>
</head>

<body>
    <?php include_once "./header.php"; ?>
    <main>
        <?php
        
        $do = $_GET['do'] ?? 'main';
        $file = "./front/$do.php";

        if(file_exists($file)){
            include_once $file;
        }else {
            include_once "./front/main.php";
        }
        
        ?>
    </main>
    <?php include_once "./footer.php"; ?>
</body>

</html>