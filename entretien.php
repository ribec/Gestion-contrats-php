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

// Recherche
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(e.objet LIKE :search OR co.nom_complet LIKE :search OR e.lieu LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Comptage total pour pagination
$count_sql = "SELECT COUNT(*) FROM entretien e LEFT JOIN contact co ON e.contact_id = co.id $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_entretiens = $count_stmt->fetchColumn();
$total_pages = ceil($total_entretiens / $limit);

// Récupération des entretiens
$sql = "SELECT e.*, co.nom_complet, co.email, co.telephone, co.ville 
        FROM entretien e 
        LEFT JOIN contact co ON e.contact_id = co.id 
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

// Liste des contacts pour le formulaire
$contacts = $pdo->query("SELECT id, nom_complet, email FROM contact ORDER BY nom_complet ASC")->fetchAll();

// Statistiques
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM entretien")->fetchColumn();
$stats['en_attente'] = $pdo->query("SELECT COUNT(*) FROM entretien WHERE statut = 'en_attente'")->fetchColumn();
$stats['conclus'] = $pdo->query("SELECT COUNT(*) FROM entretien WHERE statut = 'conclu'")->fetchColumn();
$stats['annules'] = $pdo->query("SELECT COUNT(*) FROM entretien WHERE statut = 'annule'")->fetchColumn();
$stats['aujourd_hui'] = $pdo->query("SELECT COUNT(*) FROM entretien WHERE DATE(date_entretien) = CURDATE()")->fetchColumn();
$stats['cette_semaine'] = $pdo->query("SELECT COUNT(*) FROM entretien WHERE YEARWEEK(date_entretien) = YEARWEEK(CURDATE())")->fetchColumn();

// Derniers entretiens
$derniers_entretiens = $pdo->query("SELECT e.*, co.nom_complet 
                                   FROM entretien e 
                                   LEFT JOIN contact co ON e.contact_id = co.id 
                                   ORDER BY e.date_entretien DESC 
                                   LIMIT 5")->fetchAll();

// Taux de conversion (entretiens conclus / total)
$taux_conversion = $stats['total'] > 0 ? round(($stats['conclus'] / $stats['total']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes entretiens - Gestion des entretiens</title>
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
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
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
            background: rgba(0,0,0,0.2);
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
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            display: block;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-sidebar li a:hover,
        .nav-sidebar li a.active {
            background: rgba(255,255,255,0.2);
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            border: none;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.15);
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
        
        /* Entretien card */
        .entretien-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .entretien-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .entretien-card.en_attente {
            border-left-color: #ffc107;
        }
        
        .entretien-card.conclu {
            border-left-color: #28a745;
        }
        
        .entretien-card.annule {
            border-left-color: #dc3545;
            opacity: 0.8;
        }
        
        .entretien-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, transparent 50%, rgba(0,0,0,0.02) 50%);
            pointer-events: none;
        }
        
        .entretien-date {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            text-align: center;
            min-width: 80px;
        }
        
        .entretien-date .jour {
            font-size: 20px;
            font-weight: bold;
            line-height: 1;
        }
        
        .entretien-date .mois {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .entretien-date .heure {
            font-size: 11px;
            opacity: 0.8;
            margin-top: 3px;
        }
        
        .badge-statut {
            font-size: 0.8rem;
            padding: 0.5em 1em;
            border-radius: 20px;
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
            padding: 15px;
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
        
        /* Notes tooltip */
        .notes-preview {
            max-height: 60px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }
        
        .notes-preview::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 50px;
            height: 20px;
            background: linear-gradient(to right, transparent, white);
            pointer-events: none;
        }
        
        /* Modal styles */
        .modal-content {
            border: none;
            border-radius: 15px;
        }
        
        .modal-header {
            border-radius: 15px 15px 0 0;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
                <li><a href="entretien.php" class="active"><i class="bi bi-chat-text"></i> Entretiens</a></li>
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
                        Gestion des Entretiens
                        <small class="text-muted fs-6"><?= $total_entretiens ?> entretien(s) au total</small>
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Entretiens</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalEntretien">
                        <i class="bi bi-calendar-plus"></i> Nouvel Entretien
                    </button>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="stat-value"><?= $stats['total'] ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="stat-value"><?= $stats['en_attente'] ?></div>
                        <div class="stat-label">En attente</div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-value"><?= $stats['conclus'] ?></div>
                        <div class="stat-label">Conclus</div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div class="stat-value"><?= $stats['annules'] ?></div>
                        <div class="stat-label">Annulés</div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                            <i class="bi bi-calendar-day"></i>
                        </div>
                        <div class="stat-value"><?= $stats['aujourd_hui'] ?></div>
                        <div class="stat-label">Aujourd'hui</div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="stat-value"><?= $taux_conversion ?>%</div>
                        <div class="stat-label">Conversion</div>
                    </div>
                </div>
            </div>
            
            <!-- Filtres et recherche -->
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-search"></i> Rechercher
                        </label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Objet, contact, lieu..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-tag"></i> Statut
                        </label>
                        <select name="statut" class="form-select">
                            <option value="">Tous</option>
                            <option value="en_attente" <?= (isset($_GET['statut']) && $_GET['statut'] == 'en_attente') ? 'selected' : '' ?>>En attente</option>
                            <option value="conclu" <?= (isset($_GET['statut']) && $_GET['statut'] == 'conclu') ? 'selected' : '' ?>>Conclu</option>
                            <option value="annule" <?= (isset($_GET['statut']) && $_GET['statut'] == 'annule') ? 'selected' : '' ?>>Annulé</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-person"></i> Contact
                        </label>
                        <select name="contact_id" class="form-select">
                            <option value="">Tous</option>
                            <?php foreach ($contacts as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (isset($_GET['contact_id']) && $_GET['contact_id'] == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nom_complet']) ?>
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
                            <a href="entretien.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Liste des entretiens -->
            <div class="row">
                <div class="col-lg-8">
                    <?php if (empty($entretiens)): ?>
                        <div class="text-center py-5 bg-white rounded-3">
                            <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
                            <h5>Aucun entretien trouvé</h5>
                            <p class="text-muted">Commencez par planifier un nouvel entretien</p>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalEntretien">
                                <i class="bi bi-calendar-plus"></i> Nouvel entretien
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($entretiens as $e): ?>
                        <div class="entretien-card <?= $e['statut'] ?>">
                            <div class="row">
                                <!-- Date -->
                                <div class="col-md-2">
                                    <div class="entretien-date">
                                        <div class="jour"><?= date('d', strtotime($e['date_entretien'])) ?></div>
                                        <div class="mois"><?= date('M', strtotime($e['date_entretien'])) ?></div>
                                        <div class="heure"><?= date('H:i', strtotime($e['date_entretien'])) ?></div>
                                    </div>
                                </div>
                                
                                <!-- Infos principales -->
                                <div class="col-md-7">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($e['objet']) ?></h5>
                                        <span class="badge badge-statut bg-<?= 
                                            $e['statut'] == 'conclu' ? 'success' : 
                                            ($e['statut'] == 'annule' ? 'danger' : 'warning') 
                                        ?>">
                                            <?= ucfirst(str_replace('_', ' ', $e['statut'])) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <strong>
                                            <i class="bi bi-person"></i> 
                                            <?= htmlspecialchars($e['nom_complet'] ?? 'Contact supprimé') ?>
                                        </strong>
                                        <?php if (!empty($e['email'])): ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="bi bi-envelope"></i> <?= htmlspecialchars($e['email']) ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($e['telephone'])): ?>
                                            <small class="text-muted ms-2">
                                                <i class="bi bi-telephone"></i> <?= htmlspecialchars($e['telephone']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($e['lieu'])): ?>
                                        <div class="mb-1">
                                            <i class="bi bi-geo-alt text-muted"></i> 
                                            <?= htmlspecialchars($e['lieu']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($e['delai_souhaite'])): ?>
                                        <div class="mb-1">
                                            <i class="bi bi-clock text-muted"></i> 
                                            Délai souhaité: <?= htmlspecialchars($e['delai_souhaite']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($e['notes_discussion'])): ?>
                                        <div class="notes-preview text-muted small" 
                                             onclick="viewNotes('<?= htmlspecialchars(addslashes($e['notes_discussion'])) ?>')">
                                            <i class="bi bi-chat-dots"></i> 
                                            <?= substr(htmlspecialchars($e['notes_discussion']), 0, 100) ?>...
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Actions -->
                                <div class="col-md-3 text-end">
                                    <div class="btn-group-vertical w-100">
                                        <?php if ($e['statut'] == 'conclu'): ?>
                                            <a href="contrat.php?entretien_id=<?= $e['id'] ?>" class="btn btn-sm btn-success mb-1">
                                                <i class="bi bi-file-text"></i> Créer contrat
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-outline-info mb-1" 
                                                onclick="viewDetails(<?= htmlspecialchars(json_encode($e)) ?>)">
                                            <i class="bi bi-eye"></i> Détails
                                        </button>
                                        
                                        <?php if ($e['statut'] == 'en_attente'): ?>
                                            <button class="btn btn-sm btn-outline-primary mb-1" 
                                                    onclick="editEntretien(<?= $e['id'] ?>)">
                                                <i class="bi bi-pencil"></i> Modifier
                                            </button>
                                            <button class="btn btn-sm btn-outline-success mb-1" 
                                                    onclick="updateStatut(<?= $e['id'] ?>, 'conclu')">
                                                <i class="bi bi-check-circle"></i> Conclure
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="updateStatut(<?= $e['id'] ?>, 'annule')">
                                                <i class="bi bi-x-circle"></i> Annuler
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Besoins explicites et hors périmètre (si existent) -->
                            <?php if (!empty($e['besoin_explicite']) || !empty($e['hors_perimetre'])): ?>
                            <div class="row mt-3 pt-3 border-top">
                                <?php if (!empty($e['besoin_explicite'])): ?>
                                <div class="col-md-6">
                                    <small class="text-success fw-bold">
                                        <i class="bi bi-check-circle"></i> Besoins explicites:
                                    </small>
                                    <p class="small mb-0"><?= nl2br(htmlspecialchars($e['besoin_explicite'])) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($e['hors_perimetre'])): ?>
                                <div class="col-md-6">
                                    <small class="text-danger fw-bold">
                                        <i class="bi bi-exclamation-triangle"></i> Hors périmètre:
                                    </small>
                                    <p class="small mb-0"><?= nl2br(htmlspecialchars($e['hors_perimetre'])) ?></p>
                                </div>
                                <?php endif; ?>
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
                    <!-- Calendrier miniature -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="bi bi-calendar-week me-2"></i>Cette semaine
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-bold"><?= $stats['cette_semaine'] ?> entretien(s)</span>
                                <span class="badge bg-info"><?= date('d M') ?> - <?= date('d M', strtotime('+6 days')) ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?= min(100, ($stats['cette_semaine'] / 10) * 100) ?>%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Derniers entretiens -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="bi bi-clock-history me-2"></i>Derniers entretiens
                            </h6>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($derniers_entretiens as $de): ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <div class="timeline-icon bg-light me-3">
                                        <i class="bi bi-chat-text text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold small"><?= htmlspecialchars($de['objet']) ?></div>
                                        <small class="text-muted d-block">
                                            <?= htmlspecialchars($de['nom_complet'] ?? 'Contact supprimé') ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> <?= date('d/m/Y H:i', strtotime($de['date_entretien'])) ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?= 
                                        $de['statut'] == 'conclu' ? 'success' : 
                                        ($de['statut'] == 'annule' ? 'danger' : 'warning') 
                                    ?> badge-statut">
                                        <?= ucfirst(substr($de['statut'], 0, 8)) ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Statistiques de conversion -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="bi bi-graph-up me-2"></i>Taux de conversion
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="display-4 fw-bold text-success"><?= $taux_conversion ?>%</div>
                                <small class="text-muted">des entretiens aboutissent à un contrat</small>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Conclus: <?= $stats['conclus'] ?></span>
                                <span class="text-success"><?= $stats['total'] > 0 ? round(($stats['conclus']/$stats['total'])*100) : 0 ?>%</span>
                            </div>
                            <div class="progress mb-3" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?= $stats['total'] > 0 ? ($stats['conclus']/$stats['total'])*100 : 0 ?>%"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>En attente: <?= $stats['en_attente'] ?></span>
                                <span class="text-warning"><?= $stats['total'] > 0 ? round(($stats['en_attente']/$stats['total'])*100) : 0 ?>%</span>
                            </div>
                            <div class="progress mb-3" style="height: 8px;">
                                <div class="progress-bar bg-warning" style="width: <?= $stats['total'] > 0 ? ($stats['en_attente']/$stats['total'])*100 : 0 ?>%"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Annulés: <?= $stats['annules'] ?></span>
                                <span class="text-danger"><?= $stats['total'] > 0 ? round(($stats['annules']/$stats['total'])*100) : 0 ?>%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-danger" style="width: <?= $stats['total'] > 0 ? ($stats['annules']/$stats['total'])*100 : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions rapides -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="bi bi-lightning me-2"></i>Actions rapides
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-success text-start" data-bs-toggle="modal" data-bs-target="#modalEntretien">
                                    <i class="bi bi-calendar-plus me-2"></i> Planifier un entretien
                                </button>
                                <button class="btn btn-outline-primary text-start" onclick="exportEntretiens()">
                                    <i class="bi bi-download me-2"></i> Exporter la liste
                                </button>
                                <button class="btn btn-outline-info text-start" onclick="voirRapport()">
                                    <i class="bi bi-bar-chart me-2"></i> Rapport d'activité
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALE AJOUT ENTRETIEN -->
    <div class="modal fade" id="modalEntretien" tabindex="-1" aria-labelledby="modalEntretienLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="modalEntretienLabel">
                        <i class="bi bi-calendar-plus me-2"></i>Nouvel Entretien
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                
                <form method="POST" id="entretienForm">
                    <div class="modal-body">
                        <?php if (empty($contacts)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                Aucun contact disponible. Veuillez d'abord <a href="contact.php" class="alert-link">créer un contact</a>.
                            </div>
                        <?php else: ?>
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
                                                <?= !empty($c['email']) ? '- ' . htmlspecialchars($c['email']) : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-chat"></i> Objet <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="objet" class="form-control" required maxlength="200" 
                                           placeholder="ex: Refonte site web, Conseil juridique...">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-geo-alt"></i> Lieu
                                    </label>
                                    <input type="text" name="lieu" class="form-control" maxlength="100" 
                                           placeholder="Bureau, Zoom, Téléphone...">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-clock"></i> Délai souhaité
                                    </label>
                                    <input type="text" name="delai_souhaite" class="form-control" maxlength="50" 
                                           placeholder="ex: 2 semaines, 1 mois...">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-chat-dots"></i> Notes de discussion
                                    </label>
                                    <textarea name="notes_discussion" class="form-control" rows="2" 
                                              placeholder="Résumé de la discussion..."></textarea>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-success">
                                        <i class="bi bi-check-circle"></i> Besoins explicites
                                    </label>
                                    <textarea name="besoin_explicite" class="form-control" rows="3" 
                                              placeholder="Ce que le client souhaite..."></textarea>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-danger">
                                        <i class="bi bi-exclamation-triangle"></i> Hors périmètre
                                    </label>
                                    <textarea name="hors_perimetre" class="form-control" rows="3" 
                                              placeholder="Ce qui n'est pas inclus..."></textarea>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-flag"></i> Statut initial
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
                        <?php endif; ?>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x"></i> Annuler
                        </button>
                        <?php if (!empty($contacts)): ?>
                            <button type="submit" name="save_entretien" class="btn btn-success">
                                <i class="bi bi-check-lg"></i> Enregistrer l'entretien
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
        // Fonction pour voir les notes complètes
        function viewNotes(notes) {
            Swal.fire({
                title: 'Notes de discussion',
                text: notes,
                icon: 'info',
                confirmButtonText: 'Fermer'
            });
        }
        
        // Fonction pour voir les détails complets
        function viewDetails(entretien) {
            let details = `
                <div class="text-start">
                    <p><strong>Contact:</strong> ${entretien.nom_complet || 'Non spécifié'}</p>
                    ${entretien.email ? `<p><strong>Email:</strong> ${entretien.email}</p>` : ''}
                    ${entretien.telephone ? `<p><strong>Téléphone:</strong> ${entretien.telephone}</p>` : ''}
                    <hr>
                    <p><strong>Objet:</strong> ${entretien.objet}</p>
                    <p><strong>Lieu:</strong> ${entretien.lieu || 'Non renseigné'}</p>
                    <p><strong>Date:</strong> ${new Date(entretien.date_entretien).toLocaleString('fr-FR')}</p>
                    <p><strong>Délai souhaité:</strong> ${entretien.delai_souhaite || 'Non renseigné'}</p>
                    <hr>
                    <p><strong>Notes:</strong><br>${entretien.notes_discussion || 'Aucune note'}</p>
                    <p><strong>Besoins explicites:</strong><br>${entretien.besoin_explicite || 'Aucun'}</p>
                    <p><strong>Hors périmètre:</strong><br>${entretien.hors_perimetre || 'Rien'}</p>
                </div>
            `;
            
            Swal.fire({
                title: 'Détails de l\'entretien',
                html: details,
                icon: 'info',
                width: '600px',
                confirmButtonText: 'Fermer'
            });
        }
        
        // Fonction pour modifier un entretien
        function editEntretien(id) {
            Swal.fire({
                title: 'Modifier l\'entretien',
                text: 'Fonctionnalité de modification à implémenter',
                icon: 'info'
            });
        }
        
        // Fonction pour mettre à jour le statut
        function updateStatut(id, nouveauStatut) {
            let messages = {
                'conclu': 'Voulez-vous marquer cet entretien comme conclu ?',
                'annule': 'Voulez-vous annuler cet entretien ?'
            };
            
            let couleurs = {
                'conclu': '#28a745',
                'annule': '#dc3545'
            };
            
            Swal.fire({
                title: 'Changer le statut',
                text: messages[nouveauStatut],
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: couleurs[nouveauStatut],
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Oui, confirmer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Ici vous feriez un appel AJAX pour mettre à jour le statut
                    Swal.fire(
                        'Succès !',
                        `L'entretien a été marqué comme ${nouveauStatut}`,
                        'success'
                    ).then(() => {
                        location.reload();
                    });
                }
            });
        }
        
        // Fonction pour exporter les entretiens
        function exportEntretiens() {
            Swal.fire({
                title: 'Exporter les entretiens',
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
        
        // Fonction pour voir le rapport
        function voirRapport() {
            Swal.fire({
                title: 'Rapport d\'activité',
                html: `
                    <div class="text-start">
                        <p><strong>Période:</strong> ${new Date().toLocaleDateString('fr-FR')}</p>
                        <p><strong>Total entretiens:</strong> <?= $stats['total'] ?></p>
                        <p><strong>Taux de conversion:</strong> <?= $taux_conversion ?>%</p>
                        <p><strong>Entretiens aujourd'hui:</strong> <?= $stats['aujourd_hui'] ?></p>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Fermer'
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
    </script>

    <!-- Notifications -->
    <?php if ($status == "success"): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Succès !',
            text: '<?= $status_message ?>',
            confirmButtonColor: '#28a745',
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
        
        // Auto-fermeture du modal après soumission
        <?php if ($status == "success"): ?>
        var modal = bootstrap.Modal.getInstance(document.getElementById('modalEntretien'));
        if (modal) {
            modal.hide();
        }
        <?php endif; ?>
    </script>
</body>
</html>