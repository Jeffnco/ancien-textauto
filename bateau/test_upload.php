<!DOCTYPE html>
<html>
<body>
    <h1>Test Upload</h1>
    <form action="test_upload.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="image" required>
        <button type="submit">Upload</button>
    </form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetDir = __DIR__ . "/uploads/articles/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $filename = basename($_FILES['image']['name']);
    $targetPath = $targetDir . time() . '_' . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        echo "<p style='color:green;'>✅ Upload réussi : {$targetPath}</p>";
    } else {
        echo "<p style='color:red;'>❌ Échec du déplacement de l'image</p>";
    }
}
?>
</body>
</html>
