<?php
/**
 * Proxy pour rediriger les demandes vers le webhook N8N
 * Cela permet de masquer l'URL du webhook dans le code source client
 */

// Autoriser les requêtes depuis votre domaine (à adapter selon votre configuration)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// URL du webhook (cachée aux utilisateurs)
define('WEBHOOK_URL', 'https://n8n.evolu8.fr/webhook/ffa2d9b1-1a69-4cf6-adba-1c8fad3999b6');

// Vérifier que la méthode est bien POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données envoyées
$input_data = file_get_contents('php://input');
$data = json_decode($input_data, true);

// Vérifier que les données sont valides
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Données JSON invalides']);
    exit;
}

// Valider les champs requis
$required_fields = ['keywords', 'title', 'description', 'mots_cles', 'instructions'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Le champ '$field' est requis"]);
        exit;
    }
}

// Envoyer les données au webhook
$ch = curl_init(WEBHOOK_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $input_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($input_data)
]);

// Exécuter la requête
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Gérer les erreurs
if ($curl_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la communication avec le webhook: ' . $curl_error]);
    exit;
}

// Renvoyer la réponse du webhook
http_response_code($http_code);
echo $response;
