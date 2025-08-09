<?php

require 'auth.php';
requireLogin(); // redirige vers login.php si non connecté

// === CONFIGURATION ===
$config = [
    'table_id' => 'm1ka78wn3uqsj4s',
    'project' => 'D4SEO_KEYWORD_CLUSTER_ARTICLE',
    'table' => 'Article_ecrit',
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

// Handle record deletion
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

// Handle record update
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

// Get all records
function getRecords() {
    global $config;
    
    $apiUrl = "{$config['api_v2_url']}{$config['table_id']}/records?offset=0&limit=50";
    $result = makeApiRequest($apiUrl);
    
    if ($result['httpCode'] !== 200) {
        handleError("Impossible de récupérer les enregistrements", $result['response'], $result['httpCode']);
        return [];
    }
    
    $data = json_decode($result['response'], true);
    return $data['list'] ?? [];
}

// === MAIN PROGRAM LOGIC ===

// Process delete request
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $recordId = filter_input(INPUT_GET, 'delete', FILTER_SANITIZE_STRING);
    
    if (deleteRecord($recordId)) {
        header("Location: contenu-ok.php");
        exit;
    }
}

// Process detailed record update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Id'])) {
    $id = filter_input(INPUT_POST, 'Id', FILTER_SANITIZE_STRING);
    
    $data = [
        'Final_Title' => filter_input(INPUT_POST, 'Final_Title', FILTER_SANITIZE_STRING) ?? '',
        'Meta_description' => filter_input(INPUT_POST, 'Meta_description', FILTER_SANITIZE_STRING) ?? '',
        'Projets' => filter_input(INPUT_POST, 'Projets', FILTER_SANITIZE_STRING) ?? '',
        'Persona' => filter_input(INPUT_POST, 'Persona', FILTER_SANITIZE_STRING) ?? '',
        'key_takeaways' => filter_input(INPUT_POST, 'key_takeaways', FILTER_SANITIZE_STRING) ?? '',
        'final_article' => $_POST['final_article'] ?? '', // Avoid sanitizing article content to preserve HTML
    ];
    
    if (updateRecord($id, $data)) {
        header("Location: contenu-ok.php");
        exit;
    }
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

// Fetch records and generate the page
$rows = getRecords();


// Extract unique projects and sort them
$projets = array_unique(array_filter(array_map(function($r) {
    return $r['Projets'] ?? '';
}, $rows)));
sort($projets);

// Get selected project filter
$projetFiltre = filter_input(INPUT_GET, 'projet', FILTER_SANITIZE_STRING) ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Articles générés</title>
        <link rel="stylesheet" href="/style.css">

</head>
<body>

<?php include("nav.php"); ?>

    <h1>Articles générés</h1>
    
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
    </form>

    <form method="POST" action="" id="articles_form">
        <table>
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Projet</th>
                    <th>Publication automatique</th>
                    <th>État</th>
                    <th>Supprimer</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rowCount = 0;
                foreach ($rows as $row): 
                    if ($projetFiltre && ($row['Projets'] ?? '') !== $projetFiltre) continue;
                    $rowCount++;
                ?>
                <tr>
                    <td>
                        <a href="articles.php?id=<?= htmlspecialchars($row['Id']) ?>">
                            <?= htmlspecialchars($row['Final_Title'] ?? 'Titre non défini') ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($row['Projets'] ?? '') ?></td>
                    <td>
                        <input type="hidden" name="auto_publish[<?= htmlspecialchars($row['Id']) ?>]" value="off">
                        <input type="checkbox" name="auto_publish[<?= htmlspecialchars($row['Id']) ?>]" value="on"
                               <?= isset($row['auto_publish']) && $row['auto_publish'] ? 'checked' : '' ?>>
                    </td>
                    <td><?= htmlspecialchars($row['published_status'] ?? 'en attente') ?></td>
                    <td>
                        <a class="delete-btn" href="?delete=<?= htmlspecialchars($row['Id']) ?>" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">🗑</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if ($rowCount === 0): ?>
                <tr>
                    <td colspan="5" style="text-align: center;">Aucun article trouvé<?= $projetFiltre ? ' pour ce projet' : '' ?>.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($rowCount > 0): ?>
        <button type="submit">Enregistrer les modifications</button>
        <?php endif; ?>
    </form>

    <script>
        // Add client-side validation and confirmation if needed
        document.getElementById('articles_form').addEventListener('submit', function(e) {
            if (!confirm('Voulez-vous enregistrer ces modifications ?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
