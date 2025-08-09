<form id="webhookForm" class="formN">
  <div>
    <label for="keywords">Mot clé principal :</label>
    <input type="text" id="keywords" name="keywords" required>
  </div>
  <div>
    <label for="title">Titre :</label>
    <input type="text" id="title" name="title" required>
  </div>
  <div>
    <label for="description">Description :</label>
    <textarea id="description" name="description" required></textarea>
  </div>
  <div>
    <label for="mots_cles">Mots clés :</label>
    <textarea id="mots_cles" name="mots_cles" required></textarea>
  </div>
  <div>
    <label for="instructions">Instructions :</label>
    <textarea id="instructions" name="instructions" required></textarea>
  </div>
  <div>
    <!-- Sélection des Personas -->
    <label for="persona">Choisir un persona :</label>
    <select id="persona" name="persona">
        <option value="">Sélectionner un persona</option>
        <?php foreach ($personas_list as $persona): ?>
            <option value="<?= htmlspecialchars($persona['Title']) ?>">
                <?= htmlspecialchars($persona['Title']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <!-- Champ caché pour envoyer l'ID du persona -->
    <input type="hidden" id="id_persona" name="id_persona">
  </div>
  <div>
    <!-- Sélection des Projets -->
    <label for="projet">Choisir un projet :</label>
    <select id="projet" name="projet">
        <option value="">Sélectionner un projet</option>
        <?php foreach ($projets_list as $projet): ?>
            <option value="<?= htmlspecialchars($projet['Projet']) ?>">
                <?= htmlspecialchars($projet['Projet']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <!-- Champ caché pour envoyer l'ID du projet -->
    <input type="hidden" id="id_projet" name="id_projet">
  </div>
  <div id="status-message" class="message" style="display: none;"></div>
  <button type="submit">Envoyer</button>
</form>

<script>
// Fonction pour afficher les messages de statut
function showMessage(message, isSuccess) {
    const statusElement = document.getElementById("status-message");
    statusElement.textContent = message;
    statusElement.style.display = "block";
    statusElement.style.color = isSuccess ? "#28a745" : "#dc3545";
    statusElement.style.backgroundColor = isSuccess ? "rgba(40, 167, 69, 0.1)" : "rgba(220, 53, 69, 0.1)";
    statusElement.style.padding = "10px";
    statusElement.style.borderRadius = "4px";
    statusElement.style.marginBottom = "10px";
    
    // Faire disparaître le message après 5 secondes
    setTimeout(() => {
        statusElement.style.display = "none";
    }, 5000);
}

// Met à jour l'input caché avec l'ID du persona sélectionné
document.getElementById("persona").addEventListener("change", function() {
    document.getElementById("id_persona").value = this.value;
});

// Met à jour l'input caché avec l'ID du projet sélectionné
document.getElementById("projet").addEventListener("change", function() {
    document.getElementById("id_projet").value = this.value;
});

// Gestion de la soumission du formulaire
document.getElementById("webhookForm").addEventListener("submit", function(event) {
    event.preventDefault(); // Empêche l'envoi classique du formulaire
    
    // Vérification des champs obligatoires
    const requiredFields = ["keywords", "title", "description", "mots_cles", "instructions"];
    let isValid = true;
    
    requiredFields.forEach(field => {
        const element = document.getElementById(field);
        if (!element.value.trim()) {
            element.style.borderColor = "#dc3545";
            isValid = false;
        } else {
            element.style.borderColor = "";
        }
    });
    
    if (!isValid) {
        showMessage("Veuillez remplir tous les champs obligatoires.", false);
        return;
    }
    
    // Préparation des données
    let formData = {
        keywords: document.getElementById("keywords").value,
        title: document.getElementById("title").value,
        description: document.getElementById("description").value,
        mots_cles: document.getElementById("mots_cles").value,
        instructions: document.getElementById("instructions").value,
        persona: document.getElementById("persona").value,
        projet: document.getElementById("projet").value
    };
    
    // Désactiver le bouton pendant l'envoi
    const submitButton = this.querySelector("button[type=submit]");
    submitButton.disabled = true;
    submitButton.textContent = "Envoi en cours...";
    
    // Utiliser le proxy PHP au lieu de l'URL directe du webhook
    fetch("webhook_proxy.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(formData)
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => Promise.reject(err));
        }
        return response.json();
    })
    .then(data => {
        console.log("✅ Réponse reçue :", data);
        showMessage("Article envoyé avec succès !", true);
        this.reset();
    })
    .catch(error => {
        console.error("❌ Erreur lors de l'envoi :", error);
        showMessage("Erreur lors de l'envoi de l'article : " + 
                   (error.error || "Problème de connexion au serveur"), false);
    })
    .finally(() => {
        // Réactiver le bouton
        submitButton.disabled = false;
        submitButton.textContent = "Envoyer";
    });
});
</script>
