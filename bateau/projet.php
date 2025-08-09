<?php

require 'auth.php';
requireLogin(); // redirige vers login.php si non connecté

// === CONFIGURATION ===
$project = 'D4SEO_KEYWORD_CLUSTER_ARTICLE';
$api_token = 'OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47';
$base_url = "https://nocodb.inonobu.fr/api/v1/db/data/v1/$project/";

function apiRequest($method, $endpoint, $data = null) {
    global $api_token;
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/json",
        "Content-Type: application/json",
        "xc-token: $api_token"
    ]);
    if ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $http_code,
        'data' => json_decode($response, true)
    ];
}

// === TRAITEMENT AJAX / FORMULAIRES ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Création
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $data = [
            'Projet'        => $_POST['projet'] ?? '',
            'site'          => $_POST['site'] ?? '',
            'mdp_app_wp'    => $_POST['mdp_app_wp'] ?? '',
            'publish_count' => intval($_POST['publish_count'] ?? 1),
            'publish_period'=> $_POST['publish_period'] ?? 'day',
            'publish_time'  => $_POST['publish_time'] ?? '09:00'
        ];
        $create = apiRequest('POST', $base_url . "Projets", $data);
        echo json_encode(['success' => in_array($create['status'], [200, 201])]);
        exit;
    }

    // Update
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'] ?? '';
        $data = [
            'site'          => $_POST['site'] ?? '',
            'mdp_app_wp'    => $_POST['mdp_app_wp'] ?? '',
            'publish_count' => intval($_POST['publish_count'] ?? 1),
            'publish_period'=> $_POST['publish_period'] ?? 'day',
            'publish_time'  => $_POST['publish_time'] ?? '09:00'
        ];
        $update = apiRequest('PATCH', $base_url . "Projets/" . urlencode($id), $data);
        echo json_encode(['success' => in_array($update['status'], [200, 204])]);
        exit;
    }

    // Delete
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $projectName = $_POST['project_name'] ?? '';
        $tables = [
            'Master All KW Variations',
            'Keyword Categories',
            'Content_Ideas_from_Keywords',
            'Clusters',
            'Content_Ideas_from_Clusters',
            'Article_ecrit',
            'Flux_rss',
            'Article_from_rss',
            'Projets'
        ];

        foreach ($tables as $table) {
            $field = ($table === 'Article_ecrit') ? 'Projets' : 'Projet';
            $url = $base_url . rawurlencode($table) . "?where=(" . urlencode($field) . ",eq," . urlencode($projectName) . ")";
            $get = apiRequest('GET', $url);
            if (!empty($get['data']['list'])) {
                foreach ($get['data']['list'] as $record) {
                    $id = $record['Id'] ?? $record['id'] ?? null;
                    if ($id) {
                        $deleteUrl = $base_url . rawurlencode($table) . "/" . urlencode($id);
                        apiRequest('DELETE', $deleteUrl);
                    }
                }
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action invalide']);
    exit;
}

// === Récupération des projets pour affichage ===
$projects = apiRequest('GET', $base_url . 'Projets')['data']['list'] ?? [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des projets</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; }
        form.project-form, form#add-project-form { margin-bottom: 2rem; border: 1px solid #ccc; padding: 1rem; border-radius: 8px; }
        input, select { margin-bottom: 1rem; display: block; width: 100%; padding: 0.5rem; }
        button { padding: 0.5rem 1rem; margin-right: 10px; }
        .success-message, .error-message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success-message { background: #e0ffe0; color: #0a0; }
        .error-message { background: #ffe0e0; color: #a00; }
    </style>
     <link rel="stylesheet" href="/style.css">

    <script>
    // Création projet
    function createProject(form, messageDiv) {
        const formData = new FormData(form);
        formData.append('action', 'create');
        fetch('projet.php', {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            messageDiv.textContent = data.success ? 'Projet créé avec succès' : 'Erreur à la création';
            messageDiv.className = data.success ? 'success-message' : 'error-message';
            if (data.success) setTimeout(() => location.reload(), 800);
            else setTimeout(() => messageDiv.textContent = '', 3000);
        });
    }

    // MAJ projet
    function updateProjectFields(form, id, messageDiv) {
        fetch('projet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'update',
                id: id,
                site: form.site.value,
                mdp_app_wp: form.mdp_app_wp.value,
                publish_count: form.publish_count.value,
                publish_period: form.publish_period.value,
                publish_time: form.publish_time.value
            })
        }).then(res => res.json()).then(data => {
            messageDiv.textContent = data.success ? 'Projet mis à jour avec succès' : 'Erreur de mise à jour';
            messageDiv.className = data.success ? 'success-message' : 'error-message';
            setTimeout(() => messageDiv.textContent = '', 3000);
        });
    }

    // Suppression projet
    function deleteProject(projName, formElement) {
        if (!confirm('Supprimer ce projet et toutes ses données ?')) return;
        fetch('projet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'delete',
                project_name: projName
            })
        }).then(res => res.json()).then(data => {
            if (data.success) formElement.remove();
        });
    }
    </script>
</head>
<body>
<?php include("nav.php"); ?>
<h1>Projets</h1>

<h2>Créer un projet</h2>
<form id="add-project-form" onsubmit="event.preventDefault(); createProject(this, document.getElementById('add-project-message'));">
    <label for="projet_input">Nom du projet</label>
    <input type="text" id="projet_input" name="projet" required>

    <label for="site">Site (sans https, que le domaine)</label>
    <input type="text" id="site" name="site" required>

    <label for="mdp_app_wp">
        Mot de passe application WordPress (base64)<br>
        <a href="https://www.base64encode.org/fr/" target="_blank">Base64 encoder</a>
    </label>
    <input type="password" id="mdp_app_wp" name="mdp_app_wp">

    <label for="publish_count">Nombre de publications</label>
    <input type="number" id="publish_count" name="publish_count" min="1" value="1" required>

    <label for="publish_period">Période</label>
    <select id="publish_period" name="publish_period" required>
        <option value="day">Jour</option>
        <option value="week">Semaine</option>
    </select>

    <label for="publish_time">Heure de publication</label>
    <input type="time" id="publish_time" name="publish_time" value="09:00" required>

    <button type="submit">Créer le projet</button>
    <div id="add-project-message"></div>
</form>

<hr>

<h2>Modifier les projets</h2>
<?php foreach ($projects as $proj): ?>
    <form class="project-form" onsubmit="event.preventDefault(); updateProjectFields(this, '<?= $proj['Id'] ?>', document.getElementById('message-<?= $proj['Id'] ?>'))">
        <label>Nom du projet</label>
        <input type="text" value="<?= htmlspecialchars($proj['Projet']) ?>" disabled>

        <label>Site</label>
        <input type="text" name="site" value="<?= htmlspecialchars($proj['site']) ?>">

        <label>Mot de passe (base64)</label>
        <input type="text" name="mdp_app_wp" value="<?= htmlspecialchars($proj['mdp_app_wp']) ?>">

        <label>Nombre de publications</label>
        <input type="number" min="1" name="publish_count" value="<?= htmlspecialchars($proj['publish_count'] ?? 1) ?>">

        <label>Période</label>
        <select name="publish_period">
            <option value="day" <?= (isset($proj['publish_period']) && $proj['publish_period'] === 'day') ? 'selected' : '' ?>>Jour</option>
            <option value="week" <?= (isset($proj['publish_period']) && $proj['publish_period'] === 'week') ? 'selected' : '' ?>>Semaine</option>
        </select>

        <label>Heure de publication</label>
        <input type="time" name="publish_time" value="<?= htmlspecialchars($proj['publish_time'] ?? '09:00') ?>">

        <div id="message-<?= $proj['Id'] ?>"></div>

        <button type="submit">Mettre à jour</button>
        <button type="button" onclick="deleteProject('<?= htmlspecialchars($proj['Projet']) ?>', this.form)">🗑 Supprimer le projet</button>
    </form>
<?php endforeach; ?>

</body>
</html>
