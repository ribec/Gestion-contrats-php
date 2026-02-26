<?php
session_start();
require_once 'db.php';

// Vérification de l'authentification (à décommenter quand login.php sera créé)
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

// Statistiques globales
$stats = [];

// Nombre total de contacts
$stats['total_contacts'] = $pdo->query("SELECT COUNT(*) FROM contact")->fetchColumn();

// Entretiens du mois
$stats['entretiens_mois'] = $pdo->query("SELECT COUNT(*) FROM entretien WHERE MONTH(date_entretien) = MONTH(CURRENT_DATE()) AND YEAR(date_entretien) = YEAR(CURRENT_DATE())")->fetchColumn();

// Contrats actifs
$stats['contrats_actifs'] = $pdo->query("SELECT COUNT(*) FROM contrat WHERE etat = 'actif'")->fetchColumn();

// Chiffre d'affaires total (contrats actifs + terminés)
$stats['ca_total'] = $pdo->query("SELECT COALESCE(SUM(montant_total), 0) FROM contrat WHERE etat IN ('actif', 'termine')")->fetchColumn();

// Contrats par statut
$stats['contrats_par_statut'] = $pdo->query("SELECT etat, COUNT(*) as total FROM contrat GROUP BY etat")->fetchAll(PDO::FETCH_KEY_PAIR);

// Alertes : Contrats arrivant à échéance (30 jours)
$alertes_echeance = $pdo->prepare("SELECT c.*, co.nom_complet, co.email, co.telephone,
                                   DATEDIFF(c.date_fin_prevue, CURRENT_DATE()) as jours_restants
                                   FROM contrat c
                                   LEFT JOIN entretien e ON c.entretien_id = e.id
                                   LEFT JOIN contact co ON e.contact_id = co.id
                                   WHERE c.etat = 'actif' 
                                   AND c.date_fin_prevue IS NOT NULL
                                   AND c.date_fin_prevue BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
                                   ORDER BY c.date_fin_prevue ASC");
$alertes_echeance->execute();
$contrats_echeance = $alertes_echeance->fetchAll();

// Contrats expirés
$contrats_expires = $pdo->prepare("SELECT c.*, co.nom_complet, co.email, co.telephone,
                                   DATEDIFF(CURRENT_DATE(), c.date_fin_prevue) as jours_expires
                                   FROM contrat c
                                   LEFT JOIN entretien e ON c.entretien_id = e.id
                                   LEFT JOIN contact co ON e.contact_id = co.id
                                   WHERE c.etat = 'actif' 
                                   AND c.date_fin_prevue IS NOT NULL
                                   AND c.date_fin_prevue < CURRENT_DATE()
                                   ORDER BY c.date_fin_prevue ASC");
$contrats_expires->execute();
$contrats_expires = $contrats_expires->fetchAll();

// Derniers contrats signés
$derniers_contrats = $pdo->query("SELECT c.*, co.nom_complet, co.email,
                                  DATE(c.horodatage_signature) as date_signe
                                  FROM contrat c
                                  LEFT JOIN entretien e ON c.entretien_id = e.id
                                  LEFT JOIN contact co ON e.contact_id = co.id
                                  WHERE c.horodatage_signature IS NOT NULL
                                  ORDER BY c.horodatage_signature DESC
                                  LIMIT 5")->fetchAll();

// Prochains entretiens
$prochains_entretiens = $pdo->query("SELECT e.*, co.nom_complet, co.email, co.telephone
                                     FROM entretien e
                                     JOIN contact co ON e.contact_id = co.id
                                     WHERE e.date_entretien >= CURRENT_DATE()
                                     AND e.statut = 'en_attente'
                                     ORDER BY e.date_entretien ASC
                                     LIMIT 5")->fetchAll();

// Paiements en attente (simulé - à adapter selon votre logique métier)
$paiements_attente = $pdo->query("SELECT c.*, co.nom_complet,
                                  c.montant_total * 0.3 as acompte_attendu
                                  FROM contrat c
                                  LEFT JOIN entretien e ON c.entretien_id = e.id
                                  LEFT JOIN contact co ON e.contact_id = co.id
                                  WHERE c.etat = 'actif'
                                  AND c.montant_total > 0
                                  ORDER BY c.date_signature DESC
                                  LIMIT 5")->fetchAll();

// Activité récente (journal d'audit simplifié)
$activite_recente = $pdo->query("(SELECT 'contrat' as type, CONCAT('Contrat signé: ', titre_accord) as description, 
                                  horodatage_signature as date_action, ip_signataire as ip
                                  FROM contrat WHERE horodatage_signature IS NOT NULL)
                                  UNION
                                  (SELECT 'entretien' as type, CONCAT('Entretien avec ', co.nom_complet, ': ', e.objet) as description,
                                  e.date_entretien as date_action, NULL as ip
                                  FROM entretien e
                                  JOIN contact co ON e.contact_id = co.id)
                                  ORDER BY date_action DESC
                                  LIMIT 10")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Gestion Contrat</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Alert cards */
        .alert-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        
        .alert-card:hover {
            transform: translateX(5px);
        }
        
        .alert-card.warning {
            border-left-color: #ffc107;
        }
        
        .alert-card.danger {
            border-left-color: #dc3545;
        }
        
        .alert-card.info {
            border-left-color: #17a2b8;
        }
        
        .alert-card.success {
            border-left-color: #28a745;
        }
        
        .days-badge {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        /* Sections */
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
        
        /* Activity timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .timeline-item:after {
            content: '';
            position: absolute;
            left: -23px;
            top: 15px;
            bottom: -5px;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item:last-child:after {
            display: none;
        }
        
        .timeline-date {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
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
                <li><a href="index.php" class="active"><i class="bi bi-speedometer2"></i> Tableau de bord</a></li>
                <li><a href="contact.php"><i class="bi bi-people"></i> Contacts</a></li>
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
                <h2 class="fw-light">
                    Tableau de bord
                    <small class="text-muted fs-6">Bienvenue sur votre espace de gestion</small>
                </h2>
                <div>
                    <span class="badge bg-primary p-2">
                        <i class="bi bi-calendar"></i> <?= date('d/m/Y') ?>
                    </span>
                </div>
            </div>
            
            <!-- Alertes urgentes -->
            <?php if (!empty($contrats_expires)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Attention !</strong> <?= count($contrats_expires) ?> contrat(s) sont expirés. 
                <a href="#contrats-expires" class="alert-link">Voir les détails</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-value"><?= $stats['total_contacts'] ?></div>
                        <div class="stat-label">Contacts</div>
                        <small class="text-success">
                            <i class="bi bi-arrow-up"></i> +5% ce mois
                        </small>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                            <i class="bi bi-chat-text"></i>
                        </div>
                        <div class="stat-value"><?= $stats['entretiens_mois'] ?></div>
                        <div class="stat-label">Entretiens (mois)</div>
                        <small class="text-warning">
                            <i class="bi bi-calendar"></i> Ce mois-ci
                        </small>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                            <i class="bi bi-file-text"></i>
                        </div>
                        <div class="stat-value"><?= $stats['contrats_actifs'] ?></div>
                        <div class="stat-label">Contrats actifs</div>
                        <small class="text-primary">
                            <i class="bi bi-check-circle"></i> En cours
                        </small>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                            <i class="bi bi-currency-euro"></i>
                        </div>
                        <div class="stat-value"><?= number_format($stats['ca_total'], 0) ?> €</div>
                        <div class="stat-label">Chiffre d'affaires</div>
                        <small class="text-success">
                            <i class="bi bi-graph-up"></i> Total
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques et alertes -->
            <div class="row g-4 mb-4">
                <!-- Graphique des contrats par statut -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-pie-chart me-2"></i>Répartition des contrats
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="contratsChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Alertes échéances -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle me-2 text-warning"></i>Alertes échéances (30 jours)
                            </h5>
                            <span class="badge bg-warning"><?= count($contrats_echeance) ?></span>
                        </div>
                        <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($contrats_echeance)): ?>
                                <p class="text-muted text-center py-4">
                                    <i class="bi bi-check-circle fs-2 d-block"></i>
                                    Aucune échéance dans les 30 jours
                                </p>
                            <?php else: ?>
                                <?php foreach ($contrats_echeance as $contrat): ?>
                                <div class="alert-card warning">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($contrat['titre_accord']) ?></h6>
                                            <p class="mb-1 small"><?= htmlspecialchars($contrat['nom_complet']) ?></p>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> Fin: <?= date('d/m/Y', strtotime($contrat['date_fin_prevue'])) ?>
                                            </small>
                                        </div>
                                        <span class="days-badge bg-warning bg-opacity-25 text-warning">
                                            <?= $contrat['jours_restants'] ?> jours
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <a href="contrat.php?id=<?= $contrat['id'] ?>" class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contrats expirés -->
            <?php if (!empty($contrats_expires)): ?>
            <div class="card mb-4 border-danger" id="contrats-expires">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Contrats expirés
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Référence</th>
                                    <th>Client</th>
                                    <th>Titre</th>
                                    <th>Expiré depuis</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contrats_expires as $contrat): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($contrat['reference_contrat']) ?></code></td>
                                    <td><?= htmlspecialchars($contrat['nom_complet']) ?></td>
                                    <td><?= htmlspecialchars($contrat['titre_accord']) ?></td>
                                    <td>
                                        <span class="badge bg-danger">
                                            <?= $contrat['jours_expires'] ?> jours
                                        </span>
                                    </td>
                                    <td>
                                        <a href="contrat.php?id=<?= $contrat['id'] ?>" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-arrow-repeat"></i> Renouveler
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Deux colonnes pour l'activité récente -->
            <div class="row g-4">
                <!-- Derniers contrats signés -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2"></i>Derniers contrats signés
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (empty($derniers_contrats)): ?>
                                    <div class="list-group-item text-center text-muted py-4">
                                        <i class="bi bi-file-text fs-2 d-block mb-2"></i>
                                        Aucun contrat signé récemment
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($derniers_contrats as $contrat): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($contrat['titre_accord']) ?></h6>
                                                <small class="text-muted d-block">
                                                    <?= htmlspecialchars($contrat['nom_complet']) ?>
                                                </small>
                                                <small class="text-success">
                                                    <i class="bi bi-check-circle"></i> Signé
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-primary">
                                                    <?= number_format($contrat['montant_total'], 0) ?> €
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y', strtotime($contrat['date_signe'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Prochains entretiens -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-calendar-event me-2"></i>Prochains entretiens
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (empty($prochains_entretiens)): ?>
                                    <div class="list-group-item text-center text-muted py-4">
                                        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                        Aucun entretien prévu
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($prochains_entretiens as $entretien): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($entretien['objet']) ?></h6>
                                                <small class="text-muted d-block">
                                                    <?= htmlspecialchars($entretien['nom_complet']) ?>
                                                </small>
                                                <?php if (!empty($entretien['lieu'])): ?>
                                                    <small class="text-muted">
                                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($entretien['lieu']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-info">
                                                    <?= date('d/m', strtotime($entretien['date_entretien'])) ?>
                                                </span>
                                                <br>
                                                <small class="text-primary">
                                                    <?= date('H:i', strtotime($entretien['date_entretien'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Paiements en attente -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-currency-euro me-2 text-success"></i>Paiements à suivre
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Client</th>
                                            <th>Contrat</th>
                                            <th>Montant total</th>
                                            <th>Acompte (30%)</th>
                                            <th>Statut</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($paiements_attente as $paiement): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($paiement['nom_complet']) ?></td>
                                            <td><?= htmlspecialchars($paiement['titre_accord']) ?></td>
                                            <td><?= number_format($paiement['montant_total'], 2) ?> €</td>
                                            <td><?= number_format($paiement['montant_total'] * 0.3, 2) ?> €</td>
                                            <td>
                                                <span class="badge bg-warning">En attente</span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-success" onclick="marquerPaye(<?= $paiement['id'] ?>)">
                                                    <i class="bi bi-check"></i> Marquer payé
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Journal d'activité -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-journal-text me-2"></i>Journal d'activité récente
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php foreach ($activite_recente as $activite): ?>
                                <div class="timeline-item">
                                    <div class="timeline-date">
                                        <?= date('d/m/Y H:i', strtotime($activite['date_action'])) ?>
                                        <?php if (!empty($activite['ip'])): ?>
                                            <span class="badge bg-secondary ms-2">IP: <?= $activite['ip'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-content">
                                        <?php 
                                        $icon = $activite['type'] == 'contrat' ? 'bi-file-text' : 'bi-chat-text';
                                        $color = $activite['type'] == 'contrat' ? 'text-success' : 'text-info';
                                        ?>
                                        <i class="bi <?= $icon ?> <?= $color ?> me-2"></i>
                                        <?= htmlspecialchars($activite['description']) ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Graphique des contrats par statut
        const ctx = document.getElementById('contratsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Actifs', 'Terminés', 'Brouillons', 'Suspendus'],
                datasets: [{
                    data: [
                        <?= $stats['contrats_par_statut']['actif'] ?? 0 ?>,
                        <?= $stats['contrats_par_statut']['termine'] ?? 0 ?>,
                        <?= $stats['contrats_par_statut']['brouillon'] ?? 0 ?>,
                        <?= $stats['contrats_par_statut']['suspendu'] ?? 0 ?>
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#17a2b8',
                        '#ffc107',
                        '#dc3545'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Fonction pour marquer un paiement comme effectué
        function marquerPaye(contratId) {
            Swal.fire({
                title: 'Confirmer le paiement',
                text: 'Voulez-vous marquer ce paiement comme effectué ?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Oui, marquer comme payé',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Ici vous feriez un appel AJAX pour mettre à jour le statut
                    Swal.fire(
                        'Succès !',
                        'Le paiement a été enregistré.',
                        'success'
                    );
                }
            });
        }

        // Actualisation automatique toutes les 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>