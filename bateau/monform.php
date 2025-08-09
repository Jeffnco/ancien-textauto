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
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>BLUCKT Article Writer</title>
  
 <link rel="stylesheet" href="/style.css">

</head>
<body>

<?php include("nav.php"); ?>


<h1>The Jeff BLUCKT Article Writer</h1>

<div class="tabs">
  <div class="tab active" onclick="showTab('tab-article')">Écrire un article</div>
  <div class="tab" onclick="showTab('tab-project')">Créer un projet</div>
  <div class="tab" onclick="showTab('tab-persona')">Créer un persona</div>
</div>

<div id="message"></div>

<!-- Formulaire d'article -->
<div id="tab-article" class="tab-content active">
  <?php include 'form_article.php'; ?>
</div>

<!-- Formulaire de projet -->
<div id="tab-project" class="tab-content">
  <?php include 'form_project.php'; ?>
</div>

<!-- Formulaire de persona -->
<div id="tab-persona" class="tab-content">
  <?php include 'form_persona.php'; ?>
</div>

<script>
// Fonction pour changer d'onglet
function showTab(tabId) {
  // Masquer tous les contenus d'onglet
  const tabContents = document.querySelectorAll('.tab-content');
  tabContents.forEach(tab => tab.classList.remove('active'));
  
  // Désactiver tous les onglets
  const tabs = document.querySelectorAll('.tab');
  tabs.forEach(tab => tab.classList.remove('active'));
  
  // Activer l'onglet sélectionné
  document.getElementById(tabId).classList.add('active');
  
  // Activer le bouton correspondant
  const index = Array.from(tabContents).findIndex(t => t.id === tabId);
  tabs[index].classList.add('active');
}

// Afficher un message de confirmation/erreur
function showMessage(message, isSuccess) {
  const messageDiv = document.getElementById('message');
  messageDiv.textContent = message;
  messageDiv.className = isSuccess ? 'success' : 'error';
  
  // Faire défiler vers le message
  messageDiv.scrollIntoView({ behavior: 'smooth' });
  
  // Masquer le message après 5 secondes
  setTimeout(() => {
    messageDiv.textContent = '';
    messageDiv.className = '';
  }, 5000);
}
</script>

</body>
</html>
