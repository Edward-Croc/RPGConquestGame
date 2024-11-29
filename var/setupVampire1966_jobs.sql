
INSERT INTO powers ( name, enquete, action, defence) VALUES
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
    -- ('', 0,1,1), => Combatants
    ('Agent de sécurité', 0,1,1),
    ('Pompier', 0,1,1),
    ('Ouvrier du bâtiment', 0,1,1),
    ('Étudiant (Sciences, Chimie)', 0,1,1),
    ('Racaille', 0,1,1),
    -- ('', 1,1,-1), => Glass canons
    ('Policier', 1,1,-1),
    ('Voleur', 1,1,-1),
    ('Éboueur', 1,1,-1),
    -- ('', 2,0,-1), => Maitres Enqueteurs
    ('Conducteur de taxi', 2,0,-1),
    ('SDF', 2,0,-1),
    -- ('', -1,2,1), => Maitres Combatants
    ('Militaire', -1,2,1),
    ('CRS', -1,2,1),
    ('Gardien de zoo', -1,2,1)
    --Violent 
    -- Garde du Corps, Garde, Manutentionnaire,  
    -- Professeur (EPS), 
    -- Enqueteurs
    -- Professeur (Langues, Physique), Directeur, Bibliothécaire, Mère de Famille, Gardien, Boutiquier, Agent de tourisme, Musicien de rue ,  Trader, Secrétaire,Voiturier, 
    -- Détective
;

INSERT INTO powers ( name, enquete, action, defence, other) VALUES
    ('Volontaire venu de loin', 1,0,0,'{"on_recrutment": {"action": "go_traitor", "controler_lastname": "da Firenze"}}'),
    ('Pretre', 1,1,1,'{"on_recrutment": {"action": "add_opposition", "controler_lastname": "Lorenzo"}}')
;
