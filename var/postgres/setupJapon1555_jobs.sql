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
    -- ('', 0,1,1), => Combattants
    -- ('', 1,1,-1), => Glass cannons
    -- ('', 2,0,-1), => Maitres Enqueteurs
    -- ('', -1,2,1), => Maitres Combattants

INSERT INTO powers (name, description, enquete, attack, defence) VALUES
    -- ('', 1,0,0), => Enqueteurs
    ('Kinjirō (金次郎) – Intendant financier', ', chargé de la gestion des richesses et récoltes du domaine', 1, 0, 0),
    ('Kashi (歌師) – Poète officiel', ', ou maître du chant, souvent présent pour les divertissements de cour', 1, 0, 0),
    ('Shikibu (式部) – Maître de cérémonie', ', en charge du protocole et de l’organisation des audiences', 1, 0, 0),
    ('Bugyō (奉行) – Magistrat ou officier administratif', ', responsable de fonctions judiciaires ou logistiques', 1, 0, 0),
    ('Koto-hime (琴姫) – Musicienne', ', jouant du koto lors des banquets et cérémonies', 1, 0, 0),
    ('Kōshitsu (香師) – Spécialiste de l’art de l’encens', ', en charge des parfums et de l’ambiance', 1, 0, 0),
    ('Kasō (花匠) – Artiste florale', ', chargée des arrangements ikebana dans les salons du palais', 1, 0, 0),
    ('Shodō-ka (書道家) – Calligraphe raffinée', ', pratiquant pour l’art et les documents officiels', 1, 0, 0),
    ('Hōin (法印) – Prêtresse ou religieuse conseillère', ', parfois formée aux arts divinatoires', 1, 0, 0),
    ('Yūjo (遊女) – Courtisane cultivée', ', parfois invitée lors de réceptions de haut rang', 1, 0, 0),
    ('Jōhō-gashi (情報頭) – Collecteur d’informations', ', actif sur les routes ou dans les marchés', 1, 0, 0),
    ('Mimiiri (耳入) – Informateur discret', ', placé parmi les serviteurs ou les marchands', 1, 0, 0),
    ('Kannushi (神主) – Prêtre shintō', ', gardien des sanctuaires et officiant les rites sacrés', 1, 0, 0),
    ('Onmyōji (陰陽師) – Devin et exorciste', ', spécialiste des arts occultes et des influences célestes', 1, 0, 0),
    ('Hōshi (法師) – Moine bouddhiste itinérant', ', parfois proche conseiller du daimyō', 1, 0, 0),
    ('Reishi (霊師) – Médium ou exorciste', ', intervenant lors de troubles spirituels', 1, 0, 0),
    ('Miko (巫女) – Servante shintō', ', pratiquant la danse rituelle et les oracles', 1, 0, 0),
    ('Nōgakushi (能楽師) – Acteur de théâtre Nō', ', maître de la performance codifiée mêlant chant, danse et spiritualité', 1, 0, 0),
    ('Tsūshi (通使) – Diplomate ou émissaire', ', habile orateur chargé de négociations sensibles', 1, 0, 0),
    -- ('', 2,0,-1), => Maitres Enqueteurs
    ('Karō (家老) – Conseiller principal', ', souvent responsable de l’administration du domaine', 2,0,-1),
    ('Shōshō (少将) – Intendante ou gouvernante', ', supervisant les affaires internes de la demeure', 2,0,-1),
    ('Chajin (茶人) – Maître de thé', ', responsable des cérémonies du thé et de l’étiquette liée au chanoyu', 2,0,-1),
    ('Geisha (芸者) – Artiste raffinée', ', experte en musique, danse, conversation et arts traditionnels', 2,0,-1),
    ('Biwa Hōshi (琵琶法師) – Conteur aveugle itinérant', ', chantant les épopées avec son biwa', 2,0,-1),

    -- ('', 0,1,1), => Combattants
    ('Shihan (師範) – Maître instructeur', ', en arts martiaux ou lettrés, formant les jeunes samouraïs du domaine', 0, 1,1),
    ('Kōsaku (工作) – Saboteur', ', expert en pièges et manipulations de terrain', 0, 1,1),
    ('Kuro-hatamoto (黒旗本) – Garde d’élite', ', en mission secrète, loyal au daimyō', 0, 1,1),
    ('Monomi (物見) – Éclaireur', ', ou observateur posté en avant-garde', 0, 1,1),
    ('Ninja-kahō (忍者家法) – Membre d’une lignée de ninjas', ', liés par serment au daimyō', 0, 1,1),
    ('Sodegarami (袖搦) – Garde spécialisé', ', dans l’arrestation à l’aide d’armes non létales', 0, 1,1),
    ('Hitokiri (人斬り) – Assassin redouté au sabre', ', exécuteur discret souvent marqué par la vengeance', 0, 1,1),
    -- ('', -1,2,1), => Maitres Combattants
    ('Nyūdō (入道) – Samouraï retiré', ', dans la voie monastique, servant comme conseiller sage', -1, 2,1),
    ('Onna-bugeisha (女武芸者) – Femme samouraï', ', entraînée au combat, parfois en charge de la garde rapprochée', -1, 2,1),
    ('Monogashira (物頭) – Officier en chef', ', d’un détachement armé, chargé de la sécurité ou de missions spéciales', -1, 2,1),
    ('Sōhei (僧兵) – Moine-soldat', ', à la fois religieux et combattant pour le temple ou le seigneur', -1, 2,1),
    
    -- ('', 1,1,-1), => Glass cannons
    ('Naishi (内侍) – Dame de compagnie', ', au service de l’épouse ou des filles du daimyō', 1, 1,-1),
    ('Shinobi (忍び) – Espion et agent furtif', ', maître de l’infiltration, du sabotage ou de l’assassinat', 1, 1,-1),
    ('Tsukai (使い) – Messager rapide', ', souvent à cheval, transmettant des ordres urgents', 1, 1,-1),
    ('Kagemusha (影武者) – Sosie du seigneur', ', utilisé pour leurrer l’ennemi ou éviter les attentats', 1, 1,-1),
    ('Yamabushi (山伏) – Moine-guerrier itinérant', ', parfois employé comme éclaireur ou conseiller spirituel', 1, 1,-1),
    ('Nusutto (盗人) – Voleur agile et rusé', ', opérant dans l’ombre, parfois espion déguisé ou informateur', 1, 1,-1),
    ('Sarugakushi (猿楽師) – Artiste de rue ou comédien', ', mêlant satire, acrobaties et théâtre populaire', 1, 1,-1),
    ('Kusushi (薬師) – Médecin itinérant', ', spécialiste des remèdes traditionnels à base de plantes', 1, 1,-1),
    ('Ishitsukai (医使) – Médecin de cour', ', parfois moine ou alchimiste, pratiquant acupuncture et médecine spirituelle', 1, 1,-1)
;

INSERT INTO powers ( name, enquete, attack, defence, other) VALUES
    ('Prêtre chrétien', 1,1,1,'{"on_recrutment": {"action": {"type":"go_traitor", "controller_lastname": "Miyoshi (三好)"} } }'),
    ('Marin Portugais', 1,1,1,'{"on_recrutment": {"action": {"type":"go_traitor", "controller_lastname": "Miyoshi (三好)"} } }')
;

