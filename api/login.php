<?php

include_once "./db.php";

if (isset($_POST['username']) && isset($_POST['password'])) {
    $learner = $Learner->find($_POST['username']);

    if ($learner && password_verify($_POST['password'], $learner['password'])) {

        $_SESSION['login'] = $learner['username'];

        header("Location: ../index.php?do=card_board");
        exit();
    } else {
        echo "<script>";
        echo "alert('Account or password is wrong.');";
        echo "location.href='../index.php?do=login';";
        echo "</script>";
        exit();
    }
} else {
    header("Location: ../index.php?do=login");
    exit();
}
