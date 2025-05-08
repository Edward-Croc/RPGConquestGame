
INSERT INTO powers ( name, enquete, attack, defence) VALUES
    -- Suggested Hobbies
    -- Possible Values Based on +1 :
    -- ('', 1,0,0), ('', 0,1,0), ('', 0,0,1),
    -- ('', -1,1,1), ('', 1,-1,1), ('', 1,1,-1),
    -- ('', -1,2,0), ('', -1,0,2), ('', 2,-1,0), ('', 0,-1,2), ('', 2,0,-1), ('', 0,2,-1),
    -- Possible Values Based on +1 : With imbalance on defence
    -- ('', 1,0,0), ('', 0,1,1),
    -- ('', 1,-1,1), ('', 1,1,-1),
    -- ('', -1,0,2) ('', -1,2,1), ('', 2,-1,0),('', 2,0,-1), ('', 0,2,-1),

    --kodachi
    --wakizashi
    
    -- ('', 1,0,0), => Enqueteurs
    ('Acteur Amateur', 1,0,0)
    ,('Musicien de rue', 1,0,0)
    -- ('', 0,1,1), => Combatants
    ,('Rugbyman du dimanche', 0,1,1)
    ,('Militaire réserviste', 0,1,1)
    -- ('', -1,2,1), => Maitres Combatants
    ,('Adepte de muscu', -1,2,1)
    ,('Dresseur de Pitbulls', -1,2,1)
    -- ('', 1,1,-1), => Glass Canons
    ,('Drogué à la LSD', 1,1,-1)
    ,('Punk à chien', 1,1,-1)
    -- ('', 2, 0/-1), => Maitres Enqueteurs
    ,('Astrologue Amateur', 2,0,-1)
;

/*
INSERT INTO powers ( name, enquete, attack, defence, other) VALUES
    ('Chrétien pratiquant', 1,1,1,'{"on_recrutment": {"action": {"type":"add_opposition", "controler_lastname": "Lorenzo"} } }')
;
*/