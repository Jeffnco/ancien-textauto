<?php
// auth.php — à inclure en haut de chaque page sécurisée

session_start();

function isLoggedIn() {
    return isset($_SESSION['user_Id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
