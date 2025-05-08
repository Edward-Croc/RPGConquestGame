
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
    ('Plombier', 1,0,0),
    ('Electricien', 1,0,0),
    ('Étudiant (Arts, Lettres)', 1,0,0),
    ('Marchand ambulant', 1,0,0),
    ('Retraité curieux', 1,0,0),
    ('Professeur (Langues)', 1,0,0),
    ('Boutiquier', 1,0,0),
    ('Bibliothécaire', 1,0,0),
    ('Secrétaire', 1,0,0),
    -- ('', 0,1,1), => Combattants
    ('Agent de sécurité', 0,1,1),
    ('Pompier', 0,1,1),
    ('Ouvrier du bâtiment', 0,1,1),
    ('Étudiant (Sciences, Chimie)', 0,1,1),
    ('Racaille', 0,1,1),
    ('Professeur (Sport)', 0,1,1),
    ('Camionneur', 0,1,1),
    ('Manutentionnaire', 0,1,1),
    -- ('', 1,1,-1), => Glass cannons
    ('Policier', 1,1,-1),
    ('Voleur', 1,1,-1),
    ('Éboueur', 1,1,-1),
    ('Parent solo de famille nombreuse', 1,1,-1),
    -- ('', 2,0,-1), => Maitres Enqueteurs
    ('Conducteur de taxi', 2,0,-1),
    ('SDF', 2,0,-1),
    ('Détective privé', 2,0,-1),
    -- ('', -1,2,1), => Maitres Combattants
    ('Militaire', -1,2,1),
    ('CRS', -1,2,1),
    ('Gardien de zoo', -1,2,1)
    --Violent 
    -- Garde du Corps, Garde 
    -- Enqueteurs
    -- Professeur (Physique), Directeur, Gardien, Agent de tourisme, Trader, Voiturier, 
;

INSERT INTO powers ( name, enquete, attack, defence, other) VALUES
    ('Angeli Del Fango ', 1,0,1,'{"on_recrutment": {"action":  {"type": "go_traitor", "controler_lastname": "da Firenze"} } }'),
    ('Angels of the Mud ', 1,0,1,'{"on_recrutment": {"action":  {"type": "go_traitor", "controler_lastname": "da Firenze"} } }'),
    ('Mud Angels', 1,0,1,'{"on_recrutment": {"action":  {"type": "go_traitor", "controler_lastname": "Franco"} } }'),
    ('Ange de la boue', 1,0,1,'{"on_recrutment": {"action": {"type": "go_traitor", "controler_lastname": "Bonapart"} } }'),
    ('Prêtre', 1,1,1,'{"on_recrutment": {"controler_lastname": "Lorenzo"} }')
;
