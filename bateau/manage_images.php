<?php
// manage_images.php

// === CONFIG ===
$config = [
    'project'        => 'D4SEO_KEYWORD_CLUSTER_ARTICLE',
    'table'          => 'Article_ecrit',
    'api_token'      => 'OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47',
    'api_base_url'   => 'https://nocodb.inonobu.fr/api/v1/db/data/v1/',
    'upload_dir'     => __DIR__ . '/uploads/articles/',
    'upload_url_base'=> '/uploads/articles/',
];

// === FONCTIONS ===
function makeApiRequest($url, $method = 'GET', $data = null) {
    global $config;
    $ch = curl_init($url);
    $headers = [
        "accept: application/json",
        "xc-token: {$config['api_token']}",
    ];
    if ($data !== null) {
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER    => $headers,
        CURLOPT_RETURNTRANSFER=> true,
        CURLOPT_TIMEOUT       => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp];
}

function getCurrentImageList($id, $column) {
    global $config;
    list($status, $resp) = makeApiRequest(
        "{$config['api_base_url']}{$config['project']}/{$config['table']}/" . urlencode($id)
    );
    if ($status !== 200) return [];
    $data = json_decode($resp, true);
    return isset($data[$column]) ? json_decode($data[$column], true) : [];
}

function updateArticleField($id, $column, $value) {
    global $config;
    list($status,) = makeApiRequest(
        "{$config['api_base_url']}{$config['project']}/{$config['table']}/" . urlencode($id),
        'PATCH',
        [$column => $value]
    );
    return $status === 200;
}

// === TRAITEMENT ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['article_id'], $_POST['image_type'])) {
    die('Requête invalide.');
}

$articleId = $_POST['article_id'];
$imageType = $_POST['image_type']; // 'image_a_la_une' ou 'images_article'

if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    // suppression
    $urlToDelete = $_POST['image_url'];
    if ($imageType === 'images_article') {
        // liste courante
        $list = getCurrentImageList($articleId, 'images_article');
        // on filtre
        $newList = array_values(array_filter($list, fn($u) => $u !== $urlToDelete));
        $jsonList = json_encode($newList);
        if (!updateArticleField($articleId, 'images_article', $jsonList)) {
            die('Échec de la mise à jour en BDD.');
        }
    } else {
        // image à la une
        if (!updateArticleField($articleId, 'image_a_la_une', '')) {
            die('Échec de la mise à jour en BDD.');
        }
    }
    // suppression du fichier physique
    $filePath = __DIR__ . $urlToDelete;
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    header("Location: articles.php?id=$articleId&deleted=1");
    exit;
}

// sinon upload
if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
    die('Erreur lors de l’upload du fichier.');
}

$filename   = time() . '_' . basename($_FILES['image_file']['name']);
$targetPath = $config['upload_dir'] . $filename;
$targetUrl  = $config['upload_url_base'] . $filename;

// création dossier
if (!is_dir($config['upload_dir'])) {
    mkdir($config['upload_dir'], 0775, true);
}

if (!move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
    die('Échec du déplacement du fichier.');
}

if ($imageType === 'images_article') {
    $current = getCurrentImageList($articleId, 'images_article');
    $current[] = $targetUrl;
    $newVal = json_encode($current);
} else {
    // image à la une
    $newVal = $targetUrl;
}

if (!updateArticleField($articleId, $imageType, $newVal)) {
    die('Échec de la mise à jour dans NocoDB.');
}

header("Location: articles.php?id=$articleId&upload_success=1");
exit;
