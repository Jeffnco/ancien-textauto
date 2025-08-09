<?php
// send_to_webhook.php

// URL du webhook N8N (ne pas exposer cette URL en JS)
$webhookUrl = 'https://n8n.evolu8.fr/webhook/ffa2d9b1-1a69-4cf6-adba-1c8fad3999b6';

header('Content-Type: application/json');

// Récupération et décodage du JSON envoyé
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Aucune donnée reçue']);
    exit;
}

// Préparation de la requête vers le webhook
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Vérification de la réponse du webhook
if ($httpCode == 200) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'L\'appel au webhook a échoué']);
}
?>
