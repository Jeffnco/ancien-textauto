<?php
// === CONFIG ===
$project_table = 'Projets';
$article_table = 'Article_ecrit';
$api_token = 'OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47';
$categories = []; // Initialise la variable

// Vérifie que l'ID de l'article est disponible
if (!isset($id) || empty($id)) {
    error_log("❌ ID d'article manquant dans get_wp_categories.php");
    return;
}

// Récupère les données de l'article depuis NocoDB
$get_article_url = "https://nocodb.inonobu.fr/api/v1/db/data/v1/D4SEO_KEYWORD_CLUSTER_ARTICLE/{$article_table}/" . urlencode($id);

$ch = curl_init($get_article_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "accept: application/json",
    "xc-token: $api_token"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$article = json_decode($response, true);

// Vérifie la validité de la réponse de l'article
if (!$article || isset($article['message'])) {
    error_log("❌ Article introuvable dans get_wp_categories.php");
    return;
}

// Récupère le nom du projet depuis l'article
$project_name = $article['Projets'] ?? null;
if (!$project_name) {
    error_log("❌ Aucun projet associé à l'article (ID: $id)");
    return;
}

// Récupère les infos du projet (site + identifiants)
$get_project_url = "https://nocodb.inonobu.fr/api/v1/db/data/v1/D4SEO_KEYWORD_CLUSTER_ARTICLE/{$project_table}?where=(Projet,eq," . urlencode($project_name) . ")";

$ch = curl_init($get_project_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "accept: application/json",
    "xc-token: $api_token"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$project_data = json_decode($response, true);

if (!isset($project_data['list'][0])) {
    error_log("❌ Projet '$project_name' introuvable dans la table Projets");
    return;
}

$nomDeDomaine = $project_data['list'][0]['site'] ?? null;
$encodedCredentials = $project_data['list'][0]['mdp_app_wp'] ?? null;

if (!$nomDeDomaine || !$encodedCredentials) {
    error_log("❌ Données incomplètes dans le projet (site ou mdp_app_wp manquant)");
    return;
}

// Décode les identifiants WordPress
$credentials = base64_decode($encodedCredentials);
if (!$credentials || strpos($credentials, ':') === false) {
    error_log("❌ Identifiants WordPress invalides");
    return;
}

list($username, $password) = explode(':', $credentials, 2);

// Appelle l'API WordPress pour récupérer les catégories
$wp_url = "https://$nomDeDomaine/wp-json/wp/v2/categories";

$ch = curl_init($wp_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
$response = curl_exec($ch);
curl_close($ch);

$decoded = json_decode($response, true);

if (!$decoded || isset($decoded['data'])) {
    error_log("❌ Erreur lors de l'appel à l'API WP ($wp_url) pour les catégories");
    return;
}

// Si tout va bien, on remplit $categories
$categories = $decoded;
