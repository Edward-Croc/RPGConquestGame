
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
    ('Ninja', 1,0,0)
    ,('Geisha', 1,0,0)
    ,('Acteur Nô', 1,0,0)
    ,('Moine', 1,1,-1)
    ,('Voleur', 1,1,-1)
    -- ('', 0,1,1), => Combattants
    ,('Ishin shishi', 0,1,1)
    ,('Hitokiri', 0,1,1)
    -- ('', 1,1,-1), => Glass cannons
    -- ('', 2,0,-1), => Maitres Enqueteurs
    -- ('', -1,2,1), => Maitres Combattants
;

/*
INSERT INTO powers ( name, enquete, attack, defence, other) VALUES
    ('Volontaire venu de loin', 1,0,0,'{"on_recrutment": {"action": {"type":"go_traitor", "controler_lastname": "da Firenze"} } }'),
    ('Prêtre', 1,1,1,'{"on_recrutment": {"action": {"type":"add_opposition", "controler_lastname": "Lorenzo"} } }')
;
*/
