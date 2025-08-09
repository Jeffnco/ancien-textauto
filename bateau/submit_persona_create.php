

<?php
// Vérifier si le formulaire a été soumis
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Récupérer les données du formulaire
    $nom_persona = $_POST['nom_persona'] ?? '';
    $text_src_persona = $_POST['text_src_persona'] ?? '';

    // Vérifier que les champs ne sont pas vides
    if (empty($nom_persona) || empty($text_src_persona)) {
        die("Tous les champs sont obligatoires.");
    }

    // URL du Webhook N8N (masquée dans le PHP)
    $webhook_url = "https://n8n.evolu8.fr/webhook-test/8d1545fe-5769-4965-a25e-d03530e99e99";

    // Préparer les données en JSON
    $data = [
        "nom_persona" => $nom_persona,
        "text_src_persona" => $text_src_persona
    ];

    // Initialiser cURL
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);

    // Exécuter la requête
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Vérifier la réponse de N8N
    if ($http_code === 200) {
        echo json_encode(["success" => true, "message" => "Le persona a été créé avec succès !"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur lors de l'envoi à N8N."]);
    }
    exit();
}
