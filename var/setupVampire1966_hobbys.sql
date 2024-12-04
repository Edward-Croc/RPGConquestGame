
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
    -- ('', 1,0,0), => Enqueteurs
    ('Acteur Amateur', 1,0,0),
    ('Musicien de rue', 1,0,0),
    ('Fan de romans policiers', 1,0,0),
    ('Photographe amateur', 1,0,0),
    ('Collectionneur', 1,0,0),
    ('Scout', 1,0,0),
    ('Globe trotter', 1,0,0),
    ('Ornithologue', 1,0,0),
    ('Peintre copiste', 1,0,0),
    ('Aristocrate', 1,0,0),
    ('Membre du club de crochet', 1,0,0),
    ('Propriétaire de Lévrier Italien', 1,0,0),
    ('Voisin vigilant', 1,0,0),
    -- ('', 0,1,1), => Combatants
    ('Rugbyman du dimanche', 0,1,1),
    ('Militaire réserviste', 0,1,1),
    ('Adepte d’arts martiaux', 0,1,1),
    ('Escrimeur', 0,1,1),
    ('Manifestant régulier', 0,1,1),
    ('Alcoolique', 0,1,1),
    ('Hooligan', 0,1,1),
    ('Pompier volontaire', 0,1,1),
    -- ('', -1,2,1), => Maitres Combatants
    ('Adepte de muscu', -1,2,1),
    ('Dresseur de Pitbulls', -1,2,1),
    -- ('', 1,1,-1), => Glass Canons
    ('Drogué à la LSD', 1,1,-1),
    ('Punk à chien', 1,1,-1),
    -- ('', 2, 0/-1), => Maitres Enqueteurs
    ('Astrologue Amateur', 2,0,-1),
    ('Rôliste', 2,-1,0),
    ('Oiseau de nuit', 2,-1,0)
;

INSERT INTO powers ( name, enquete, attack, defence, other) VALUES
    ('Chrétien pratiquant', 1,1,1,'{"on_recrutment": {"action": "add_opposition", "controler_lastname": "Lorenzo"}}')
;
