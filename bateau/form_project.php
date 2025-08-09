<form id="createProjectForm" class="formN">
  <div>
    <label for="projet_input">Nom du projet :</label>
    <input type="text" id="projet_input" name="projet" required>
  </div>
  <div>
    <label for="frequence">Fréquence de publication auto :</label>
    <input type="text" id="frequence" name="frequence" required>
  </div>
  <div>
    <label for="site">Site : (sans https, que le domaine)</label>
    <input type="text" id="site" name="site" required>
  </div>
  <div>
    <label for="mdp_app_wp">Mot de passe application WordPress :(codé en base 64 admin:mdp_application => <a href="https://www.base64encode.org/fr/">https://www.base64encode.org/fr/</a></label>
    <input type="password" id="mdp_app_wp" name="mdp_app_wp">
  </div>
  <button type="submit">Créer le projet</button>
</form>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("createProjectForm").addEventListener("submit", function(event) {
        event.preventDefault(); // Empêche le rechargement de la page

        const formData = {
            projet: document.getElementById("projet_input").value,
            frequence: document.getElementById("frequence").value,
            site: document.getElementById("site").value,
            mdp_app_wp: document.getElementById("mdp_app_wp").value
        };
        
        console.log("Data envoyée :", formData);

        fetch("submit_project.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, true);
                document.getElementById("createProjectForm").reset();
            } else {
                showMessage(data.message, false);
            }
        })
        .catch(error => {
            console.error("Erreur lors de l'envoi :", error);
            showMessage("Erreur lors de la création du projet", false);
        });
    });
});
</script>
