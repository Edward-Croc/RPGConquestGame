
INSERT INTO {prefix}powers ( name, enquete, attack, defence) VALUES
    -- Suggested Hobbies
    -- Possible Values Based on +1 :
    -- ('', 1,0,0), ('', 0,1,0), ('', 0,0,1),
    -- ('', -1,1,1), ('', 1,-1,1), ('', 1,1,-1),
    -- ('', -1,2,0), ('', -1,0,2), ('', 2,-1,0), ('', 0,-1,2), ('', 2,0,-1), ('', 0,2,-1),
    -- Possible Values Based on +1 : With imbalance on defence
    -- ('', 1,0,0), ('', 0,1,1),
    -- ('', 1,-1,1), ('', 1,1,-1),
    -- ('', -1,0,2) ('', -1,2,1), ('', 2,-1,0),('', 2,0,-1), ('', 0,2,-1),

    -- ('', 1,0,0), => Enquêteur.rice.s
    ('Acteur.rice amateur.rice', 1,0,0),
    ('Musicien.ne de rue', 1,0,0),
    ('Fan de romans policiers', 1,0,0),
    ('Photographe amateur.rice', 1,0,0),
    ('Collectionneur.se obsessionnel.le', 1,0,0),
    ('Scout', 1,0,0),
    ('Globe-trotteur.se', 1,0,0),
    ('Ornithologue', 1,0,0),
    ('Peintre copiste', 1,0,0),
    ('Aristocrate', 1,0,0),
    ('Membre du club de crochet', 1,0,0),
    ('Propriétaire de lévrier italien', 1,0,0),
    ('Voisin.e vigilant.e', 1,0,0),
    ('Cinéphile néoréaliste', 1,0,0),
    ('Bricoleur.se autodidacte', 1,0,0),
    ('Poète urbain.e', 1,0,0),
    ('Joueur.se d’échecs', 1,0,0),
    ('Radio-amateur.rice', 1,0,0),
    ('Danseur.se de bal populaire', 1,0,0),
    ('Écrivain.e de science-fiction', 1,0,0),
    ('Joueur.se de scopa (cartes)', 1,0,0),
    ('Lecteur.rice de fumetti (BD)', 1,0,0),
    ('Intellectuel.le marxiste', 1,0,0),

    -- ('', 0,1,1), => Combattant.e.s
    ('Rugbyman.woman du dimanche', 0,1,1),
    ('Militaire réserviste', 0,1,1),
    ('Adepte d’arts martiaux', 0,1,1),
    ('Escrimeur.se', 0,1,1),
    ('Manifestant.e régulier.ère', 0,1,1),
    ('Alcoolique', 0,1,1),
    ('Pompier.ère volontaire', 0,1,1),
    ('Chasseur.se du dimanche', 0,1,1),
    ('Boxeur.se en club', 0,1,1),
    ('Footballeur.se de quartier', 0,1,1),
    ('Marcheur.se de randonnée', 0,1,1),
    ('Ancien.ne Résistant.e', 0,1,1),
    ('Tireur.se de foire', 0,1,1),
    ('Collectionneur.se de couteaux', 0,1,1),
    ('Artiste en graffitis politiques', 0,1,1),
    ('Petite frappe de la mafia', 0,1,1),

    -- ('', -1,2,1), => Maître.sse.s Combattant.e.s
    ('Adepte de muscu', -1,2,1),
    ('Dresseur.se de pitbulls', -1,2,1),
    ('Chauffard.e en Vespa', -1,2,1),
    ('Hooligan "tifoso.a"', -1,2,1),

    -- ('', 1,1,-1), => Glass canon
    ('Personne droguée au LSD', 1,1,-1),
    ('Punk à chien', 1,1,-1),
    ('Militant.e écologique', 1,1,-1),
    ('Pêcheur.se solitaire', 1,1,-1),
    ('Membre du Parti communiste', 1,1,-1),
    ('Anarchiste romantique', 1,1,-1),

    -- ('', 2, 0/-1), => Maître.sse.s Enquêteur.rice.s
    ('Astronome amateur.rice', 2,0,-1),
    ('Rôliste', 2,-1,0),
    ('Oiseau de nuit', 2,-1,0)
;


INSERT INTO {prefix}powers ( name, enquete, attack, defence, other) VALUES
    ('Chrétien.ne pratiquant.e', 1,1,1,'{"on_recrutment": {
        "action": {"type":"add_opposition", "controller_lastname": "Lorenzo"},
        "action": {"type": "go_traitor", "controller_lastname": "Lorenzo"}
        } }'
    )
;

