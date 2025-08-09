<?php

require 'auth.php';
requireLogin(); // redirige vers login.php si non connecté

// === CONFIGURATION ===
$config = [
    'table_id' => 'mn6gwo9aoewrbcb',
    'project' => 'D4SEO_KEYWORD_CLUSTER_ARTICLE',
    'table' => 'Content_Ideas_from_Keywords',
    'api_token' => 'OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47',
    'api_base_url' => 'https://nocodb.inonobu.fr/api/v1/db/data/v1/',
    'api_v2_url' => 'https://nocodb.inonobu.fr/api/v2/tables/'
];

// === ERROR HANDLING ===
function handleError($message, $response = null, $code = null) {
    echo "<div style='color: red; padding: 10px; background: #ffeeee; border: 1px solid #ffcccc; margin: 10px 0;'>";
    echo "<strong>Erreur:</strong> " . htmlspecialchars($message);
    if ($code) echo " (Code HTTP: $code)";
    if ($response) echo "<pre>" . htmlspecialchars($response) . "</pre>";
    echo "</div>";
}

// === API HELPER FUNCTIONS ===
function makeApiRequest($url, $method = 'GET', $data = null, $headers = []) {
    global $config;
    
    $ch = curl_init($url);
    
    // Set default headers
    $defaultHeaders = [
        "accept: application/json",
        "xc-token: {$config['api_token']}"
    ];
    
    // Add Content-Type header if data is provided
    if ($data !== null && $method !== 'GET') {
        $defaultHeaders[] = "Content-Type: application/json";
    }
    
    // Merge with custom headers
    $headers = array_merge($defaultHeaders, $headers);
    
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    // Add data if provided
    if ($data !== null && $method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'error' => $error
    ];
}

// === ACTIONS ===

// Process individual record deletion via GET
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $recordId = filter_input(INPUT_GET, 'delete', FILTER_SANITIZE_STRING);
    
    if (deleteRecord($recordId)) {
        header("Location: idee_contenu_keyword.php");
        exit;
    }
}

// Process detailed record update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Id'])) {
    $id = filter_input(INPUT_POST, 'Id', FILTER_SANITIZE_STRING);
    
    $data = [
        'Statut' => filter_input(INPUT_POST, 'Statut', FILTER_SANITIZE_STRING) ?? '',

        'Title' => filter_input(INPUT_POST, 'Title', FILTER_SANITIZE_STRING) ?? '',
        'Description' => filter_input(INPUT_POST, 'Description', FILTER_SANITIZE_STRING) ?? '',
        'Projet' => filter_input(INPUT_POST, 'Projet', FILTER_SANITIZE_STRING) ?? '',
        'Keyword' => filter_input(INPUT_POST, 'Keyword', FILTER_SANITIZE_STRING) ?? '',
        'Category' => filter_input(INPUT_POST, 'Category', FILTER_SANITIZE_STRING) ?? '',
        'Primary Keyword' => filter_input(INPUT_POST, 'Primary Keyword', FILTER_SANITIZE_STRING) ?? '',
        'Persona' => filter_input(INPUT_POST, 'Persona', FILTER_SANITIZE_STRING) ?? '',
        'key_takeaways' => filter_input(INPUT_POST, 'key_takeaways', FILTER_SANITIZE_STRING) ?? '',
        'final_article' => $_POST['final_article'] ?? '' // On évite de filtrer pour préserver le HTML
    ];
    
    if (updateRecord($id, $data)) {
        header("Location: contenu-ok.php");
        exit;
    }
}

// Process bulk deletion if le bouton dédié est cliqué
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bulk'])) {
    if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
        $deletedCount = 0;
        foreach ($_POST['selected_ids'] as $id) {
            $id = filter_var($id, FILTER_SANITIZE_STRING);
            if (deleteRecord($id)) {
                $deletedCount++;
            }
        }
    }
    header("Location: contenu-ok.php");
    exit;
}

// Process auto-publish updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_publish'])) {
    $successCount = 0;
    $totalCount = 0;
    
    foreach ($_POST['auto_publish'] as $id => $autoPublishValue) {
        $id = filter_var($id, FILTER_SANITIZE_STRING);
        $autoPublish = ($autoPublishValue === 'on');
        $totalCount++;
        
        $data = [
            'auto_publish' => $autoPublish,
            'published_status' => $autoPublish ? 'en attente' : 'non publié'
        ];
        
        if (updateRecord($id, $data)) {
            $successCount++;
        }
    }
    
    if ($successCount === $totalCount) {
        header("Location: contenu-ok.php");
        exit;
    }
}

// Get all records
$rows = getRecords();

// Extract unique projects and sort them
$projets = array_unique(array_filter(array_map(function($r) {
    return $r['Projet'] ?? '';
}, $rows)));
sort($projets);

// Get selected project and primary keyword filters
$projetFiltre = filter_input(INPUT_GET, 'projet', FILTER_SANITIZE_STRING) ?? '';
$primaryKeywordFiltre = filter_input(INPUT_GET, 'primary_keyword', FILTER_SANITIZE_STRING) ?? '';

// Si un projet est sélectionné, extraire les Primary Keywords associés à ce projet
$primaryKeywords = [];
if ($projetFiltre) {
    $primaryKeywords = array_unique(array_filter(array_map(function($r) use ($projetFiltre) {
        return (isset($r['Projet']) && $r['Projet'] === $projetFiltre) ? ($r['Primary Keyword'] ?? '') : null;
    }, $rows)));
    sort($primaryKeywords);
}

// === API HELPER FUNCTIONS (suite) ===
function deleteRecord($id) {
    global $config;
    
    $deleteUrl = "{$config['api_base_url']}{$config['project']}/{$config['table']}/" . urlencode($id);
    $result = makeApiRequest($deleteUrl, 'DELETE');
    
    if ($result['httpCode'] !== 200) {
        handleError("Impossible de supprimer l'enregistrement", $result['response'], $result['httpCode']);
        return false;
    }
    
    return true;
}

function updateRecord($id, $data) {
    global $config;
    
    $updateUrl = "{$config['api_base_url']}{$config['project']}/{$config['table']}/" . urlencode($id);
    $result = makeApiRequest($updateUrl, 'PATCH', $data);
    
    if ($result['httpCode'] !== 200) {
        handleError("Impossible de mettre à jour l'enregistrement", $result['response'], $result['httpCode']);
        return false;
    }
    
    return true;
}

function getRecords() {
    global $config;
    
    $apiUrl = "{$config['api_v2_url']}{$config['table_id']}/records?offset=0&limit=5000";
    $result = makeApiRequest($apiUrl);
    
    if ($result['httpCode'] !== 200) {
        handleError("Impossible de récupérer les enregistrements", $result['response'], $result['httpCode']);
        return [];
    }
    
    $data = json_decode($result['response'], true);
    return $data['list'] ?? [];
}
?>




<?php
// Si la fonction sanitizeInput n'existe pas, ajoutez-la
function sanitizeInput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

define('WEBHOOK_PERSONAS', 'https://n8n.evolu8.fr/webhook/8a2879ae-f457-439e-b5bb-d7a29289271c');

$personas_result = makeApiRequest(WEBHOOK_PERSONAS, 'GET');
$personas_list = [];

if ($personas_result['httpCode'] === 200) {
    $personas_data = json_decode($personas_result['response'], true);
    // Vérifier que la structure de données correspond à ce qui est attendu
    if (isset($personas_data['persona']) && is_array($personas_data['persona'])) {
        foreach ($personas_data['persona'] as $item) {
            if (isset($item['Title'])) {
                $personas_list[] = $item['Title'];
            }
        }
    } else {
        error_log("Format inattendu des données reçues depuis le webhook personas: " . $personas_result['response']);
    }
} else {
    error_log("Erreur lors de l'appel au webhook personas: HTTP Code " . $personas_result['httpCode'] . " - Réponse: " . $personas_result['response']);
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Idées Articles Keyword</title>
        <link rel="stylesheet" href="/style.css">

</head>
<body>
<?php include("nav.php"); ?>

    <h1>Idée d'article par Keyword</h1>
    
    <form method="get" class="filter-form">
        <label for="projet">Filtrer par projet :</label>
        <select name="projet" id="projet" onchange="this.form.submit()">
            <option value="">-- Tous --</option>
            <?php foreach ($projets as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>" <?= $p === $projetFiltre ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label for="primary_keyword">Filtrer par Primary Keyword :</label>
        <select name="primary_keyword" id="primary_keyword" <?= $projetFiltre ? '' : 'disabled' ?> onchange="this.form.submit()">
            <?php if (!$projetFiltre): ?>
                <option value="">Sélectionnez d'abord un projet</option>
            <?php else: ?>
                <option value="">-- Tous --</option>
                <?php foreach ($primaryKeywords as $pk): ?>
                    <option value="<?= htmlspecialchars($pk) ?>" <?= $pk === $primaryKeywordFiltre ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pk) ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </form>

    <!-- Le même formulaire pour la mise à jour automatique et la suppression bulk -->
    <form method="POST" action="" id="articles_form">
        <table>
            <thead>
                <tr>
                    <!-- Colonne pour la sélection bulk -->
                    <th><input type="checkbox" id="select_all"></th>
                    <th>Titre</th>
                    <th>Mot clés</th>
                    <th>Description</th>
                    <th>Role article</th>
                    <th>Primary Keyword Origine</th>
                    <th>Persona</th>
                    <th>Projet</th>
                    <th>Publication automatique</th>
                    <th>État</th>
                    <!-- Dans <thead> (ajouter en fin de ligne d'entête) -->
					<th>Envoyer vers N8N</th>

                    <th>Supprimer</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rowCount = 0;
                foreach ($rows as $row): 
                    // Filtre par projet
                    if ($projetFiltre && (($row['Projet'] ?? '') !== $projetFiltre)) continue;
                    // Filtre par Primary Keyword si défini
                    if ($primaryKeywordFiltre && (($row['Primary Keyword'] ?? '') !== $primaryKeywordFiltre)) continue;
                    $rowCount++;
                ?>
                <tr>
                    <!-- Checkbox pour la suppression en bulk -->
                    <td>
                        <input type="checkbox" class="select_item" name="selected_ids[]" value="<?= htmlspecialchars($row['Id']) ?>">
                    </td>
                    <td>
                            <?= htmlspecialchars($row['Title'] ?? 'Titre non défini') ?>
                    </td>
                    <td><?= htmlspecialchars($row['Keyword'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['Description'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['Category'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['Primary Keyword'] ?? '') ?></td>
                    
  <td>
    <!-- Sélecteur de Persona -->
    <select name="persona[<?= htmlspecialchars($row['Id']) ?>]">
        <option value="">Choisir une persona</option>
        <?php foreach ($personas_list as $persona): ?>
            <option value="<?= sanitizeInput($persona) ?>" <?= (isset($row['persona']) && $row['persona'] == $persona) ? 'selected' : '' ?>>
                <?= sanitizeInput($persona) ?>
            </option>
        <?php endforeach; ?>
    </select>
</td>

<td><?= htmlspecialchars($row['Projet'] ?? '') ?></td>
<td>
    <input type="hidden" name="auto_publish[<?= htmlspecialchars($row['Id']) ?>]" value="off">
    <input type="checkbox" name="auto_publish[<?= htmlspecialchars($row['Id']) ?>]" value="on"
           <?= isset($row['auto_publish']) && $row['auto_publish'] ? 'checked' : '' ?>>
</td>
<td><?= htmlspecialchars($row['Statut'] ?? '') ?></td>

<td>
  <button type="button" class="send-to-webhook" 
    data-id="<?= htmlspecialchars($row['Id']) ?>"
    data-title="<?= htmlspecialchars($row['Title'] ?? '') ?>"
    data-keywords="<?= htmlspecialchars($row['Keyword'] ?? '') ?>"
    data-description="<?= htmlspecialchars($row['Description'] ?? '') ?>"
    data-primary-keyword="<?= htmlspecialchars($row['Primary Keyword'] ?? '') ?>"
    data-persona="<?= htmlspecialchars($row['persona'] ?? '') ?>" 
    data-projet="<?= htmlspecialchars($row['Projet'] ?? '') ?>"
    data-status="<?= htmlspecialchars($row['published_status'] ?? 'en attente') ?>">
    Envoyer
  </button>
</td>

<td>
    <a class="delete-btn" href="?delete=<?= htmlspecialchars($row['Id']) ?>" 
       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">🗑</a>
</td>

                </tr>
                <?php endforeach; ?>
                
                <?php if ($rowCount === 0): ?>
                <tr>
                    <td colspan="10" style="text-align: center;">Aucun article trouvé<?= $projetFiltre || $primaryKeywordFiltre ? ' pour ce filtre' : '' ?>.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Bouton pour la mise à jour des auto publish -->
        <button type="submit">Enregistrer les modifications</button>
        <!-- Bouton pour la suppression bulk -->
<button type="button" id="delete-selected" style="margin-left: 10px;">Supprimer les lignes sélectionnées</button>    </form>

<script>
document.getElementById('delete-selected').addEventListener('click', function() {
    if (!confirm("Êtes-vous sûr de vouloir supprimer les articles sélectionnés ?")) return;

    const form = document.getElementById('articles_form');
    const checkboxes = form.querySelectorAll('.select_item:checked');

    if (checkboxes.length === 0) {
        alert("Aucun article sélectionné.");
        return;
    }

    const formData = new FormData();
    formData.append('delete_bulk', '1');
    checkboxes.forEach(cb => {
        formData.append('selected_ids[]', cb.value);
    });

    fetch('', {
        method: 'POST',
        body: formData
    }).then(() => {
        location.reload();
    }).catch(() => {
        alert("Erreur lors de la suppression.");
    });
});
</script>

    <script>
        // Confirmation avant soumission pour le formulaire de mise à jour
        document.getElementById('articles_form').addEventListener('submit', function(e) {
            // Le bouton bulk a sa propre confirmation dans l'attribut onclick
            if (!confirm('Voulez-vous enregistrer ces modifications ?')) {
                e.preventDefault();
            }
        });
        
        // Fonctionnalité "Select All" pour les checkbox bulk
        document.getElementById('select_all').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('.select_item');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = document.getElementById('select_all').checked;
            });
        });
    </script>
    
    
    <script>   
     // Fonction pour l'envoi d'un article avec persona
            function sendArticle(id) {
                const select = document.querySelector(`select[name="persona[${id}]"]`);
                if (select) {
                    const selectedPersona = select.value;
                    if (selectedPersona === '') {
                        alert('Veuillez sélectionner un persona avant d\'envoyer.');
                        return false;
                    }

                    // Créer un formulaire dynamique pour l'envoi
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.pathname;

                    const inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'send_article_id';
                    inputId.value = id;
                    form.appendChild(inputId);

                    const inputPersona = document.createElement('input');
                    inputPersona.type = 'hidden';
                    inputPersona.name = `persona[${id}]`;
                    inputPersona.value = selectedPersona;
                    form.appendChild(inputPersona);

                    document.body.appendChild(form);
                    form.submit();
                }
                return false;
            }

    
     </script>   
    
<script>
document.querySelectorAll('.send-to-webhook').forEach(function(button) {
  button.addEventListener('click', function() {
    // Récupération de l'ID associé à la ligne
    const id = this.getAttribute('data-id');
    // Recherche du select de persona correspondant (nommé "persona[ID]")
    const personaSelect = document.querySelector(`select[name="persona[${id}]"]`);
    if (!personaSelect) {
      alert('Sélecteur de persona introuvable.');
      return;
    }
    // Récupérer la valeur sélectionnée
    const selectedPersona = personaSelect.value;
    if (selectedPersona.trim() === '') {
      alert('Veuillez sélectionner un persona avant d\'envoyer.');
      return;
    }
    
    // Construit l'objet à envoyer en utilisant la valeur du select
    const data = {
      Id: this.getAttribute('data-id'),
      /* ici je donne la provenance */
      Provenance: "from_idee_keyword",
      title: this.getAttribute('data-title'),
      keywords: this.getAttribute('data-keywords'),
      description: this.getAttribute('data-description'),
      primary_keyword: this.getAttribute('data-primary-keyword'),
      persona: selectedPersona,  // La valeur récupérée depuis le select
      projet: this.getAttribute('data-projet'),
      status: this.getAttribute('data-status')
    };

    fetch('submit_idee_contenu_keyword.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        alert('Envoyé avec succès vers N8N');
      } else {
        alert('Erreur : ' + result.error);
      }
    })
    .catch(err => {
      console.error('Erreur lors de l\'appel au serveur :', err);
      alert('Erreur lors de l\'appel au serveur');
    });
  });
});
</script>

</body>
</html>
