<?php
// index.php - Page d'accueil / Landing page
session_start();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - Gestion Contrat Pro - Solution complète de gestion contractuelle</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
            padding: 15px 0;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-link {
            font-weight: 500;
            color: #2d3748;
            margin: 0 10px;
            transition: color 0.3s;
        }

        .nav-link:hover {
            color: #667eea;
        }

        .btn-demo {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .btn-demo:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        /* Hero Section */
        .hero {
            padding: 150px 0 100px;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 80%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .hero-badge {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 20px;
        }

        .hero-title {
            font-size: 52px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 20px;
            color: #1a202c;
        }

        .hero-title span {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 18px;
            color: #4a5568;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .hero-stats {
            display: flex;
            gap: 40px;
            margin-top: 40px;
        }

        .hero-stat-item {
            text-align: center;
        }

        .hero-stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #1a202c;
            line-height: 1;
        }

        .hero-stat-label {
            color: #718096;
            font-size: 14px;
            font-weight: 500;
        }

        .hero-image {
            position: relative;
            z-index: 1;
        }

        .hero-image img {
            max-width: 100%;
            border-radius: 20px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.1);
        }

        .floating-card {
            position: absolute;
            background: white;
            padding: 15px 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: float 3s ease-in-out infinite;
        }

        .floating-card-1 {
            top: 20%;
            left: -30px;
            animation-delay: 0s;
        }

        .floating-card-2 {
            bottom: 20%;
            right: -30px;
            animation-delay: 1.5s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        /* Sections communes */
        .section-padding {
            padding: 80px 0;
        }

        .section-title {
            text-align: center;
            font-size: 36px;
            font-weight: 800;
            color: #1a202c;
            margin-bottom: 15px;
        }

        .section-subtitle {
            text-align: center;
            font-size: 18px;
            color: #718096;
            max-width: 700px;
            margin: 0 auto 50px;
        }

        /* Problèmes / Solutions */
        .problem-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            height: 100%;
            transition: transform 0.3s;
            border: 1px solid #e9ecef;
        }

        .problem-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.1);
        }

        .problem-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #fff5f5 0%, #ffe3e3 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .problem-icon i {
            font-size: 30px;
            color: #e53e3e;
        }

        .solution-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #f0fff4 0%, #dcfce7 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .solution-icon i {
            font-size: 30px;
            color: #38a169;
        }

        /* Fonctionnalités */
        .feature-card {
            text-align: center;
            padding: 40px 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            height: 100%;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(102, 126, 234, 0.15);
            border-color: transparent;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 35px;
        }

        .feature-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #1a202c;
        }

        .feature-description {
            color: #718096;
            line-height: 1.6;
        }

        /* Différenciateurs */
        .diff-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            height: 100%;
        }

        .diff-card.light {
            background: white;
            color: #1a202c;
            border: 1px solid #e9ecef;
        }

        .diff-icon {
            font-size: 40px;
            margin-bottom: 20px;
        }

        .diff-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .diff-list {
            list-style: none;
            padding: 0;
        }

        .diff-list li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .diff-list li i {
            color: #38a169;
        }

        /* Témoignages */
        .testimonial-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            height: 100%;
            position: relative;
        }

        .testimonial-card::before {
            content: '"';
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 80px;
            color: #667eea;
            opacity: 0.1;
            font-family: serif;
        }

        .testimonial-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .testimonial-text {
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 20px;
            font-style: italic;
        }

        .testimonial-author {
            font-weight: 700;
            color: #1a202c;
        }

        .testimonial-role {
            color: #718096;
            font-size: 14px;
        }

        /* Prix */
        .pricing-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            height: 100%;
            transition: transform 0.3s;
            position: relative;
            overflow: hidden;
        }

        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(102, 126, 234, 0.15);
        }

        .pricing-card.popular {
            border: 2px solid #667eea;
            transform: scale(1.05);
        }

        .pricing-card.popular:hover {
            transform: scale(1.05) translateY(-10px);
        }

        .popular-badge {
            position: absolute;
            top: 20px;
            right: -30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 40px;
            transform: rotate(45deg);
            font-size: 12px;
            font-weight: 600;
        }

        .pricing-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .pricing-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .pricing-price {
            font-size: 48px;
            font-weight: 800;
            color: #667eea;
        }

        .pricing-price small {
            font-size: 16px;
            font-weight: 400;
            color: #718096;
        }

        .pricing-features {
            list-style: none;
            padding: 0;
            margin: 30px 0;
        }

        .pricing-features li {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pricing-features li i {
            color: #38a169;
        }

        .pricing-features li.disabled {
            color: #cbd5e0;
        }

        .pricing-features li.disabled i {
            color: #cbd5e0;
        }

        .btn-pricing {
            width: 100%;
            padding: 12px;
            border-radius: 50px;
            font-weight: 600;
            border: 2px solid #667eea;
            color: #667eea;
            background: transparent;
            transition: all 0.3s;
        }

        .btn-pricing:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .btn-pricing.popular {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        /* CTA */
        .cta-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 80px 0;
            color: white;
        }

        .cta-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .cta-subtitle {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .btn-cta {
            background: white;
            color: #667eea;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
            border: none;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .btn-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.2);
            color: #667eea;
        }

        .btn-cta-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.3s;
        }

        .btn-cta-outline:hover {
            background: white;
            color: #667eea;
        }

        /* Footer */
        .footer {
            background: #1a202c;
            color: white;
            padding: 60px 0 30px;
        }

        .footer-logo {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }

        .footer-text {
            color: #a0aec0;
            line-height: 1.6;
        }

        .footer-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: #a0aec0;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: white;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s;
        }

        .social-links a:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: translateY(-3px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            margin-top: 30px;
            border-top: 1px solid #2d3748;
            color: #a0aec0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero {
                padding: 100px 0 50px;
            }

            .hero-title {
                font-size: 36px;
            }

            .hero-stats {
                flex-wrap: wrap;
                gap: 20px;
            }

            .floating-card {
                display: none;
            }

            .pricing-card.popular {
                transform: scale(1);
            }

            .pricing-card.popular:hover {
                transform: translateY(-10px);
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-file-text"></i> GESTION CONTRAT PRO
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Fonctionnalités</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#difference">Notre différence</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Tarifs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Témoignages</a>
                    </li>
                    <li class="nav-item ms-3">
                        <a href="login.php" class="btn btn-demo">
                            <i class="bi bi-box-arrow-in-right"></i> Se connecter
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="hero-badge">
                        <i class="bi bi-star-fill me-2"></i>
                        La solution n°1 pour les professionnels
                    </div>
                    <h1 class="hero-title">
                        La gestion de contrats <span>simplifiée</span> et <span>sécurisée</span>
                    </h1>
                    <p class="hero-subtitle">
                        Fini les PDF éparpillés, les signatures scannées et les dates d'échéance oubliées.
                        Gestion Contrat Pro centralise, automatise et sécurise tout votre cycle contractuel.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#demo" class="btn btn-demo btn-lg">
                            <i class="bi bi-play-circle"></i> Voir la démo
                        </a>
                        <a href="#features" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-arrow-down"></i> En savoir plus
                        </a>
                    </div>

                    <div class="hero-stats">
                        <div class="hero-stat-item">
                            <div class="hero-stat-number">500+</div>
                            <div class="hero-stat-label">Clients satisfaits</div>
                        </div>
                        <div class="hero-stat-item">
                            <div class="hero-stat-number">10k+</div>
                            <div class="hero-stat-label">Contrats gérés</div>
                        </div>
                        <div class="hero-stat-item">
                            <div class="hero-stat-number">98%</div>
                            <div class="hero-stat-label">Taux de satisfaction</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6" data-aos="fade-left">
                    <div class="hero-image">
                        <img src="https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-1.2.1&auto=format&fit=crop&w=1267&q=80"
                            alt="Dashboard" class="img-fluid">

                        <div class="floating-card floating-card-1">
                            <div class="bg-success bg-opacity-10 p-2 rounded">
                                <i class="bi bi-check-circle text-success"></i>
                            </div>
                            <div>
                                <small>Signature électronique</small>
                                <strong>24 contrats ce mois</strong>
                            </div>
                        </div>

                        <div class="floating-card floating-card-2">
                            <div class="bg-warning bg-opacity-10 p-2 rounded">
                                <i class="bi bi-clock text-warning"></i>
                            </div>
                            <div>
                                <small>Échéances</small>
                                <strong>5 contrats expirent</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Problèmes / Solutions -->
    <section class="section-padding" id="problems">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Le constat actuel</h2>
            <p class="section-subtitle" data-aos="fade-up">
                Pourquoi la gestion de contrats traditionnelle n'est plus adaptée
            </p>

            <div class="row g-4">
                <div class="col-md-6" data-aos="fade-right">
                    <div class="problem-card">
                        <div class="problem-icon">
                            <i class="bi bi-files"></i>
                        </div>
                        <h4>Documents éparpillés</h4>
                        <p class="text-muted">
                            Contrats dans des dossiers, signatures scannées sur le bureau,
                            emails perdus... La recherche d'un document devient un parcours du combattant.
                        </p>
                    </div>
                </div>

                <div class="col-md-6" data-aos="fade-left">
                    <div class="solution-card problem-card" style="border-left-color: #38a169;">
                        <div class="solution-icon">
                            <i class="bi bi-cloud-check"></i>
                        </div>
                        <h4 class="text-success">Centralisation unique</h4>
                        <p>
                            Tous vos contrats, entretiens et signatures au même endroit,
                            accessibles en 2 clics depuis n'importe quel appareil.
                        </p>
                    </div>
                </div>

                <div class="col-md-6" data-aos="fade-right" data-aos-delay="100">
                    <div class="problem-card">
                        <div class="problem-icon">
                            <i class="bi bi-calendar-x"></i>
                        </div>
                        <h4>Échéances oubliées</h4>
                        <p class="text-muted">
                            Des contrats qui expirent sans que vous le sachiez,
                            des renouvellements manqués, des opportunités perdues.
                        </p>
                    </div>
                </div>

                <div class="col-md-6" data-aos="fade-left" data-aos-delay="100">
                    <div class="solution-card problem-card" style="border-left-color: #38a169;">
                        <div class="solution-icon">
                            <i class="bi bi-bell"></i>
                        </div>
                        <h4 class="text-success">Alertes automatiques</h4>
                        <p>
                            Notification 30 jours avant échéance, rappels personnalisés,
                            plus jamais de contrat expiré sans réaction.
                        </p>
                    </div>
                </div>

                <div class="col-md-6" data-aos="fade-right" data-aos-delay="200">
                    <div class="problem-card">
                        <div class="problem-icon">
                            <i class="bi bi-pen"></i>
                        </div>
                        <h4>Signatures chronophages</h4>
                        <p class="text-muted">
                            Impression, signature manuelle, scan, envoi... Un processus
                            qui prend des heures et manque de professionalisme.
                        </p>
                    </div>
                </div>

                <div class="col-md-6" data-aos="fade-left" data-aos-delay="200">
                    <div class="solution-card problem-card" style="border-left-color: #38a169;">
                        <div class="solution-icon">
                            <i class="bi bi-phone"></i>
                        </div>
                        <h4 class="text-success">Signature électronique</h4>
                        <p>
                            Signature sur tablette, smartphone ou ordinateur en 30 secondes,
                            directement intégrée au contrat.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Notre différence -->
    <section class="section-padding bg-light" id="difference">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Ce qui nous rend unique</h2>
            <p class="section-subtitle" data-aos="fade-up">
                Découvrez pourquoi Gestion Contrat Pro est différent des solutions traditionnelles
            </p>

            <div class="row g-4">
                <div class="col-md-4" data-aos="flip-left">
                    <div class="diff-card">
                        <div class="diff-icon">
                            <i class="bi bi-diagram-3"></i>
                        </div>
                        <h3 class="diff-title">Approche intégrée</h3>
                        <p>Pas juste un gestionnaire de contrats, mais un écosystème complet :</p>
                        <ul class="diff-list">
                            <li><i class="bi bi-check-circle-fill"></i> Contacts → Entretiens → Contrats</li>
                            <li><i class="bi bi-check-circle-fill"></i> Traçabilité complète</li>
                            <li><i class="bi bi-check-circle-fill"></i> Historique des interactions</li>
                            <li><i class="bi bi-check-circle-fill"></i> Pipeline de conversion</li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-4" data-aos="flip-left" data-aos-delay="100">
                    <div class="diff-card light">
                        <div class="diff-icon">
                            <i class="bi bi-shield-check" style="color: #667eea;"></i>
                        </div>
                        <h3 class="diff-title">Signature légale</h3>
                        <p>Une signature qui a valeur juridique :</p>
                        <ul class="diff-list">
                            <li><i class="bi bi-check-circle-fill text-success"></i> Horodatage certifié</li>
                            <li><i class="bi bi-check-circle-fill text-success"></i> IP du signataire enregistrée</li>
                            <li><i class="bi bi-check-circle-fill text-success"></i> Preuve de consentement</li>
                            <li><i class="bi bi-check-circle-fill text-success"></i> Archivage sécurisé</li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-4" data-aos="flip-left" data-aos-delay="200">
                    <div class="diff-card">
                        <div class="diff-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <h3 class="diff-title">Vision 360°</h3>
                        <p>Des tableaux de bord en temps réel :</p>
                        <ul class="diff-list">
                            <li><i class="bi bi-check-circle-fill"></i> Chiffre d'affaires projeté</li>
                            <li><i class="bi bi-check-circle-fill"></i> Taux de conversion</li>
                            <li><i class="bi bi-check-circle-fill"></i> Alertes personnalisées</li>
                            <li><i class="bi bi-check-circle-fill"></i> Rapports exportables</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Comparaison -->
            <div class="row mt-5">
                <div class="col-12" data-aos="fade-up">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h4 class="text-center mb-4">Comparaison avec les solutions traditionnelles</h4>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fonctionnalité</th>
                                            <th>Solutions classiques</th>
                                            <th>Gestion Contrat Pro</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Centralisation</td>
                                            <td><i class="bi bi-x-circle text-danger"></i> Dossiers éparpillés</td>
                                            <td><i class="bi bi-check-circle text-success"></i> Base unique</td>
                                        </tr>
                                        <tr>
                                            <td>Signature</td>
                                            <td><i class="bi bi-x-circle text-danger"></i> Scan après impression</td>
                                            <td><i class="bi bi-check-circle text-success"></i> Électronique intégrée</td>
                                        </tr>
                                        <tr>
                                            <td>Alertes</td>
                                            <td><i class="bi bi-x-circle text-danger"></i> Manuel (calendrier)</td>
                                            <td><i class="bi bi-check-circle text-success"></i> Automatiques</td>
                                        </tr>
                                        <tr>
                                            <td>Traçabilité</td>
                                            <td><i class="bi bi-x-circle text-danger"></i> Aucune</td>
                                            <td><i class="bi bi-check-circle text-success"></i> Complète (IP, date, heure)</td>
                                        </tr>
                                        <tr>
                                            <td>Tableau de bord</td>
                                            <td><i class="bi bi-x-circle text-danger"></i> Inexistant</td>
                                            <td><i class="bi bi-check-circle text-success"></i> Temps réel</td>
                                        </tr>
                                        <tr>
                                            <td>Export PDF</td>
                                            <td><i class="bi bi-x-circle text-danger"></i> Manuelle</td>
                                            <td><i class="bi bi-check-circle text-success"></i> Automatique</td>
                                        </tr>
                                        <tr>
                                            <td>Suivi des échéances</td>
                                            <td><i class="bi bi-x-circle text-danger"></i> Risque d'oubli</td>
                                            <td><i class="bi bi-check-circle text-success"></i> Alertes visuelles</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Fonctionnalités clés -->
    <section class="section-padding" id="features">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Des fonctionnalités pensées pour vous</h2>
            <p class="section-subtitle" data-aos="fade-up">
                Tout ce dont vous avez besoin pour gérer votre cycle contractuel de A à Z
            </p>

            <div class="row g-4">
                <div class="col-md-4" data-aos="zoom-in">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="feature-title">Gestion des contacts</h3>
                        <p class="feature-description">
                            Centralisez tous vos clients, prospects et partenaires.
                            Historique complet, filtres avancés, import/export.
                        </p>
                    </div>
                </div>

                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="50">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-chat-text"></i>
                        </div>
                        <h3 class="feature-title">Entretiens & suivis</h3>
                        <p class="feature-description">
                            Planifiez et documentez vos entretiens. Besoins explicites,
                            hors périmètre, notes détaillées.
                        </p>
                    </div>
                </div>

                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-file-text"></i>
                        </div>
                        <h3 class="feature-title">Contrats professionnels</h3>
                        <p class="feature-description">
                            Générez des contrats avec clauses personnalisées,
                            montants, fréquences de paiement et conditions particulières.
                        </p>
                    </div>
                </div>

                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="150">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-pen"></i>
                        </div>
                        <h3 class="feature-title">Signature électronique</h3>
                        <p class="feature-description">
                            Signature directement sur l'écran (ordinateur, tablette, mobile).
                            Valeur juridique et horodatage certifié.
                        </p>
                    </div>
                </div>

                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-bell"></i>
                        </div>
                        <h3 class="feature-title">Alertes intelligentes</h3>
                        <p class="feature-description">
                            Notifications automatiques pour les échéances,
                            renouvellements, et relances personnalisées.
                        </p>
                    </div>
                </div>

                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="250">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h3 class="feature-title">Tableaux de bord</h3>
                        <p class="feature-description">
                            Visualisez vos indicateurs clés : CA, taux de conversion,
                            contrats actifs, échéances à venir.
                        </p>
                    </div>
                </div>

                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-file-pdf"></i>
                        </div>
                        <h3 class="feature-title">Export PDF</h3>
                        <p class="feature-description">
                            Générez automatiquement des documents professionnels
                            avec signature intégrée, prêts à partager.
                        </p>
                    </div>
                </div>

                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="350">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h3 class="feature-title">Sécurité renforcée</h3>
                        <p class="feature-description">
                            Authentification, traçabilité des actions,
                            enregistrement IP, conformité RGPD.
                        </p>
                    </div>
                </div>

                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-phone"></i>
                        </div>
                        <h3 class="feature-title">Mobile friendly</h3>
                        <p class="feature-description">
                            Accédez à vos contrats partout, signez sur tablette,
                            consultez depuis votre smartphone.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Témoignages -->
    <section class="section-padding bg-light" id="testimonials">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Ils nous font confiance</h2>
            <p class="section-subtitle" data-aos="fade-up">
                Découvrez ce que nos clients pensent de Gestion Contrat Pro
            </p>

            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">JD</div>
                        <p class="testimonial-text">
                            "Avant, je perdais des heures à chercher mes contrats et à gérer les signatures.
                            Maintenant, tout est centralisé, les alertes me préviennent des échéances.
                            Un gain de temps énorme !"
                        </p>
                        <div class="testimonial-author">Jean Dupont</div>
                        <div class="testimonial-role">Avocat, Cabinet Dupont & Associés</div>
                    </div>
                </div>

                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">SL</div>
                        <p class="testimonial-text">
                            "La signature électronique intégrée est un vrai plus. Mes clients signent
                            directement sur tablette, c'est professionnel et rapide. Le tableau de bord
                            me donne une vision claire de mon activité."
                        </p>
                        <div class="testimonial-author">Sophie Lambert</div>
                        <div class="testimonial-role">Agent immobilier</div>
                    </div>
                </div>

                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">MB</div>
                        <p class="testimonial-text">
                            "La traçabilité est parfaite. Je sais exactement quand et par qui chaque
                            contrat a été signé, avec l'IP enregistrée. C'est rassurant d'un point de
                            vue juridique."
                        </p>
                        <div class="testimonial-author">Marc Bernard</div>
                        <div class="testimonial-role">Expert-comptable</div>
                    </div>
                </div>
            </div>

            <!-- Chiffres clés -->
            <div class="row mt-5 text-center">
                <div class="col-md-3" data-aos="count-up">
                    <div class="display-3 fw-bold text-primary">500+</div>
                    <p class="text-muted">Clients actifs</p>
                </div>
                <div class="col-md-3" data-aos="count-up" data-aos-delay="100">
                    <div class="display-3 fw-bold text-primary">98%</div>
                    <p class="text-muted">Taux de satisfaction</p>
                </div>
                <div class="col-md-3" data-aos="count-up" data-aos-delay="200">
                    <div class="display-3 fw-bold text-primary">15min</div>
                    <p class="text-muted">Temps de prise en main</p>
                </div>
                <div class="col-md-3" data-aos="count-up" data-aos-delay="300">
                    <div class="display-3 fw-bold text-primary">24/7</div>
                    <p class="text-muted">Support disponible</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Tarifs -->
    <section class="section-padding" id="pricing">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Des tarifs adaptés à vos besoins</h2>
            <p class="section-subtitle" data-aos="fade-up">
                Choisissez la formule qui correspond à votre activité
            </p>

            <div class="row g-4 align-items-center">
                <div class="col-md-4" data-aos="fade-right">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <div class="pricing-name">Essentiel</div>
                            <div class="pricing-price">29€ <small>/mois</small></div>
                        </div>
                        <ul class="pricing-features">
                            <li><i class="bi bi-check-circle"></i> Jusqu'à 50 contacts</li>
                            <li><i class="bi bi-check-circle"></i> Gestion des entretiens</li>
                            <li><i class="bi bi-check-circle"></i> Création de contrats</li>
                            <li><i class="bi bi-check-circle"></i> Signature électronique</li>
                            <li class="disabled"><i class="bi bi-x-circle"></i> Alertes avancées</li>
                            <li class="disabled"><i class="bi bi-x-circle"></i> Tableaux de bord personnalisés</li>
                        </ul>
                        <button class="btn-pricing">Commencer</button>
                    </div>
                </div>

                <div class="col-md-4" data-aos="zoom-in">
                    <div class="pricing-card popular">
                        <div class="popular-badge">POPULAIRE</div>
                        <div class="pricing-header">
                            <div class="pricing-name">Professionnel</div>
                            <div class="pricing-price">59€ <small>/mois</small></div>
                        </div>
                        <ul class="pricing-features">
                            <li><i class="bi bi-check-circle"></i> Contacts illimités</li>
                            <li><i class="bi bi-check-circle"></i> Gestion avancée des entretiens</li>
                            <li><i class="bi bi-check-circle"></i> Contrats illimités</li>
                            <li><i class="bi bi-check-circle"></i> Signature électronique</li>
                            <li><i class="bi bi-check-circle"></i> Alertes et rappels</li>
                            <li><i class="bi bi-check-circle"></i> Tableaux de bord complets</li>
                            <li><i class="bi bi-check-circle"></i> Export PDF automatisé</li>
                            <li><i class="bi bi-check-circle"></i> Support prioritaire</li>
                        </ul>
                        <button class="btn-pricing popular">Commencer</button>
                    </div>
                </div>

                <div class="col-md-4" data-aos="fade-left">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <div class="pricing-name">Entreprise</div>
                            <div class="pricing-price">99€ <small>/mois</small></div>
                        </div>
                        <ul class="pricing-features">
                            <li><i class="bi bi-check-circle"></i> Tout le plan Professionnel</li>
                            <li><i class="bi bi-check-circle"></i> API dédiée</li>
                            <li><i class="bi bi-check-circle"></i> Multi-utilisateurs</li>
                            <li><i class="bi bi-check-circle"></i> Personnalisation avancée</li>
                            <li><i class="bi bi-check-circle"></i> Audit et conformité</li>
                            <li><i class="bi bi-check-circle"></i> Formation incluse</li>
                        </ul>
                        <button class="btn-pricing">Nous contacter</button>
                    </div>
                </div>
            </div>

            <!-- Garantie -->
            <div class="text-center mt-5" data-aos="fade-up">
                <div class="bg-light p-4 rounded-3">
                    <i class="bi bi-shield-check text-primary fs-1"></i>
                    <h5>Garantie satisfait ou remboursé pendant 30 jours</h5>
                    <p class="text-muted">
                        Testez Gestion Contrat Pro sans risque. Si vous n'êtes pas convaincu,
                        nous vous remboursons intégralement.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta-section" id="demo">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 text-center text-lg-start" data-aos="fade-right">
                    <h2 class="cta-title">Prêt à révolutionner votre gestion de contrats ?</h2>
                    <p class="cta-subtitle">
                        Rejoignez plus de 500 professionnels qui ont déjà simplifié leur cycle contractuel.
                        Essayez gratuitement pendant 14 jours, sans engagement.
                    </p>
                </div>
                <div class="col-lg-4 text-center text-lg-end" data-aos="fade-left">
                    <div class="d-flex gap-3 justify-content-center justify-content-lg-end">
                        <a href="register.php" class="btn-cta">
                            <i class="bi bi-rocket"></i> Essai gratuit
                        </a>
                        <a href="demo.php" class="btn-cta-outline">
                            <i class="bi bi-calendar"></i> Planifier une démo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="footer-logo">
                        <i class="bi bi-file-text"></i> GESTION CONTRAT PRO
                    </div>
                    <p class="footer-text">
                        La solution complète pour gérer vos contrats, entretiens et signatures
                        électroniques en toute simplicité.
                    </p>
                    <div class="social-links">
                        <a href="#"><i class="bi bi-linkedin"></i></a>
                        <a href="#"><i class="bi bi-twitter"></i></a>
                        <a href="#"><i class="bi bi-facebook"></i></a>
                        <a href="#"><i class="bi bi-github"></i></a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="footer-title">Produit</h5>
                    <ul class="footer-links">
                        <li><a href="#features">Fonctionnalités</a></li>
                        <li><a href="#pricing">Tarifs</a></li>
                        <li><a href="#demo">Démo</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="footer-title">Ressources</h5>
                    <ul class="footer-links">
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Documentation</a></li>
                        <li><a href="#">Support</a></li>
                        <li><a href="#">API</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="footer-title">Légal</h5>
                    <ul class="footer-links">
                        <li><a href="#">Mentions légales</a></li>
                        <li><a href="#">Confidentialité</a></li>
                        <li><a href="#">CGU</a></li>
                        <li><a href="#">RGPD</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="footer-title">Contact</h5>
                    <ul class="footer-links">
                        <li><i class="bi bi-envelope"></i> contact@gestioncontrat.pro</li>
                        <li><i class="bi bi-telephone"></i> +33 1 23 45 67 89</li>
                        <li><i class="bi bi-geo-alt"></i> Paris, France</li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2024 Gestion Contrat Pro. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // Initialisation des animations AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });

        // Smooth scroll pour les ancres
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animation des chiffres au scroll
        function animateNumbers() {
            const numberElements = document.querySelectorAll('[data-aos="count-up"] .display-3');
            numberElements.forEach(el => {
                const value = el.innerText.replace(/[^0-9]/g, '');
                if (value && !el.classList.contains('animated')) {
                    let start = 0;
                    let end = parseInt(value);
                    let duration = 2000;
                    let step = end / (duration / 16);

                    let current = start;
                    let timer = setInterval(() => {
                        current += step;
                        if (current >= end) {
                            el.innerText = el.innerText.replace(/[0-9]+/g, end);
                            clearInterval(timer);
                        } else {
                            el.innerText = el.innerText.replace(/[0-9]+/g, Math.floor(current));
                        }
                    }, 16);

                    el.classList.add('animated');
                }
            });
        }

        // Observer pour déclencher l'animation des nombres
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateNumbers();
                }
            });
        });

        document.querySelectorAll('[data-aos="count-up"]').forEach(el => observer.observe(el));

        // Navbar background change on scroll
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.05)';
            }
        });
    </script>
</body>

</html>