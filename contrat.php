<?php
session_start();
require_once 'db.php';

// Vérification de l'authentification (à décommenter quand login.php sera créé)
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

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

                // Récupérer l'IP du signataire
                $ip_signataire = $_SERVER['REMOTE_ADDR'] ?? null;

                $sql = "INSERT INTO contrat (entretien_id, reference_contrat, titre_accord, description_service, 
                        clause_particuliere, montant_total, devise, frequence_paiement, date_debut_prevue, 
                        date_fin_prevue, modalite_rupture, etat, signature_image, ip_signataire, horodatage_signature) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['entretien_id'],
                    $reference,
                    htmlspecialchars(trim($_POST['titre_accord'])),
                    htmlspecialchars(trim($_POST['description_service'] ?? '')),
                    htmlspecialchars(trim($_POST['clause_particuliere'] ?? '')),
                    !empty($_POST['montant_total']) ? floatval($_POST['montant_total']) : 0.00,
                    htmlspecialchars(trim($_POST['devise'] ?? 'EUR')),
                    htmlspecialchars(trim($_POST['frequence_paiement'] ?? '')),
                    $_POST['date_debut_prevue'] ?: null,
                    $_POST['date_fin_prevue'] ?: null,
                    htmlspecialchars(trim($_POST['modalite_rupture'] ?? '')),
                    $_POST['etat'] ?? 'actif',
                    $signature,
                    $ip_signataire
                ]);
                $status = "success";
                $status_message = "Le contrat a été créé et signé avec succès ! Réf: " . $reference;
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

// 2. RÉCUPÉRATION DES CONTRATS AVEC PAGINATION ET FILTRES
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$where_conditions = [];
$params = [];

// Recherche
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(c.reference_contrat LIKE :search OR c.titre_accord LIKE :search OR co.nom_complet LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

// Filtre par état
if (isset($_GET['etat']) && !empty($_GET['etat'])) {
    $where_conditions[] = "c.etat = :etat";
    $params[':etat'] = $_GET['etat'];
}

// Filtre par contact
if (isset($_GET['contact_id']) && !empty($_GET['contact_id'])) {
    $where_conditions[] = "co.id = :contact_id";
    $params[':contact_id'] = $_GET['contact_id'];
}

// Filtre par période
if (isset($_GET['date_debut']) && !empty($_GET['date_debut'])) {
    $where_conditions[] = "c.date_debut_prevue >= :date_debut";
    $params[':date_debut'] = $_GET['date_debut'];
}

if (isset($_GET['date_fin']) && !empty($_GET['date_fin'])) {
    $where_conditions[] = "c.date_fin_prevue <= :date_fin";
    $params[':date_fin'] = $_GET['date_fin'];
}

// Filtre par montant
if (isset($_GET['montant_min']) && !empty($_GET['montant_min'])) {
    $where_conditions[] = "c.montant_total >= :montant_min";
    $params[':montant_min'] = $_GET['montant_min'];
}

if (isset($_GET['montant_max']) && !empty($_GET['montant_max'])) {
    $where_conditions[] = "c.montant_total <= :montant_max";
    $params[':montant_max'] = $_GET['montant_max'];
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Comptage total pour pagination
$count_sql = "SELECT COUNT(*) FROM contrat c 
              LEFT JOIN entretien e ON c.entretien_id = e.id 
              LEFT JOIN contact co ON e.contact_id = co.id 
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_contrats = $count_stmt->fetchColumn();
$total_pages = ceil($total_contrats / $limit);

// Récupération des contrats
$sql = "SELECT c.*, co.nom_complet, co.email, co.telephone, co.ville, e.objet as entretien_objet,
        DATEDIFF(c.date_fin_prevue, CURDATE()) as jours_restants
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
$entretiens_prets = $pdo->query("SELECT e.id, e.objet, co.nom_complet, co.email, co.telephone
                                FROM entretien e 
                                JOIN contact co ON e.contact_id = co.id 
                                WHERE e.statut = 'conclu'
                                AND NOT EXISTS (SELECT 1 FROM contrat c WHERE c.entretien_id = e.id)
                                ORDER BY e.date_entretien DESC")->fetchAll();

// Liste des contacts pour le filtre
$contacts_filtre = $pdo->query("SELECT DISTINCT co.id, co.nom_complet 
                               FROM contrat c 
                               LEFT JOIN entretien e ON c.entretien_id = e.id 
                               LEFT JOIN contact co ON e.contact_id = co.id 
                               WHERE co.id IS NOT NULL 
                               ORDER BY co.nom_complet")->fetchAll();

// Statistiques
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM contrat")->fetchColumn();
$stats['montant_total'] = $pdo->query("SELECT COALESCE(SUM(montant_total), 0) FROM contrat WHERE etat IN ('actif', 'termine')")->fetchColumn();
$stats['actifs'] = $pdo->query("SELECT COUNT(*) FROM contrat WHERE etat = 'actif'")->fetchColumn();
$stats['termines'] = $pdo->query("SELECT COUNT(*) FROM contrat WHERE etat = 'termine'")->fetchColumn();
$stats['brouillons'] = $pdo->query("SELECT COUNT(*) FROM contrat WHERE etat = 'brouillon'")->fetchColumn();
$stats['suspendus'] = $pdo->query("SELECT COUNT(*) FROM contrat WHERE etat = 'suspendu'")->fetchColumn();

// Contrats expirant bientôt (30 jours)
$stats['expirant_bientot'] = $pdo->query("SELECT COUNT(*) FROM contrat 
                                         WHERE etat = 'actif' 
                                         AND date_fin_prevue IS NOT NULL 
                                         AND date_fin_prevue BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

// Contrats expirés
$stats['expires'] = $pdo->query("SELECT COUNT(*) FROM contrat 
                                WHERE etat = 'actif' 
                                AND date_fin_prevue IS NOT NULL 
                                AND date_fin_prevue < CURDATE()")->fetchColumn();

// Derniers contrats signés
$derniers_contrats = $pdo->query("SELECT c.*, co.nom_complet 
                                 FROM contrat c 
                                 LEFT JOIN entretien e ON c.entretien_id = e.id 
                                 LEFT JOIN contact co ON e.contact_id = co.id 
                                 WHERE c.horodatage_signature IS NOT NULL 
                                 ORDER BY c.horodatage_signature DESC 
                                 LIMIT 5")->fetchAll();

// Top contrats par montant
$top_contrats = $pdo->query("SELECT c.*, co.nom_complet 
                            FROM contrat c 
                            LEFT JOIN entretien e ON c.entretien_id = e.id 
                            LEFT JOIN contact co ON e.contact_id = co.id 
                            WHERE c.etat IN ('actif', 'termine') 
                            ORDER BY c.montant_total DESC 
                            LIMIT 5")->fetchAll();

// Répartition par devise
$repartition_devise = $pdo->query("SELECT devise, COUNT(*) as total, SUM(montant_total) as montant 
                                  FROM contrat 
                                  WHERE devise IS NOT NULL 
                                  GROUP BY devise")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Contrats - Gestion des Contrats</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --sidebar-width: 280px;
        }

        body {
            background: #f4f6f9;
        }

        .wrapper {
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-header h3 {
            margin: 0;
            font-weight: 300;
            letter-spacing: 2px;
        }

        .sidebar-header h3 strong {
            font-weight: 700;
        }

        .user-info {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }

        .user-info img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid white;
        }

        .nav-sidebar {
            list-style: none;
            padding: 0;
        }

        .nav-sidebar li {
            margin: 5px 15px;
        }

        .nav-sidebar li a {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            display: block;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .nav-sidebar li a:hover,
        .nav-sidebar li a.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
        }

        .nav-sidebar li a i {
            margin-right: 10px;
            width: 25px;
        }

        /* Main content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px;
        }

        /* Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            border: none;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Contrat card */
        .contrat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            border-left: 4px solid transparent;
            position: relative;
        }

        .contrat-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .contrat-card.actif {
            border-left-color: #28a745;
        }

        .contrat-card.termine {
            border-left-color: #17a2b8;
        }

        .contrat-card.brouillon {
            border-left-color: #ffc107;
        }

        .contrat-card.suspendu {
            border-left-color: #dc3545;
        }

        .contrat-card.expire {
            border-left-color: #dc3545;
            background: #fff5f5;
        }

        .contrat-card.expirant {
            border-left-color: #fd7e14;
        }

        .reference-badge {
            font-family: 'Courier New', monospace;
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .signature-preview {
            max-width: 80px;
            max-height: 40px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 2px;
            background: white;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .signature-preview:hover {
            transform: scale(1.5);
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .badge-etat {
            font-size: 0.75rem;
            padding: 0.4em 0.8em;
            border-radius: 20px;
        }

        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #dee2e6;
        }

        .section-title i {
            margin-right: 10px;
            color: #667eea;
        }

        /* Timeline */
        .timeline-item {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            transition: background 0.2s;
        }

        .timeline-item:hover {
            background: #f8f9fa;
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        /* Progress bar for contract duration */
        .contract-progress {
            height: 6px;
            border-radius: 3px;
            background: #e9ecef;
            margin: 10px 0;
        }

        .contract-progress-bar {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, #28a745, #20c997);
        }

        /* Modal signature */
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
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .modal-content {
            border: none;
            border-radius: 15px;
        }

        .modal-header {
            border-radius: 15px 15px 0 0;
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            color: white;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
                z-index: 1000;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3><strong>GESTION</strong> CONTRAT</h3>
                <p class="mb-0 small opacity-75">v2.0 Professional</p>
            </div>

            <div class="user-info">
                <div class="d-flex align-items-center">
                    <img src="https://via.placeholder.com/50" alt="User">
                    <div class="ms-3">
                        <p class="mb-0 fw-bold">Administrateur</p>
                        <small class="opacity-75">admin@gestion.com</small>
                    </div>
                </div>
            </div>

            <ul class="nav-sidebar">
                <li><a href="index.php"><i class="bi bi-speedometer2"></i> Tableau de bord</a></li>
                <li><a href="contact.php"><i class="bi bi-people"></i> Contacts</a></li>
                <li><a href="entretien.php"><i class="bi bi-chat-text"></i> Entretiens</a></li>
                <li><a href="contrat.php" class="active"><i class="bi bi-file-text"></i> Contrats</a></li>
                <li><a href="paiements.php"><i class="bi bi-currency-euro"></i> Paiements</a></li>
                <li><a href="rapports.php"><i class="bi bi-bar-chart"></i> Rapports</a></li>
                <li><a href="parametres.php"><i class="bi bi-gear"></i> Paramètres</a></li>
                <li><a href="logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-light">
                        Gestion des Contrats
                        <small class="text-muted fs-6"><?= $total_contrats ?> contrat(s) au total</small>
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Contrats</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalContrat"
                        <?= empty($entretiens_prets) ? 'disabled' : '' ?>>
                        <i class="bi bi-file-earmark-text"></i> Nouveau Contrat
                    </button>
                </div>
            </div>

            <!-- Alertes -->
            <?php if ($stats['expires'] > 0): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Attention !</strong> <?= $stats['expires'] ?> contrat(s) sont expirés.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($stats['expirant_bientot'] > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-clock-history me-2"></i>
                    <strong>Rappel :</strong> <?= $stats['expirant_bientot'] ?> contrat(s) expirent dans moins de 30 jours.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <i class="bi bi-file-text"></i>
                        </div>
                        <div class="stat-value"><?= $stats['total'] ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-value"><?= $stats['actifs'] ?></div>
                        <div class="stat-label">Actifs</div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div class="stat-value"><?= $stats['termines'] ?></div>
                        <div class="stat-label">Terminés</div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                            <i class="bi bi-pencil"></i>
                        </div>
                        <div class="stat-value"><?= $stats['brouillons'] ?></div>
                        <div class="stat-label">Brouillons</div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stat-value"><?= $stats['expirant_bientot'] ?></div>
                        <div class="stat-label">Expirent bientôt</div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <i class="bi bi-currency-euro"></i>
                        </div>
                        <div class="stat-value"><?= number_format($stats['montant_total'], 0) ?> €</div>
                        <div class="stat-label">CA Total</div>
                    </div>
                </div>
            </div>

            <!-- Filtres avancés -->
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-search"></i> Rechercher
                        </label>
                        <input type="text" name="search" class="form-control"
                            placeholder="Référence, titre, client..."
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-tag"></i> État
                        </label>
                        <select name="etat" class="form-select">
                            <option value="">Tous</option>
                            <option value="brouillon" <?= (isset($_GET['etat']) && $_GET['etat'] == 'brouillon') ? 'selected' : '' ?>>Brouillon</option>
                            <option value="actif" <?= (isset($_GET['etat']) && $_GET['etat'] == 'actif') ? 'selected' : '' ?>>Actif</option>
                            <option value="termine" <?= (isset($_GET['etat']) && $_GET['etat'] == 'termine') ? 'selected' : '' ?>>Terminé</option>
                            <option value="suspendu" <?= (isset($_GET['etat']) && $_GET['etat'] == 'suspendu') ? 'selected' : '' ?>>Suspendu</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-person"></i> Contact
                        </label>
                        <select name="contact_id" class="form-select">
                            <option value="">Tous</option>
                            <?php foreach ($contacts_filtre as $cf): ?>
                                <option value="<?= $cf['id'] ?>" <?= (isset($_GET['contact_id']) && $_GET['contact_id'] == $cf['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cf['nom_complet']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-calendar"></i> Date début
                        </label>
                        <input type="date" name="date_debut" class="form-control" value="<?= $_GET['date_debut'] ?? '' ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-calendar"></i> Date fin
                        </label>
                        <input type="date" name="date_fin" class="form-control" value="<?= $_GET['date_fin'] ?? '' ?>">
                    </div>

                    <div class="col-md-1 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-funnel"></i>
                            </button>
                            <a href="contrat.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Filtres supplémentaires (montant) -->
                <div class="row mt-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-currency-euro"></i> Montant min
                        </label>
                        <input type="number" name="montant_min" form="filtres" class="form-control" step="100" min="0"
                            value="<?= $_GET['montant_min'] ?? '' ?>" placeholder="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-currency-euro"></i> Montant max
                        </label>
                        <input type="number" name="montant_max" form="filtres" class="form-control" step="100" min="0"
                            value="<?= $_GET['montant_max'] ?? '' ?>" placeholder="10000">
                    </div>
                </div>
            </div>

            <!-- Liste des contrats -->
            <div class="row">
                <div class="col-lg-8">
                    <?php if (empty($contrats)): ?>
                        <div class="text-center py-5 bg-white rounded-3">
                            <i class="bi bi-file-earmark-x fs-1 text-muted d-block mb-3"></i>
                            <h5>Aucun contrat trouvé</h5>
                            <p class="text-muted">Commencez par créer un nouveau contrat</p>
                            <?php if (!empty($entretiens_prets)): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalContrat">
                                    <i class="bi bi-file-earmark-text"></i> Nouveau contrat
                                </button>
                            <?php else: ?>
                                <p class="text-warning">
                                    <i class="bi bi-info-circle"></i> Aucun entretien conclu disponible
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($contrats as $c):
                            $classe_expiration = '';
                            if ($c['etat'] == 'actif' && $c['date_fin_prevue']) {
                                if ($c['jours_restants'] < 0) {
                                    $classe_expiration = 'expire';
                                } elseif ($c['jours_restants'] <= 30) {
                                    $classe_expiration = 'expirant';
                                }
                            }
                        ?>
                            <div class="contrat-card <?= $c['etat'] ?> <?= $classe_expiration ?>">
                                <div class="row">
                                    <!-- Référence et titre -->
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="reference-badge"><?= htmlspecialchars($c['reference_contrat']) ?></span>
                                            <span class="badge badge-etat bg-<?=
                                                                                $c['etat'] == 'actif' ? 'success' : ($c['etat'] == 'termine' ? 'info' : ($c['etat'] == 'brouillon' ? 'warning' : 'danger'))
                                                                                ?>">
                                                <?= ucfirst($c['etat']) ?>
                                            </span>
                                            <?php if ($classe_expiration == 'expire'): ?>
                                                <span class="badge bg-danger">Expiré</span>
                                            <?php elseif ($classe_expiration == 'expirant'): ?>
                                                <span class="badge bg-warning">Expire dans <?= $c['jours_restants'] ?>j</span>
                                            <?php endif; ?>
                                        </div>

                                        <h5 class="fw-bold mb-2"><?= htmlspecialchars($c['titre_accord']) ?></h5>

                                        <div class="mb-2">
                                            <strong>
                                                <i class="bi bi-person"></i>
                                                <?= htmlspecialchars($c['nom_complet'] ?? 'Contact non spécifié') ?>
                                            </strong>
                                            <?php if (!empty($c['email'])): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($c['email']) ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if (!empty($c['telephone'])): ?>
                                                <small class="text-muted ms-2">
                                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($c['telephone']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($c['entretien_objet'])): ?>
                                            <div class="mb-1">
                                                <i class="bi bi-chat-text text-muted"></i>
                                                <small>Issu de l'entretien: <?= htmlspecialchars($c['entretien_objet']) ?></small>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Période du contrat -->
                                        <div class="row mt-2">
                                            <div class="col-md-6">
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-calendar"></i> Début:
                                                    <?= $c['date_debut_prevue'] ? date('d/m/Y', strtotime($c['date_debut_prevue'])) : 'Non définie' ?>
                                                </small>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-calendar"></i> Fin:
                                                    <?= $c['date_fin_prevue'] ? date('d/m/Y', strtotime($c['date_fin_prevue'])) : 'Non définie' ?>
                                                </small>
                                            </div>
                                        </div>

                                        <!-- Barre de progression si dates définies -->
                                        <?php if ($c['date_debut_prevue'] && $c['date_fin_prevue'] && $c['etat'] == 'actif'):
                                            $debut = strtotime($c['date_debut_prevue']);
                                            $fin = strtotime($c['date_fin_prevue']);
                                            $now = time();
                                            $total = $fin - $debut;
                                            $ecoule = $now - $debut;
                                            $pourcentage = $total > 0 ? min(100, max(0, ($ecoule / $total) * 100)) : 0;
                                        ?>
                                            <div class="contract-progress">
                                                <div class="contract-progress-bar" style="width: <?= $pourcentage ?>%"></div>
                                            </div>
                                            <small class="text-muted">Progression: <?= round($pourcentage) ?>%</small>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Montant et signature -->
                                    <div class="col-md-4 text-end">
                                        <div class="mb-3">
                                            <span class="display-6 fw-bold text-primary">
                                                <?= number_format($c['montant_total'], 0) ?>
                                            </span>
                                            <small class="text-muted"><?= htmlspecialchars($c['devise'] ?? 'EUR') ?></small>

                                            <?php if (!empty($c['frequence_paiement'])): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-arrow-repeat"></i> <?= htmlspecialchars($c['frequence_paiement']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($c['signature_image'])): ?>
                                            <div class="mb-3">
                                                <img src="<?= htmlspecialchars($c['signature_image']) ?>"
                                                    class="signature-preview"
                                                    alt="Signature"
                                                    onclick="viewSignature('<?= htmlspecialchars($c['signature_image']) ?>')"
                                                    title="Cliquer pour agrandir">
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-check-circle text-success"></i>
                                                    Signé le <?= date('d/m/Y', strtotime($c['horodatage_signature'])) ?>
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-3 text-warning">
                                                <i class="bi bi-exclamation-triangle"></i>
                                                <small>Non signé</small>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Actions -->
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-info"
                                                onclick="viewContrat(<?= htmlspecialchars(json_encode($c)) ?>)"
                                                title="Voir détails">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary"
                                                onclick="downloadContrat(<?= $c['id'] ?>)"
                                                title="Télécharger PDF">
                                                <i class="bi bi-file-pdf"></i>
                                            </button>
                                            <?php if ($c['etat'] == 'actif'): ?>
                                                <button class="btn btn-sm btn-outline-success"
                                                    onclick="updateStatut(<?= $c['id'] ?>, 'termine')"
                                                    title="Marquer comme terminé">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($c['etat'] == 'brouillon'): ?>
                                                <button class="btn btn-sm btn-outline-warning"
                                                    onclick="editContrat(<?= $c['id'] ?>)"
                                                    title="Modifier">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description du service (si existante) -->
                                <?php if (!empty($c['description_service'])): ?>
                                    <div class="mt-3 pt-2 border-top">
                                        <small class="fw-bold">Description:</small>
                                        <p class="small mb-0 text-muted">
                                            <?= nl2br(htmlspecialchars(substr($c['description_service'], 0, 200))) ?>
                                            <?php if (strlen($c['description_service']) > 200): ?>
                                                ... <a href="#" onclick="viewDescription('<?= htmlspecialchars(addslashes($c['description_service'])) ?>')">lire plus</a>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Sidebar droite -->
                <div class="col-lg-4">
                    <!-- Entretiens disponibles pour contrat -->
                    <?php if (!empty($entretiens_prets)): ?>
                        <div class="card mb-4 border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="card-title mb-0 fw-bold">
                                    <i class="bi bi-chat-text me-2"></i>Entretiens prêts pour contrat
                                </h6>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($entretiens_prets, 0, 3) as $ep): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-center">
                                            <div class="timeline-icon bg-success bg-opacity-10 me-3">
                                                <i class="bi bi-person text-success"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold small"><?= htmlspecialchars($ep['nom_complet']) ?></div>
                                                <small class="text-muted d-block"><?= htmlspecialchars($ep['objet']) ?></small>
                                            </div>
                                            <button class="btn btn-sm btn-outline-success"
                                                onclick="createContract(<?= $ep['id'] ?>)">
                                                <i class="bi bi-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($entretiens_prets) > 3): ?>
                                    <div class="list-group-item text-center">
                                        <small class="text-muted">
                                            + <?= count($entretiens_prets) - 3 ?> autre(s) entretien(s) disponible(s)
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Top contrats -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="bi bi-trophy me-2 text-warning"></i>Top contrats
                            </h6>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($top_contrats as $tc): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold small"><?= htmlspecialchars($tc['nom_complet'] ?? 'N/A') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($tc['titre_accord']) ?></small>
                                        </div>
                                        <span class="badge bg-primary">
                                            <?= number_format($tc['montant_total'], 0) ?> €
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Derniers contrats -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="bi bi-clock-history me-2"></i>Derniers contrats
                            </h6>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($derniers_contrats as $dc): ?>
                                <div class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="timeline-icon bg-light me-3">
                                            <i class="bi bi-file-text text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small"><?= htmlspecialchars($dc['reference_contrat']) ?></div>
                                            <small class="text-muted d-block">
                                                <?= htmlspecialchars($dc['nom_complet'] ?? 'N/A') ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i>
                                                <?= date('d/m/Y', strtotime($dc['horodatage_signature'])) ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?=
                                                                $dc['etat'] == 'actif' ? 'success' : ($dc['etat'] == 'termine' ? 'info' : ($dc['etat'] == 'brouillon' ? 'warning' : 'danger'))
                                                                ?>">
                                            <?= ucfirst(substr($dc['etat'], 0, 6)) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Répartition par devise -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="bi bi-pie-chart me-2"></i>Répartition par devise
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($repartition_devise as $rd): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?= htmlspecialchars($rd['devise']) ?></span>
                                    <span class="badge bg-primary rounded-pill"><?= $rd['total'] ?></span>
                                </div>
                                <div class="progress mb-3" style="height: 5px;">
                                    <div class="progress-bar" style="width: <?= ($rd['total'] / $stats['total']) * 100 ?>%"></div>
                                </div>
                            <?php endforeach; ?>

                            <div class="mt-3 text-center">
                                <small class="text-muted">
                                    Montant total: <?= number_format($stats['montant_total'], 0) ?> €
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALE CONTRAT + SIGNATURE -->
    <div class="modal fade" id="modalContrat" tabindex="-1" aria-labelledby="modalContratLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalContratLabel">
                        <i class="bi bi-file-earmark-text me-2"></i>Nouveau Contrat avec Signature
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>

                <form method="POST" id="formContrat" onsubmit="return validateSignature()">
                    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                        <?php if (empty($entretiens_prets)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle fs-4 d-block text-center mb-2"></i>
                                <p class="mb-0 text-center">
                                    Aucun entretien conclu disponible.<br>
                                    <a href="entretien.php" class="alert-link">Voir les entretiens</a> pour en conclure un.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <!-- Sélection entretien -->
                                <div class="col-12">
                                    <div class="card bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-3">
                                                <i class="bi bi-chat-text me-2"></i>1. Sélectionner l'entretien conclu
                                            </h6>
                                            <select name="entretien_id" class="form-select" required>
                                                <option value="">Choisir un entretien...</option>
                                                <?php foreach ($entretiens_prets as $ep): ?>
                                                    <option value="<?= $ep['id'] ?>">
                                                        <?= htmlspecialchars($ep['nom_complet']) ?> - <?= htmlspecialchars($ep['objet']) ?>
                                                        <?= !empty($ep['email']) ? ' (' . htmlspecialchars($ep['email']) . ')' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Informations contrat -->
                                <div class="col-12 mt-3">
                                    <h6 class="fw-bold mb-3">
                                        <i class="bi bi-file-text me-2"></i>2. Informations du contrat
                                    </h6>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Titre de l'accord <span class="text-danger">*</span></label>
                                    <input type="text" name="titre_accord" class="form-control" required
                                        maxlength="200" placeholder="ex: Contrat de prestation de services">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Montant total</label>
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

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Fréquence de paiement</label>
                                    <select name="frequence_paiement" class="form-select">
                                        <option value="">Unique</option>
                                        <option value="mensuel">Mensuel</option>
                                        <option value="trimestriel">Trimestriel</option>
                                        <option value="annuel">Annuel</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Date de début</label>
                                    <input type="date" name="date_debut_prevue" class="form-control"
                                        min="<?= date('Y-m-d') ?>">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Date de fin</label>
                                    <input type="date" name="date_fin_prevue" class="form-control"
                                        min="<?= date('Y-m-d') ?>">
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">Description des services</label>
                                    <textarea name="description_service" class="form-control" rows="3"
                                        placeholder="Détail des services fournis..."></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-info">Clauses particulières</label>
                                    <textarea name="clause_particuliere" class="form-control" rows="3"
                                        placeholder="Conditions spécifiques..."></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-warning">Modalités de rupture</label>
                                    <textarea name="modalite_rupture" class="form-control" rows="3"
                                        placeholder="Conditions de résiliation..."></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">État initial</label>
                                    <select name="etat" class="form-select">
                                        <option value="brouillon">Brouillon</option>
                                        <option value="actif" selected>Actif (signé)</option>
                                    </select>
                                </div>

                                <!-- Zone de signature -->
                                <div class="col-12 mt-4">
                                    <div class="signature-container">
                                        <h6 class="fw-bold mb-3 text-primary">
                                            <i class="bi bi-pen me-2"></i>3. Signature du client <span class="text-danger">*</span>
                                        </h6>

                                        <div class="text-center mb-3">
                                            <canvas id="signature-pad" width="600" height="200"
                                                style="border: 2px solid #dee2e6; border-radius: 8px; 
                                                           max-width: 100%; height: auto; cursor: crosshair; 
                                                           background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                            </canvas>
                                        </div>

                                        <div class="d-flex justify-content-center gap-2 mb-3">
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="undoSignature" title="Annuler le dernier tracé">
                                                <i class="bi bi-arrow-counterclockwise"></i> Annuler
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                id="clearSignature" title="Tout effacer">
                                                <i class="bi bi-eraser"></i> Effacer tout
                                            </button>
                                        </div>

                                        <input type="hidden" name="signature_data" id="signature_data">

                                        <div class="text-center text-muted small">
                                            <i class="bi bi-info-circle"></i>
                                            Dessinez votre signature dans la zone ci-dessus (souris ou doigt sur tablette)
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x"></i> Annuler
                        </button>
                        <?php if (!empty($entretiens_prets)): ?>
                            <button type="submit" name="save_contrat" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Créer et signer le contrat
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Configuration du Signature Pad
        const canvas = document.getElementById('signature-pad');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            let drawing = false;
            let paths = [
                []
            ];
            let currentPath = [];

            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#000';

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
                currentPath = [coords];

                ctx.beginPath();
                ctx.moveTo(coords.x, coords.y);
            }

            function draw(e) {
                e.preventDefault();
                if (!drawing) return;

                const coords = getCoordinates(e);
                currentPath.push(coords);

                ctx.lineTo(coords.x, coords.y);
                ctx.stroke();

                ctx.beginPath();
                ctx.moveTo(coords.x, coords.y);
            }

            function stopDrawing() {
                if (drawing && currentPath.length > 0) {
                    paths.push([...currentPath]);
                }
                drawing = false;
                ctx.beginPath();
            }

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

            function undo() {
                if (paths.length > 1) {
                    paths.pop();
                    redraw();
                }
            }

            function clear() {
                paths = [
                    []
                ];
                redraw();
            }

            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);

            canvas.addEventListener('touchstart', startDrawing, {
                passive: false
            });
            canvas.addEventListener('touchmove', draw, {
                passive: false
            });
            canvas.addEventListener('touchend', stopDrawing);
            canvas.addEventListener('touchcancel', stopDrawing);

            document.getElementById('undoSignature')?.addEventListener('click', undo);
            document.getElementById('clearSignature')?.addEventListener('click', clear);

            // Réinitialiser à l'ouverture du modal
            document.getElementById('modalContrat')?.addEventListener('show.bs.modal', clear);
        }

        // Validation de la signature
        window.validateSignature = function() {
            const canvas = document.getElementById('signature-pad');
            if (!canvas) return true;

            const ctx = canvas.getContext('2d');
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const pixels = imageData.data;
            let hasDrawing = false;

            for (let i = 3; i < pixels.length; i += 4) {
                if (pixels[i] > 0) {
                    hasDrawing = true;
                    break;
                }
            }

            if (!hasDrawing) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Signature requise',
                    text: 'Veuillez signer avant de créer le contrat'
                });
                return false;
            }

            document.getElementById('signature_data').value = canvas.toDataURL('image/png');
            return true;
        };

        // Fonctions d'affichage
        function viewContrat(contrat) {
            let signature = contrat.signature_image ?
                `<img src="${contrat.signature_image}" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; border-radius: 4px;">` :
                '<span class="text-muted">Non signé</span>';

            let details = `
                <div class="text-start" style="max-height: 400px; overflow-y: auto;">
                    <p><strong>Référence:</strong> ${contrat.reference_contrat}</p>
                    <p><strong>Client:</strong> ${contrat.nom_complet || 'N/A'}</p>
                    ${contrat.email ? `<p><strong>Email:</strong> ${contrat.email}</p>` : ''}
                    <hr>
                    <p><strong>Titre:</strong> ${contrat.titre_accord}</p>
                    <p><strong>Montant:</strong> ${new Intl.NumberFormat('fr-FR', { style: 'currency', currency: contrat.devise || 'EUR' }).format(contrat.montant_total || 0)}</p>
                    ${contrat.frequence_paiement ? `<p><strong>Fréquence:</strong> ${contrat.frequence_paiement}</p>` : ''}
                    <p><strong>Période:</strong> ${contrat.date_debut_prevue || 'N/A'} - ${contrat.date_fin_prevue || 'N/A'}</p>
                    <hr>
                    ${contrat.description_service ? `<p><strong>Description:</strong><br>${contrat.description_service}</p>` : ''}
                    ${contrat.clause_particuliere ? `<p><strong>Clauses particulières:</strong><br>${contrat.clause_particuliere}</p>` : ''}
                    ${contrat.modalite_rupture ? `<p><strong>Modalités de rupture:</strong><br>${contrat.modalite_rupture}</p>` : ''}
                    <hr>
                    <p><strong>Signature:</strong></p>
                    <div class="text-center">${signature}</div>
                    <p class="text-muted small text-center mt-2">
                        Signé le ${new Date(contrat.horodatage_signature).toLocaleString('fr-FR')}
                        ${contrat.ip_signataire ? `<br>IP: ${contrat.ip_signataire}` : ''}
                    </p>
                </div>
            `;

            Swal.fire({
                title: 'Détails du contrat',
                html: details,
                icon: 'info',
                width: '600px',
                confirmButtonText: 'Fermer'
            });
        }

        function viewSignature(signature) {
            Swal.fire({
                title: 'Signature',
                html: `<img src="${signature}" style="max-width: 100%; border: 1px solid #ddd; border-radius: 4px;">`,
                confirmButtonText: 'Fermer'
            });
        }

        function viewDescription(description) {
            Swal.fire({
                title: 'Description complète',
                text: description,
                confirmButtonText: 'Fermer'
            });
        }

        function downloadContrat(id) {
            Swal.fire({
                title: 'Téléchargement PDF',
                text: 'Génération du contrat en cours...',
                icon: 'info',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Prêt !',
                    text: 'Le téléchargement va commencer',
                    timer: 1000,
                    showConfirmButton: false
                });
            });
        }

        function updateStatut(id, nouveauStatut) {
            let messages = {
                'termine': 'Voulez-vous marquer ce contrat comme terminé ?',
                'suspendu': 'Voulez-vous suspendre ce contrat ?',
                'actif': 'Voulez-vous réactiver ce contrat ?'
            };

            Swal.fire({
                title: 'Changer le statut',
                text: messages[nouveauStatut] || 'Confirmez le changement de statut',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Oui',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Succès !', 'Statut mis à jour', 'success').then(() => {
                        location.reload();
                    });
                }
            });
        }

        function editContrat(id) {
            Swal.fire({
                title: 'Modifier le contrat',
                text: 'Fonctionnalité de modification à implémenter',
                icon: 'info'
            });
        }

        function createContract(entretienId) {
            $('#modalContrat').modal('show');
            // Ici vous pourriez pré-sélectionner l'entretien
        }

        // Auto-fermeture du modal après soumission
        <?php if ($status == "success"): ?>
            var modal = bootstrap.Modal.getInstance(document.getElementById('modalContrat'));
            if (modal) {
                modal.hide();
            }
        <?php endif; ?>
    </script>

    <!-- Notifications -->
    <?php if ($status == "success"): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Contrat créé !',
                text: '<?= $status_message ?>',
                confirmButtonColor: '#007bff',
                timer: 5000,
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

    <!-- Script pour le toggle du sidebar sur mobile -->
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const isClickInside = sidebar.contains(event.target);

            if (window.innerWidth <= 768 && !isClickInside && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>

</html>