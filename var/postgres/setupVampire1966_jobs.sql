
INSERT INTO powers ( name, enquete, attack, defence) VALUES
    -- Suggested Jobs :
    -- Possible Values Based on +1 :
    -- ('', 1,0,0), ('', 0,1,1), ('', 0,0,1),
    -- ('', -1,1,1), ('', 1,-1,1), ('', 1,1,-1),
    -- ('', -1,2,0), ('', -1,0,2), ('', 2,-1,0), ('', 0,-1,2), ('', 2,0,-1), ('', 0,2,-1),
    -- Possible Values Based on +1 : With imbalance on defence
    -- ('', 1,0,0), ('', 0,1,1),
    -- ('', 1,-1,1), ('', 1,1,-1),
    -- ('', -1,0,2) ('', -1,2,1), ('', 2,-1,0),('', 2,0,-1), ('', 0,2,-1),

    -- ('', 1,0,0), => Enqueteurs
    ('Plombier.ère', 1,0,0),
    ('Électricien.ne', 1,0,0),
    ('Étudiant.e en arts et lettres', 1,0,0),
    ('Écrivain.e raté.e', 1,0,0),
    ('Marchand.e ambulant.e', 1,0,0),
    ('Retraité.e curieux.se', 1,0,0),
    ('Professeur.e de langues', 1,0,0),
    ('Professeur.e de philo', 1,0,0),
    ('Boutiquier.ère', 1,0,0),
    ('Bibliothécaire', 1,0,0),
    ('Secrétaire', 1,0,0),
    ('Concierge', 1,0,0),
    ('Postier.ère à vélo', 1,0,0),
    ('Contrôleur.se de train', 1,0,0),
    ('Pianiste de bar', 1,0,0),
    ('Comptable méticuleux.se', 1,0,0),
    ('Serveur.se de bistrot', 1,0,0),
    ('Coiffeur.se de quartier', 1,0,0),
    ('Journaliste radio', 1,0,0),
    ('Photographe de presse', 1,0,0),
    ('Caissier.ère insomniaque', 1,0,0),
    ('Gardien.ne de musée', 1,0,0),
    ('Pharmacien.ne de quartier', 1,0,0),
    ('Assistant.e d’imprimerie', 1,0,0),
    ('Employé.e du cadastre', 1,0,0),
    ('Guichetier.ère de gare', 1,0,0),

    -- ('', 0,1,1), => Combattants
    ('Agent.e de sécurité', 0,1,1),
    ('Pompier.ère', 0,1,1),
    ('Ouvrier.ère du bâtiment', 0,1,1),
    ('Ouvrier.ère en usine', 0,1,1),
    ('Chercheur.euse en chimie', 0,1,1),
    ('Racaille', 0,1,1),
    ('Professeur.e de sport', 0,1,1),
    ('Camionneur.se', 0,1,1),
    ('Manutentionnaire', 0,1,1),
    ('Garagiste indépendant.e', 0,1,1),
    ('Facteur.trice syndiqué.e', 0,1,1),
    ('Livreur.se à vélo', 0,1,1),
    ('Agent.e de voirie', 0,1,1),
    ('Ambulancier.ère', 0,1,1),
    ('Déménageur.se', 0,1,1),
    ('Mécanicien.ne', 0,1,1),
    ('Gardien.ne de zoo', 0,1,1),
    ('Mafieux.se', 0,1,1),
    ('Pêcheur.se sicilien.ne', 0,1,1),
    ('Ouvrier.ère métallurgiste', 0,1,1),
    ('Maçon.ne calabrais.e', 0,1,1),
    ('Journalier.ère contestataire', 0,1,1),
    ('Ouvrier.ère Fiat', 0,1,1),
    ('Vendeur.se au marché noir', 0,1,1),

    -- ('', 1,1,-1), => Glass cannons
    ('Policier.ère', 1,1,-1),
    ('Voleur.se', 1,1,-1),
    ('Éboueur.se', 1,1,-1),
    ('Parent solo de famille nombreuse', 1,1,-1),
    ('Médecin urgentiste', 1,1,-1),
    ('Chef.fe de chantier', 1,1,-1),
    ('Militant.e féministe', 1,1,-1),

    -- ('', 2,0,-1), => Maitres Enqueteurs
    ('Conducteur.rice de taxi', 2,0,-1),
    ('SDF', 2,0,-1),
    ('Détective privé.e', 2,0,-1),
    ('Inspecteur.rice des impôts', 2,0,-1),

    -- ('', -1,2,1), => Maitres Combattants
    ('Militaire', -1,2,1),
    ('CRS', -1,2,1),
    ('Gardien.ne de prison', -1,2,1)
;

INSERT INTO powers ( name, enquete, attack, defence, other) VALUES
    ('Angeli Del Fango ', 1,0,1,'{"on_recrutment": {"action":  {"type": "go_traitor", "controller_lastname": "da Firenze"} } }')
    , ('Angels of the Mud ', 1,0,1,'{"on_recrutment": {"action":  {"type": "go_traitor", "controller_lastname": "da Firenze"} } }')
    , ('Mud Angels', 1,0,1,'{"on_recrutment": {"action":  {"type": "go_traitor", "controller_lastname": "Franco"} } }')
    , ('Ange de la boue', 1,0,1,'{"on_recrutment": {"action": {"type": "go_traitor", "controller_lastname": "Bonapart"} } }')
    , ('Prêtre', 1,1,1,'{"on_recrutment": {"action": {"type": "go_traitor", "controller_lastname": "Lorenzo"} } }')
;
