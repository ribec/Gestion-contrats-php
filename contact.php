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
        error_log("Erreur d'ajout de contact : " . $e->getMessage());
    }
}

// 3. RÉCUPÉRATION DES CONTACTS AVEC PAGINATION ET FILTRES
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$where_conditions = [];
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(nom_complet LIKE :search OR email LIKE :search OR telephone LIKE :search OR ville LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $where_conditions[] = "type_contact = :type";
    $params[':type'] = $_GET['type'];
}

if (isset($_GET['ville']) && !empty($_GET['ville'])) {
    $where_conditions[] = "ville = :ville";
    $params[':ville'] = $_GET['ville'];
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Comptage total pour pagination
$count_sql = "SELECT COUNT(*) FROM contact $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_contacts = $count_stmt->fetchColumn();
$total_pages = ceil($total_contacts / $limit);

// Récupération des contacts
$sql = "SELECT * FROM contact $where_clause ORDER BY date_creation DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$contacts = $stmt->fetchAll();

// Liste des villes pour le filtre
$villes = $pdo->query("SELECT DISTINCT ville FROM contact WHERE ville IS NOT NULL AND ville != '' ORDER BY ville")->fetchAll(PDO::FETCH_COLUMN);

// Statistiques
$stats = [];
$stats['total'] = $total_contacts;
$stats['professionnels'] = $pdo->query("SELECT COUNT(*) FROM contact WHERE type_contact = 'professionnel'")->fetchColumn();
$stats['particuliers'] = $pdo->query("SELECT COUNT(*) FROM contact WHERE type_contact = 'particulier'")->fetchColumn();
$stats['avec_contrat'] = $pdo->query("SELECT COUNT(DISTINCT e.contact_id) FROM entretien e JOIN contrat c ON e.id = c.entretien_id")->fetchColumn();

// Derniers contacts ajoutés
$derniers_contacts = $pdo->query("SELECT * FROM contact ORDER BY date_creation DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes contacts - Gestion des contacts</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

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

        /* Contact card for list view */
        .contact-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid transparent;
        }

        .contact-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .contact-card.professionnel {
            border-left-color: #17a2b8;
        }

        .contact-card.particulier {
            border-left-color: #6c757d;
        }

        .contact-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
        }

        .badge-type {
            font-size: 0.7rem;
            padding: 0.4em 0.8em;
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

        .quick-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .quick-stat-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 10px 15px;
            flex: 1;
            min-width: 120px;
        }

        .quick-stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }

        .quick-stat-value {
            font-size: 20px;
            font-weight: bold;
        }

        /* Modal styles */
        .modal-content {
            border: none;
            border-radius: 15px;
        }

        .modal-header {
            border-radius: 15px 15px 0 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
                <li><a href="contact.php" class="active"><i class="bi bi-people"></i> Contacts</a></li>
                <li><a href="entretien.php"><i class="bi bi-chat-text"></i> Entretiens</a></li>
                <li><a href="contrat.php"><i class="bi bi-file-text"></i> Contrats</a></li>
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
                        Gestion des Contacts
                        <small class="text-muted fs-6"><?= $total_contacts ?> contact(s) enregistré(s)</small>
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Contacts</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdd">
                        <i class="bi bi-plus-lg"></i> Nouveau Contact
                    </button>
                </div>
            </div>

            <!-- Statistiques rapides -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-value"><?= $stats['total'] ?></div>
                        <div class="stat-label">Total contacts</div>
                        <small class="text-success">
                            <i class="bi bi-arrow-up"></i> +<?= $stats['total'] > 0 ? round(($stats['total'] / max(1, $stats['total'] - 5)) * 100) : 0 ?>%
                        </small>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="stat-value"><?= $stats['professionnels'] ?></div>
                        <div class="stat-label">Professionnels</div>
                        <small class="text-primary">
                            <i class="bi bi-briefcase"></i> Entreprises
                        </small>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="stat-value"><?= $stats['particuliers'] ?></div>
                        <div class="stat-label">Particuliers</div>
                        <small class="text-warning">
                            <i class="bi bi-person-badge"></i> Clients
                        </small>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                            <i class="bi bi-file-text"></i>
                        </div>
                        <div class="stat-value"><?= $stats['avec_contrat'] ?></div>
                        <div class="stat-label">Avec contrat</div>
                        <small class="text-success">
                            <i class="bi bi-check-circle"></i> Actifs
                        </small>
                    </div>
                </div>
            </div>

            <!-- Filtres et recherche -->
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-search"></i> Rechercher
                        </label>
                        <input type="text" name="search" class="form-control"
                            placeholder="Nom, email, téléphone, ville..."
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-tag"></i> Type
                        </label>
                        <select name="type" class="form-select">
                            <option value="">Tous</option>
                            <option value="particulier" <?= (isset($_GET['type']) && $_GET['type'] == 'particulier') ? 'selected' : '' ?>>Particulier</option>
                            <option value="professionnel" <?= (isset($_GET['type']) && $_GET['type'] == 'professionnel') ? 'selected' : '' ?>>Professionnel</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-geo-alt"></i> Ville
                        </label>
                        <select name="ville" class="form-select">
                            <option value="">Toutes</option>
                            <?php foreach ($villes as $ville): ?>
                                <option value="<?= htmlspecialchars($ville) ?>" <?= (isset($_GET['ville']) && $_GET['ville'] == $ville) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ville) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-funnel"></i> Filtrer
                            </button>
                            <a href="contact.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Liste des contacts -->
            <div class="row">
                <div class="col-lg-8">
                    <!-- Vue en liste -->
                    <?php if (empty($contacts)): ?>
                        <div class="text-center py-5 bg-white rounded-3">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                            <h5>Aucun contact trouvé</h5>
                            <p class="text-muted">Commencez par ajouter un nouveau contact</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdd">
                                <i class="bi bi-plus-lg"></i> Ajouter un contact
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="contact-card <?= $contact['type_contact'] ?>">
                                <div class="d-flex align-items-center">
                                    <!-- Avatar -->
                                    <div class="contact-avatar me-3">
                                        <?= strtoupper(substr($contact['nom_complet'], 0, 1)) ?>
                                    </div>

                                    <!-- Infos principales -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($contact['nom_complet']) ?></h5>
                                            <span class="badge badge-type bg-<?= $contact['type_contact'] == 'professionnel' ? 'info' : 'secondary' ?>">
                                                <?= ucfirst($contact['type_contact']) ?>
                                            </span>
                                            <?php if (!empty($contact['nom_entreprise'])): ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="bi bi-building"></i> <?= htmlspecialchars($contact['nom_entreprise']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($contact['email'] ?? 'Non renseigné') ?>
                                                </small>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($contact['telephone'] ?? 'Non renseigné') ?>
                                                </small>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($contact['ville'] ?? 'Non renseignée') ?>
                                                </small>
                                            </div>
                                            <div class="col-md-2">
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-upc-scan"></i> IFU: <?= htmlspecialchars($contact['numero_ifu'] ?? 'N/A') ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="ms-3">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-info" title="Voir détails" onclick="viewContact(<?= $contact['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="entretien.php?contact_id=<?= $contact['id'] ?>" class="btn btn-sm btn-outline-success" title="Nouvel entretien">
                                                <i class="bi bi-chat-text"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-primary" title="Modifier" onclick="editContact(<?= $contact['id'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
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

                <!-- Sidebar droite avec infos complémentaires -->
                <div class="col-lg-4">
                    <!-- Derniers contacts ajoutés -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="bi bi-clock-history me-2"></i>Derniers ajouts
                            </h6>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($derniers_contacts as $dc): ?>
                                <div class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="contact-avatar me-2" style="width: 35px; height: 35px; font-size: 14px;">
                                            <?= strtoupper(substr($dc['nom_complet'], 0, 1)) ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small"><?= htmlspecialchars($dc['nom_complet']) ?></div>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($dc['date_creation'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Répartition géographique -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="bi bi-globe me-2"></i>Répartition par ville
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $repartition_villes = $pdo->query("SELECT ville, COUNT(*) as total 
                                                               FROM contact 
                                                               WHERE ville IS NOT NULL AND ville != '' 
                                                               GROUP BY ville 
                                                               ORDER BY total DESC 
                                                               LIMIT 5")->fetchAll();
                            ?>
                            <?php foreach ($repartition_villes as $rv): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?= htmlspecialchars($rv['ville']) ?></span>
                                    <span class="badge bg-primary rounded-pill"><?= $rv['total'] ?></span>
                                </div>
                                <div class="progress mb-3" style="height: 5px;">
                                    <div class="progress-bar" style="width: <?= ($rv['total'] / $stats['total']) * 100 ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div class="card mt-4">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="bi bi-lightning me-2"></i>Actions rapides
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary text-start" data-bs-toggle="modal" data-bs-target="#modalAdd">
                                    <i class="bi bi-person-plus me-2"></i> Nouveau contact
                                </button>
                                <button class="btn btn-outline-success text-start" onclick="importContacts()">
                                    <i class="bi bi-upload me-2"></i> Importer des contacts
                                </button>
                                <button class="btn btn-outline-info text-start" onclick="exportContacts()">
                                    <i class="bi bi-download me-2"></i> Exporter la liste
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALE D'AJOUT -->
    <div class="modal fade" id="modalAdd" tabindex="-1" aria-labelledby="modalAddLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalAddLabel">
                        <i class="bi bi-person-plus me-2"></i>Ajouter un Contact
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>

                <form method="POST" id="contactForm">
                    <div class="modal-body">
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
                                <input type="text" name="nom_complet" class="form-control" required maxlength="100"
                                    placeholder="Jean Dupont">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nom entreprise</label>
                                <input type="text" name="nom_entreprise" class="form-control" maxlength="100"
                                    placeholder="Entreprise SARL">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Numéro IFU</label>
                                <input type="text" name="numero_ifu" class="form-control" maxlength="20"
                                    placeholder="IFU123456">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control" maxlength="150"
                                    placeholder="contact@email.com">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Téléphone</label>
                                <input type="tel" name="telephone" class="form-control" maxlength="25"
                                    placeholder="+33 1 23 45 67 89">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Adresse</label>
                                <input type="text" name="adresse" class="form-control" maxlength="255"
                                    placeholder="123 rue Example">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Ville</label>
                                <input type="text" name="ville" class="form-control" maxlength="100"
                                    placeholder="Paris">
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
                            <i class="bi bi-check-lg"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Fonction pour voir les détails d'un contact
        function viewContact(id) {
            // Ici vous feriez un appel AJAX pour récupérer les détails
            Swal.fire({
                title: 'Détails du contact',
                html: `
                    <div class="text-start">
                        <p><strong>ID:</strong> ${id}</p>
                        <p><i class="bi bi-info-circle"></i> Fonctionnalité de détails à implémenter</p>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Fermer'
            });
        }

        // Fonction pour modifier un contact
        function editContact(id) {
            Swal.fire({
                title: 'Modification',
                text: 'Fonctionnalité de modification à implémenter',
                icon: 'info'
            });
        }

        // Fonction pour importer des contacts
        function importContacts() {
            Swal.fire({
                title: 'Importer des contacts',
                html: `
                    <input type="file" class="form-control" accept=".csv,.xlsx">
                    <small class="text-muted d-block mt-2">Formats acceptés: CSV, Excel</small>
                `,
                showCancelButton: true,
                confirmButtonText: 'Importer',
                cancelButtonText: 'Annuler'
            });
        }

        // Fonction pour exporter les contacts
        function exportContacts() {
            Swal.fire({
                title: 'Exporter les contacts',
                text: 'Choisissez le format d\'exportation',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'CSV',
                cancelButtonText: 'Excel',
                showDenyButton: true,
                denyButtonText: 'PDF'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Export CSV', 'Téléchargement en cours...', 'success');
                } else if (result.isDenied) {
                    Swal.fire('Export PDF', 'Téléchargement en cours...', 'success');
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    Swal.fire('Export Excel', 'Téléchargement en cours...', 'success');
                }
            });
        }

        // Validation du formulaire
        document.getElementById('contactForm')?.addEventListener('submit', function(e) {
            let nomComplet = this.querySelector('[name="nom_complet"]').value;
            let email = this.querySelector('[name="email"]').value;

            if (nomComplet.trim() === '') {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation',
                    text: 'Le nom complet est obligatoire'
                });
                return false;
            }

            if (email && !isValidEmail(email)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Email invalide',
                    text: 'Veuillez saisir une adresse email valide'
                });
                return false;
            }
        });

        // Fonction de validation email
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Auto-hide des alertes après 5 secondes
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>

    <!-- Notifications -->
    <?php if ($status == "success"): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Succès !',
                text: '<?= $status_message ?>',
                confirmButtonColor: '#667eea',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false
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
        // Bouton pour toggle le sidebar sur mobile (à ajouter dans l'en-tête si besoin)
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Fermer le sidebar quand on clique en dehors sur mobile
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