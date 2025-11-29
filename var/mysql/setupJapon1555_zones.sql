
INSERT INTO zones (name, description) VALUES
      ('Côte Ouest d’Iyo', 'La porte vers l’île de Kyūshū, cette bande littorale est animée par les flux incessants de navires marchands, pêcheurs et patrouilleurs. Les criques cachent parfois des comptoirs discrets ou des avant-postes de contrebandiers. Les brumes marines y sont fréquentes, rendant les approches aussi incertaines que les intentions de ses habitants.')
    , ('Montagnes d’Iyo', 'Entourant le redouté mont Ishizuchi, plus haut sommet de Shikoku, ces montagnes sacrées sont le domaine des ascètes, des Yamabushi et des esprits anciens. Les chemins escarpés sont peuplés de temples isolés, de cascades énigmatiques, et d’histoires transmises à demi-mots. Nul ne traverse ces hauteurs sans y laisser un peu de son âme.')
    , ('Cap sud de Tosa', 'Battue par les vents de l’océan Pacifique, cette pointe rocheuse est riche en minerai de fer, extrait dans la sueur et le sel. Le paysage austère dissuade les faibles, mais attire les clans ambitieux. Les tempêtes y sont violentes, et même les dragons du ciel semblent redouter ses falaises noires.')
    , ('Grande Baie de Kochi', 'Centre de pouvoir du clan Chōsokabe, cette baie est à la fois un havre de paix et un verrou stratégique. Bordée de rizières fertiles et de ports animés, elle est défendue par des flottes aguerries et des forteresses discrètes. On dit que ses eaux reflètent les ambitions de ceux qui la contrôlent.')
    , ('Vallée d’Iya et d’Oboké d’Awa', 'Ces vallées profondes, creusées par les torrents et le temps, abritent des plantations de thé précieuses et des villages suspendus au flanc des falaises. Peu accessibles, elles sont le refuge de ceux qui fuient la guerre, la loi ou le destin. Le thé qui y pousse a le goût amer des secrets oubliés.')
    , ('Côte Est d’Awa', 'Sur cette façade tournée vers le large, le clan Miyoshi établit son pouvoir entre les ports et les postes fortifiés. Bien que prospère, la région est sous tension : les vassaux y sont fiers, les ambitions grandes, et les flottes ennemies jamais loin. La mer y apporte autant de trésors que de périls.')
    , ('Province de Sanuki', 'Plaine fertile dominée par les haras impériaux et les sanctuaires oubliés, Sanuki est renommée pour ses chevaux rapides et robustes. Les émissaires s’y rendent pour négocier montures de guerre, messagers ou montures sacrées. C’est aussi une terre de festivals éclatants et de compétitions féroces.')
    , ('Ile d’Awaji', 'Pont vivant entre Shikoku et Honshū, Awaji est stratégiquement vitale et toujours convoitée. Les vents y sont brutaux, les détroits traîtres, et les seigneurs prudents. Ses collines cachent des fortins, ses criques des repaires, et ses chemins sont surveillés par des yeux invisibles.')
    , ('Ile de Shōdoshima', 'Île montagneuse et sauvage, jadis sanctuaire, aujourd’hui repaire des pirates Wako. Ses ports semblent paisibles, mais ses criques abritent des embarcations rapides prêtes à fondre sur les convois marchands. Les autorités ferment souvent les yeux, car même le vice paie tribut.')
;

INSERT INTO zones (hide_turn_zero, name, description) VALUES
    (1, 'Plaines du Kansai', 'Étendue fertile au cœur du Japon, les Plaines du Kansai sont bordées par la cité animée d’Osaka, les sanctuaires anciens de Wakayama, et les ports marchands de Kobe. Sous la surface prospère de ses rizières, le sang versé à Kyōto imprègne encore la terre, témoignant des intrigues et batailles passées. Ici, le commerce rivalise avec les complots, et les vents chargés de cendres et de parfums annoncent toujours un nouvel orage de pouvoir.')
    , (1, 'Cité impériale de Kyōto', 'Capitale impériale, centre des arts, des lettres et des poisons subtils. Ses palais cachent les plus anciennes lignées, ses ruelles, les complots les plus jeunes. Kyōto ne brandit pas l’épée, mais ceux qui y règnent peuvent faire plier des provinces entières par un sourire ou un silence.')
;

-- Controle des zones au départ
UPDATE zones SET
    claimer_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Chōsokabe (長宗我部)'),
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Chōsokabe (長宗我部)')
    WHERE name IN( 'Grande Baie de Kochi', 'Cap sud de Tosa' ) ;
UPDATE zones SET
    claimer_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Miyoshi (三好)'),
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Miyoshi (三好)')
    WHERE name = 'Côte Est d’Awa';
UPDATE zones SET
    claimer_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Hosokawa (細川)'),
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Hosokawa (細川)')
    WHERE name = 'Province de Sanuki';
UPDATE zones SET
    claimer_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)'),
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)')
    WHERE name = 'Ile de Shōdoshima';
UPDATE zones SET
    claimer_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)'),
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)')
    WHERE name = 'Montagnes d’Iyo';
UPDATE zones SET
    claimer_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)'),
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)')
    WHERE name IN( 'Cité impériale de Kyōto', 'Plaines du Kansai' );

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- Secrets scénario
INSERT INTO locations (name, discovery_diff, zone_id, controller_id, description, hidden_description) Values
    -- Ajouter un secret sur l'arrivée des rebels Ikko-ikki sur l'ile par petits groupes
    ('Plaine d’Uwajima', 7, (SELECT ID FROM zones WHERE name = 'Côte Ouest d’Iyo'), (SELECT ID FROM controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
        , 'Les vastes plaines d’Uwajima semblent paisibles sous le soleil, entre cultures clairsemées et sentiers oubliés.
Mais depuis plusieurs semaines, des groupes d’hommes en haillons, armés de fourches, de bâtons ou de sabres grossiers, y ont été aperçus.
Ces paysans ne sont pas d’ici : ils avancent discrètement, se regroupent à la tombée du jour, et prêchent un discours de révolte contre les samouraïs.',
    'Ce sont les avant-gardes des Ikko-ikki, infiltrés depuis le continent par voie maritime. Découvrir quel est le chef qui les unit pourrait permettre d’agir avant qu’il ne soit trop tard.'
    )
    -- Ajouter un secret sur l'arrivée de Rennyo déposée par les Kaizokushū Wako il y a quelques semaines à peinne
    , ('Port de Saijō', 8, (SELECT ID FROM zones WHERE name = 'Montagnes d’Iyo'), (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)')
        , 'Le port de Saijō est d’ordinaire animé par les pêcheurs locaux et les petits marchands.
Mais depuis peu, les anciens disent avoir vu, au crépuscule, un navire étrange accoster sans bannière, escorté par des pirates tatoués.',
        'Un moine en est descendu, un colosse au regard brûlant de ferveur : Rennyo lui-même, leader spirituel des Ikko-ikki.
Selon certains, il se serait enfoncé dans les montagnes d’Iyo avec une poignée de fidèles.
Ce secret, s’il venait à être révélé, pourrait changer l’équilibre religieux de toute l’île.'
    )
    -- Ajouter un secret sur l’alliance maritale entre les Chosokabe Motochika et les Hosokawa Tama
    , ('Relais de poste d’Ikeda', 8, (SELECT ID FROM zones WHERE name = 'Province de Sanuki'), NULL
        , 'Une auberge modeste, près de la grande route de Sanuki, reçoit parfois à l’aube des cavaliers fatigués, porteurs de missives cachetées.
L’une d’elles, récemment interceptée, contenait une promesse de mariage scellée entre Motochika Chōsokabe et Tama Hosokawa, fille de Fujitaka.
Si elle venait à se concrétiser, cette alliance unirait deux grandes maisons sur Shikoku et bouleverserait les rapports de pouvoir dans toute la région.
Pour l’instant, l’information est gardée secrète, mais les rumeurs montent.
Si vous avez entendu parler d’une rumeur sortant de la maison close de Marugame, ce n’est qu’une version déformée de cette vérité.',
    NULL
    )
    -- Ajouter un secret sur l'ile d'awaji à propos de la bataille de Kunichika contre les Ikko-ikki
    , ('Sanctuaire des blessés de guerre', 8, (SELECT ID FROM zones WHERE name = 'Ile d’Awaji'), NULL,
    'Dans les rizières embrumées des Plaines du Kansai, un ancien pavillon de thé, reconstruit après les guerres, sert de refuge à d’anciens samouraïs et émissaires.
Ces hommes portent les cicatrices des campagnes sanglantes de Kyōto, mais surtout, ils détiennent un autre récit : Kunichika Chōsokabe n’a pas fui par lâcheté lors de la bataille des plaines de Kyōto.',
    'Son armée fut encerclée par une manœuvre habile des Ikko-ikki, alliés aux Takeda (武田).
Les chroniques officielles ont effacé cette vérité, étouffée par les rivaux du clan et la honte des survivants. 
(Vous pouvez demander à l’orga la carte 3 et 4 de la bataille)'
)
    -- Ajouter un secret sur Shōdoshima à propos de la fuite des forces de Fujitaka face a l'avant garde Takedas alliées aux Ikko-ikki. Permettant de lever la rumeur sur sa couardise et de confirmé sa capture par un général du Shogun
    , ('Camp de deserteurs', 7, (SELECT ID FROM zones WHERE name = 'Ile de Shōdoshima'), NULL
        , 'Dans une gorge dissimulée parmi les pins tordus de Shōdoshima, quelques hommes efflanqués vivent en silence, fuyant le regard des pêcheurs et des samouraïs.
Ce sont des survivants de la déroute des plaines de Kyōto, dont ils racontent des versions bien différentes de celle propagée à la cour.',
    'Nous avons pu déterminer que : L’avant-garde des Chōsokabe, commandée par Fujitaka Hosokawa, se serait retrouvée face aux fanatiques Ikko-ikki, qui auraient écrasé ses lignes avant même que l’ordre de retraite ne puisse être donné.
Fujitaka, séparé de la force principale, aurait fui précipitamment vers Kyōto, mais aurait été aperçu capturé par un général des forces du Shogun Ashikaga (足利). Ces aveux, étouffés sous le fracas des récits officiels, pourraient bien réhabiliter l’honneur du Daimyō déchu — ou bouleverser les équilibres fragiles entre les clans.
(Vous pouvez demander les cartes 1 et 2 de la bataille.)'
    )
    -- Ajouter un secret sur Kyoto a propos de l’inimitié du Shogun contre les Chosokabe suit a sa débandade et fuite honteuse devant l'armée des takedas.
    , ('Cour impériale', 7, (SELECT ID FROM zones WHERE name = 'Cité impériale de Kyōto'), NULL
        , 'Au sein des couloirs feutrés de la Cour Impériale, on ne parle plus qu’à demi-mot des récents affrontements.
Le nom des Chōsokabe (長宗我部) y est devenu tabou, soufflé avec mépris : leur armée, jadis fière, aurait fui sans gloire devant l’avant-garde Takeda (武田).
Le Shogun Ashikaga (足利), humilié par leur débâcle, aurait juré de ne plus leur accorder confiance ni territoire.
Ce ressentiment pourrait être exploité — ou au contraire, désamorcé — selon les preuves et récits qui parviendraient à émerger de l’ombre.',
    NULL
    )
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- Secret des joueurs
INSERT INTO locations (name, description, hidden_description, discovery_diff, can_be_destroyed, zone_id, controller_id) VALUES
    -- Temple de Kimii-dera archive de Shōzan, qui est secretement Yoshiaki le frère du Shogun
    -- archives de Kukai, qui était en réalité un Chosokabe
    (
        'Temple de Kimii-dera',
        'Dans les collines boisées entourant le port de Wakayama, un vieux temple isolé abrite, au milieu des cerisiers et des statues de Bouddha, de multiples ouvrages bouddhistes et shintoïstes, ainsi que des registres des moines Tendai.
        L’un de ces ouvrages relate les entrées au temple, dont l’entrée du moine Shōzan, qui est en réalité Yoshiaki (義昭), frère cadet du Shogun Ashikaga (足利), ayant choisi la vie monastique pour échapper aux intrigues de la Cour.
        Nous pourrions tenter de le convaincre de revenir à la vie politique en lui promettant un soutien militaire et financier et en faire un Shogun allié.
        Nous n’avons pas pu consulter la totalité des registres, il nous faudrait y retourner, pour en apprendre plus sur les entrées au temple des moines de Shikoku.',
        'Nous avons pu accéder aux archives secrètes du vieux temple.
        Ce temple sert de sanctuaire à des moines, qui sont les archivistes de la secte Tendai.
        Nous avons pu consulter les registres, dont celui sur les entrées au temple des moines de Shikoku.
        Nous avons découvert que le véritable nom de Kūkai (空海) Kōbō-Daishi (弘法大師) — Kūkai le Grand Instructeur, était Makoto Sakana (眞魚) — Mao le « Poisson de vérité ».
        Il est le troisième fils de Katsushika (葛飾) Chōsokabe (長宗我部), le conquérant de Shikoku, ce qui en fait le grand-oncle de Motochika (元親) Chōsokabe et le frère de Kanetsugu (兼続) Chōsokabe.',
        6, 0,
        (SELECT ID FROM zones WHERE name = 'Plaines du Kansai'),
        (SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)')
    ),

    -- Temple du Jodo-Shinshu de Kobe, les bléssée de la bataille de Kyoto
    (
        'Temple de Kobe',
        'Dans les ruelles calmes de la petite ville de Kobe, un temple discret des Jōdo-Shinshū abrite un attroupement étrange.
Sous le regard bienveillant d’une statue du Bouddha Amida, des hommes et des femmes, vêtus de haillons, d’armures Ashigaru (paysannes) et de tenues de moines-soldats  du Jōdo-Shinshū, se soignent mutuellement.
Ils portent les cicatrices des batailles récentes, et certains sont gravement blessés.
Ils racontent qu’ils sont des survivants de la déroute des plaines de Kyōto du printemps 1555, mais sans préciser pour quel camp.
Il nous faut gagner leur confiance, et enquêter sur leur identité et leurs intentions.',
       'Dans les ruelles calmes de la petite ville de Kobe, un temple discret des Jōdo-Shinshū abrite un attroupement étrange de blessés de guerre.
Nous avons pu nous infiltrer parmi leurs rangs de fanatiques et découvrir que les forces Jōdo-Shinshū sous la bannière des Ikko-ikki ont bien affronté les troupes Chōsokabe (長宗我部) aux côtés des Takeda (武田) lors de la bataille des plaines de Kyōto au printemps 1555.
Ayant gagné leur confiance, nous avons pu accéder aux archives locales de l’arbre familial de Rennyo (蓮如), huitième abbé du mouvement.
Son fils adoptif Ren-jō (連城) n’est autre que Harumoto (晴元) Hosokawa (細川), un allié des Takeda et un ennemi du Shogun, exilé il y a 5 ans après sa défaite et désormais devenu moine.
(Vous pouvez demander à l’orga la carte 1 et 2 de la bataille)',
        7, 0,
        (SELECT ID FROM zones WHERE name = 'Plaines du Kansai'),
        (SELECT ID FROM controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
    )
;

INSERT INTO locations (name, description, discovery_diff, can_be_destroyed, zone_id, controller_id, activate_json) VALUES
    -- Temple du pelerinage de Shikoku de la Secte Tendai
    (
        'Ishizuchi-jinja (石鎚神社), le 64ᵉ temple du pèlerinage',
        'Situé au pied du mont Ishizuchi, le plus haut sommet de Shikoku, ce temple est le 64ᵉ des 88 temples du pèlerinage de Shikoku.
       Il est l’une des résidences de Kūkai (空海) Kōbō-Daishi (弘法大師) — Kūkai le Grand Instructeur, fondateur de la secte bouddhiste Shingon, et de Yūbien (宥辡 ‘Yū biàn‘) Shinnen (真念 – apaisement sincère), moine érudit qui a compilé le guide le plus complet du pèlerinage.
       Nous avons découvert dans les archives du temple qu’avant de prendre le nom de Yūbien, le jeune homme venu se faire moine se nommait Michinao (通直) Kōno (河野).',
        7,  0,
        (SELECT ID FROM zones WHERE name = 'Montagnes d’Iyo'),  
        (SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)'),
        '{ "update_location": {
            "name": "Ruines de l’Ishizuchi-jinja (石鎚神社)", 
            "discovery_diff": 6, "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Au pied du mont sacré Ishizuchi, il ne reste du temple qu’un champ de cendres et des colonnes calcinées dressées comme des spectres. Les archives ont été déchirées ou emportées, les statues brisées à coups de marteau ; seule l’odeur persistante du bois brûlé rappelle qu’ici priaient jadis les disciples de Kūkai et de Shinnen.",
            "future_location": {
                "name": "Ishizuchi-jinja (石鎚神社), le 64ᵉ temple du pèlerinage", "discovery_diff": 7,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Au pied du mont Ishizuchi, le temple renaît de ses cendres, ses toits vernis reflétant la lumière du matin comme des éclats de jade. Les cloches résonnent à nouveau dans la vallée, et les fidèles reprennent le pèlerinage sur les sentiers rajeunis, tandis que les statues soigneusement réparées semblent veiller sur chaque pas des visiteurs, rappelant la sagesse de Kūkai et les espoirs de Yūbien Shinnen. Les moines reconstituent les archives de mémoire, comme le nom du jeune homme venu se faire moine qui se nommait Michinao (通直) Kōno (河野)."
            }
        }}'
    ),
    -- Ōyamazumi-jinja (大山祇神社) -- sanctuaire shinto
    (
        'Ōyamazumi-jinja (大山祇神社) -- sanctuaire shinto', 
        'Ce sanctuaire, situé sur l’île d’Ōmishima dans la mer intérieure de Seto, au nord de Saijō, fait partie des vingt temples mineurs du pèlerinage de Shikoku. C’est le sanctuaire ancestral du clan Kōno (河野), descendants d’Iyo-Shinnō, fils de l’empereur Kanmu (781–806), qui fonda la province d’Iyo sur l’île de Shikoku. Le sanctuaire est dédié aux dieux qui protègent les marins et les soldats. Pour cette raison, de nombreux Daimyōs viennent y faire des offrandes dans l’espoir de succès, ou en remerciement de leurs victoires. Les derniers membres du clan Kōno (河野) s’y retrouvent parfois pour parler, à voix basse et triste, de leur héritage disparu et de leurs terres confisquées. (Pour explorer davantage ce lieu, allez voir un orga !)',
        7, 0,
        (SELECT ID FROM zones WHERE name = 'Côte Ouest d’Iyo'),
        (SELECT ID FROM controllers WHERE lastname = 'Kōno (河野)'),
        '{ "update_location": {
            "name": "Ruines de l’Ōyamazumi-jinja (大山祇神社)", 
            "discovery_diff": 6, "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Sur l’île d’Ōmishima, le sanctuaire d’Oyamazumi-Jinja gît en ruines, ses toits effondrés et ses portes brisées laissant entrer le vent salé de la mer intérieure de Seto. Les statues des dieux protecteurs sont éclatées, les offrandes piétinées, et le silence des lieux ne porte plus que le souvenir des Kōno et de leur héritage perdu.",
            "future_location": {
                "name": "Temple de Ōyamazumi-jinja (大山祇神社) -- sanctuaire shinto", "discovery_diff": 7,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Sur l’île d’Ōmishima, l’Oyamazumi-Jinja s’élève à nouveau dans une splendeur éclatante : toits laqués, bois sculpté et dorures étincelantes célèbrent la puissance des dieux protecteurs des marins et des guerriers. Une plaque commémorative montrant le Mōn des Kōno rappelle que leur mémoire n’a pas été effacée par les horreurs commises ici, et les statues ancestrales veillent de nouveau sur les lieux. Les membres du clan viennent y murmurer leurs prières, comme pour renouer avec l’héritage de leurs ancêtres. (Pour explorer davantage ce lieu, allez voir un orga !)"
            }
        }}'
    )
;

-- Lieux secrets
INSERT INTO locations (name, description, discovery_diff, can_be_destroyed, zone_id, controller_id, activate_json) VALUES
    -- Geôles impériales de Kyoto
    (
        'Geôles impériales',
        'Sous les fondations de la Cité Impériale, ces geôles étouffantes résonnent des cris affaiblis des oubliés du Shogun.
L’air y est moite, chargé de remords et d’encre séchée — là où les sentences furent calligraphiées avant d’être exécutées.
Peu en ressortent, et ceux qui le font ne parlent plus.',
        8, 1,
        (SELECT ID FROM zones WHERE name = 'Cité impériale de Kyōto'),
        (SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)'),
        '{"indestructible":"TRUE"}'
    ),

    -- Geôles des pirates (Shōdoshima)
    (
        'Geôles des Kaizokushū', 
        'Creusées dans la falaise même, ces cavernes humides servent de prison aux captifs des Wako.
Des chaînes rouillées pendent aux murs, et l’eau salée suinte sans cesse, rongeant la volonté des enfermés.
Le silence n’y est troublé que par les pas des geôliers — ou les rires des pirates.',
        8, 1,
        (SELECT ID FROM zones WHERE name = 'Ile de Shōdoshima'),
        (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)'),
        '{"update_location": {
            "name": "Prison en ruines", "discovery_diff": 6,
            "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Creusées dans la falaise, les anciennes geôles des Wako ne sont plus qu’un amoncellement de pierres effondrées et de poutres brisées. Les chaînes tordues et rouillées pendent désormais au-dessus de flaques d’eau croupie, et le vent hurle à travers les fissures, emportant les échos des rires cruels qui autrefois glaçaient le sang des captifs.",
            "future_location": {
                "name": "Geôles des Kaizokushū", "discovery_diff": 8,
            "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
            "description": "Les geôles creusées dans la falaise ont été solidement restaurées : les murs sont renforcés de pierres taillées et les chaînes ont été reforgées, conservant l’aspect redoutable des lieux. Le silence oppressant retrouve son pouvoir de crainte, rappelant à tous la discipline et l’autorité des Kaizokushu."
            }
        }}'
    ),

    -- Retraite secrete des Chosokabe (cape sud de Tosa)
    (
        'Retraite secrète des Chōsokabe', 
        'Caché sur les flancs escarpés du cap sud de Tosa, un pavillon de chasse sert de lieu de villégiature à une étrange concentration de serviteurs Chōsokabe.
On y trouve des armes et des provisions, tout le nécessaire pour qu’un membre de la famille puisse s’y cacher.',
        8, 1,
        (SELECT ID FROM zones WHERE name = 'Cap sud de Tosa'),
        (SELECT ID FROM controllers WHERE lastname = 'Chōsokabe (長宗我部)'),
        '{"update_location": {
            "name": "Ruines d’un pavillon de chasse", "discovery_diff": 6,
            "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Perché sur les flancs escarpés du cap sud de Tosa, le pavillon de chasse des Chōsokabe gît en ruines. Les murs sont éventrés, les provisions brûlées ou pillées, et les armes jonchent le sol noirci par le feu. Les traces de lutte et de chaos témoignent d’une intrusion violente, laissant derrière elle un lieu autrefois sûr devenu un souvenir menaçant et silencieux.",
            "future_location": {
                "name": "Pavillon de chasse des Chōsokabe", "discovery_diff": 8,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Perché sur les flancs escarpés du cap sud de Tosa, le pavillon de chasse des Chōsokabe a été reconstruit avec soin et élégance. Les murs de bois tiédissent au soleil, les provisions et les armes sont à nouveau rangées avec ordre. La retraite offre un refuge paisible, mêlant confort discret et préparation guerrière, fidèle à l’esprit vigilant du clan."
            }
        }}'
    )
    -- Ajouter un secret sur la présence du christianisme et du pretre Luís Fróis Japonologue et Jésuite -- https://fr.wikipedia.org/wiki/Lu%C3%ADs_Fr%C3%B3is 
    , (
        'Sanctuaire clandestin du Port de Tokushima',
        'Dans les ruelles du port de Tokushima, à l’écart des marchés, une maison basse aux volets clos abrite parfois un hôte peu commun : Luís Fróis, prêtre jésuite portugais et érudit des mœurs japonaises.
Il y aurait établi un sanctuaire clandestin, enseignant les paroles du Christ à quelques convertis du clan Sogo.
Sa présence confirme l’implantation secrète du christianisme à Tokushima et menace de faire basculer les équilibres religieux et politiques de Shikoku.
Ce lieu sert également de relais discret pour faire entrer armes, livres et messagers depuis Nagasaki.'
        , 8, 1,
        (SELECT ID FROM zones WHERE name = 'Côte Est d’Awa'),
        (SELECT ID FROM controllers WHERE lastname = 'Sogō (十河)'),
        '{"update_location": {
            "name": "Ruines d’un Sanctuaire chrétien clandestin", 
            "discovery_diff": 5, "save_to_json": "TRUE",
            "can_be_destroyed": 0, "can_be_repaired": 0,
            "description": "Les ruelles du port de Tokushima portent encore l’odeur âcre du feu. Là où se tenait la maison aux volets tirés, il ne reste qu’un amas de cendres et de poutres tordues. Les symboles du Christ ont été brisés, mêlés à la suie et au sel. Certains murmurent qu’on a vu des silhouettes en armure quitter les lieux avant l’aube, d’autres jurent avoir entendu des prières étouffées sous les cris. Le sanctuaire clandestin de Luís Fróis n’est plus — seulement un tas de décombres où même les mouettes se taisent."
        }}'
    )
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- Temples des Yokais
INSERT INTO locations (name, discovery_diff, can_be_destroyed, zone_id, controller_id, description, activate_json ) VALUES
     -- Feu - Teppō
    ('Vieux temple des colines de Kubokawa', 8, 1, (SELECT ID FROM zones WHERE name = 'Cap sud de Tosa'),  (SELECT ID FROM controllers WHERE lastname = 'Shikoku (四国)'),
        'Accroché aux flancs escarpés de la côte sud de Tosa, un petit sanctuaire noirci repose au bord d’une ancienne veine de fer oubliée.
Au loin, dans la vallée, les marteaux des forgerons de Kuroshio résonnent comme une prière sourde.
Mais chaque nuit, une odeur de poudre flotte dans l’air, et un claquement sec — sec comme un tir — fait envoler les corbeaux.
(Pour explorer davantage ce lieu, allez voir un orga !)',
        '{ "update_location": {
            "name": "Ruines des colines de Kubokawa", 
            "discovery_diff": 6, "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Accroché aux flancs ravinés de la côte de Tosa, le sanctuaire n’est plus qu’un amas de poutres brisées et de pierres éclatées. Les forges de la vallée battent encore, mais ici ne règnent plus que la suie, l’odeur de poudre et le silence d’un lieu profané.",
            "future_location": {
                "name": "Temple des colines de Kubokawa", "discovery_diff": 8,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Tel une araignée, un sanctuaire rénové est accroché aux flancs d’un ravin de la côte de Tosa. Les poutres, qui ont été coupées dans des arbres centenaires, suintent de sève, et la roche de la falaise a été retaillée bien net. Les forges résonnent joyeusement dans la vallée. La nuit, une légère odeur de poudre se fait sentir et on peut entendre des cliquetis secs, accompagnés des étincelles du silex sur le métal. (Pour explorer davantage ce lieu, allez voir un orga !)"
            }
        }}')
    -- Vent - Tessen
    , ('Vieux temple de la falaise d’Esaki', 8, 1, (SELECT ID FROM zones WHERE name = 'Ile d’Awaji'),  (SELECT ID FROM controllers WHERE lastname = 'Shikoku (四国)'),
        'Perché au sommet d’une falaise d’Awaji, un petit pavillon de bois battu par les vents se dresse, fragile et silencieux.
La porte ne ferme plus, et le papier des lanternes s’effiloche. Pourtant, nul grain de poussière ne s’y pose.
Lorsque l’on entre, l’air se fait soudain glacé, et un bruissement court dans les chevrons — comme si un éventail invisible fendait l’air avec colère.
(Pour explorer davantage ce lieu, allez voir un orga !)',
        '{ "update_location": {
            "name": "Ruines de la falaise d’Esaki", 
            "discovery_diff": 6, "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Perché au sommet de la falaise d’Awaji, il ne reste plus du pavillon qu’une carcasse calcinée. Les piliers noircis s’élèvent comme des doigts tordus, griffant le ciel, et les tuiles éclatées jonchent le sol en éclats tranchants. Les lanternes sont réduites à des carcasses de bois carbonisé, et l’autel, brisé à coups de hache, suinte encore d’une odeur de cendre froide. Un silence lourd règne, brisé parfois par le vent qui gémit à travers les poutres fendues — comme un sanglot étouffé du sanctuaire profané.",
            "future_location": {
                "name": "Temple de la falaise d’Esaki", "discovery_diff": 8,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Perché au sommet d’une falaise d’Awaji, un petit pavillon de bois battu par les vents se dresse, des planches fraîches de cèdre sentent encore la sève. Ce lieu est neuf, reconstruit il y a peu par une bonne âme après une destruction insensée. Les fondations de vieilles pierres portent les cicatrices d’un feu ayant même fendu la roche par endroits. Un bruissement léger se fait entendre de temps en temps, presque comme si un éventail invisible s’agitait insouciamment. (Pour explorer davantage ce lieu, allez voir un orga !)"
            }
        }}'
)
     -- Paresse - Biwa
    , ('Vieux temple du vallon de Tengu-Iwa', 7, 1, (SELECT ID FROM zones WHERE name = 'Ile de Shōdoshima'),  (SELECT ID FROM controllers WHERE lastname = 'Shikoku (四国)'),
        'Ce temple oublié, dissimulé dans un vallon brumeux de Shōdoshima, semble abandonné depuis des décennies.
Pourtant, chaque crépuscule, les accords las d’un biwa résonnent sous les poutres vermoulues, portés par une brise douce où flotte un parfum de saké tiède.
Pourtant nul prêtre et nul pèlerin en vue.
(Pour explorer davantage ce lieu, allez voir un orga !)',
        '{ "update_location": {
            "name": "Ruines du vallon de Tengu-Iwa", 
            "discovery_diff": 6, "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Dans le vallon brumeux, il ne reste du temple que des poutres éclatées et des tuiles fendues, mêlées aux cendres froides. Le parfum de saké s’est mué en odeur de fumée âcre, et une vibration sourde et gênante semble provenir de la roche.",
            "future_location": {
                "name": "Temple du vallon de Tengu-Iwa", "discovery_diff": 7,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Sous les brumes de Shōdoshima, le temple du vallon de Tengu-Iwa renaît, patiemment reconstruit sur ses ruines. Les poutres neuves exhalent encore l’odeur du bois frais, et le biwa résonne à nouveau au crépuscule, plus clair, plus mélancolique qu’autrefois. Certains pèlerins affirment pourtant que lorsque le vent tombe, on entend sous les chants une note étrangère — comme un écho venu des cendres anciennes. (Pour explorer davantage ce lieu, allez voir un orga !)"
            }
        }}'
    )
     -- Roche - Chigiriki
    , ('Vieux temple du Mont Ishizuchi', 8, 1, (SELECT ID FROM zones WHERE name = 'Montagnes d’Iyo'),  (SELECT ID FROM controllers WHERE lastname = 'Shikoku (四国)'),
        'Perché sur un piton rocheux des montagnes d’Ehimé, un ancien temple taillé à même la pierre repose, figé comme un souvenir.
Nul vent n’y souffle, nul oiseau n’y niche.
Parfois, on y entend cliqueter une chaîne sur la pierre nue, comme si une arme traînait seule sur le sol.
(Pour explorer davantage ce lieu, allez voir un orga !)',
        '{ "update_location": {
            "name": "Ruines au sommet du Mont Ishizuchi", 
            "discovery_diff": 6, "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Taillé dans la roche, le temple d’Ishizuchi a été souillé par ceux qui n’ont pu détruire les pierres robustes. Des graffitis ont été tracés au charbon, les autels renversés, et la porte d’entrée sortie de ses gonds gît dans le précipice. Le reste est rongé par les intempéries. Les chaînes qui pendaient jadis à son flanc gisent brisées, et quand le vent s’y engouffre, on croirait entendre le râle d’un guerrier mort oublié dans la montagne.",
            "future_location": {
                "name": "Temple au sommet du Mont Ishizuchi", "discovery_diff": 7,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Creusé à même la roche, le temple d’Ishizuchi a été nettoyé, la pierre retaillée pour effacer les traces de la profanation et la peinture fraîche remplace l’odeur de brûlé qui hantait les lieux. Des chaînes robustes pendent sur le flanc de montagne mais aucun vent ne semble les faire bouger. Pourtant, on peut parfois les entendre cliqueter sur la pierre nue. Pas un oiseau n’ose s’y nicher. (Pour explorer davantage ce lieu, allez voir un orga !)"
            }
        }}'
    )
;

-- Ressources
INSERT INTO locations (name, description, discovery_diff, zone_id) VALUES
    -- Thé d’Oboké
    ('Vallée fertile d’Iya', 
        'Dans la vallée profonde d’Iya, où le bruit de la rivière est permanent, poussent à flanc de roche de rares théiers.
Leurs feuilles, amères et puissantes, sont cueillies à la main par les familles montagnardes, suspendues au-dessus du grondement des eaux.
Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare, sinon nous pouvons toujours tenter de négocier avec le clan qui contrôle cette zone.',
        6, (SELECT ID FROM zones WHERE name = 'Vallée d’Iya et d’Oboké d’Awa')
    ),

    -- Armure en fer de Tosa
    ('Mine de fer de Kubokawa',
        'Dans les profondeurs du cap sud de Tosa, des veines de fer noir sont extraites à la force des bras puis forgées en cuirasses robustes dans les forges voisines.
Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare, sinon nous pouvons toujours tenter de négocier avec le clan qui contrôle cette zone.',
        6, (SELECT ID FROM zones WHERE name = 'Cap sud de Tosa')
    ),

    -- Cheval de Sanuki
    ('Écuries de Takamatsu',
        'Les vastes pâturages de Sanuki forment l’écrin idéal pour l’élevage de chevaux endurants, prisés tant pour la guerre que pour les grandes caravanes.
Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare, sinon nous pourrions toujours tenter de négocier avec le clan qui contrôle ce territoire.',
        6, (SELECT ID FROM zones WHERE name = 'Province de Sanuki')
    ),

    -- Encens coréen
    ('Port marchand de Matsuyama',
        'Des voiliers venus de la péninsule coréenne accostent à Matsuyama, chargés de résines rares dont les parfums servent aux temples autant qu’aux intrigues.
Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare, sinon nous pouvons toujours tenter de négocier avec le clan qui contrôle cette zone.',
        6, (SELECT ID FROM zones WHERE name = 'Côte Ouest d’Iyo')
);

-- Fluff
INSERT INTO locations (name, description, discovery_diff, zone_id) VALUES
    ('Port d’Uwajima',
     'Un port animé aux quais denses et bruyants, où s’échangent riz, bois et rumeurs en provenance autant de Kyūshū que de Corée.
Les marins disent que la brume y reste plus longtemps qu’ailleurs.',
     6, (SELECT ID FROM zones WHERE name = 'Côte Ouest d’Iyo')),

    ('Mt Ishizuchi',
     'Plus haut sommet de l’île, le mont Ishizuchi domine les vallées alentour comme un sabre dressé vers le ciel.
On dit qu’un pèlerinage ancien y conduit à une dalle sacrée où les esprits s’expriment lorsque les vents tournent.',
     6, (SELECT ID FROM zones WHERE name = 'Montagnes d’Iyo')),

    ('Port de Kochi',
     'Protégé par une anse naturelle, ce port militaire et marchand voit passer jonques, bateaux de guerre et pirates repentis.
Son arsenal est surveillé nuit et jour par des Ashigaru au Môn des 7 fleurs tandis que les jardins privés du château abritent les beautés les plus gracieuses.',
     6, (SELECT ID FROM zones WHERE name = 'Grande Baie de Kochi')),

    ('Village d’Oboké',
     'Petit village de montagne aux maisons de bois noircies par le temps.
Les voyageurs s’y arrêtent pour goûter un saké réputé, brassé à l’eau fraîche des gorges qui serpentent en contrebas.
Le bain des hommes de l’onsen d’Iya aurait été souillé par un étranger au corps couvert d’encre. Cependant, les rumeurs accusant une dame de lui avoir parlé sont fausses, tout comme celles le décrivant comme un pirate Wako.',
     6, (SELECT ID FROM zones WHERE name = 'Vallée d’Iya et d’Oboké d’Awa')),

    ('Port de Naruto',
     'Carrefour maritime entre Honshū et Shikoku, le port de Naruto bruisse de dialectes et de voiles étrangères.
Dans les ruelles proches du marché, on parle parfois portugais, ou latin, à voix basse.',
     6, (SELECT ID FROM zones WHERE name = 'Côte Est d’Awa')),

    ('Grande route et relais de poste',
     'Relie Tokushima à Kōchi en serpentant à travers les plaines fertiles de la province de Sanuki au nord.
À chaque relais, les montures peuvent être changées, et les messagers impériaux y trouvent toujours une couche et un bol chaud.',
     6, (SELECT ID FROM zones WHERE name = 'Province de Sanuki')),

    ('Rumeurs de la bataille',
     'Les pêcheurs d’Awaji parlent encore d’un combat féroce dans les plaines du Kansai sur Honshu, entre troupes en fuite et rebelles aux visages masqués.
S’il y a un prisonnier important, il doit être caché sur une île plus discrète, en tout cas, il n’y a personne au phare abandonné. Les seuls Chōsokabe qui sont venus ici sont les rescapés de guerre et ils ne sont pas restés.
Certains affirment en revanche avoir vu une lueur étrange au-dessus du vieux temple de la falaise d’Esaki.',
     6, (SELECT ID FROM zones WHERE name = 'Ile d’Awaji')),

    ('Détroit d’Okayama',
     'Étroit et venteux, ce détroit aux eaux traîtresses sépare Shikoku de Honshū.
Difficile de tenter cette traversée sans être épié par les habitants de l’île de Shōdoshima.
Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d’être intercepté par les Kaizokushū.',
     6, (SELECT ID FROM zones WHERE name = 'Ile de Shōdoshima')),

    ('Suzaku Mon',
     'Porte monumentale ouvrant sur la grande artère pavée de la capitale impériale, menant tout droit au Palais. Sous ses tuiles rouges, l’ombre des complots se mêle aux parfums de thé, et les bannières flottent dans un silence cérémoniel.',
     6, (SELECT ID FROM zones WHERE name = 'Cité impériale de Kyōto'))
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- Fake News :
INSERT INTO locations (name, discovery_diff, zone_id, description) VALUES
    ('Maison close de Marugame', 5,  (SELECT ID FROM zones WHERE name = 'Province de Sanuki'),
        'À Marugame, dans une maison close réputée pour son saké sucré et ses éventails peints à la main, des courtisanes murmurent entre deux chansons.
L’une d’elles prétend avoir lu une lettre scellée, confiée par un émissaire enivré, annonçant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre éclair contre les Chōsokabe.'
    ),
    ('Crique de Tonoshō', 5, (SELECT ID FROM zones WHERE name = 'Ile de Shōdoshima'),
        'Cette crique isolée, souvent balayée par les vents, est connue des contrebandiers comme des pêcheurs.
Depuis quelques jours, un bruit court : un important émissaire impérial aurait été intercepté par les pirates Wako et détenu dans une grotte voisine, en attendant rançon ou silence.'
    ),
    -- Un dementi est présent sur l'info 'Camp des éclaireur Takeda'
    ('Maison de thé de la Lune d’Or', 5, (SELECT ID FROM zones WHERE name = 'Cité impériale de Kyōto'),
        'Située à l’écart de la Suzaku Mon, la "Lune d’Or" attire les lettrés, les poètes… et les oreilles curieuses.
On dit qu’un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.
Selon une geisha, il serait en réalité un espion du clan Takeda (武田), infiltré pour sonder la loyauté des Daimyōs de l’Est.
Il aurait même été vu avec un membre de la famille Chōsokabe (長宗我部).
Pourtant, nul ne peut confirmer cette histoire, et certains prétendent qu’il n’est en réalité qu’un veuf mélancolique, égaré dans ses souvenirs.
Mais à Kyōto, les apparences mentent plus souvent qu’elles ne disent vrai.'
    ),
    ('Phare abandonné d`Esaki', 5,
    -- Un démenti est existant sur le 'vieux temple d'Esaki', il est possible de le trouver dans les rumeurs
        (SELECT ID FROM zones WHERE name = 'Ile d’Awaji'),
        'Isolé au bout d’une presqu’île battue par les vents, le vieux phare d’Esaki n’est plus qu’un squelette de pierre rongé par le sel.
Pourtant, certains pêcheurs affirment y voir passer des silhouettes armées à la tombée de la nuit.
La rumeur court qu’un prisonnier de valeur y est gardé en secret par le clan Chōsokabe, un traître capturé lors des affrontements récents.'
    ),
    ('Sanctuaire brisé de Nahari', 5,
    -- Un démenti est existant sur la 'congrégation du calvaire de Nahari', il est possible de le trouver dans les rumeurs
    (SELECT ID FROM zones WHERE name = 'Grande Baie de Kochi'),
        'Surplombant la mer, les ruines du sanctuaire de Nahari sont battues par les embruns.
On dit que des prêtres étrangers y ont été aperçus de nuit, en compagnie d’émissaires du clan Chōsokabe.
La rumeur parle d’un pacte impie : en échange d’armes à feu venues de Nagasaki, le clan accepterait d’abriter des convertis clandestins.'
    ),
    ('La congrégation du Calvaire de Nahari', 7,
        -- démenti du sanctuaire brisé de Nahari
        (SELECT ID FROM zones WHERE name = 'Grande Baie de Kochi'),
        'Surplombant la mer, les ruines du sanctuaire bouddhiste de Nahari, battues par les embruns, abritent un ancien calvaire chrétien.
Le prêtre chrétien en a été chassé par les moines bouddhistes de la secte Tendai.
Mais aucune arme à feu n’a été aperçue, et les moines affirment que les rumeurs de pacte avec les chrétiens sont infondées.'
    ),
    ('Comptoir d`Hiwasa', 5,
    --  démenti par la rumeur du vaisseau noir au comptoir d'Hiwasa
    (SELECT ID FROM zones WHERE name = 'Côte Est d’Awa'),
        'Ce modeste comptoir marchand, adossé à une crique discrète, connaît une activité étrange depuis quelques semaines.
Des jonques aux voiles noires y accostent en silence, et leurs capitaines refusent de dire d’où ils viennent.
Certains affirment que les Wako auraient reçu des fonds d’un clan du Nord — peut-être les Hosokawa — pour saboter les entrepôts du port de Tokushima.
D’autres n’y voient qu’un simple commerce de sel et de fer… Mais alors, pourquoi tant de discrétion ? Et pourquoi autant de lames prêtes à jaillir à la moindre question ?'
    ),
    ('Un vaisseau noir au Comptoir d`Hiwasa', 8,
    -- démenti de la rumeur du comptoir d'Hiwasa
    (SELECT ID FROM zones WHERE name = 'Côte Est d’Awa'),
        'Ce modeste comptoir marchand, adossé à une crique discrète, a été le théâtre de la plus étrange des scènes.
Les rapports précédents faisant état de jonques aux voiles noires, de capitaines Kaizokushū œuvrant à saboter les entrepôts du port de Tokushima, sont entièrement faux.
En réalité, nous avons découvert qu’un vaisseau noir, immense, aux voiles carrées, a été aperçu au large, et les marins affirment qu’il s’agit d’un navire de guerre portugais.
Le commerce, mené dans un secret relatif, consiste en un échange de sel et d’argent du clan Miyoshi contre des armes à feu européennes.
Le comptoir est sous le contrôle de moines chrétiens, et les rumeurs de piraterie ne sont qu’un écran de fumée.'
    ),
    ('Onsen de la rivière Iya', 5,
    (SELECT ID FROM zones WHERE name = 'Vallée d’Iya et d’Oboké d’Awa'),
        'On dit qu’un capitaine Kaizokushu aurait été aperçu, tard dans la nuit, aux sources chaudes d’Iya. Ses tatouages de dragons auraient fait fuir les servantes, mais pas la noble dame qui entrait aux bains en yukata léger. Quand il en est sorti, l’eau bouillait encore — et ceux qui s’y sont baignés depuis jurent avoir senti une main invisible leur caresser la peau sous l’eau. Quant à la jeune femme, personne ne l’a revue.'
    ),
    ('Forges de Kuroshio', 5,
    (SELECT ID FROM zones WHERE name = 'Vallée d’Iya et d’Oboké d’Awa'),
        'On dit que dans les forges de Kuroshio, certains marteaux battent au rythme d’un autre tambour — celui de la révolte populaire. Des émissaires venus du Kansai auraient été vus dans la vallée au crépuscule, apportant de l’or et des serments. Les moines du temple voisin prétendent que chaque épée forgée ainsi à la flamme de la révolte est destinée à faire couler le sang d’un tyran.'
    )
;

-- démenti de la fausse piste de la Lune D’Or
INSERT INTO locations (name, description, discovery_diff, is_base, can_be_destroyed, can_be_repaired, zone_id, controller_id, activate_json) VALUES
    ('Camp des éclaireurs Takeda (武田)', 
    'On trouve, caché dans un bosquet, entre deux collines, un camp qui fait clairement partie des forces Takeda (武田). Ils ont l’air d’avoir été battus lors de l’affrontement du printemps 1555 et fait de multiples prisonniers dans les forces Chōsokabe (長宗我部).
Il est clair que les rumeurs d’alliances entre les Chōsokabe et les Takeda sont sans fondement, mais cette simple constatation ne sera pas suffisante pour convaincre.
La défaite des Takeda n’a pas réduit leurs intentions belliqueuses envers le Shogun.
(Si vous voulez entrer en contact avec les Takeda, allez voir un orga ! )',
    5, 1, 1, 0, (SELECT ID FROM zones WHERE name = 'Plaines du Kansai'),
    (SELECT ID FROM controllers WHERE lastname = 'Takeda (武田)'),
    '{"update_location": {
        "name": "Ruines du Camp Takeda (武田)", "discovery_diff": 5,
        "is_base": 0,
        "can_be_destroyed": 0, "can_be_repaired": 1,
        "description": "Les ruines d’un camp militaire autrefois occupé par les forces Takeda (武田). Sa destruction semble nettement plus récente que la bataille du printemps 1555. Des ninjas nous ont proposé, contre une somme conséquente, d’organiser une rencontre avec Shingen Takeda (武田). (Pour explorer davantage ce lieu, adressez-vous à un orga !)",
        "future_location": {
            "name": "Camp de la révolte Takeda (武田)", "discovery_diff": 7,
            "is_base": 1,
            "can_be_destroyed": 0, "can_be_repaired": 1, "save_to_json": "TRUE",
            "description": "Un camp militaire de la révolte Takeda (武田), clairement établi sur la plaine du Kansai. Reste à déterminer s’ils nourrissent encore des intentions belliqueuses envers le Shōgun. (Si vous souhaitez entrer en contact avec le clan Takeda, adressez-vous à un orga !)",
        }
    }}'
);

INSERT INTO locations (name, description, discovery_diff, can_be_destroyed, can_be_repaired, zone_id, controller_id, activate_json) VALUES
    ('Ruines du Daihō-ji (大宝寺)',
        'Le Daihō-ji (大宝寺), 44ème temple du pèlerinage de Shikoku, n’a que quelques piliers carbonisés qui subsistent, noirs et fissurés par les flammes. Ici, les pèlerins affirment avoir vu un artefact étrange caché sous l’autel — une croix d’argent gravée d’inscriptions latines.
Les paysans parlent d’un prêtre chrétien et de l’Inquisition jésuite elle-même. Mais les recherches menées par les Yamabushi locaux (milices et petits samouraïs vassaux) n’ont rien révélé de probant. (Peut-être pouvons-nous restaurer ce lieu ?)',
         5, 0, 1, (SELECT ID FROM zones WHERE name = 'Montagnes d’Iyo'), NULL,
        '{"update_location": {
                "name": "Temple Daihō-ji (大宝寺)", "discovery_diff": 6,
                "can_be_destroyed": 1, "can_be_repaired": 0,
                "description": "Le 44ᵉ temple du pèlerinage de Shikoku, Daihō-ji (大宝寺), dont les piliers ont été remplacés et les toits refaits de bois verni, a retrouvé son éclat d’antan. L’autel, soigneusement restauré, abrite de nouveau des objets rituels et les pèlerins viennent réciter leurs prières dans le calme retrouvé, tandis que les légendes de la violence des prêtres chrétiens et de l’Inquisition se murmurent à voix basse, comme des échos du passé. Les forces Yamabushi (milices et petits samouraïs vassaux) veillent sur ce temple avec prudence.",
                "future_location": {
                    "name": "Ruines du Daihō-ji (大宝寺)", "discovery_diff": 5,
                    "can_be_destroyed": 0, "can_be_repaired": 1, "save_to_json": "TRUE",
                    "description": "Les toits brillants du 44ᵉ temple du pèlerinage de Shikoku, Daihō-ji (大宝寺), ont été effondrés, les piliers neufs taillés à la hache et l’autel a été renversé avec violence, répandant les offrandes et objets de culte. Les larmes des pèlerins mouillent les charbons encore chauds des murs de bois vert. Quels monstres ont bien pu s’en prendre à la beauté des lieux ? Certains pointent du doigt les chrétiens et l’Inquisition mais aucune preuve n’a été avancée."
                }
            }
        }'
    )
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- https://fr.wikipedia.org/wiki/P%C3%A8lerinage_de_Shikoku
INSERT INTO locations (name, description, discovery_diff, can_be_destroyed, zone_id, controller_id, activate_json) VALUES
    -- Le chemin de l'éveil (AWA)
    ('Dainichi-ji (大日寺) -- Le chemin de l’éveil', 
    'Niché entre les forêts brumeuses d’Iya, ce temple vibre encore du souffle ancien des premiers pas du pèlerin.
On dit que les pierres du sentier y murmurent des prières oubliées à ceux qui s’y attardent.
Le silence y est si pur qu’on entend le battement de son propre cœur.
(Pour explorer davantage ce lieu, allez voir un orga !)', 
    7, 1,
    (SELECT ID FROM zones WHERE name = 'Vallée d’Iya et d’Oboké d’Awa'),  
    (SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)'),
        '{ "update_location": {
            "name": "Ruines du Dainichi-ji (大日寺)", 
            "discovery_diff": 6, "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Enfoui sous les pins d’Iya, Danichi-ji n’est plus qu’un champ de ruines noircies, ses pavés brisés jonchés de cendres froides. Les pierres qui murmuraient jadis des prières ne portent plus que le silence sourd d’un sanctuaire où même l’écho du cœur semble s’être éteint.",
            "future_location": {
                "name": "Temple de Yashima-ji (屋島寺) -- Le chemin du Nirvana", "discovery_diff": 7,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Niché entre les forêts brumeuses d’Iya, ce temple montre encore les plaies fraîchement refermées d’une attaque récente. Le bois vert est par endroits assemblé avec des poutres anciennes encore en bon état et les nouvelles pierres taillées ont une couleur plus claire que les anciennes se souvenant des pèlerins passés. Lorsque l’on s’y attarde, le silence n’est brisé que par les craquements du bois neuf qui travaille en séchant et les battement de son propre cœur. (Pour explorer davantage ce lieu, allez voir un orga !)"
            }
        }}'
)

    -- Le chemin de l'ascèse (TOSA) 
    ,('Chikurin-ji (竹林寺) -- Le chemin de l’ascèse', 
    'Perché au sommet d’une colline surplombant la baie, le temple veille parmi les bambous.
Les moines y pratiquent une ascèse rigoureuse, veillant jour et nuit face à l’océan sans fin.
Le vent porte leurs chants jusqu’aux barques des pêcheurs, comme des prières salées.
(Pour explorer davantage ce lieu, allez voir un orga !)', 
    7, 1,
    (SELECT ID FROM zones WHERE name = 'Grande Baie de Kochi'),  
    (SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)'),
        '{ "update_location": {
            "name": "Ruines du Chikurin-ji (竹林寺)", 
            "discovery_diff": 6, "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Au sommet de la colline, Chikurin-ji n’est plus qu’un squelette calciné, ses bambous brûlés dressés comme des lances noires contre le ciel. Les prières se sont tues : il ne reste que le fracas du vent dans les cendres et l’écho d’un silence sans ferveur.",
            "future_location": {
                "name": "Temple de Chikurin-ji (竹林寺) -- Le chemin de l’ascèse", "discovery_diff": 7,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Une colline surplombant la baie laisse entendre des prières vivaces, semblant compenser une période de silence. Le temple Chikurin-ji a visiblement été remis en état il y a peu. Les pans de papier de riz sont plus blancs que l’écume et le parfum du cèdre embaume les lieux. Le ressac couvre les prières dans un rythme que seule la mer connaît. (Pour explorer davantage ce lieu, allez voir un orga !)"
            }
        }}'
    )

    -- Le chemin de l'illumination (Iyo) 
    ,('Ryūkō-ji (竜光寺) -- Le chemin de l’illumination', 
    'Suspendu à flanc de montagne, le Ryūkō-ji contemple la mer intérieure comme un dragon endormi.
On raconte qu’au lever du soleil, les brumes se déchirent et révèlent un éclat doré émanant de l’autel.
Les sages disent que ceux qui y méditent peuvent entrevoir la Lumière Véritable.
(Pour explorer davantage ce lieu, allez voir un orga !)', 
    7,  1,
    (SELECT ID FROM zones WHERE name = 'Côte Ouest d’Iyo'),  
    (SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)'),
        '{ "update_location": {
            "name": "Ruines du Ryūkō-ji (竜光寺)", 
            "discovery_diff": 6, "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Accroché à la montagne, le Ryūkō-ji n’est plus qu’un squelette calciné, ses poutres noires béant vers la mer comme les côtes d’un dragon mort. L’autel n’exhale plus de lumière : seulement des cendres froides que le vent disperse au fil des brumes.",
            "future_location": {
                "name": "Temple de Ryūkō-ji (竜光寺) -- Le chemin de l’illumination", "discovery_diff": 7,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Soutenu par des poutres fraîchement peintes, le Ryūkō-ji rénové contemple de nouveau la mer intérieure comme un dragon convalescent. On raconte qu’au lever du soleil, les brumes se déchirent et révèlent un éclat doré émanant de l’autel reconstruit. Les sages, revenus après s’être dispersés lors des attaques, disent que ceux qui y méditent peuvent entrevoir la Lumière Véritable. (Pour explorer davantage ce lieu, allez voir un orga !)"
            }
        }}'
    )

    -- Le chemin du Nirvana (Sanuki) 
    ,('Yashima-ji (屋島寺) -- Le chemin du Nirvana', 
    'Ancien bastion surplombant les flots, Yashima-ji garde la mémoire des batailles et des ermites. Les brumes de l’aube y voilent statues et stupas, comme pour dissimuler les mystères du Nirvana.
Certains pèlerins affirment y avoir senti l’oubli du monde descendre sur eux comme une paix.
(Pour explorer davantage ce lieu, allez voir un orga !)', 
    7,  1,
    (SELECT ID FROM zones WHERE name = 'Province de Sanuki'),  
    (SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)'),
        '{ "update_location": {
            "name": "Ruines du Yashima-ji (屋島寺)", 
            "discovery_diff": 6, "can_be_destroyed": 0, "can_be_repaired": 1,
            "description": "Perché sur son promontoire, Yashima-ji n’est plus qu’un squelette calciné, ses statues brisées gisant parmi les pierres éclatées. Là où régnaient jadis la paix et le silence, il ne reste que le vent hurlant à travers les stupas fendus, emportant les cendres d’un autel brisé.",
            "future_location": {
                "name": "Temple de Yashima-ji (屋島寺) -- Le chemin du Nirvana", "discovery_diff": 7,
                "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
                "description": "Le parfum du cèdre est la signature du travail des Miyadaikus (charpentiers spécialisés dans les temples et sanctuaires) qui ont rétabli la beauté du Yashima-ji. Les efforts des sculpteurs ont redressé les statues et retracé les chemins de pierre. Ce bastion rénové surplombe les flots, il reste le gardien de la mémoire des batailles et des ermites. Les pèlerins peuvent de nouveau y sentir l’oubli du monde. (Pour explorer davantage ce lieu, allez voir un orga !)"
            }
        }}'
    )
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
INSERT INTO locations (name, description, hidden_description, zone_id, controller_id, is_base, can_be_destroyed, can_be_repaired, discovery_diff, activate_json) VALUES
(
    'Forteresse des Samouraïs Ashikaga',
    'Nous avons localisé la forteresse du Shōgun, siège du clan Ashikaga (足利).
Il faudrait être insensé pour songer à renverser le Shōgun — mais prendre ce château serait une étape inévitable pour cela, et nul bastion n’est réellement imprenable.
Une telle attaque provoquerait sans doute des remous à la cour shogunale et ferait de nous des ennemis de la nation, au même titre que les Takeda.
Leur défense, toutefois, n’est pas encore totalement rétablie après la guerre.',
    'Nous avons intercepté des extraits des rapports personnels du Shōgun. Aucun des cinq Shugo ne paraît en mesure de lui prêter main-forte, et les deux Kanrei ne sont plus que des titres vides, occupés par des nobles sans influence.
À la cour, les conseillers murmurent que l’autorité du Shōgun ne repose plus que sur un équilibre fragile — celui des traditions qu’il incarne et des ambitions qui le cernent.',
    (SELECT ID FROM zones WHERE name = 'Cité Impériale de Kyoto'),
    (SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)'),
    1, 1, 0, 8,
    '{"update_location": {
        "name": "Forteresse des Samouraïs Ashikaga",
        "discovery_diff": 7, "can_be_destroyed": 0, "can_be_repaired": 0,
        "description": "Les murs de la forteresse du Shōgun portent encore les marques de l’assaut : portes arrachées, pavés noircis, bannières shogunales déchirées. Là où régnaient le protocole et la majesté de la tradition, il ne subsiste que le chaos — un affront direct à la lignée Ashikaga et à l’autorité même du bakufu. Dès lors, celui qui a osé porter la main sur le siège du Shōgun n’est plus simplement un rival : il est devenu l’ennemi public, un rebelle qu’il est désormais permis — voire attendu — d’abattre. Les samouraïs des provinces invoquent l’honneur pour brandir leurs lances, et les temples dénoncent la rupture des ordres établis. Le Japon tout entier retient son souffle, prêt à s’embraser.",
        "hidden_description": ""
    }}'
);

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
INSERT INTO artefacts (name, description, full_description, location_id) VALUES
    (
        'Fujitaka (藤孝) Hosokawa (細川) le Daimyō prisonnier',
        'Nous avons découvert que cet homme, que tous pensaient mort, est en réalité enfermé dans une geôle oubliée, gardée par ceux qui craignent son retour.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', (SELECT ID FROM locations WHERE name = 'Geôles impériales')
    ), (
        'Kunichika (国親) Chōsokabe (長宗我部) blessé, brisé, il vit toujours',
        'L’ancien seigneur de Shikoku n’est pas tombé à la guerre — il est retenu en otage, par ceux qui craignent son retour ou cherchent à exploiter sa valeur politique.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', (SELECT ID FROM locations WHERE name = 'Geôles des Kaizokushū')
    ), (
        'Chikayasu (親泰) Chōsokabe (長宗我部), le fils caché',
        '3ème fils de Kunichika, la charge de l’héritage lui a été épargnée jusqu’ici, mais il ferait un otage politique important.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', (SELECT ID FROM locations WHERE name = 'Retraite secrète des Chōsokabe')
    ), (
        'Shigemasa (重存) Sogō (十河), l’héritier du clan Sogo',
        'Fils de Kazumasa (一存) et de Kujo (九条) Sogo (十河), l’héritier du clan Sogo et détenteur du sang des Miyoshi est désormais un otage politique important.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', NULL
    ), (
        'Yoshioki (義興) Miyoshi (三好), l’enfant maudit',
        'Fils de Nagayoshi (長慶) Miyoshi (三好), l’héritier du clan Miyoshi est un enfant malade maintenu en vie par les potions et les prières. Il n’en reste pas moins un otage politique important aux yeux de son père.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', NULL
    ), (
        'Luís Fróis, prêtre jésuite portugais et érudit des mœurs japonaises.',
        'Cet homme de foi pacifiste pourrait faire un pion de valeur à échanger auprès de certaines personnes.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', (SELECT ID FROM locations WHERE name = 'Sanctuaire clandestin du Port de Tokushima')
    )
    
;
