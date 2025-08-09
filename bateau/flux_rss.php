<?php

require 'auth.php';
requireLogin(); // redirige vers login.php si non connecté

/**
 * Gestion des flux RSS et des articles via NocoDB
 * Optimisé pour la performance et la sécurité
 */

// Configuration NocoDB et constantes
define('API_BASE_URL', 'https://nocodb.inonobu.fr/api');
define('API_TOKEN', 'OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47');
define('PROJECT_ID', 'D4SEO_KEYWORD_CLUSTER_ARTICLE');
define('TABLE_FLUX', 'Flux_rss');
define('TABLE_ARTICLES', 'Article_from_rss');
define('WEBHOOK_URL', 'https://n8n.evolu8.fr/webhook/2c558c07-65c6-41a4-a61f-98e975a9d288');
define('WEBHOOK_PERSONAS', 'https://n8n.evolu8.fr/webhook/8a2879ae-f457-439e-b5bb-d7a29289271c');

$success = false;
$error = false;

// Classe pour les requêtes API
class ApiClient {
    private static function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
        $defaultHeaders = [
            'accept: application/json',
            'xc-token: ' . API_TOKEN
        ];
        
        if ($method === 'POST' || $method === 'PATCH') {
            $defaultHeaders[] = 'Content-Type: application/json';
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($data && ($method === 'POST' || $method === 'PATCH')) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("Erreur cURL: $error");
            return false;
        }
        
        return ['code' => $httpCode, 'response' => json_decode($response, true)];
    }
    
    public static function getProjects() {
        $url = API_BASE_URL . "/v1/db/data/v1/" . PROJECT_ID . "/Projets";
        $result = self::makeRequest($url);
        return $result['response']['list'] ?? [];
    }
    
    public static function getAllFlux() {
        $url = API_BASE_URL . "/v2/tables/mnj5dlvpeqkzq92/records";
        $result = self::makeRequest($url);
        return $result['response']['list'] ?? [];
    }
    
    public static function createFlux($data) {
        $url = API_BASE_URL . "/v2/tables/mnj5dlvpeqkzq92/records";
        return self::makeRequest($url, 'POST', $data);
    }
    
    public static function deleteFlux($id) {
        $url = API_BASE_URL . "/v1/db/data/v1/" . PROJECT_ID . "/" . TABLE_FLUX . "/" . $id;
        return self::makeRequest($url, 'DELETE');
    }
    
    public static function toggleFlux($id, $newValue) {
        $url = API_BASE_URL . "/v1/db/data/v1/" . PROJECT_ID . "/" . TABLE_FLUX . "/" . $id;
        return self::makeRequest($url, 'PATCH', ['actif' => $newValue]);
    }
    
    public static function getAllArticles() {
        $articles = [];
        $limit = 100;
        $offset = 0;
        
        do {
            $url = API_BASE_URL . "/v1/db/data/v1/" . PROJECT_ID . "/" . TABLE_ARTICLES . "?limit=$limit&offset=$offset";
            $result = self::makeRequest($url);
            $fetchedArticles = $result['response']['list'] ?? [];
            
            $articles = array_merge($articles, $fetchedArticles);
            $offset += $limit;
        } while (count($fetchedArticles) === $limit);
        
        return $articles;
    }
    
    public static function getArticleById($id) {
        $url = API_BASE_URL . "/v1/db/data/v1/" . PROJECT_ID . "/" . TABLE_ARTICLES . "/" . urlencode($id);
        $result = self::makeRequest($url);
        
        if ($result['code'] != 200) {
            return false;
        }
        
        return $result['response'];
    }
    
    public static function deleteArticle($id) {
        $url = API_BASE_URL . "/v1/db/data/v1/" . PROJECT_ID . "/" . TABLE_ARTICLES . "/" . urlencode($id);
        return self::makeRequest($url, 'DELETE');
    }
    
    public static function fetchWebhookData($url) {
        return self::makeRequest($url)['response'] ?? [];
    }
    
    public static function sendToWebhook($url, $data) {
        $headers = ['Content-Type: application/json'];
        return self::makeRequest($url, 'POST', $data, $headers);
    }
}

// Sécurité: Fonction pour valider les entrées
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

// Gérer les actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Création d'un flux RSS
    if (isset($_POST['create_flux'])) {
        if (isset($_POST['nom_site'], $_POST['rss_url'], $_POST['projet'])) {
            $data = [
                'nom_site' => $_POST['nom_site'],
                'rss_url' => $_POST['rss_url'],
                'Projet' => $_POST['projet'],
                'actif' => true
            ];
            
            ApiClient::createFlux($data);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
            exit;
        }
    }
    
    // Suppression multiple d'articles
    if (isset($_POST['delete_articles'])) {
        if (isset($_POST['selected_articles']) && is_array($_POST['selected_articles'])) {
            foreach ($_POST['selected_articles'] as $articleId) {
                ApiClient::deleteArticle($articleId);
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Envoi d'un article (traité via GET mais avec données POST)
    if (isset($_POST['send_article_id'])) {
        $record_id = $_POST['send_article_id'];
        $persona = $_POST['persona'][$record_id] ?? '';
        
        $article = ApiClient::getArticleById($record_id);
        
        if (!$article) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?error=article_not_found');
            exit;
        }
        
        $data_to_send = [
            'titre' => $article['titre_rss'] ?? '',
            'contenu' => $article['contenu_rss'] ?? '',
            'date_pub' => $article['date_pub_rss'] ?? '',
            'url' => $article['url_article_rss'] ?? '',
            'persona' => $persona,
            'nom_site' => $article['nom_site_rss'] ?? '',
            'projet' => $article['Projet'] ?? ''
        ];
        
        ApiClient::sendToWebhook(WEBHOOK_URL, $data_to_send);
        
        // Log pour débogage
        file_put_contents('webhook_debug.txt', json_encode($data_to_send));
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?success_article=1');
        exit;
    }
}

// Gérer les actions GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Suppression d'un flux
    if (isset($_GET['delete'])) {
        ApiClient::deleteFlux($_GET['delete']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Changer l'état actif d'un flux
    if (isset($_GET['toggle']) && isset($_GET['value'])) {
        $current_value = $_GET['value'] === '1';
        ApiClient::toggleFlux($_GET['toggle'], !$current_value);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Suppression d'un article
    if (isset($_GET['delete_article'])) {
        ApiClient::deleteArticle($_GET['delete_article']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Confirmation d'ajout de flux
    if (isset($_GET['success']) && $_GET['success'] == 1) {
        $success = true;
    }
    
    // Message d'erreur
    if (isset($_GET['error'])) {
        $error = $_GET['error'];
    }
}

// Récupération des données
$projects = ApiClient::getProjects();
$flux = ApiClient::getAllFlux();
$articles = ApiClient::getAllArticles();

// Récupération des personas
$personas_data = ApiClient::fetchWebhookData(WEBHOOK_PERSONAS);
$personas_list = [];
if (isset($personas_data['persona']) && is_array($personas_data['persona'])) {
    foreach ($personas_data['persona'] as $item) {
        if (isset($item['Title'])) {
            $personas_list[] = $item['Title'];
        }
    }
}

// Filtrer les flux si un projet est sélectionné
$filtreProjet = $_GET['filtre_projet'] ?? '';
if (!empty($filtreProjet)) {
    $flux = array_filter($flux, function ($f) use ($filtreProjet) {
        return isset($f['Projet']) && $f['Projet'] === $filtreProjet;
    });
}

// Récupérer tous les projets présents dans les articles (pour le filtre)
$projets_articles = array_unique(array_column($articles, 'Projet'));
sort($projets_articles);

// Filtrage des articles par projet
$selected_article_filter = $_GET['filtre_article'] ?? '';
if ($selected_article_filter !== '') {
    $articles = array_filter($articles, function($article) use ($selected_article_filter) {
        return isset($article['Projet']) && $article['Projet'] === $selected_article_filter;
    });
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Flux RSS & Articles (via NocoDB)</title>
    <link rel="stylesheet" href="/style.css">

    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --light-bg: #d7dee3;
            --border-color: #ddd;
            --success-color: #28a745;
            --danger-color: #dc3545;
        }
        
        * {
            box-sizing: border-box;
        }
    
        
        main {
            max-width: 1280px;
            margin: 0 auto;
        }
        
        h1, h2 {
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            overflow-x: auto;
            display: block;
        }
        
        @media (min-width: 1024px) {
            table {
                display: table;
            }
        }
        
        table th, table td {
            padding: 10px;
            border: 1px solid var(--border-color);
            vertical-align: top;
            word-wrap: break-word;
        }
        
        .content-cell {
            max-height: 100px;
            overflow: hidden;
            position: relative;
            word-wrap: break-word;
        }
        
        .content-cell.expanded {
            max-height: none;
        }
        
        .content-cell-scroll {
            max-height: 100px;
            overflow-y: auto;
            padding-right: 5px;
            white-space: normal;
        }
        
        .petit_contenu {
            width: 300px;
        }
        
        .toggle-btn {
            margin-top: 5px;
            font-size: 0.8em;
            cursor: pointer;
            border: none;
            background-color: var(--primary-color);
            color: white;
            padding: 3px 7px;
            border-radius: 3px;
        }
        
        .toggle-btn:hover {
            background-color: var(--primary-hover);
        }
        
        .formN {
            width: 100%;
            max-width: 800px;
            padding: 2rem;
            background: var(--light-bg);
            border-radius: 1rem;
            margin-bottom: 2rem;
        }
        
        .formN input, .formN select, .formN button {
            padding: 8px;
            margin: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        
        .formN button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .formN button:hover {
            background-color: var(--primary-hover);
        }
        
        .filter-controls {
            margin-bottom: 20px;
        }
        
        .action-btn {
            display: inline-block;
            padding: 3px 8px;
            margin: 2px;
            text-decoration: none;
            border-radius: 3px;
            color: white;
        }
        
        .action-btn.delete {
            background-color: var(--danger-color);
        }
        
        .action-btn.send {
            background-color: var(--primary-color);
        }
        
        .action-btn:hover {
            opacity: 0.9;
        }
        
        .success-msg {
            color: var(--success-color);
            padding: 10px;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: 4px;
        }
        
        .error-msg {
            color: var(--danger-color);
            padding: 10px;
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: 4px;
        }
    </style>
</head>
<body>

<?php include("nav.php"); ?>


    <main>
        <h1>Ajouter un flux RSS</h1>

<a href="https://morss.it">https://morss.it</a> (pour générer des faux flux rss / Choisir feed as "json").

        <?php if ($success): ?>
            <div class="success-msg">✅ Flux RSS bien ajouté !</div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-msg">❌ Erreur: <?= sanitizeInput($error) ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success_article']) && $_GET['success_article'] == 1): ?>
    <div style="background: #e6ffed; border: 1px solid #b2f2bb; padding: 10px; margin: 10px 0; color: #2f8132;">
        ✅ Article envoyé avec succès au webhook !
    </div>
<?php endif; ?>

        <!-- Formulaire de création d'un flux RSS -->
        <form method="POST" class="formN">
            <input type="text" name="nom_site" placeholder="Nom du site" required>
            <input type="url" name="rss_url" placeholder="URL du flux RSS" required>
            <select name="projet" required>
                <option value="">-- Choisir un projet --</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= sanitizeInput($p['Projet']) ?>">
                        <?= sanitizeInput($p['Projet']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="create_flux">Ajouter</button>
        </form>

        <h2>Liste des flux enregistrés</h2>
        
        <!-- Filtre des flux par projet -->
        <div class="filter-controls">
            <h3>Trier par projet</h3>
            <form method="GET">
                <select name="filtre_projet" onchange="this.form.submit()">
                    <option value="">-- Tous les projets --</option>
                    <?php foreach ($projects as $p): 
                        $selected = ($filtreProjet === $p['Projet']) ? 'selected' : '';
                    ?>
                        <option value="<?= sanitizeInput($p['Projet']) ?>" <?= $selected ?>>
                            <?= sanitizeInput($p['Projet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <table class="formN">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom du site</th>
                    <th>URL</th>
                    <th>Projet</th>
                    <th>Actif</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($flux as $f): ?>
                    <tr>
                        <td><?= sanitizeInput($f['Id']) ?></td>
                        <td><?= sanitizeInput($f['nom_site']) ?></td>
                        <td><?= sanitizeInput($f['rss_url']) ?></td>
                        <td><?= sanitizeInput($f['Projet'] ?? '') ?></td>
                        <td><?= $f['actif'] ? '✅' : '⛔' ?></td>
                        <td>
                            <a href="?toggle=<?= $f['Id'] ?>&value=<?= $f['actif'] ? '1' : '0' ?>" class="action-btn">
                                <?= $f['actif'] ? 'Mettre en pause' : 'Réactiver' ?>
                            </a>
                            <a href="?delete=<?= $f['Id'] ?>" onclick="return confirm('Supprimer ce flux ?')" class="action-btn delete">🗑️ Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <hr>

        <!-- Partie Articles -->
        <h2>Articles enregistrés</h2>

        <!-- Filtre des articles par projet -->
        <div class="filter-controls">
            <form method="GET" action="">
                <label for="filtre_article">Filtrer par projet :</label>
                <select name="filtre_article" id="filtre_article" onchange="this.form.submit()">
                    <option value="">-- Tous les projets --</option>
                    <?php foreach ($projets_articles as $projet): ?>
                        <option value="<?= sanitizeInput($projet) ?>" <?= $selected_article_filter === $projet ? 'selected' : '' ?>>
                            <?= sanitizeInput($projet) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>


        <!-- Formulaire pour suppression multiple des articles -->
        <form method="POST" action="" id="articles_form">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select_all"></th>
                        <th>Titre</th>
                        <th>Contenu</th>
                        <th>Date de publication</th>
                        <th>URL</th>
                        <th>Nom du site</th>
                        <th>Persona</th>
                        <th>Projet</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $a): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_articles[]" value="<?= sanitizeInput($a['Id']) ?>"></td>
                            <td><?= sanitizeInput($a['titre_rss'] ?? '') ?></td>
                            <td>
                                <div class="content-cell content-cell-scroll petit_contenu"><?= nl2br(sanitizeInput($a['contenu_rss'] ?? '')) ?></div>
                                <button type="button" class="toggle-btn">Afficher plus</button>
                            </td>
                            <td><?= sanitizeInput($a['date_pub_rss'] ?? '') ?></td>
                            <td><a href="<?= sanitizeInput($a['url_article_rss'] ?? '#') ?>" target="_blank">Voir</a></td>
                            <td><?= sanitizeInput($a['nom_site_rss'] ?? '') ?></td>
                            <td>
                                <!-- Sélecteur de Persona -->
                                <select name="persona[<?= sanitizeInput($a['Id']) ?>]">
                                    <option value="">Choisir une persona</option>
                                    <?php foreach ($personas_list as $persona): ?>
                                        <option value="<?= sanitizeInput($persona) ?>" <?= (isset($a['persona']) && $a['persona'] == $persona) ? 'selected' : '' ?>>
                                            <?= sanitizeInput($persona) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><?= sanitizeInput($a['Projet'] ?? '') ?></td>
                            <td>
                                <button type="button" onclick="sendArticle('<?= $a['Id'] ?>')" class="action-btn send">🚀 Envoyer</button>
                                <a href="?delete_article=<?= urlencode($a['Id']) ?>" onclick="return confirm('Supprimer cet article ?')" class="action-btn delete">🗑️ Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <br>
            <button type="submit" name="delete_articles" onclick="return confirm('Supprimer les articles sélectionnés ?')" class="action-btn delete">🗑️ Supprimer les articles sélectionnés</button>
        </form>

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

            // Script pour gérer l'affichage du contenu
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('toggle-btn')) {
                    const contentCell = e.target.previousElementSibling;
                    contentCell.classList.toggle('expanded');
                    e.target.textContent = contentCell.classList.contains('expanded') ? 'Réduire' : 'Afficher plus';
                }
            });

            // Cocher / Décocher toutes les cases
            document.getElementById('select_all').addEventListener('change', function(e) {
                const checkboxes = document.querySelectorAll('input[name="selected_articles[]"]');
                checkboxes.forEach(checkbox => checkbox.checked = e.target.checked);
            });
        </script>
    </main>
</body>
</html>
