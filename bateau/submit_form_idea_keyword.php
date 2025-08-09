<?php
// Vérifier que le formulaire a été soumis par la méthode POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Récupérer les données des champs du formulaire
    $primary_keyword = $_POST['primary_keyword'] ?? '';
    $location        = $_POST['location'] ?? '';
    $language        = $_POST['language'] ?? '';
    $limit           = $_POST['limit'] ?? '';
    $depth           = $_POST['depth'] ?? '';
    $projets           = $_POST['projets'] ?? '';
    
    

    // Vérifier que tous les champs sont remplis
    if (empty($primary_keyword) || empty($location) || empty($language) || empty($limit) || empty($depth)) {
        echo json_encode(["success" => false, "message" => "Tous les champs sont obligatoires."]);
        exit();
    }

    // URL du Webhook N8N (gardée confidentielle dans le code serveur)
    $webhook_url = "https://n8n.evolu8.fr/webhook/009f2666-6a3f-4ada-9e27-31674ea2eaf0";

    // Préparer les données à envoyer sous forme de JSON
    $data = [
        "primary_keyword" => $primary_keyword,
        "location"        => $location,
        "language"        => $language,
        "limit"           => $limit,
        "depth"           => $depth,
        "projets"           => $projets
    ];

    // Initialiser cURL et configurer les options pour l'envoi POST
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    // Encodage des données en JSON avant envoi
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);

    // Exécuter la requête cURL vers le webhook N8N
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Vérifier le code HTTP de la réponse
    if ($http_code === 200) {
        echo json_encode(["success" => true, "message" => "Le webhook a été envoyé avec succès !"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur lors de l'envoi à N8N."]);
    }
    exit();
}
?>
