<?php
session_start();

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email && $password) {
    // Infos API
    $project = "D4SEO_KEYWORD_CLUSTER_ARTICLE"; // ⚠️ nom exact du projet
    $table = "users";
    $base_url = "https://nocodb.inonobu.fr/api/v1/db/data/v1";
    $token = "OHziyF3fHJQjV6LmVmy9Pf--u7Ai7x6FzrHATo47";

    // Encodage de l’email pour l’URL
    $encoded_email = urlencode($email);

    // Construction de l’URL API V1
    $apiUrl = "$base_url/$project/$table?filter=(email,eq,$encoded_email)";

    // Préparation headers
    $headers = [
        "accept: application/json",
        "xc-token: $token"
    ];

    // Appel CURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);



    $data = json_decode($response, true);

    if (!empty($data['list'])) {
        $user = $data['list'][0];
        $hash = $user['password_hash'];

        if (password_verify($password, $hash)) {
            $_SESSION['user_Id'] = $user['Id'];
            $_SESSION['user_email'] = $user['email'];
            header("Location: monform.php");
            exit;
        } else {
            $error = "Mot de passe incorrect";
        }
    } else {
        $error = "Utilisateur non trouvé";
    }
}
?>

<!-- Formulaire -->
<form method="POST">
    <input type="email" name="email" required placeholder="Email">
    <input type="password" name="password" required placeholder="Mot de passe">
    <button type="submit">Connexion</button>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
</form>
