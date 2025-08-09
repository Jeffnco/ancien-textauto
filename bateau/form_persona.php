<form id="createPersonaForm" class="formN">
  <div>
    <label for="nom_persona">Nom du persona :</label>
    <input type="text" id="nom_persona" name="nom_persona" required>
  </div>
  <div>
    <label for="text_src_persona">Texte source du persona :</label>
    <textarea id="text_src_persona" name="text_src_persona" required></textarea>
  </div>
  <button type="submit">Créer le persona</button>
</form>

<script>
document.getElementById("createPersonaForm").addEventListener("submit", function(event) {
    event.preventDefault(); // Empêche le rechargement de la page

    let formData = new FormData();
    formData.append('nom_persona', document.getElementById("nom_persona").value);
    formData.append('text_src_persona', document.getElementById("text_src_persona").value);

    fetch("submit_persona_create.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, true);
            document.getElementById("createPersonaForm").reset();
        } else {
            showMessage(data.message, false);
        }
    })
    .catch(error => {
        console.error("Erreur :", error);
        showMessage("Erreur lors de la création du persona", false);
    });
});
</script>
