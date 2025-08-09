<?php

require 'auth.php';
requireLogin(); // redirige vers login.php si non connecté

// Configuration des webhooks N8N
$webhook_personas = "https://n8n.evolu8.fr/webhook/8a2879ae-f457-439e-b5bb-d7a29289271c";
$webhook_projets = "https://n8n.evolu8.fr/webhook/b634887e-8423-45db-a596-7db4498d975b";

// Fonction pour récupérer les données d'un webhook
function fetchWebhookData($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Récupération des personas et des projets
$personas = fetchWebhookData($webhook_personas);
$projets = fetchWebhookData($webhook_projets);

// Vérifier si les données sont valides
$personas_list = isset($personas['persona']) && is_array($personas['persona']) ? $personas['persona'] : [];
$projets_list = isset($projets['Projets']) && is_array($projets['Projets']) ? $projets['Projets'] : [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Envoi du Primary Keyword et informations complémentaires</title>
      <link rel="stylesheet" href="/style.css">

</head>

<?php include("nav.php"); ?>


<body>
  <form id="search_primary_keyword" class="formN">
    <div>
      <label for="primary_keyword">Primary Keyword :</label>
      <input type="text" id="primary_keyword" name="primary_keyword" required>
    </div>
    <div>
      <label for="location">Location :</label>
      <input type="text" id="location" name="location" required>
    </div>
    <div>
      <label for="language">Language :</label>
      <input type="text" id="language" name="language" required>
    </div>
    <div>
      <label for="limit">Limit :</label>
      <input type="number" id="limit" name="limit" required>
    </div>
    <div>
      <label for="depth">Depth :</label>
      <input type="number" id="depth" name="depth" required>
    </div>
    
    
    
        <!-- Sélection des Projets -->
    <label for="projets">Choisir un projet :</label>
    <select id="projets" name="projets">
        <option value="">Sélectionner un projet</option>
        <?php foreach ($projets_list as $projet): ?>
            <option value="<?= htmlspecialchars($projet['Projet']) ?>">
                <?= htmlspecialchars($projet['Projet']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <!-- Champ caché pour envoyer l'ID du projet -->
    <input type="hidden" id="id_projet" name="id_projet">
    
    <button type="submit">Rechercher des idées en fonction du mot clé</button>
  </form>

  <script>
    document.getElementById("search_primary_keyword").addEventListener("submit", function(event) {
      event.preventDefault(); // Empêche le rechargement de la page

      // Création de l'objet FormData et ajout de toutes les valeurs
      let formData = new FormData();
      formData.append('primary_keyword', document.getElementById("primary_keyword").value);
      formData.append('location', document.getElementById("location").value);
      formData.append('language', document.getElementById("language").value);
      formData.append('limit', document.getElementById("limit").value);
      formData.append('depth', document.getElementById("depth").value);
      formData.append('projets', document.getElementById("projets").value);


      // Envoi sécurisé via fetch vers le fichier PHP
      fetch("submit_form_idea_keyword.php", {
          method: "POST",
          body: formData
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              alert(data.message);
              document.getElementById("search_primary_keyword").reset();
          } else {
              alert(data.message);
          }
      })
      .catch(error => {
          console.error("Erreur :", error);
          alert("Erreur lors de l'envoi du webhook");
      });
    });
  </script>
</body>
</html>
