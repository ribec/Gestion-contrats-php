CREATE DATABASE IF NOT EXISTS `gestion_contrat` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `gestion_contrat`;

CREATE TABLE IF NOT EXISTS `contact` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type_contact` ENUM('particulier', 'professionnel') DEFAULT 'particulier',
    `nom_complet` VARCHAR(150) NOT NULL,
    `nom_entreprise` VARCHAR(100) DEFAULT NULL,
    `numero_ifu` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(150),
    `telephone` VARCHAR(25),
    `adresse` TEXT,
    `ville` VARCHAR(100),                
    `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `entretien` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `contact_id` INT NOT NULL,
    `date_entretien` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `objet` VARCHAR(255) NOT NULL,
    `lieu` VARCHAR(255),            
    `notes_discussion` TEXT,    
    `besoin_explicite` TEXT,      
    `hors_perimetre` TEXT,          
    `delai_souhaite` VARCHAR(100),  
    `statut` ENUM('en_attente', 'conclu', 'annule') DEFAULT 'en_attente',
    FOREIGN KEY (`contact_id`) REFERENCES `contact`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `contrat` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `entretien_id` INT NOT NULL,
    `reference_contrat` VARCHAR(50) UNIQUE, 
    `titre_accord` VARCHAR(255) NOT NULL,
    `description_service` TEXT,             
    `clause_particuliere` TEXT, 
    `montant_total` DECIMAL(10,2) DEFAULT 0.00,
    `devise` VARCHAR(3) DEFAULT 'EUR',      
    `frequence_paiement` VARCHAR(50),       
    `date_debut_prevue` DATE,               
    `date_fin_prevue` DATE,                 
    `modalite_rupture` TEXT,               
    `date_signature` DATE,
    `etat` ENUM('brouillon', 'actif', 'termine', 'suspendu') DEFAULT 'brouillon',
    `url_document_signe` VARCHAR(255),
    `signature_image` LONGTEXT DEFAULT NULL,     
    `ip_signataire` VARCHAR(45) DEFAULT NULL,  
    `horodatage_signature` DATETIME DEFAULT NULL, 
    
    FOREIGN KEY (`entretien_id`) REFERENCES `entretien`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

