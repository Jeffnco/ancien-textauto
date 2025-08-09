<?php
session_start();

if (isset($_SESSION['user_id'])) {
    // Utilisateur déjà connecté → on le redirige vers la page principale
    header('Location: monform.php');
    exit;
}

// Sinon, on le redirige vers la page de connexion
header('Location: login.php');
exit;
