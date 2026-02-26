<?php
include 'db.php'; // 1. CONNEXION À LA BASE DE DONNÉES

$status = "";
$status_message = "";

// 2. TRAITEMENT DU FORMULAIRE (PHP)
if (isset($_POST['save_contact'])) {
    try {
        // Validation des données
        $errors = [];
        
        if (empty($_POST['nom_complet'])) {
            $errors[] = "Le nom complet est requis";
        }
        
        if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'adresse email n'est pas valide";
        }
        
        if (empty($errors)) {
            $sql = "INSERT INTO contact (type_contact, nom_complet, nom_entreprise, numero_ifu, email, telephone, adresse, ville, date_creation) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['type_contact'],
                htmlspecialchars(trim($_POST['nom_complet'])),
                htmlspecialchars(trim($_POST['nom_entreprise'] ?? '')),
                htmlspecialchars(trim($_POST['numero_ifu'] ?? '')),
                filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
                htmlspecialchars(trim($_POST['telephone'] ?? '')),
                htmlspecialchars(trim($_POST['adresse'] ?? '')),
                htmlspecialchars(trim($_POST['ville'] ?? ''))
            ]);
            $status = "success";
            $status_message = "Contact ajouté au répertoire avec succès !";
        } else {
            $status = "error";
            $status_message = implode("<br>", $errors);
        }
    } catch (Exception $e) {
        $status = "error";
        $status_message = "Une erreur est survenue lors de l'ajout du contact.";
        // Log l'erreur pour le débogage (à implémenter selon votre configuration)
        error_log("Erreur d'ajout de contact : " . $e->getMessage());
    }
}

// 3. RÉCUPÉRATION DES CONTACTS AVEC PAGINATION
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$total_contacts = $pdo->query("SELECT COUNT(*) FROM contact")->fetchColumn();
$total_pages = ceil($total_contacts / $limit);

$contact = $pdo->prepare("SELECT * FROM contact ORDER BY date_creation DESC LIMIT :limit OFFSET :offset");
$contact->bindParam(':limit', $limit, PDO::PARAM_INT);
$contact->bindParam(':offset', $offset, PDO::PARAM_INT);
$contact->execute();
$contacts = $contact->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des contacts - Système de gestion</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .contact-card {
            transition: transform 0.2s;
        }
        .contact-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .badge-type {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .contact-info i {
            width: 20px;
            color: #6c757d;
        }
        .pagination {
            margin-bottom: 0;
        }
        .table-actions {
            white-space: nowrap;
        }
        .search-box {
            max-width: 300px;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-building"></i> Gestion Contrat
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link active" href="contact.php">
                        <i class="bi bi-people"></i> Contacts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="entretien.php">
                        <i class="bi bi-chat-text"></i> Entretiens
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contrat.php">
                        <i class="bi bi-file-text"></i> Contrats
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-people-fill me-2"></i>Répertoire des contacts
                    <span class="badge bg-secondary ms-2"><?= $total_contacts ?></span>
                </h5>
                <div class="d-flex gap-2">
                    <div class="search-box">
                        <input type="text" class="form-control form-control-sm" placeholder="Rechercher..." id="searchInput">
                    </div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdd">
                        <i class="bi bi-plus-lg"></i> Nouveau Contact
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="contactsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>Nom Complet</th>
                            <th>Entreprise / IFU</th>
                            <th>Coordonnées</th>
                            <th>Ville</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block"></i>
                                    Aucun contact trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $c): ?>
                            <tr class="contact-row">
                                <td>
                                    <span class="badge badge-type bg-<?= $c['type_contact'] == 'professionnel' ? 'info' : 'secondary' ?>">
                                        <i class="bi bi-<?= $c['type_contact'] == 'professionnel' ? 'building' : 'person' ?> me-1"></i>
                                        <?= ucfirst($c['type_contact']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold"><?= htmlspecialchars($c['nom_complet']) ?></td>
                                <td>
                                    <?php if (!empty($c['nom_entreprise'])): ?>
                                        <small class="d-block text-muted">
                                            <i class="bi bi-building"></i> <?= htmlspecialchars($c['nom_entreprise']) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if (!empty($c['numero_ifu'])): ?>
                                        <small class="text-primary">
                                            <i class="bi bi-upc-scan"></i> <?= htmlspecialchars($c['numero_ifu']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="contact-info">
                                    <?php if (!empty($c['email'])): ?>
                                        <div class="small">
                                            <i class="bi bi-envelope"></i>
                                            <a href="mailto:<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['telephone'])): ?>
                                        <div class="small">
                                            <i class="bi bi-telephone"></i>
                                            <a href="tel:<?= htmlspecialchars($c['telephone']) ?>"><?= htmlspecialchars($c['telephone']) ?></a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['ville'])): ?>
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($c['ville']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions text-center">
                                    <a href="entretien.php?contact_id=<?= $c['id'] ?>" class="btn btn-outline-success btn-sm" title="Nouvel Entretien">
                                        <i class="bi bi-chat-text"></i>
                                    </a>
                                    <a href="contrat.php?contact_id=<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm" title="Nouveau Contrat">
                                        <i class="bi bi-file-text"></i>
                                    </a>
                                    <button class="btn btn-outline-info btn-sm" title="Voir détails" onclick="viewContact(<?= $c['id'] ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Affichage de <?= $offset + 1 ?> à <?= min($offset + $limit, $total_contacts) ?> sur <?= $total_contacts ?> contacts
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>">Précédent</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">Suivant</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODALE D'AJOUT -->
<div class="modal fade" id="modalAdd" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" id="contactForm">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-person-plus me-2"></i>Ajouter un Contact
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Type de contact <span class="text-danger">*</span></label>
                        <select name="type_contact" class="form-select" required>
                            <option value="particulier">Particulier</option>
                            <option value="professionnel">Professionnel</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nom complet <span class="text-danger">*</span></label>
                        <input type="text" name="nom_complet" class="form-control" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nom entreprise</label>
                        <input type="text" name="nom_entreprise" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Numéro IFU / Fiscal</label>
                        <input type="text" name="numero_ifu" class="form-control" maxlength="50">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="tel" name="telephone" class="form-control" maxlength="20">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Adresse</label>
                        <input type="text" name="adresse" class="form-control" maxlength="255">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Ville</label>
                        <input type="text" name="ville" class="form-control" maxlength="100">
                    </div>
                </div>
                <div class="mt-3 small text-muted">
                    <i class="bi bi-info-circle"></i> Les champs marqués d'un <span class="text-danger">*</span> sont obligatoires
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x"></i> Annuler
                </button>
                <button type="submit" name="save_contact" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Enregistrer le contact
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- NOTIFICATIONS SWEETALERT -->
<?php if ($status == "success"): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Succès !',
        text: '<?= $status_message ?>',
        confirmButtonColor: '#0d6efd',
        timer: 3000,
        timerProgressBar: true
    });
</script>
<?php elseif ($status == "error"): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Erreur',
        html: '<?= $status_message ?>',
        confirmButtonColor: '#dc3545'
    });
</script>
<?php endif; ?>

<!-- Fonctions JavaScript -->
<script>
// Fonction pour afficher les détails d'un contact
function viewContact(id) {
    // Implémenter la logique d'affichage des détails
    Swal.fire({
        title: 'Détails du contact',
        text: 'Fonctionnalité à implémenter',
        icon: 'info'
    });
}

// Recherche en temps réel
document.getElementById('searchInput').addEventListener('keyup', function() {
    let searchValue = this.value.toLowerCase();
    let rows = document.querySelectorAll('.contact-row');
    
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});

// Validation du formulaire avant soumission
document.getElementById('contactForm').addEventListener('submit', function(e) {
    let nomComplet = this.querySelector('[name="nom_complet"]').value;
    if (nomComplet.trim() === '') {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Validation',
            text: 'Le nom complet est obligatoire'
        });
    }
});
</script>

</body>
</html>