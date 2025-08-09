<?php
require 'auth.php';
requireLogin();

$project = 'D4SEO_KEYWORD_CLUSTER_ARTICLE';
$api_token = 'OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47';
$webhook_personas = "https://n8n.evolu8.fr/webhook/8a2879ae-f457-439e-b5bb-d7a29289271c";
$base_url = "https://nocodb.inonobu.fr/api/v1/db/data/v1/$project/personas";

function apiRequest($method, $url, $data = null) {
    global $api_token;
    $headers = [
        "accept: application/json",
        "Content-Type: application/json",
        "xc-token: $api_token"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $mode = $_POST['mode'] ?? 'manual';
        $title = trim($_POST['title'] ?? '');

        if ($mode === 'manual') {
            $style = trim($_POST['style_manual'] ?? '');
            if ($title && $style) {
                apiRequest('POST', $base_url, [
                    'title' => $title,
                    'style_persona' => $style
                ]);
            }
        } elseif ($mode === 'n8n') {
            $description = trim($_POST['style_n8n'] ?? '');
            if ($title && $description) {
                $payload = json_encode([
                    'title' => $title,
                    'description' => $description
                ]);
                $ch = curl_init($webhook_personas);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }

    if (isset($_POST['update_id'], $_POST['new_style'])) {
        $id = $_POST['update_id'];
        $new = $_POST['new_style'];
        apiRequest('PATCH', "$base_url/$id", ['style_persona' => $new]);
    }

    if (isset($_POST['delete_id'])) {
        $id = $_POST['delete_id'];
        apiRequest('DELETE', "$base_url/$id");
    }

    header("Location: personas.php"); // éviter le repost
    exit;
}

$personas = apiRequest('GET', $base_url)['list'] ?? [];

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des personas</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; }
        form { margin-bottom: 1.5rem; }
        input, textarea, select { padding: 0.5rem; margin-bottom: 1rem; width: 100%; max-width: 600px; }
        button { padding: 0.5rem 1rem; margin-right: 10px; }
    </style>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<?php include("nav.php"); ?>

<h1>Gestion des personas</h1>

<h2>Ajouter un persona</h2>
<form method="POST">
    <label>Nom du persona</label><br>
    <input type="text" name="title" placeholder="Ex : Commercial motivé" required>

    <label>Mode de création</label><br>
    <select name="mode" onchange="toggleMode(this.value)">
        <option value="manual">Saisie manuelle du style</option>
        <option value="n8n">Génération via description (N8N)</option>
    </select>

    <div id="manual-mode">
        <label>Style d’écriture</label><br>
        <textarea name="style_manual" rows="4" placeholder="Style d’écriture ici..."></textarea>
    </div>

    <div id="n8n-mode" style="display: none;">
        <label>Description du persona (ex : Parle comme un expert SEO...)</label><br>
        <textarea name="style_n8n" rows="4" placeholder="Phrase de style à faire interpréter par N8N..."></textarea>
    </div>

    <button type="submit" name="add">Ajouter</button>
</form>

<hr>

<h2>Liste des personas</h2>
<?php foreach ($personas as $persona): ?>
    <form method="POST">
        <input type="hidden" name="update_id" value="<?= $persona['Id'] ?>">

        <strong><?= htmlspecialchars($persona['Title'] ?? 'Sans titre') ?></strong><br>
        <textarea name="new_style" rows="3" required><?= htmlspecialchars($persona['style_persona']) ?></textarea><br>

        <button type="submit">💾 Modifier</button>
        <button type="submit" name="delete_id" value="<?= $persona['Id'] ?>" onclick="return confirm('Supprimer ce persona ?')">🗑 Supprimer</button>
    </form>
<?php endforeach; ?>

<script>
function toggleMode(mode) {
    document.getElementById('manual-mode').style.display = (mode === 'manual') ? 'block' : 'none';
    document.getElementById('n8n-mode').style.display = (mode === 'n8n') ? 'block' : 'none';
}
</script>

</body>
</html>
