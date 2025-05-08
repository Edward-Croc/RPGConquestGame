
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
    ('Plombier', 1,0,0)
    ,('Electricien', 1,0,0)
    -- ('', 0,1,1), => Combattants
    ,('Agent de sécurité', 0,1,1)
    ,('Pompier', 0,1,1)
    -- ('', 1,1,-1), => Glass cannons
    ,('Policier', 1,1,-1)
    ,('Voleur', 1,1,-1)
    -- ('', 2,0,-1), => Maitres Enqueteurs
    ,('Conducteur de taxi', 2,0,-1)
    ,('SDF', 2,0,-1)
    -- ('', -1,2,1), => Maitres Combattants
    ,('Militaire', -1,2,1)
    ,('CRS', -1,2,1)
    --Violent 
    -- Garde du Corps, Garde 
    -- Enqueteurs
    -- Professeur (Physique), Directeur, Gardien, Agent de tourisme, Trader, Voiturier, 
;

/*
INSERT INTO powers ( name, enquete, attack, defence, other) VALUES
    ('Volontaire venu de loin', 1,0,0,'{"on_recrutment": {"action": {"type":"go_traitor", "controler_lastname": "da Firenze"} } }'),
    ('Prêtre', 1,1,1,'{"on_recrutment": {"action": {"type":"add_opposition", "controler_lastname": "Lorenzo"} } }')
;
*/
