
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

-- players with start worker limits
INSERT INTO controllers (
    firstname, lastname,
    start_workers, recruited_workers, turn_recruited_workers,
    faction_id, fake_faction_id,
    story
) VALUES
    (
        'Régence Motochika (元親)', 'Chōsokabe (長宗我部)', --https://fr.wikipedia.org/wiki/Clan_Ch%C5%8Dsokabe
        1, 0, 0,
        (SELECT ID FROM factions WHERE name = 'Samouraï Chōsokabe' ),
        (SELECT ID FROM factions WHERE name = 'Samouraï Chōsokabe' ),
        'Kunichika Chōsokabe est présumé mort.
        Le fils Motochika Chōsokabe n’as pas encore l’age pour être Daimyo.
        Vous vous devez de tenir la barre du clan durant cette période de transition.
        Malheureusement sans grande assistance vue que vos meilleurs éléments ne sont jamais rentrée de guerre.
        Heuresement vous avez un accord avec Miyoshi, Fudžisan la petit soeur de leur héritier, désormais devenu Daimyo, épousera Motochika.
        Les enfants, de Motochika et Fudžisan, du clan Chōsokabe reigneront sur l’ile et le clan Miyoshi en administrera la moitiée.
        Il suffit de tenir 2 ans que Motochika soit un adulte.
        '
    )
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
    ('Kūkai (空海)', 'Kōbō-Daishi (弘法大師)', 'passif',
        2, 1, 1, 0,
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes'),
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes')
    ),
    ('Yoshiteru (義輝)', 'Ashikaga (足利)', 'passif',
        2, 1, 1, 0,
        (SELECT ID FROM factions WHERE name = 'Samouraï Ashikaga'),
        (SELECT ID FROM factions WHERE name = 'Samouraï Ashikaga')
    )
;

-- players with no start worker limits
INSERT INTO controllers (
    firstname, lastname,
    faction_id, fake_faction_id,
    story
) VALUES
    ('Daïmyo Nagayoshi (長慶)', 'Miyoshi (三好氏)',  --https://fr.wikipedia.org/wiki/Clan_Miyoshi
        (SELECT ID FROM factions WHERE name = 'Chrétiens' ),
        (SELECT ID FROM factions WHERE name = 'Samouraï Miyoshi' ),
        'Motonaga Miyoshi votre père et Daimyo précédent est présumé mort.
        Vous êtes enfin Daimyo et libre de vos actes.

        Personnellement vous avez abandonnée le boudhisme pour une nouvelle religion, celle du christ precher par les moines accompagnant les vaisseaux noirs des portugais.
        Vous avez passer un marché avec eux et avez accès aux fusils à meches un avantage non négligeable et pour l’instant secret.
        Ces temps troubles sont peut-être le bon moment pour un changement religieux dans la région.

        Votre père avait un accord avec le clan Chōsokabe, Fudžisan votre petite soeur épousera Motochika Chōsokabe le futur Daimyo de Shikoku quand il sera majeur.
        Vos neuveux, les enfants de Motochika et Fudžisan, du clan Chōsokabe reigneront sur l’ile et vos enfants le clan Miyoshi en administrera la moitiée.
        Il suffit de tenir 2 ans que Motochika soit un adulte et que les noces ait lieu.
        Ou pas, car c’est temps troubles seraient le moment opportun pour vous éxtraire de la position de clan vassal et de devenir enfin maitre de l’ile.
        '
    ),
    ('Shinshō-in (信証院)', 'Rennyo (蓮如)', -- https://fr.wikipedia.org/wiki/Rennyo
        (SELECT ID FROM factions WHERE name = 'Ikkō-ikki' ),
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes' ),
        'Vous êtes le 8eme abbé du mouvement boudique Jōdo shinshū (la Véritable école de la Terre pure).
        Les Ikkō-ikki sont la face visible du Jōdo shinshū composé des petits nobles-locaux, des paysans, des moines guerriers des pretres shintos qui se rebellent ouvertement contre la caste des samourails.
        
        Vous avez quitté Fukui à l’ouest de Honshu à la tête d’une armée Ikko-ikki bien décidé a boutter le Shougunat hors de Kyoto, et levé le joug des Samourails sur l’autorité de l’empereur.
        Mais les plaines autour de la capitale sont une zone dangereuse en ce moment et vos troupes ont été défaites par celles de Kunichika Chōsokabe.
        Nos sans que vous leur ayez donné du fil à retordre.

        Vus avez pu vous échapper et avez appris peut de temps plus tard que la force blessé de Chōsokabe avait affronter désastreusement la cavalerie Takeda.

        La disparitions de Kunichika Chōsokabe et ses vassaux va crée un confit de pouvoir entre les clans de l’ile de Shikoku. 
        Vous avez appelé certains de vos fils pour venir lancé le mouvement Ikkō-ikki dans la région.
        Et vous avez acheter votre passage sur l’ile auprès des Marins Wano.

        Désormais depuis votre repaire dans le mont Ishizuchi vous préparez votre conquête.
        Peut être qu’en eveillant quelques Yōkais vous pourrez rendre votre conquête plus facile.
        '
    ),
    ('Daïmyo Tadaoki (忠興)', 'Hosokawa (細川氏)', -- https://fr.wikipedia.org/wiki/Clan_Hosokawa
        (SELECT ID FROM factions WHERE name = 'Samouraï Hosokawa' ),
        (SELECT ID FROM factions WHERE name = 'Samouraï Hosokawa' ),
        ' Fujitaka Hosokawa votre père et Daimyo précédent est présumé mort.
          Vous êtes désormais Daimyo. Vous vous devez de tenir la barre du clan durant cette période de troubles.
          Malheureusement sans grande assistance vue que vos meilleurs éléments ne sont jamais rentrée de guerre.
          Vous venez d’une illustre famille sur le déclin. Vous êtes capable de tracé vos racines jusqu’a la lignée de l’empereur Seiwa Gengi il y a 700 ans.
          Il y a peinne 100ans Votre clan dominait Tokushima, Awaji, Kawaga et même jusqu’e Settsu sur Honshu. Mais assassinats et guerres fratricides vous on fait perdre la face.
          Désormais vous êtes un clan vasal des Chōsokabe, mais plus pour longtemps la mort de Kunichika Chōsokabe, accompagné de son vassal Fujitaka Hosokawa ont laissé une faille.
          Il ne vous reste plus qu’à bien jouer vos cartes pour profiter de la faiblesse du clan Chōsokabe, vous avez 5 ans devant vous avant que Motochika soit asser agé pour gouverner seul.
          Peut-être est-ce le temps du renouveau.'
    ),
    ('Murai', 'Wako (和光)', --
        (SELECT ID FROM factions WHERE name = 'Kaizokushū' ),
        (SELECT ID FROM factions WHERE name = 'Kaizokushū' ),
        ''
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
        (SELECT ID FROM controllers WHERE lastname in ('Miyoshi (三好氏)'))
    ),
    (
        (SELECT ID FROM players WHERE username = 'player3'),
        (SELECT ID FROM controllers WHERE lastname = 'Rennyo (蓮如)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player4'),
        (SELECT ID FROM controllers WHERE lastname = 'Hosokawa (細川氏)')
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
    , ('Ishizuchi','', 6, (SELECT ID FROM zones WHERE name = 'Montagnes d’Echime'))
    , ('Mine de fer de Kubokawa', '', 8, (SELECT ID FROM zones WHERE name = 'Cap sud de Kochi'))
    , ('Port de Kochi', '', 0, (SELECT ID FROM zones WHERE name = 'Grande Baie de Kochi'))
    , ('Ikeda', '', 0, (SELECT ID FROM zones WHERE name = 'Vallées d’Iya et d’Oboké de Tokushima'))
    , ('Oboke', '', 8, (SELECT ID FROM zones WHERE name = 'Vallées d’Iya et d’Oboké de Tokushima'))
    , ('Port de Tokushima', '', 0, (SELECT ID FROM zones WHERE name = 'Cote Est de Tokushima'))
    , ('Vieux temple', '', 8, (SELECT ID FROM zones WHERE name = 'Ile d’Awaji')) -- TEMPLE VENT
    , ('Vieux temple', '', 8, (SELECT ID FROM zones WHERE name = 'Ile de Shödoshima')) --PARRESSE
    , ('La cour impériale', '', 6, (SELECT ID FROM zones WHERE name = 'Cité Impériale de Kyoto'))
    , ('Les geoles impériales', '', 10, (SELECT ID FROM zones WHERE name = 'Cité Impériale de Kyoto'))
;
INSERT INTO locations (name, description, discovery_diff, zone_id, controller_id) VALUES
    ('Geoles Pirates', '', 8, (SELECT ID FROM zones WHERE name = 'Ile de Shödoshima'),  (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)')) --PARRESSE
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
      ', le développement après l’introduction des mousquets portugais vers 1543' )
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

