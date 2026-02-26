<?php
include 'db.php';
$status = "";
$status_message = "";

// 1. TRAITEMENT DE LA SIGNATURE OU CRÉATION
if (isset($_POST['save_contrat'])) {
    try {
        // Validation des données
        $errors = [];

        if (empty($_POST['entretien_id'])) {
            $errors[] = "L'entretien lié est requis";
        }

        if (empty($_POST['titre_accord'])) {
            $errors[] = "Le titre de l'accord est requis";
        }

        if (empty($_POST['signature_data'])) {
            $errors[] = "La signature est requise";
        }

        if (empty($errors)) {
            // Nettoyer et valider les données
            $signature = $_POST['signature_data'];
            // Vérifier que c'est une image valide
            if (strpos($signature, 'data:image/png;base64,') === 0) {
                // Générer une référence unique
                $reference = "CTR-" . date('Ymd') . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

                $sql = "INSERT INTO contrat (entretien_id, reference_contrat, titre_accord, description_service, 
                        montant_total, devise, date_debut_prevue, date_fin_prevue, etat, signature_image, horodatage_signature) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['entretien_id'],
                    $reference,
                    htmlspecialchars(trim($_POST['titre_accord'])),
                    htmlspecialchars(trim($_POST['description_service'] ?? '')),
                    !empty($_POST['montant_total']) ? floatval($_POST['montant_total']) : null,
                    htmlspecialchars(trim($_POST['devise'] ?? 'EUR')),
                    $_POST['date_debut_prevue'] ?: null,
                    $_POST['date_fin_prevue'] ?: null,
                    'actif',
                    $signature
                ]);
                $status = "success";
                $status_message = "Le contrat a été créé et signé avec succès !";
            } else {
                $status = "error";
                $status_message = "La signature n'est pas valide.";
            }
        } else {
            $status = "error";
            $status_message = implode("<br>", $errors);
        }
    } catch (Exception $e) {
        $status = "error";
        $status_message = "Une erreur est survenue lors de la création du contrat.";
        error_log("Erreur d'ajout de contrat : " . $e->getMessage());
    }
}

// 2. RÉCUPÉRATION DES CONTRATS AVEC PAGINATION
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$where_conditions = [];
$params = [];

if (isset($_GET['etat']) && !empty($_GET['etat'])) {
    $where_conditions[] = "c.etat = :etat";
    $params[':etat'] = $_GET['etat'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(c.reference_contrat LIKE :search OR co.nom_complet LIKE :search OR c.titre_accord LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Comptage total
$count_sql = "SELECT COUNT(*) FROM contrat c 
              LEFT JOIN entretien e ON c.entretien_id = e.id 
              LEFT JOIN contact co ON e.contact_id = co.id 
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_contrats = $count_stmt->fetchColumn();
$total_pages = ceil($total_contrats / $limit);

// Récupération des contrats
$sql = "SELECT c.*, co.nom_complet, co.email, co.telephone, e.objet as entretien_objet 
        FROM contrat c 
        LEFT JOIN entretien e ON c.entretien_id = e.id 
        LEFT JOIN contact co ON e.contact_id = co.id 
        $where_clause 
        ORDER BY c.horodatage_signature DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$contrats = $stmt->fetchAll();

// Récupération des entretiens "CONCLUS" pour le formulaire
$entretiens_prets = $pdo->query("SELECT e.id, e.objet, co.nom_complet, co.email 
                                FROM entretien e 
                                JOIN contact co ON e.contact_id = co.id 
                                WHERE e.statut = 'conclu'
                                AND NOT EXISTS (SELECT 1 FROM contrat c WHERE c.entretien_id = e.id)
                                ORDER BY e.date_entretien DESC")->fetchAll();

// Statistiques
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN etat = 'actif' THEN 1 ELSE 0 END) as actifs,
    SUM(CASE WHEN etat = 'termine' THEN 1 ELSE 0 END) as termines,
    SUM(CASE WHEN etat = 'annule' THEN 1 ELSE 0 END) as annules,
    COALESCE(SUM(montant_total), 0) as montant_total
    FROM contrat")->fetch();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Contrats</title>
    <!-- Bootstrap 5 CSS -->
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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

        .signature-preview {
            max-width: 100px;
            max-height: 50px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 2px;
            background: white;
        }

        #signature-pad {
            border: 2px dashed #ccc;
            border-radius: 8px;
            cursor: crosshair;
            background: #fff;
            width: 100%;
            height: auto;
            max-width: 500px;
            touch-action: none;
        }

        .signature-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .badge-etat {
            font-size: 0.8rem;
            padding: 0.5em 0.8em;
        }

        .filter-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .contrat-details {
            max-height: 400px;
            overflow-y: auto;
            text-align: left;
        }

        .modal-content {
            border: none;
            border-radius: 12px;
        }

        .modal-header {
            border-radius: 12px 12px 0 0;
        }

        .reference-badge {
            font-family: monospace;
            background: #e9ecef;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
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
                        <a class="nav-link" href="entretien.php">
                            <i class="bi bi-chat-text"></i> Entretiens
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="contrat.php">
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
                                <h6 class="card-title mb-1">Total contrats</h6>
                                <h2 class="mb-0"><?= $stats['total'] ?></h2>
                            </div>
                            <div class="stat-icon bg-white bg-opacity-25">
                                <i class="bi bi-file-earmark-text"></i>
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
                                <h6 class="card-title mb-1">Actifs</h6>
                                <h2 class="mb-0"><?= $stats['actifs'] ?></h2>
                            </div>
                            <div class="stat-icon bg-white bg-opacity-25">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-1">Montant total</h6>
                                <h2 class="mb-0"><?= number_format($stats['montant_total'], 0) ?> €</h2>
                            </div>
                            <div class="stat-icon bg-white bg-opacity-25">
                                <i class="bi bi-currency-euro"></i>
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
                                <h6 class="card-title mb-1">Terminés</h6>
                                <h2 class="mb-0"><?= $stats['termines'] ?></h2>
                            </div>
                            <div class="stat-icon bg-white bg-opacity-25">
                                <i class="bi bi-clock-history"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres et recherche -->
        <div class="filter-card shadow-sm">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Rechercher</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Référence, client, titre..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">État</label>
                    <select name="etat" class="form-select">
                        <option value="">Tous</option>
                        <option value="actif" <?= (isset($_GET['etat']) && $_GET['etat'] == 'actif') ? 'selected' : '' ?>>Actif</option>
                        <option value="termine" <?= (isset($_GET['etat']) && $_GET['etat'] == 'termine') ? 'selected' : '' ?>>Terminé</option>
                        <option value="annule" <?= (isset($_GET['etat']) && $_GET['etat'] == 'annule') ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filtrer
                        </button>
                        <a href="contrat.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalContrat">
                        <i class="bi bi-pen"></i> Nouveau Contrat
                    </button>
                </div>
            </form>
        </div>

        <!-- Liste des contrats -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-file-earmark-text me-2"></i>Liste des Contrats
                        <span class="badge bg-secondary ms-2"><?= $total_contrats ?></span>
                    </h5>
                    <?php if (empty($entretiens_prets)): ?>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Aucun entretien conclu disponible
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Référence</th>
                                <th>Client</th>
                                <th>Titre Accord</th>
                                <th>Montant</th>
                                <th>Signature</th>
                                <th>État</th>
                                <th>Date signature</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($contrats)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block"></i>
                                        Aucun contrat trouvé
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($contrats as $ct): ?>
                                    <tr>
                                        <td>
                                            <span class="reference-badge"><?= htmlspecialchars($ct['reference_contrat']) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($ct['nom_complet'] ?? 'N/A') ?></div>
                                            <?php if (!empty($ct['email'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars($ct['email']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($ct['titre_accord']) ?>
                                            <?php if (!empty($ct['entretien_objet'])): ?>
                                                <small class="d-block text-muted"><?= htmlspecialchars($ct['entretien_objet']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold"><?= number_format($ct['montant_total'], 2) ?></span>
                                            <small><?= htmlspecialchars($ct['devise']) ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($ct['signature_image'])): ?>
                                                <img src="<?= htmlspecialchars($ct['signature_image']) ?>" class="signature-preview" alt="Signature">
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = match ($ct['etat']) {
                                                'actif' => 'success',
                                                'termine' => 'info',
                                                'annule' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge badge-etat bg-<?= $badge_class ?>">
                                                <?= ucfirst($ct['etat']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y', strtotime($ct['horodatage_signature'])) ?>
                                            <small class="d-block text-muted"><?= date('H:i', strtotime($ct['horodatage_signature'])) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info" title="Voir détails" onclick='viewContrat(<?= json_encode($ct, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" title="Télécharger PDF" onclick="downloadContrat(<?= $ct['id'] ?>)">
                                                    <i class="bi bi-file-pdf"></i>
                                                </button>
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
                            Affichage de <?= $offset + 1 ?> à <?= min($offset + $limit, $total_contrats) ?> sur <?= $total_contrats ?> contrats
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

    <!-- MODALE CONTRAT + SIGNATURE - VERSION CORRIGÉE AVEC FOOTER VISIBLE -->
    <div class="modal fade" id="modalContrat" tabindex="-1" aria-labelledby="modalContratLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="formContrat" onsubmit="return validateSignature()">
                    <!-- HEADER -->
                    <div class="modal-header bg-primary text-white py-3">
                        <h5 class="modal-title fw-bold" id="modalContratLabel">
                            <i class="bi bi-file-earmark-text me-2"></i>Nouveau Contrat avec Signature
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>

                    <!-- BODY avec hauteur maximale contrôlée et défilement -->
                    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                        <?php if (empty($entretiens_prets)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle fs-4 d-block text-center mb-2"></i>
                                <p class="mb-0 text-center">
                                    Aucun entretien conclu disponible.<br>
                                    <a href="entretien.php" class="alert-link">Voir les entretiens</a>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="container-fluid px-0">
                                <!-- Informations de l'entretien -->
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="card bg-light border-0">
                                            <div class="card-body">
                                                <h6 class="card-title fw-bold mb-3">
                                                    <i class="bi bi-chat-text me-2"></i>Sélectionner l'entretien
                                                </h6>
                                                <select name="entretien_id" class="form-select" required>
                                                    <option value="">Choisir un entretien conclu...</option>
                                                    <?php foreach ($entretiens_prets as $ep): ?>
                                                        <option value="<?= $ep['id'] ?>">
                                                            <?= htmlspecialchars($ep['nom_complet']) ?> - <?= htmlspecialchars($ep['objet']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Détails du contrat -->
                                <div class="row g-3 mt-2">
                                    <div class="col-12">
                                        <h6 class="fw-bold mb-3">
                                            <i class="bi bi-file-text me-2"></i>Détails du contrat
                                        </h6>
                                    </div>

                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold">Titre de l'accord <span class="text-danger">*</span></label>
                                        <input type="text" name="titre_accord" class="form-control" required
                                            placeholder="ex: Contrat de développement web">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Montant</label>
                                        <div class="input-group">
                                            <input type="number" name="montant_total" class="form-control"
                                                step="0.01" min="0" placeholder="0.00">
                                            <select name="devise" class="form-select" style="max-width: 80px;">
                                                <option value="EUR">€</option>
                                                <option value="USD">$</option>
                                                <option value="GBP">£</option>
                                                <option value="XOF">F CFA</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Date de début</label>
                                        <input type="date" name="date_debut_prevue" class="form-control">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Date de fin</label>
                                        <input type="date" name="date_fin_prevue" class="form-control">
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Description des services</label>
                                        <textarea name="description_service" class="form-control" rows="3"
                                            placeholder="Décrivez les services à fournir..."></textarea>
                                    </div>
                                </div>

                                <!-- Zone de signature améliorée -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="card border-primary">
                                            <div class="card-header bg-primary bg-opacity-10 text-primary fw-bold">
                                                <i class="bi bi-pen me-2"></i>Signature du client
                                                <span class="text-danger">*</span>
                                            </div>
                                            <div class="card-body">
                                                <!-- Instructions -->
                                                <div class="alert alert-info py-2 mb-3">
                                                    <small>
                                                        <i class="bi bi-info-circle me-2"></i>
                                                        Dessinez votre signature dans la zone ci-dessous
                                                    </small>
                                                </div>

                                                <!-- Canvas pour la signature -->
                                                <div class="text-center mb-3">
                                                    <canvas id="signature-pad" width="500" height="200"
                                                        style="border: 2px solid #dee2e6; border-radius: 8px; 
                                                               max-width: 100%; height: auto; cursor: crosshair; 
                                                               background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                    </canvas>
                                                </div>

                                                <!-- Boutons de contrôle de la signature -->
                                                <div class="d-flex justify-content-center gap-2 mb-2">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        id="undoSignature" title="Annuler le dernier tracé">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Annuler
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        id="clearSignature" title="Tout effacer">
                                                        <i class="bi bi-eraser"></i> Effacer tout
                                                    </button>
                                                </div>

                                                <!-- Champ caché pour la signature -->
                                                <input type="hidden" name="signature_data" id="signature_data">

                                                <!-- Aperçu en temps réel (optionnel) -->
                                                <div class="text-center text-muted small">
                                                    <i class="bi bi-mouse"></i> Utilisez la souris ou votre doigt (sur tablette)
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- FOOTER toujours visible -->
                    <div class="modal-footer bg-light border-top">
                        <div class="d-flex justify-content-between w-100">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg"></i> Annuler
                            </button>

                            <?php if (!empty($entretiens_prets)): ?>
                                <button type="submit" name="save_contrat" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Valider et signer le contrat
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS avec Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('signature-pad');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            let drawing = false;
            let paths = []; // Stocker tous les chemins pour pouvoir annuler
            let currentPath = [];

            // Configuration initiale
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#000';

            // Sauvegarder l'état initial (vide)
            function saveState() {
                paths.push([]); // Nouveau chemin vide
                currentPath = [];
            }

            saveState(); // État initial

            function getCoordinates(e) {
                const rect = canvas.getBoundingClientRect();
                const scaleX = canvas.width / rect.width;
                const scaleY = canvas.height / rect.height;

                let clientX, clientY;

                if (e.touches) {
                    clientX = e.touches[0].clientX;
                    clientY = e.touches[0].clientY;
                    e.preventDefault();
                } else {
                    clientX = e.clientX;
                    clientY = e.clientY;
                }

                return {
                    x: (clientX - rect.left) * scaleX,
                    y: (clientY - rect.top) * scaleY
                };
            }

            function startDrawing(e) {
                e.preventDefault();
                drawing = true;
                const coords = getCoordinates(e);

                // Nouveau chemin
                currentPath = [coords];

                ctx.beginPath();
                ctx.moveTo(coords.x, coords.y);
            }

            function draw(e) {
                e.preventDefault();
                if (!drawing) return;

                const coords = getCoordinates(e);

                // Ajouter au chemin courant
                currentPath.push(coords);

                ctx.lineTo(coords.x, coords.y);
                ctx.stroke();

                ctx.beginPath();
                ctx.moveTo(coords.x, coords.y);
            }

            function stopDrawing() {
                if (drawing && currentPath.length > 0) {
                    paths.push([...currentPath]); // Sauvegarder le chemin
                }
                drawing = false;
                ctx.beginPath();
            }

            // Fonction pour redessiner tous les chemins
            function redraw() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                paths.forEach(path => {
                    if (path.length === 0) return;

                    ctx.beginPath();
                    ctx.moveTo(path[0].x, path[0].y);

                    for (let i = 1; i < path.length; i++) {
                        ctx.lineTo(path[i].x, path[i].y);
                    }

                    ctx.stroke();
                });
            }

            // Fonction pour annuler le dernier tracé
            function undo() {
                if (paths.length > 1) { // Garder au moins l'état initial
                    paths.pop(); // Supprimer le dernier chemin
                    redraw();
                }
            }

            // Fonction pour tout effacer
            function clear() {
                paths = [
                    []
                ]; // Réinitialiser avec un chemin vide
                redraw();
            }

            // Événements souris
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);

            // Événements tactiles
            canvas.addEventListener('touchstart', startDrawing, {
                passive: false
            });
            canvas.addEventListener('touchmove', draw, {
                passive: false
            });
            canvas.addEventListener('touchend', stopDrawing);
            canvas.addEventListener('touchcancel', stopDrawing);

            // Boutons de contrôle
            document.getElementById('undoSignature')?.addEventListener('click', undo);
            document.getElementById('clearSignature')?.addEventListener('click', clear);

            // Validation avant soumission
            window.validateSignature = function() {
                // Vérifier si au moins un chemin non vide a été dessiné
                const hasSignature = paths.some(path => path.length > 0);

                if (!hasSignature) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Signature requise',
                        text: 'Veuillez signer avant de valider le contrat'
                    });
                    return false;
                }

                // Convertir en Base64
                document.getElementById('signature_data').value = canvas.toDataURL('image/png');
                return true;
            };

            // Réinitialiser quand le modal s'ouvre
            document.getElementById('modalContrat')?.addEventListener('show.bs.modal', function() {
                clear(); // Effacer la signature précédente
            });
        });
    </script>
    <?php if ($status == "success"): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Contrat signé !',
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