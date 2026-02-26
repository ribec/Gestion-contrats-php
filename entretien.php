<?php
include 'db.php';

$status = "";
$status_message = "";

// 2. TRAITEMENT DE L'AJOUT D'ENTRETIEN
if (isset($_POST['save_entretien'])) {
    try {
        // Validation des données
        $errors = [];
        
        if (empty($_POST['contact_id'])) {
            $errors[] = "Le contact est requis";
        }
        
        if (empty($_POST['objet'])) {
            $errors[] = "L'objet du rendez-vous est requis";
        }
        
        if (empty($errors)) {
            $sql = "INSERT INTO entretien (contact_id, objet, lieu, notes_discussion, besoin_explicite, hors_perimetre, delai_souhaite, statut, date_entretien) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['contact_id'],
                htmlspecialchars(trim($_POST['objet'])),
                htmlspecialchars(trim($_POST['lieu'] ?? '')),
                htmlspecialchars(trim($_POST['notes_discussion'] ?? '')),
                htmlspecialchars(trim($_POST['besoin_explicite'] ?? '')),
                htmlspecialchars(trim($_POST['hors_perimetre'] ?? '')),
                htmlspecialchars(trim($_POST['delai_souhaite'] ?? '')),
                $_POST['statut']
            ]);
            $status = "success";
            $status_message = "L'entretien a été enregistré avec succès !";
        } else {
            $status = "error";
            $status_message = implode("<br>", $errors);
        }
    } catch (Exception $e) {
        $status = "error";
        $status_message = "Une erreur est survenue lors de l'enregistrement.";
        error_log("Erreur d'ajout d'entretien : " . $e->getMessage());
    }
}

// 3. RÉCUPÉRATION DES DONNÉES AVEC FILTRES
$where_conditions = [];
$params = [];

// Filtre par statut
if (isset($_GET['statut']) && !empty($_GET['statut'])) {
    $where_conditions[] = "e.statut = :statut";
    $params[':statut'] = $_GET['statut'];
}

// Filtre par contact
if (isset($_GET['contact_id']) && !empty($_GET['contact_id'])) {
    $where_conditions[] = "e.contact_id = :contact_id";
    $params[':contact_id'] = $_GET['contact_id'];
}

// Filtre par date
if (isset($_GET['date_debut']) && !empty($_GET['date_debut'])) {
    $where_conditions[] = "DATE(e.date_entretien) >= :date_debut";
    $params[':date_debut'] = $_GET['date_debut'];
}

if (isset($_GET['date_fin']) && !empty($_GET['date_fin'])) {
    $where_conditions[] = "DATE(e.date_entretien) <= :date_fin";
    $params[':date_fin'] = $_GET['date_fin'];
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Comptage total pour pagination
$count_sql = "SELECT COUNT(*) FROM entretien e LEFT JOIN contact c ON e.contact_id = c.id $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_entretiens = $count_stmt->fetchColumn();
$total_pages = ceil($total_entretiens / $limit);

// Récupération des entretiens
$sql = "SELECT e.*, c.nom_complet, c.email, c.telephone 
        FROM entretien e 
        LEFT JOIN contact c ON e.contact_id = c.id 
        $where_clause 
        ORDER BY e.date_entretien DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$entretiens = $stmt->fetchAll();

// Liste des contacts pour la liste déroulante
$contacts = $pdo->query("SELECT id, nom_complet FROM contact ORDER BY nom_complet ASC")->fetchAll();

// Statistiques pour le tableau de bord
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN statut = 'conclu' THEN 1 ELSE 0 END) as conclus,
    SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
    SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) as annules
    FROM entretien")->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Entretiens</title>
    <!-- Bootstrap 5 CSS - CDN CORRIGÉ -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-radius: 10px;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .filter-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .entretien-details {
            max-height: 400px;
            overflow-y: auto;
            text-align: left;
        }
        .badge-statut {
            font-size: 0.8rem;
            padding: 0.5em 0.8em;
        }
        .modal-content {
            border: none;
            border-radius: 12px;
        }
        .modal-header {
            border-radius: 12px 12px 0 0;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-building"></i> GESTION CONTRAT
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">
                        <i class="bi bi-people"></i> Contacts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="entretien.php">
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
    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total entretiens</h6>
                            <h2 class="mb-0"><?= $stats['total'] ?></h2>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Conclus</h6>
                            <h2 class="mb-0"><?= $stats['conclus'] ?></h2>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">En attente</h6>
                            <h2 class="mb-0"><?= $stats['en_attente'] ?></h2>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Annulés</h6>
                            <h2 class="mb-0"><?= $stats['annules'] ?></h2>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-x-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filter-card shadow-sm">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold">Statut</label>
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    <option value="en_attente" <?= (isset($_GET['statut']) && $_GET['statut'] == 'en_attente') ? 'selected' : '' ?>>En attente</option>
                    <option value="conclu" <?= (isset($_GET['statut']) && $_GET['statut'] == 'conclu') ? 'selected' : '' ?>>Conclu</option>
                    <option value="annule" <?= (isset($_GET['statut']) && $_GET['statut'] == 'annule') ? 'selected' : '' ?>>Annulé</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Contact</label>
                <select name="contact_id" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    <?php foreach ($contacts as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (isset($_GET['contact_id']) && $_GET['contact_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nom_complet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Date début</label>
                <input type="date" name="date_debut" class="form-control form-control-sm" value="<?= $_GET['date_debut'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Date fin</label>
                <input type="date" name="date_fin" class="form-control form-control-sm" value="<?= $_GET['date_fin'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-funnel"></i> Filtrer
                    </button>
                    <a href="entretien.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Liste des entretiens -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-chat-text me-2"></i>Historique des Entretiens
                    <span class="badge bg-secondary ms-2"><?= $total_entretiens ?></span>
                </h5>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalEntretien">
                    <i class="bi bi-calendar-plus"></i> Nouvel Entretien
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Contact</th>
                            <th>Objet</th>
                            <th>Lieu</th>
                            <th>Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entretiens)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block"></i>
                                    Aucun entretien trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($entretiens as $e): ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold"><?= date('d/m/Y', strtotime($e['date_entretien'])) ?></span>
                                    <small class="d-block text-muted"><?= date('H:i', strtotime($e['date_entretien'])) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($e['nom_complet'] ?? 'Contact supprimé') ?></div>
                                    <?php if (!empty($e['email'])): ?>
                                        <small class="text-muted">
                                            <i class="bi bi-envelope"></i> <?= htmlspecialchars($e['email']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($e['objet']) ?></td>
                                <td>
                                    <?php if (!empty($e['lieu'])): ?>
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($e['lieu']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $badge_class = match($e['statut']) {
                                        'conclu' => 'success',
                                        'en_attente' => 'warning',
                                        'annule' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge badge-statut bg-<?= $badge_class ?>">
                                        <?= ucfirst(str_replace('_', ' ', $e['statut'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-info" title="Voir détails" onclick='viewDetails(<?= json_encode($e, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if($e['statut'] == 'conclu'): ?>
                                            <a href="contrat.php?entretien_id=<?= $e['id'] ?>" class="btn btn-outline-success" title="Créer Contrat">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Affichage de <?= $offset + 1 ?> à <?= min($offset + $limit, $total_entretiens) ?> sur <?= $total_entretiens ?> entretiens
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Précédent</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Suivant</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODALE AJOUT ENTRETIEN -->
<div class="modal fade" id="modalEntretien" tabindex="-1" aria-labelledby="modalEntretienLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" id="entretienForm">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="modalEntretienLabel">
                    <i class="bi bi-calendar-plus me-2"></i>Nouvel Entretien
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-person"></i> Contact <span class="text-danger">*</span>
                        </label>
                        <select name="contact_id" class="form-select" required>
                            <option value="">Sélectionnez un contact...</option>
                            <?php foreach ($contacts as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (isset($_GET['contact_id']) && $_GET['contact_id'] == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nom_complet']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-chat"></i> Objet <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="objet" class="form-control" required maxlength="200" placeholder="ex: Refonte site web">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-geo-alt"></i> Lieu
                        </label>
                        <input type="text" name="lieu" class="form-control" maxlength="100" placeholder="Bureau, Zoom, etc.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-clock"></i> Délai souhaité
                        </label>
                        <input type="text" name="delai_souhaite" class="form-control" maxlength="50" placeholder="ex: 3 mois">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-chat-dots"></i> Notes de discussion
                        </label>
                        <textarea name="notes_discussion" class="form-control" rows="2" placeholder="Résumé de la discussion..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-success">
                            <i class="bi bi-check-circle"></i> Besoins explicites
                        </label>
                        <textarea name="besoin_explicite" class="form-control" rows="3" placeholder="Ce que le client souhaite..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-danger">
                            <i class="bi bi-exclamation-triangle"></i> Hors périmètre
                        </label>
                        <textarea name="hors_perimetre" class="form-control" rows="3" placeholder="Ce qui n'est pas inclus..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-flag"></i> Statut
                        </label>
                        <select name="statut" class="form-select">
                            <option value="en_attente">En attente</option>
                            <option value="conclu">Conclu (Prêt pour contrat)</option>
                            <option value="annule">Annulé</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 small text-muted">
                    <i class="bi bi-info-circle"></i> Les champs marqués d'un <span class="text-danger">*</span> sont obligatoires
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="bi bi-x"></i> Annuler
                </button>
                <button type="submit" name="save_entretien" class="btn btn-success px-4">
                    <i class="bi bi-check-lg"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bootstrap JS avec Popper.js - CDN CORRIGÉ -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

<!-- Scripts personnalisés -->
<script>
// Fonction pour voir les détails d'un entretien
function viewDetails(entretien) {
    let details = `
        <div class="entretien-details">
            <p><strong>Contact :</strong> ${entretien.nom_complet || 'Non spécifié'}</p>
            ${entretien.email ? `<p><strong>Email :</strong> ${entretien.email}</p>` : ''}
            ${entretien.telephone ? `<p><strong>Téléphone :</strong> ${entretien.telephone}</p>` : ''}
            <hr>
            <p><strong>Objet :</strong> ${entretien.objet}</p>
            <p><strong>Lieu :</strong> ${entretien.lieu || 'Non renseigné'}</p>
            <p><strong>Délai souhaité :</strong> ${entretien.delai_souhaite || 'Non renseigné'}</p>
            <hr>
            <p><strong>Notes de discussion :</strong><br>${entretien.notes_discussion || 'Aucune note'}</p>
            <p><strong>Besoins explicites :</strong><br>${entretien.besoin_explicite || 'Aucun besoin explicite'}</p>
            <p><strong>Hors périmètre :</strong><br>${entretien.hors_perimetre || 'Rien à signaler'}</p>
        </div>
    `;
    
    Swal.fire({
        title: 'Détails de l\'entretien',
        html: details,
        icon: 'info',
        confirmButtonText: 'Fermer',
        width: '600px',
        customClass: {
            container: 'swal-details-container'
        }
    });
}

// Validation du formulaire
document.getElementById('entretienForm')?.addEventListener('submit', function(e) {
    let contact = this.querySelector('[name="contact_id"]').value;
    let objet = this.querySelector('[name="objet"]').value;
    
    if (!contact || !objet) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Validation',
            text: 'Veuillez remplir tous les champs obligatoires'
        });
    }
});

// Fermeture automatique des modales après soumission (optionnel)
<?php if ($status == "success"): ?>
    var modal = bootstrap.Modal.getInstance(document.getElementById('modalEntretien'));
    if (modal) {
        modal.hide();
    }
<?php endif; ?>
</script>

<?php if ($status == "success"): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Enregistré !',
        text: '<?= $status_message ?>',
        confirmButtonColor: '#198754',
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

</body>
</html>