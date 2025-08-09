<?php
header('Content-Type: application/json');

// === CONFIGURATION ===
$project = 'D4SEO_KEYWORD_CLUSTER_ARTICLE';
$table = 'Projets';
$api_token = 'OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47'; 
$nocodb_url = "https://nocodb.inonobu.fr/api/v1/db/data/v1/$project/$table";

// === Récupération des données JSON envoyées par fetch() ===
$input = json_decode(file_get_contents("php://input"), true);

// Logs pour déboguer si besoin
file_put_contents('log_input.txt', print_r($input, true), FILE_APPEND);

$projet = $input['projet'] ?? '';
$frequence = $input['frequence'] ?? '';
$site = $input['site'] ?? '';
$mdp_app_wp = $input['mdp_app_wp'] ?? '';

// Vérifie que les champs requis sont bien présents
if (!empty($projet) && !empty($frequence) && !empty($site)) {
    $data = [
        'Projet' => $projet,
        'publish_frequency' => $frequence,
        'site' => $site,
        'mdp_app_wp' => $mdp_app_wp
    ];

    // Envoie des données à NocoDB via cURL
    $ch = curl_init($nocodb_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/json",
        "Content-Type: application/json",
        "xc-token: $api_token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log de la réponse pour analyse si besoin
    file_put_contents('log_response.txt', "HTTP: $http_code\n$response\n", FILE_APPEND);

    // Vérifie si la requête a réussi
    if ($http_code === 200 || $http_code === 201) {
        echo json_encode([
            "success" => true,
            "message" => "Le projet a été créé avec succès.",
            "api_response" => json_decode($response, true)
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Erreur lors de la création du projet (code $http_code).",
            "api_response" => json_decode($response, true),
            "raw_response" => $response
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Tous les champs sont obligatoires."
    ]);
}
