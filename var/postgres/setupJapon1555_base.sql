
UPDATE config SET value = '1,2,3,4,5,6,7' WHERE name = 'recrutement_origin_list';
UPDATE config SET value =  '1,2,3,4,5,6' WHERE name = 'local_origin_list';
UPDATE config SET value =  '2' WHERE name = 'recrutement_disciplines';

INSERT INTO config (name, value, description)
VALUES
    -- MAP INFO
    ('map_file', 'shikoku.png', 'Map file to use'),
    ('map_alt', 'Carte de Shikoku', 'Map alt'),
    ('textForZoneType', 'territoire', 'Text for the type of zone'),
    ('timeValue', 'Trimestre', 'Text for time span'),
    ('timeDenominatorThis', 'ce', 'Denominator ’this’ for time text')
;

INSERT INTO players (username, passwd, is_privileged) VALUES
    ('player0', 'zero', False),
    ('player1', 'one', False),
    ('player2', 'two', False),
    ('player3', 'three', False),
    ('player4', 'four', False),
    ('player5', 'five', False),
    ('player6', 'six', False),
    ('player7', 'seven', False)
;

INSERT INTO factions (name) VALUES
    ('Samouraï Chōsokabe')
    ,('Samouraï Miyoshi')
    ,('Samouraï Hosokawa')
    ,('Samouraï Ashikaga')
    ,('Moines Bouddhistes')
    ,('Ikkō-ikki') --https://fr.wikipedia.org/wiki/Ikk%C5%8D-ikki
    ,('Kaizokushū') -- (海賊衆)
    ,('Chrétiens') -- https://histoiredujapon.com/2021/04/05/etrangers-japon-ancien/#index_id1
    ,('Yōkai')
;


-- IA with start workers limits
INSERT INTO controllers (
    firstname, lastname, ia_type,
    start_workers, recruited_workers, turn_recruited_workers, turn_firstcome_workers,
    faction_id, fake_faction_id
) VALUES
    ('Yōkai (妖怪)', 'Shikoku (四国)', 'violent', -- https://fr.wikipedia.org/wiki/Y%C5%8Dkai#:~:text=Le%20terme%20y%C5%8Dkai%20(%E5%A6%96%E6%80%AA%2C%20%C2%AB,la%20culture%20orale%20au%20Japon.
        2, 1, 1, 0,
        (SELECT ID FROM factions WHERE name = 'Yōkai'),
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes')
    ),
    ('Yoshiteru (義輝)', 'Ashikaga (足利)', 'passif',
        2, 1, 1, 0,
        (SELECT ID FROM factions WHERE name = 'Samouraï Ashikaga'),
        (SELECT ID FROM factions WHERE name = 'Samouraï Ashikaga')
    )
;
INSERT INTO controllers (
    firstname, lastname, ia_type, url, story,
    start_workers, recruited_workers, turn_recruited_workers, turn_firstcome_workers,
    faction_id, fake_faction_id
) VALUES
    ('Kūkai (空海)', 'Kōbō-Daishi (弘法大師)', 'passif', -- https://en.wikipedia.org/wiki/K%C5%ABkai
        'https://docs.google.com/document/d/1bP2AGEA7grFw4k4CatLrTmeZkDDlczTqUEGg151GpQ8',
        '',
        2, 1, 1, 0,
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes'),
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes')
    )
;

UPDATE controllers set url = 'https://docs.google.com/document/d/1bP2AGEA7grFw4k4CatLrTmeZkDDlczTqUEGg151GpQ8' WHERE lastname = 'Kōbō-Daishi (弘法大師)';

-- players with no start worker limits
INSERT INTO controllers (
    firstname, lastname,
    faction_id, fake_faction_id,
    url, 
    story
) VALUES
    (
        'La Régence', 'Chōsokabe (長宗我部)', --https://fr.wikipedia.org/wiki/Clan_Ch%C5%8Dsokabe
        (SELECT ID FROM factions WHERE name = 'Samouraï Chōsokabe' ),
        (SELECT ID FROM factions WHERE name = 'Samouraï Chōsokabe' ),
        'https://docs.google.com/document/d/1P2Mz4PAkw00DMXXG4hgyod3FJNJkdXHU2JHbvkn327I',
        'Le parfum du sang flotte encore sur les rizières.
          L’arrivée de l’été aurait dû annoncer la victoire, mais il n’apporte que les échos d’une défaite humiliante.
          Kunichika (国親) Chōsokabe(長宗我部) est présumé mort, tombé sur les terres de Honshu(本州) aux côtés de ses vassaux, dans une guerre qu’il aurait dû gagner.
          À présent, c’est vous qui devez gouverner. Le jeune héritier, Motochika (元親), n’a que treize ans. Trop jeune pour régner, trop précieux pour tomber.
          Les regards se tournent vers vous, régent.e du clan, gardien.ne d’un pouvoir vacillant.
          Vos ennemis vous observent. Vos alliés hésitent. Mais l’avenir n’est pas encore écrit.
          Dans deux ans, Motochika atteindra l’âge de la majorité, et si vous parvenez à le protéger jusque-là, l’accord scellé avec le clan Hosokawa(細川) pourrait garantir une ère de stabilité.
          Saurez-vous protéger l’héritier de votre clan ou vous couvrirez vous de honte?
        '
    ),
    (
        'Daïmyo Nagayoshi (長慶)', 'Miyoshi (三好)',  --https://fr.wikipedia.org/wiki/Clan_Miyoshi
        (SELECT ID FROM factions WHERE name = 'Chrétiens' ),
        (SELECT ID FROM factions WHERE name = 'Samouraï Miyoshi' ),
        'https://docs.google.com/document/d/1W95lJ9bq0-KWRTCijgQ0Ua4koFsjTdLp3nvPTrnvCOc',
        ' Depuis 5 ans vous êtes le Daimyō du clan Miyoshi(三好), comme votre père Motonaga avant vous et son père avant lui.
          Mais vous, vous avez secrètement abandonné le bouddhisme pour embrasser la foi chrétienne, inspiré par les missionnaires venus avec les vaisseaux noirs portugais.
          En échange de votre protection et de votre conversion, ils vous ont offert un cadeau inestimable : le secret des fusils à mèche occidentaux.
          En cette fin de printemps, le parfum du sang flotte encore sur les rizières. Le Japon est à feu et à sang.
          Deux daimyō sont morts à la guerre et les croyances vacillent.
          Peut-être est-ce là l’heure bénie pour purger Shikoku(四国) des anciennes superstitions... faire de l’île la première terre chrétienne du Japon et du clan Miyoshi un clan majeur.
        '
    ),
    ('Shinshō-in (信証院)', 'Rennyo (蓮如)', -- https://fr.wikipedia.org/wiki/Rennyo
        (SELECT ID FROM factions WHERE name = 'Ikkō-ikki' ),
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes' ),
        'https://docs.google.com/document/d/1xKYPslqDdxlps6A4ydFh_iUu6cvdP5VC9145goVmLrA',
        ' Vous êtes Rennyo (蓮如) le Shinshō-in, huitième abbé du mouvement Jōdo Shinshū(浄土真宗) — la véritable école de la Terre pure.
          Votre foi ne prêche pas seulement la voie du salut : elle appelle à la révolution. Les Ikkō-ikki, bras armé de cette croyance, rassemblent les paysans révoltés, les petits seigneurs opprimés, les moines guerriers et les prêtres shintō brisés par le joug des samouraïs et du Shogun.
          C’est vos manigances tordues qui ont mené à la mort des Daimyô de Shikoku(四国), il ne vous reste qu’à terminer le travail de conquête de l’île.
        '
    ),
    ('Daïmyo Tadaoki (忠興)', 'Hosokawa (細川)', -- https://fr.wikipedia.org/wiki/Clan_Hosokawa
        (SELECT ID FROM factions WHERE name = 'Samouraï Hosokawa' ),
        (SELECT ID FROM factions WHERE name = 'Samouraï Hosokawa' ),
        'https://docs.google.com/document/d/14R_8j-5zbjC8Wzm72SsHS9QC8KDQ8l3AbkW5ZNmECAg',
        ' Le parfum du sang flotte encore sur les rizières.
          L’arrivée de l’été aurait dû annoncer la victoire, mais il n’apporte que les échos d’une défaite humiliante.
          Votre père, Fujitaka(藤孝) Hosokawa, a disparu durant la désastreuse campagne de Kyoto. Tous le croient mort.
          Vous, non. Vous avez toujours senti son esprit battre, disparu, en fuite, ou peut-être captif… Mais vivant.
          En attendant, le pouvoir du clan est désormais entre vos mains. Vous êtes jeune, ambitieux, et les chaînes de l’obéissance vous pèsent.
          Votre sœur, Tama (玉), est promise au jeune Motochika(元親) Chōsokabe(長宗我部), héritier encore trop jeune pour gouverner.
          Une alliance utile, risquée si elle venait à être dévoilée trop tôt. Une chaîne de plus que votre père vous a laissé.
          Deux ans. Voilà ce qu’il vous reste pour jouer vos cartes. Servir, trahir ou renaître. Le choix vous appartient.
          '
    ),
    ('Murai', 'Wako (和光)', --
        (SELECT ID FROM factions WHERE name = 'Kaizokushū' ),
        (SELECT ID FROM factions WHERE name = 'Kaizokushū' ),
        'https://docs.google.com/document/d/1lgVjCyPTpzxA0nU649PyeDldVxCKtLSh9t7AJOmwREg',
        ' Vous êtes Murai (村井), capitaine des Wako (和光), et maître incontesté d’un archipel sans lois.
          Vous ne croyez ni aux daimyōs, ni aux dieux, ni aux rêves de paix. Ce que vous servez, c’est le vent, l’or, et l’opportunité.
	      Depuis la guerre d’Ōnin (応仁の乱, Ōnin no ran?) et l’affaiblissement du Shogunat, les vôtres pillent, commercent, et manipulent les seigneurs des côtes de la mer intérieure de Seto, de la baie de Tokushima et même jusqu’en Corée.
          Le chaos actuel est une bénédiction.
          À la faveur d’une embuscade habile, vos hommes ont capturé Kunichika Chōsokabe, le daimyō de Shikoku. Blessé, brisé, il vit toujours.
          Et dans votre forteresse cachée de Shödoshima, il vaut plus que n’importe quel trésor.
	      Vous pourriez le vendre à ses ennemis. Le rançonner à son clan. L’utiliser comme monnaie d’échange pour garantir votre place dans le futur de l’île. Ou simplement le laisser moisir jusqu’à ce qu’il ne reste rien de son nom.
          Une chose est sûre : Si l’île s’unifie, votre liberté prendra fin. Mais tant que la guerre fait rage, les Wako régneront sur les brumes.
        '
    )
;

INSERT INTO player_controller (controller_id, player_id)
    SELECT ID, (SELECT ID FROM players WHERE username = 'gm')
    FROM controllers;

INSERT INTO player_controller (player_id, controller_id) VALUES
    (
        (SELECT ID FROM players WHERE username = 'player0'),
        (SELECT ID FROM controllers WHERE lastname in ('Shikoku (四国)'))
    ),
    (
        (SELECT ID FROM players WHERE username = 'player1'),
        (SELECT ID FROM controllers WHERE lastname = 'Chōsokabe (長宗我部)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player2'),
        (SELECT ID FROM controllers WHERE lastname in ('Miyoshi (三好)'))
    ),
    (
        (SELECT ID FROM players WHERE username = 'player3'),
        (SELECT ID FROM controllers WHERE lastname = 'Rennyo (蓮如)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player4'),
        (SELECT ID FROM controllers WHERE lastname = 'Hosokawa (細川)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player5'),
        (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player6'),
        (SELECT ID FROM controllers WHERE lastname = 'Kōbō-Daishi (弘法大師)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player7'),
        (SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)')
    )
;

INSERT INTO zones (name, description) VALUES
      ('Cote Ouest d’Echime', 'La porte vers l’île de Kyūshū, cette bande littorale est animée par les flux incessants de navires marchands, pêcheurs et patrouilleurs. Les criques cachent parfois des comptoirs discrets ou des avant-postes de contrebandiers. Les brumes marines y sont fréquentes, rendant les approches aussi incertaines que les intentions de ses habitants.')
    , ('Montagnes d’Echime', 'Entourant le redouté mont Ishizuchi, plus haut sommet de Shikoku, ces montagnes sacrées sont le domaine des ascètes, des yamabushi et des esprits anciens. Les chemins escarpés sont peuplés de temples isolés, de cascades énigmatiques, et d’histoires transmises à demi-mot. Nul ne traverse ces hauteurs sans y laisser un peu de son âme.')
    , ('Cap sud de Kochi', 'Battue par les vents de l’océan Pacifique, cette pointe rocheuse est riche en minerai de fer, extrait dans la sueur et le sel. Le paysage austère dissuade les faibles, mais attire les clans ambitieux. Les tempêtes y sont violentes, et même les dragons du ciel semblent redouter ses falaises noires.')
    , ('Grande Baie de Kochi', 'Centre de pouvoir du clan Chōsokabe, cette baie est à la fois un havre de paix et un verrou stratégique. Bordée de rizières fertiles et de ports animés, elle est défendue par des flottes aguerries et des forteresses discrètes. On dit que ses eaux reflètent les ambitions de ceux qui la contrôlent.')
    , ('Vallées d’Iya et d’Oboké de Tokushima', 'Ces vallées profondes, creusées par les torrents et le temps, abritent des plantations de thé précieuses et des villages suspendus au flanc des falaises. Peu accessibles, elles sont le refuge de ceux qui fuient la guerre, la loi ou le destin. Le thé qui y pousse a le goût amer des secrets oubliés.')
    , ('Cote Est de Tokushima', 'Sur cette façade tournée vers le large, le clan Miyoshi établit son pouvoir entre les ports et les postes fortifiés. Bien que prospère, la région est sous tension : les vassaux y sont fiers, les ambitions grandes, et les flottes ennemies jamais loin. La mer y apporte autant de trésors que de périls.')
    , ('Prefecture de Kagawa', 'Plaine fertile dominée par les haras impériaux et les sanctuaires oubliés, Kagawa est renommée pour ses chevaux rapides et robustes. Les émissaires s’y rendent pour négocier montures de guerre, messagers ou montures sacrées. C’est aussi une terre de festivals éclatants et de compétitions féroces.')
    , ('Ile d’Awaji', 'Pont vivant entre Shikoku et Honshū, Awaji est stratégiquement vitale et toujours convoitée. Les vents y sont brutaux, les détroits traîtres, et les seigneurs prudents. Ses collines cachent des fortins, ses criques des repaires, et ses chemins sont surveillés par des yeux invisibles.')
    , ('Ile de Shödoshima', 'Ile montagneuse et sauvage, jadis sanctuaire, aujourd’hui repaire des pirates Wako. Ses ports semblent paisibles, mais ses criques abritent des embarcations rapides prêtes à fondre sur les convois marchands. Les autorités ferment souvent les yeux, car même le vice paie tribut.')
    , ('Cité Impériale de Kyoto', 'Capitale impériale, centre des arts, des lettres et des poisons subtils. Les palais y cachent les plus anciennes lignées, les ruelles les complots les plus jeunes. Kyōto ne brandit pas l’épée, mais ceux qui y règnent peuvent faire plier des provinces entières par un sourire ou un silence.')
;


-- https://fr.wikipedia.org/wiki/P%C3%A8lerinage_de_Shikoku
-- Insert the data
INSERT INTO locations (name, description, discovery_diff, zone_id) VALUES
    ('Port d’Uwajima', '', 0, (SELECT ID FROM zones WHERE name = 'Cote Ouest d’Echime'))
    , ('Plaine d’Uwajima', '', 0, (SELECT ID FROM zones WHERE name = 'Cote Ouest d’Echime'))
    , ('Port de Matsuyama', '', 0, (SELECT ID FROM zones WHERE name = 'Montagnes d’Echime'))
    , ('Mt Ishizuchi','', 6, (SELECT ID FROM zones WHERE name = 'Montagnes d’Echime'))
    , ('Mine de fer de Kubokawa', '', 8, (SELECT ID FROM zones WHERE name = 'Cap sud de Kochi'))
    , ('Port de Kochi', '', 0, (SELECT ID FROM zones WHERE name = 'Grande Baie de Kochi'))
    , ('Ikeda', '', 0, (SELECT ID FROM zones WHERE name = 'Vallées d’Iya et d’Oboké de Tokushima'))
    , ('Oboke', '', 8, (SELECT ID FROM zones WHERE name = 'Vallées d’Iya et d’Oboké de Tokushima'))
    , ('Port de Tokushima', '', 0, (SELECT ID FROM zones WHERE name = 'Cote Est de Tokushima'))
    , ('La cour impériale', '', 6, (SELECT ID FROM zones WHERE name = 'Cité Impériale de Kyoto'))
    , ('Les geoles impériales', 'Ici sont retenus ', 10, (SELECT ID FROM zones WHERE name = 'Cité Impériale de Kyoto'))
;
INSERT INTO locations (name, description, discovery_diff, zone_id, controller_id) VALUES
    ('Geoles Pirates', '', 8, (SELECT ID FROM zones WHERE name = 'Ile de Shödoshima'),  (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)')) --PARRESSE
    , ('Vieux temple', '', 8, (SELECT ID FROM zones WHERE name = 'Ile d’Awaji'),  (SELECT ID FROM controllers WHERE lastname = 'Shikoku (四国')) -- TEMPLE VENT
    , ('Vieux temple', '', 8, (SELECT ID FROM zones WHERE name = 'Ile de Shödoshima'),  (SELECT ID FROM controllers WHERE lastname = 'Shikoku (四国')) --PARRESSE
;

INSERT INTO artefacts (name, description, full_description, location_id) VALUES
    ('Hosokawa (細川) Fujitaka (藤孝) le daimyô prisonnier', 'Nous avons trouvée','Si vous décidé de rendre sa liberté ou de supprimé cet homme, aller voir un orga.', (SELECT ID FROM locations WHERE name = 'Les geoles impériales'))
    , ('Kunichika(国親) Chōsokabe(長宗我部) blessé, brisé, il vit toujours', 'Nous avons trouvée','Si vous décidé de rendre sa liberté ou de supprimé cet homme, aller voir un orga.', (SELECT ID FROM locations WHERE name = 'Geoles Pirates'))
    , ('Motochika (元親) daimyô en devenir', 'Nous avons trouvée','Si vous décidé de rendre sa liberté ou de supprimé cet homme, aller voir un orga.', (SELECT ID FROM locations WHERE name = 'Les geoles impériales'))
    , ('Tama (玉), fille de Fujitaka, petite soeur de Tadaoki, 17 ans', 'Nous avons trouvée','Si vous décidé de rendre sa liberté ou de supprimé cet homme, aller voir un orga.', NULL)
    , ('Fudžisan(富士山), 26 ans, petit soeur du daimyô.', 'Nous avons trouvée','Si vous décidé de rendre sa liberté ou de supprimé cet homme, aller voir un orga.', NULL)
;

-- Table of Fixed Power Types used by code
INSERT INTO power_types (id, name, description) VALUES
    (1, 'Hobby', 'Objet fétiche'),
    (2, 'Metier', 'Rôle'),
    (3, 'Discipline', 'Maitrise des Arts'),
    (4, 'Transformation', 'Equipements Rares');

-- Table of powers
-- other possible keys hidden, on_recrutment, on_transformation
INSERT INTO powers ( name, enquete, attack, defence, other) VALUES
    ('Cheval Kagawa', 0, 1,1, '{"hidden" : "0", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Prefecture de Kagawa", "worker_in_zone": "Prefecture de Kagawa" } }')
    , ('Armure en fer de Kochi', 0, 1,1, '{"hidden" : "0", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Cap sud de Kochi", "worker_in_zone": "Cap sud de Kochi"  } }')
    , ('Thé d’Oboké et d’Iya', 1, 0,0, '{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Vallées d’Iya et d’Oboké de Tokushima", "worker_in_zone": "Vallées d’Iya et d’Oboké de Tokushima" } }')
    , ('Encens Coréen', 1, 0,0, '{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Cote Ouest d’Echime", "worker_in_zone": "Cote Ouest d’Echime"} }')
;

INSERT INTO  link_power_type ( power_type_id, power_id ) VALUES
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Cheval Kagawa'))
    , ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Armure en fer de Kochi'))
    , ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Thé d’Oboké et d’Iya'))
    , ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Encens Coréen'))
;

UPDATE config SET value = '''Sōjutsu (槍術) – Art de la lance (Yari)'', ''Kyūjutsu (弓術) – Art du tir à l’arc'', ''Shodō (書道) – Calligraphie'', ''Kadō / Ikebana (華道 / 生け花) – Art floral'''
WHERE name = 'basePowerNames';

--https://fr.wikipedia.org/wiki/Ashigaru
INSERT INTO powers ( name, enquete, attack, defence, description) VALUES
    -- Suggested Disciplines
    -- Possible Values Based on +2 :
    -- ('', 1,1,0), ('', 0,1,1), ('', 1,0,1),
    -- ('', 2,0,0), ('', 0,2,0), ('', 0,0,2),
    -- ('', -1,2,1), ('', -1,1,2), ('', 2,-1,1), ('', 1,-1,2), ('', 1,2,-1),('', 2,1,-1),
    -- Possible Values Based on +2 : with imbalance on strong defence
    -- ('', 1,1,1),
    -- ('', 2,0,0), ('', 0,2,1), ('', 0,1,2),
    -- ('', -1,2,2), ('', 2,-1,1), ('', 2,2,-1),
    -- ('', 1,1,1),
    -- Basiques
    ('Sōjutsu (槍術) – Art de la lance (Yari)', 0, 1,1,
      ', l’utilisation du yari à pied ou à cheval' )
    ,('Kyūjutsu (弓術) – Art du tir à l’arc', 0, 2,0,
      ', ancien kyūdō)' )
    ,('Shodō (書道) – Calligraphie', 1, 1,0,
      ', le maniement du pinceau, reflet de l’esprit' )
    ,('Kadō / Ikebana (華道 / 生け花) – Art floral', 1, 0,1,
      ', pratiqué pour l’harmonie intérieure' )

    -- Samouraï Chōsokabe
    ,('Kenjutsu (剣術) – Art du sabre', 0, 2,1,
      ', la pratique du katana en combat' )
    ,('Heihō (兵法) – Stratégie militaire', 1, 1,1,
      ', l’étude de la tactique, souvent influencée par les textes chinois comme le Sun Tzu' )
    ,('Waka (和歌) – Poésie classique', 2, 0,0,
      ', plus ancienne que le haïku, utilisée dans les échanges lettrés et parfois politiques' )

    -- Miyoshi Samouraï
    ,('Hōjutsu (砲術) – Art des armes à feu (teppō)', -1, 3,2,
      ', développé après l’introduction des mousquets portugais vers 1543' )
    ,('Bajutsu (馬術) – Art de l’équitation militaire', 1, 1,1,
      ', inclut la cavalerie et le tir à l’arc monté' )
    ,('Gagaku (雅楽) – Musique de cour', 2, 0,0,
      ', peu courante chez les samouraïs de terrain, mais appréciée dans les cercles aristocratiques ou les familles cultivées' )

    -- Samouraï Hosokawa
    ,('Iaijutsu (居合術) – Art de dégainer et frapper en un mouvement', 0, 2,1,
      '' )
    ,('Bugaku (舞楽) – Danse de cour', 1, 1,1,
      ', parfois pratiquée dans le cadre de cérémonies religieuses ou impériales' )
    ,('Chadō (茶道) – Voie du thé', 2, -1,1,
      ', cérémonie du thé comme forme de discipline spirituelle et esthétique' )

    -- Ikkō-ikki
    ,('Jūjutsu (柔術) – Techniques de lutte à mains nues', 0, 1,2,
      ', techniques de projection, immobilisation, étranglement ou désarmement' )
    ,('Ninjutsu (忍術) – Techniques d’espionnage et de guérilla', 2, 1,-1,
      'moins honorable, mais parfois utilisé par certains samouraïs ou leurs agents' )
    ,('Reiki / Kujikiri (霊気 / 九字切り) – Pratiques ésotériques', 1, 0,2,
      '' )

    -- Moines Bouddhistes
    ,('Yawara (和) – Ancienne forme de techniques de soumission', 0, 2,1,
        ', liée au jūjutsu')
    ,('Naginatajutsu (薙刀術) – Art de la hallebarde', 0, 1,2,
      '' )
    ,('Haikai / Haiku (俳諧 / 俳句) – Poésie courte', 2, 0,0,
      ', forme brève, souvent liée à la nature et à la spiritualité zen' )

    -- Kaizokushū Pirates
    ,('Tantōjutsu (短刀術) – Combat au couteau', 0, 2,1,
      ', surtout utilisé en combat rapproché ou en cas de désarmement' )
    ,('Shigin (詩吟) – Chant poétique', 2, 0,0,
      ', récitation chantée de poèmes chinois ou japonais, souvent associé à une posture noble et une pratique méditative' )
    ,('Tessenjutsu (鉄扇術) – L’art du combat à l’éventail de fer', 0, 1,2,
      ', pratiqué par les samouraïs, notamment lorsqu’ils étaient désarmés, en visite à la cour ou dans des lieux où le port du sabre était interdit'
    )

    -- Yōkai 
    ,('Kōdō (香道) – Voie de l’encens', 2, 0,0,
      ', art de « sentir » et d’apprécier les parfums rares dans des rituels très codifiés' )
    ,('Kagenkō (影言講) – L’art de la parole de l’ombre', 1, 1,1,
      ', art oratoire utilisé par les yōkai pour semer la confusion ou manipuler les humains en jouant avec les doubles sens, les murmures et les voix venues des ténèbres' )
    ,('Kagekui-ryū (影喰流) – École du Mange-Ombre', 0, 2,2, 
     ', art martial occulte / discipline hybride entre ninjutsu et pratiques yōkai' )
;

INSERT INTO link_power_type (power_type_id, power_id) VALUES
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Sōjutsu (槍術) – Art de la lance (Yari)')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Kyūjutsu (弓術) – Art du tir à l’arc')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Shodō (書道) – Calligraphie')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Kadō / Ikebana (華道 / 生け花) – Art floral')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Kenjutsu (剣術) – Art du sabre')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Heihō (兵法) – Stratégie militaire')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Waka (和歌) – Poésie classique')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Hōjutsu (砲術) – Art des armes à feu (teppō)')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Bajutsu (馬術) – Art de l’équitation militaire')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Gagaku (雅楽) – Musique de cour')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Iaijutsu (居合術) – Art de dégainer et frapper en un mouvement')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Bugaku (舞楽) – Danse de cour')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Chadō (茶道) – Voie du thé')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Jūjutsu (柔術) – Techniques de lutte à mains nues')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Ninjutsu (忍術) – Techniques d’espionnage et de guérilla')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Reiki / Kujikiri (霊気 / 九字切り) – Pratiques ésotériques')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Yawara (和) – Ancienne forme de techniques de soumission')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Naginatajutsu (薙刀術) – Art de la hallebarde')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Haikai / Haiku (俳諧 / 俳句) – Poésie courte')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Tantōjutsu (短刀術) – Combat au couteau')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Shigin (詩吟) – Chant poétique')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Tessenjutsu (鉄扇術) – L’art du combat à l’éventail de fer')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Kōdō (香道) – Voie de l’encens')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Kagenkō (影言講) – L’art de la parole de l’ombre')),
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Kagekui-ryū (影喰流) – École du Mange-Ombre'));


-- Add base powers to the factions :
-- Samouraï Chōsokabe
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Samouraï Chōsokabe'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Kenjutsu (剣術) – Art du sabre'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraï Chōsokabe'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Heihō (兵法) – Stratégie militaire'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraï Chōsokabe'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Waka (和歌) – Poésie classique'
    ));

-- Samouraï Miyoshi /Chrétiens 
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Samouraï Miyoshi'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Hōjutsu (砲術) – Art des armes à feu (teppō)'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraï Miyoshi'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Bajutsu (馬術) – Art de l’équitation militaire'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraï Miyoshi'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Gagaku (雅楽) – Musique de cour'
    )),
    ((SELECT ID FROM factions WHERE name = 'Chrétiens'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Hōjutsu (砲術) – Art des armes à feu (teppō)'
    )),
    ((SELECT ID FROM factions WHERE name = 'Chrétiens'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Bajutsu (馬術) – Art de l’équitation militaire'
    )),
    ((SELECT ID FROM factions WHERE name = 'Chrétiens'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Gagaku (雅楽) – Musique de cour'
    ));

-- Samouraï Hosokawa
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Samouraï Hosokawa'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Iaijutsu (居合術) – Art de dégainer et frapper en un mouvement'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraï Hosokawa'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Bugaku (舞楽) – Danse de cour'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraï Hosokawa'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Chadō (茶道) – Voie du thé'
    ));

-- Samouraï Ashikaga
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Samouraï Ashikaga'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Iaijutsu (居合術) – Art de dégainer et frapper en un mouvement'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraï Ashikaga'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Bugaku (舞楽) – Danse de cour'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraï Ashikaga'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Chadō (茶道) – Voie du thé'
    ));

-- Ikkō-ikki
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Ikkō-ikki'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Jūjutsu (柔術) – Techniques de lutte à mains nues'
    )),
    ((SELECT ID FROM factions WHERE name = 'Ikkō-ikki'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Ninjutsu (忍術) – Techniques d’espionnage et de guérilla'
    )),
    ((SELECT ID FROM factions WHERE name = 'Ikkō-ikki'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Reiki / Kujikiri (霊気 / 九字切り) – Pratiques ésotériques'
    ));

-- Moines Bouddhistes
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Moines Bouddhistes'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Yawara (和) – Ancienne forme de techniques de soumission'
    )),
    ((SELECT ID FROM factions WHERE name = 'Moines Bouddhistes'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Naginatajutsu (薙刀術) – Art de la hallebarde'
    )),
    ((SELECT ID FROM factions WHERE name = 'Moines Bouddhistes'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Haikai / Haiku (俳諧 / 俳句) – Poésie courte'
    ));

-- Kaizokushū Pirates
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Kaizokushū'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Tantōjutsu (短刀術) – Combat au couteau'
    )),
    ((SELECT ID FROM factions WHERE name = 'Kaizokushū'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Shigin (詩吟) – Chant poétique'
    )),
    ((SELECT ID FROM factions WHERE name = 'Kaizokushū'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Tessenjutsu (鉄扇術) – L’art du combat à l’éventail de fer'
    ));

-- Yōkai
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Yōkai'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Kōdō (香道) – Voie de l’encens'
    )),
    ((SELECT ID FROM factions WHERE name = 'Yōkai'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Kagenkō (影言講) – L’art de la parole de l’ombre'
    )),
    ((SELECT ID FROM factions WHERE name = 'Yōkai'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Kagekui-ryū (影喰流) – École du Mange-Ombre'
    ));

