<?php die("DEBUG DEBUT DU FICHIER");
// Include WordPress categories functionality
require __DIR__ . '/get_wp_categories.php';

// === CONFIGURATION ===
$config = [
    'project' => 'D4SEO_KEYWORD_CLUSTER_ARTICLE',
    'table' => 'Article_ecrit',
    'api_token' => 'OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47',
    'api_base_url' => 'https://nocodb.inonobu.fr/api/v1/db/data/v1/',
    'n8n_webhook' => 'https://n8n.evolu8.fr/webhook-test/98cbf91e-e031-4391-8029-5e7e1ba12254'
];

// === ERROR HANDLING FUNCTIONS ===
function displayError($message, $response = null, $httpCode = null) {
    echo '<div style="color: red; background-color: #ffeeee; padding: 15px; border: 1px solid #ffcccc; margin: 15px 0;">';
    echo '<strong>Erreur:</strong> ' . htmlspecialchars($message);
    if ($httpCode) echo " (Code HTTP: $httpCode)";
    if ($response) echo "<pre>" . htmlspecialchars($response) . "</pre>";
    echo '</div>';
    echo '<p><a href="contenu-ok.php">← Retour à la liste</a></p>';
}

// === API HELPER FUNCTIONS ===
function makeApiRequest($url, $method = 'GET', $data = null, $headers = []) {
    global $config;
    
    $ch = curl_init($url);
    
    // Default headers for NocoDB API
    $defaultApiHeaders = [
        "accept: application/json",
        "xc-token: {$config['api_token']}"
    ];
    
    // Default headers for other API calls (like N8N)
    $defaultOtherHeaders = [
        "Content-Type: application/json"
    ];
    
    // Determine if this is a NocoDB API call
    $isNocoDbCall = strpos($url, 'nocodb.inonobu.fr') !== false;
    
    // Set appropriate default headers
    $defaultHeaders = $isNocoDbCall ? $defaultApiHeaders : $defaultOtherHeaders;
    
    // Add Content-Type header if we're sending data
    if ($data !== null && $method !== 'GET' && $isNocoDbCall) {
        $defaultHeaders[] = "Content-Type: application/json";
    }
    
    // Merge with custom headers
    $headers = array_merge($defaultHeaders, $headers);
    
    // Set cURL options
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
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Return the result
    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'error' => $error
    ];
}

// Function to get an article by ID
function getArticle($id) {
    global $config;
    $url = "{$config['api_base_url']}{$config['project']}/{$config['table']}/" . urlencode($id);
    return makeApiRequest($url);
}

// Function to update an article
function updateArticle($id, $data) {
    global $config;
    $url = "{$config['api_base_url']}{$config['project']}/{$config['table']}/" . urlencode($id);
    return makeApiRequest($url, 'PATCH', $data);
}

// Function to publish article to WordPress via N8N
function publishToWordPress($data) {
    global $config;
    return makeApiRequest($config['n8n_webhook'], 'POST', $data);
}

// Function to get all projects
function getProjects() {
    global $config;
    $url = "{$config['api_base_url']}{$config['project']}/Projets";
    $result = makeApiRequest($url);
    
    if ($result['httpCode'] !== 200) {
        return [];
    }
    
    $data = json_decode($result['response'], true);
    return isset($data['list']) ? $data['list'] : [];
}

// === VALIDATE ID IN URL ===
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_STRING);

if (empty($id)) {
    displayError("ID manquant. Veuillez spécifier un identifiant d'article valide.");
    exit;
}

// === GET ARTICLE DATA ===
$articleResult = getArticle($id);

echo "<pre>";
echo "Réponse brute de l'API :\n\n";
print_r($articleResult['response']);
echo "</pre>";
exit;

if ($articleResult['httpCode'] !== 200) {
    displayError("Impossible de récupérer l'article", $articleResult['response'], $articleResult['httpCode']);
    exit;
}

$article = json_decode($articleResult['response'], true);

if (!$article || isset($article['message'])) {
    displayError("Article introuvable ou format de réponse invalide.");
    exit;
}

// === GET PROJECTS ===
$projects = getProjects();

// === PROCESS FORM SUBMISSIONS ===

// Handle article publishing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish') {
    // Sanitize inputs
    $title = filter_input(INPUT_POST, 'Final_Title', FILTER_SANITIZE_STRING);
    $metaDescription = filter_input(INPUT_POST, 'Meta_description', FILTER_SANITIZE_STRING);
    $projet = filter_input(INPUT_POST, 'Projets', FILTER_SANITIZE_STRING);
    $categorie = filter_input(INPUT_POST, 'Categorie_wp', FILTER_SANITIZE_STRING);
    $image_prompt = filter_input(INPUT_POST, 'image_prompt', FILTER_SANITIZE_STRING);
    
    // Content should not be sanitized to preserve HTML formatting
    $content = $_POST['final_article'] ?? '';
    
    // Prepare data for N8N webhook
    $payload = [
        'title' => $title,
        'content' => $content,
        'meta_description' => $metaDescription,
        'projet' => $projet,
        'Categorie_wp' => $categorie,
        'image_prompt' => $image_prompt,
    ];
    
    // Send to WordPress via N8N webhook
    $publishResult = publishToWordPress($payload);
    
    if ($publishResult['httpCode'] === 200 || $publishResult['httpCode'] === 204) {
        // Update the article status in NocoDB
        $updateData = [
            'published_status' => 'publié'
        ];
        
        updateArticle($id, $updateData);
        
        // Redirect with success message
        header("Location: article.php?id=$id&published=1");
        exit;
    } else {
        displayError("Erreur lors de la publication vers WordPress", $publishResult['response'], $publishResult['httpCode']);
        exit;
    }
}

// Handle article updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    // Sanitize inputs
    $title = filter_input(INPUT_POST, 'Final_Title', FILTER_SANITIZE_STRING);
    $projet = filter_input(INPUT_POST, 'Projets', FILTER_SANITIZE_STRING);
    $metaDescription = filter_input(INPUT_POST, 'Meta_description', FILTER_SANITIZE_STRING);
    $keyTakeaways = filter_input(INPUT_POST, 'key_takeaways', FILTER_SANITIZE_STRING);
    $categorie = filter_input(INPUT_POST, 'Categorie_wp', FILTER_SANITIZE_STRING);
    $image_prompt = filter_input(INPUT_POST, 'image_prompt', FILTER_SANITIZE_STRING);

    
    // Content should not be sanitized to preserve HTML formatting
    $content = $_POST['final_article'] ?? '';
    
    $data = [
        'Final_Title' => $title,
        'Projets' => $projet,
        'Meta_description' => $metaDescription,
        'key_takeaways' => $keyTakeaways,
        'final_article' => $content,
        'Categorie_wp' => $categorie,
        'image_prompt' => $image_prompt,
    ];
    
    $updateResult = updateArticle($id, $data);
    
    if ($updateResult['httpCode'] !== 200) {
        displayError("Erreur lors de la mise à jour de l'article", $updateResult['response'], $updateResult['httpCode']);
        exit;
    }
    
    // Refresh article data
    $articleResult = getArticle($id);
    $article = json_decode($articleResult['response'], true);
    
    // Redirect with success message
    header("Location: article.php?id=$id&updated=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($article['Final_Title'] ?? 'Modifier l\'article') ?></title>
    <link rel="stylesheet" href="/style.css">

    <style>
     
    </style>
</head>
<body>
<nav>
<ul>
<li>
<a href="monform.php">Accueil</a>
</li>
<li>
<a href="contenu-ok.php">Articles écrit</a>
</li>
<li>
<a href="flux_rss.php">FLux RSS</a>
</li>
<li>
<a href="form_idea_keyword.php">Recherche idée contenu</a>
</li>
<li>
<a href="idee_contenu_cluster.php">Idée de contenu par Cluster</a>
</li>
<li>
<a href="idee_contenu_keyword.php">Idée de contenu par Keyword</a>
</li></ul>
</nav>
    <h1>Modifier l'article</h1>
    
        <p><a href="contenu-ok.php">← Retour à la liste</a></p>


    <?php if (isset($_GET['updated'])): ?>
        <div class="success-message">
            <strong>✅ Succès!</strong> Modifications enregistrées dans NocoDB
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['published'])): ?>
        <div class="success-message">
            <strong>🚀 Succès!</strong> Article envoyé à WordPress via N8N
        </div>
    <?php endif; ?>

    <form method="POST">
        <label for="Final_Title">Titre</label>
        <input type="text" name="Final_Title" id="Final_Title" value="<?= htmlspecialchars($article['Final_Title'] ?? '') ?>" required>

        <label for="Projets">Projet</label>
        <select name="Projets" id="Projets">
            <option value="">Sélectionnez un projet</option>
            <?php foreach ($projects as $project): ?>
                <?php if (!empty($project['Projet'])): ?>
                    <option value="<?= htmlspecialchars($project['Projet']) ?>" 
                            <?= (isset($article['Projets']) && $article['Projets'] == $project['Projet']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($project['Projet']) ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>

        <label for="Meta_description">Meta description</label>
        <textarea name="Meta_description" id="Meta_description" rows="3"><?= htmlspecialchars($article['Meta_description'] ?? '') ?></textarea>

        <label for="key_takeaways">Key Takeaways</label>
        <textarea name="key_takeaways" id="key_takeaways" rows="6"><?= htmlspecialchars($article['key_takeaways'] ?? '') ?></textarea>

        <label for="image_prompt"> Image_prompt</label>
        <textarea name="image_prompt" id="image_prompt" rows="6"><?= htmlspecialchars($article['image_prompt'] ?? '') ?></textarea>

        <label for="final_article">Article</label>
        <textarea name="final_article" id="final_article" rows="15"><?= htmlspecialchars($article['final_article'] ?? '') ?></textarea>
        
        <label for="Categorie_wp">Catégorie</label>
        <select name="Categorie_wp" id="Categorie_wp">
            <option value="no_categorie" <?= (empty($article['Categorie_wp']) || $article['Categorie_wp'] === 'no_categorie') ? 'selected' : '' ?>>
                No catégorie
            </option>
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category['id']) ?>" 
                            <?= (isset($article['Categorie_wp']) && $article['Categorie_wp'] == $category['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="" disabled>Aucune catégorie trouvée</option>
            <?php endif; ?>
        </select>

        <button type="submit">💾 Enregistrer</button>
    </form>
    
    <hr>
    
    <div class="publish-form">
        <h2>Publier l'article dans WordPress</h2>
        <p>Cette action enverra l'article à WordPress via N8N.</p>
        
        <form method="post">
            <input type="hidden" name="action" value="publish">
            <input type="hidden" name="Final_Title" value="<?= htmlspecialchars($article['Final_Title'] ?? '') ?>">
            <input type="hidden" name="final_article" value="<?= htmlspecialchars($article['final_article'] ?? '') ?>">
            <input type="hidden" name="Meta_description" value="<?= htmlspecialchars($article['Meta_description'] ?? '') ?>">
            <input type="hidden" name="Projets" value="<?= htmlspecialchars($article['Projets'] ?? '') ?>">
            <input type="hidden" name="Categorie_wp" value="<?= htmlspecialchars($article['Categorie_wp'] ?? '') ?>">
            <input type="hidden" name="image_prompt" value="<?= htmlspecialchars($article['image_prompt'] ?? '') ?>">
            
            <button type="submit" class="publish-button">🚀 Publier dans WordPress</button>
        </form>
    </div>

    <p><a href="contenu-ok.php">← Retour à la liste</a></p>
</body>
</html>
