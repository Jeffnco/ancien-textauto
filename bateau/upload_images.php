<?php
// upload_images.php

// === CONFIG ===
$config = [
    'project' => 'D4SEO_KEYWORD_CLUSTER_ARTICLE',
    'table' => 'Article_ecrit',
    'api_token' => 'OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47',
    'api_base_url' => 'https://nocodb.inonobu.fr/api/v1/db/data/v1/',
    'upload_dir' => __DIR__ . '/uploads/articles/',
    'upload_url_base' => '/uploads/articles/', // à adapter si le site est dans un sous-dossier
];

// === FUNCTIONS ===

// Récupère les images actuelles d'un champ donné
function getCurrentImageList($id, $column) {
    global $config;
    $url = "{$config['api_base_url']}{$config['project']}/{$config['table']}/" . urlencode($id);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "xc-token: {$config['api_token']}"
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return isset($data[$column]) ? json_decode($data[$column], true) : [];
}

// Met à jour un champ image dans l'article
function updateArticleImage($id, $column, $imageData) {
    global $config;

    $url = "{$config['api_base_url']}{$config['project']}/{$config['table']}/" . urlencode($id);
    $data = [ $column => $imageData ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "Content-Type: application/json",
            "xc-token: {$config['api_token']}"
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}

// === MAIN ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['article_id'], $_POST['image_type'])) {
    $articleId = $_POST['article_id'];
    $imageType = $_POST['image_type']; // image_a_la_une ou images_article

    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        die('Erreur lors de l’upload du fichier.');
    }

    $filename = time() . '_' . basename($_FILES['image_file']['name']);
    $targetPath = $config['upload_dir'] . $filename;
    $targetUrl = $config['upload_url_base'] . $filename;

    if (!is_dir($config['upload_dir'])) {
        mkdir($config['upload_dir'], 0775, true);
    }

    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
        // Cas : images_article → empile les images dans un tableau JSON
        if ($imageType === 'images_article') {
            $currentImages = getCurrentImageList($articleId, 'images_article');
            if (!is_array($currentImages)) $currentImages = [];

            $currentImages[] = $targetUrl;
            $newValue = json_encode($currentImages);
        } else {
            // Cas : image_a_la_une → juste un string
            $newValue = $targetUrl;
        }

        $success = updateArticleImage($articleId, $imageType, $newValue);
        if ($success) {
            header("Location: articles.php?id=$articleId&upload_success=1");
            exit;
        } else {
            die("L’image a été uploadée mais la mise à jour dans NocoDB a échoué.");
        }
    } else {
        die("Échec du déplacement du fichier.");
    }
} else {
    die("Requête invalide.");
}
