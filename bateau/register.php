<?php
// Démarre la session
session_start();

// Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = "Email et mot de passe requis.";
    } else {
        // Hash sécurisé du mot de passe
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Configuration NocoDB
        $project = "D4SEO_KEYWORD_CLUSTER_ARTICLE"; // ⚠️ nom exact du projet
        $table = "users";
        $base_url = "https://nocodb.inonobu.fr/api/v1/db/data/v1";
        $token = "OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47";

        $url = "$base_url/$project/$table";

        $headers = [
            "Content-Type: application/json",
            "accept: application/json",
            "xc-token: $token"
        ];

        $data = [
            "email" => $email,
            "password_hash" => $password_hash
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            $success = "Utilisateur enregistré avec succès. <a href='login.php'>Se connecter</a>";
        } else {
            $error = "Erreur à la création de l'utilisateur : $response";
        }
    }
}
?>

<!-- Formulaire HTML -->
<form method="POST">
    <input type="email" name="email" required placeholder="Email">
    <input type="password" name="password" required placeholder="Mot de passe">
    <button type="submit">Créer mon compte</button>
</form>

<?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php if (isset($success)) echo "<p style='color:green;'>$success</p>"; ?>
