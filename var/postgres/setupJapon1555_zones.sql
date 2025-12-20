
INSERT INTO {prefix}zones (name, description) VALUES
      ('Côte Ouest d’Iyo', 'La porte vers l’île de Kyūshū, cette bande littorale est animée par les flux incessants de navires marchands, pêcheurs et patrouilleurs. Les criques cachent parfois des comptoirs discrets ou des avant-postes de contrebandiers. Les brumes marines y sont fréquentes, rendant les approches aussi incertaines que les intentions de ses habitants.')
    , ('Montagnes d’Iyo', 'Entourant le redouté mont Ishizuchi, plus haut sommet de Shikoku, ces montagnes sacrées sont le domaine des ascètes, des yamabushi et des esprits anciens. Les chemins escarpés sont peuplés de temples isolés, de cascades énigmatiques, et d’histoires transmises à demi-mot. Nul ne traverse ces hauteurs sans y laisser un peu de son âme.')
    , ('Cap sud de Tosa', 'Battue par les vents de l’océan Pacifique, cette pointe rocheuse est riche en minerai de fer, extrait dans la sueur et le sel. Le paysage austère dissuade les faibles, mais attire les clans ambitieux. Les tempêtes y sont violentes, et même les dragons du ciel semblent redouter ses falaises noires.')
    , ('Grande Baie de Kochi', 'Centre de pouvoir du clan Chōsokabe, cette baie est à la fois un havre de paix et un verrou stratégique. Bordée de rizières fertiles et de ports animés, elle est défendue par des flottes aguerries et des forteresses discrètes. On dit que ses eaux reflètent les ambitions de ceux qui la contrôlent.')
    , ('Vallée d’Iya et d’Oboké d’Awa', 'Ces vallées profondes, creusées par les torrents et le temps, abritent des plantations de thé précieuses et des villages suspendus au flanc des falaises. Peu accessibles, elles sont le refuge de ceux qui fuient la guerre, la loi ou le destin. Le thé qui y pousse a le goût amer des secrets oubliés.')
    , ('Côte Est d’Awa', 'Sur cette façade tournée vers le large, le clan Miyoshi établit son pouvoir entre les ports et les postes fortifiés. Bien que prospère, la région est sous tension : les vassaux y sont fiers, les ambitions grandes, et les flottes ennemies jamais loin. La mer y apporte autant de trésors que de périls.')
    , ('Province de Sanuki', 'Plaine fertile dominée par les haras impériaux et les sanctuaires oubliés, Sanuki est renommée pour ses chevaux rapides et robustes. Les émissaires s’y rendent pour négocier montures de guerre, messagers ou montures sacrées. C’est aussi une terre de festivals éclatants et de compétitions féroces.')
    , ('Ile d’Awaji', 'Pont vivant entre Shikoku et Honshū, Awaji est stratégiquement vitale et toujours convoitée. Les vents y sont brutaux, les détroits traîtres, et les seigneurs prudents. Ses collines cachent des fortins, ses criques des repaires, et ses chemins sont surveillés par des yeux invisibles.')
    , ('Ile de Shōdoshima', 'Ile montagneuse et sauvage, jadis sanctuaire, aujourd’hui repaire des pirates Wako. Ses ports semblent paisibles, mais ses criques abritent des embarcations rapides prêtes à fondre sur les convois marchands. Les autorités ferment souvent les yeux, car même le vice paie tribut.')
;

INSERT INTO {prefix}zones (hide_turn_zero, name, description) VALUES
    (TRUE, 'Plaines du Kansai', 'Étendue fertile au cœur du Japon, les Plaines du Kansai sont bordées par les cités animées d’Osaka, les sanctuaires anciens de Nara, et les ports marchands de Kobe. Sous la surface prospère de ses rizières, le sang versé à Kyōto imprègne encore la terre, témoignant des intrigues et batailles passées. Ici, le commerce rivalise avec les complots, et les vents chargés de cendres et de parfums annoncent toujours un nouvel orage de pouvoir.')
    , (TRUE, 'Cité Impériale de Kyoto', 'Capitale impériale, centre des arts, des lettres et des poisons subtils. Ses palais cachent les plus anciennes lignées, ses ruelles, les complots les plus jeunes. Kyōto ne brandit pas l’épée, mais ceux qui y règnent peuvent faire plier des provinces entières par un sourire ou un silence.')
;

-- Controle des zones au départ
UPDATE {prefix}zones SET
    claimer_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Chōsokabe (長宗我部)'),
    holder_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Chōsokabe (長宗我部)')
    WHERE name IN( 'Grande Baie de Kochi', 'Cap sud de Tosa' ) ;
UPDATE {prefix}zones SET
    claimer_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Miyoshi (三好)'),
    holder_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Miyoshi (三好)')
    WHERE name = 'Côte Est d’Awa';
UPDATE {prefix}zones SET
    claimer_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Hosokawa (細川)'),
    holder_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Hosokawa (細川)')
    WHERE name = 'Province de Sanuki';
UPDATE {prefix}zones SET
    claimer_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Wako (和光)'),
    holder_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Wako (和光)')
    WHERE name = 'Ile de Shōdoshima';
UPDATE {prefix}zones SET
    claimer_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)'),
    holder_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)')
    WHERE name = 'Montagnes d’Iyo';
UPDATE {prefix}zones SET
    claimer_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Ashikaga (足利)'),
    holder_controller_id = (SELECT ID FROM {prefix}controllers WHERE lastname = 'Ashikaga (足利)')
    WHERE name IN( 'Cité Impériale de Kyoto', 'Plaines du Kansai' );

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- Secrets scénario
INSERT INTO {prefix}locations (name, discovery_diff, zone_id, controller_id, description) Values
    -- Ajouter un secret sur l'arrivée des rebels Ikko-ikki sur l'ile par petits groupes
    ('Plaine d’Uwajima', 8, (SELECT ID FROM {prefix}zones WHERE name = 'Côte Ouest d’Iyo'), (SELECT ID FROM {prefix}controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
        , 'Les vastes plaines d’Uwajima semblent paisibles sous le soleil, entre cultures clairsemées et sentiers oubliés.
Mais depuis plusieurs semaines, des groupes d’hommes en haillons, armés de fourches, de bâtons ou de sabres grossiers, y ont été aperçus.
Ces paysans ne sont pas d’ici : ils avancent discrètement, se regroupent à la tombée du jour, et prêchent un discours de révolte contre les samouraïs.
Ce sont les avant-gardes des Ikko-ikki, infiltrés depuis le continent par voie maritime.
Découvrir quel est le chef qui les unit pourrait permettre d’agir avant qu’il ne soit trop tard.'
    )
    -- Ajouter un secret sur l'arrivée de Rennyo déposée par les Kaizokushū Wako il y a quelques semaines à peinne
    , ('Port de Saijō', 8, (SELECT ID FROM {prefix}zones WHERE name = 'Montagnes d’Iyo'), (SELECT ID FROM {prefix}controllers WHERE lastname = 'Wako (和光)')
        , 'Le port de Saijō est d’ordinaire animé par les pêcheurs locaux et les petits marchands.
Mais depuis peu, les anciens disent avoir vu, au crépuscule, un navire étrange accoster sans bannière, escorté par des pirates tatoués.
Un moine en est descendu, maigre, vieux, au regard brûlant de ferveur : Rennyo lui-même, leader spirituel des Ikko-ikki.
Selon certains, il se serait enfoncé dans les Montagnes d’Iyo avec une poignée de fidèles.
Ce secret, s’il venait à être révélé, pourrait changer l’équilibre religieux de toute l’île.'
    )
    -- Ajouter un secret sur l’alliance maritale entre les Chosokabe Motochika et les Hosokawa Tama
    , ('Relais de poste d’Ikeda', 8, (SELECT ID FROM {prefix}zones WHERE name = 'Province de Sanuki'), NULL
        , 'Une auberge modeste, près de la grande route de Sanuki, reçoit parfois à l’aube des cavaliers fatigués, porteurs de missives cachetées.
L’une d’elles, récemment interceptée, contenait une promesse de mariage scellée entre Motochika Chōsokabe et Tama Hosokawa, fille de Fujitaka.
Si elle venait à se concrétiser, cette alliance unirait deux grandes maisons sur Shikoku et bouleverserait les rapports de pouvoir dans toute la région.
Pour l’instant, l’information est gardée secrète, mais les rumeurs montent.
Si vous avez entendu parler d’une rumeur sortant de la maison close de Marugame, ce n’est qu’une version déformée de cette vérité.'
    )
    -- Ajouter un secret sur l'ile d'awaji à propos de la bataille de Kunichika contre les Ikko-ikki
    , ('Sanctuaire des blessés de guerre', 9, (SELECT ID FROM {prefix}zones WHERE name = 'Ile d’Awaji'), NULL,
  'Dans les colines embrumées de l’ile, un ancien pavillon de thé, reconstruit après les guerres, sert de refuge à d’anciens samouraïs et émissaires de passage. 
Ces hommes portent encore les cicatrices des campagnes sanglantes de Kyōto, mais surtout, ils détiennent un autre récit : Kunichika Chōsokabe n’a pas fui par lâcheté lors de la bataille des plaines de Kyōto. 
Son armée fut encerclée par une manœuvre habile des Ikko-ikki alliés aux Takeda. 
Les chroniques officielles ont effacé cette vérité, étouffée par les rivaux du clan et la honte des survivants. 
Si ces récits étaient révélés, l’honneur du clan Chōsokabe pourrait être restauré.')
    -- Ajouter un secret sur Shōdoshima à propos de la fuite des forces de Fujitaka face a l'avant garde Takedas alliées aux Ikko-ikki. Permettant de lever la rumeur sur sa couardise et de confirmé sa capture par un général du Shogun
    , ('Camp de deserteurs', 8, (SELECT ID FROM {prefix}zones WHERE name = 'Ile de Shōdoshima'), NULL
        , 'Dans une gorge dissimulée parmi les pins tordus de Shōdoshima, quelques hommes efflanqués vivent en silence, fuyant le regard des pêcheurs et des samouraïs.
Ce sont des survivants de la déroute des plaines de Kyoto, dont ils racontent une version bien différente de celle propagée à la cour : l’avant-garde des Chōsokabe, commandée par Fujitaka Hosokawa, se serait retrouvée face aux fanatiques Ikko-ikki, qui auraient écrasé ses lignes avant même que l’ordre de retraite ne puisse être donné.
Fujitaka, séparé de la force principale, aurait fui précipitamment vers Kyoto, mais aurait été aperçu capturé par un général des forces du shogun Ashikaga. Ces aveux, étouffés sous le fracas des récits officiels, pourraient bien réhabiliter l’honneur du daimyō déchu — ou bouleverser les équilibres fragiles entre les clans.'
    )
    -- Ajouter un secret sur Kyoto a propos de l’inimitié du Shogun contre les Chosokabe suit a sa débandade et fuite honteuse devant l'armée des takedas.
    , ('Cour impériale', 7, (SELECT ID FROM {prefix}zones WHERE name = 'Cité Impériale de Kyoto'), NULL
        , 'Au sein des couloirs feutrés de la cour impériale, on ne parle plus qu’à demi-mot des récents affrontements.
Le nom des Chōsokabe y est devenu tabou, soufflé avec mépris : leur armée, jadis fière, aurait fui sans gloire devant l’avant-garde Takeda.
Le Shogun Ashikaga, humilié par leur débâcle, aurait juré de ne plus leur accorder confiance ni territoire.
Ce ressentiment pourrait être exploité — ou au contraire, désamorcé — selon les preuves et récits qu’on parvient à faire émerger de l’ombre.'
    )
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- Secret des joueurs
INSERT INTO {prefix}locations (name, description, discovery_diff, can_be_destroyed, zone_id, controller_id) VALUES
    -- Temple de Nara archive de Shōzan, qui est secretement Yoshiaki le frère du Shogun
    (
        'Temple de Nara-ji',
        'Dans les collines boisées entourant l’ancienne capitale de Nara, un vieux temple isolé abrite au milieu des statues de bouddha, des multiples ouvrages bouddhiste et Shinto et surtout des registres des moines tendai.
L’un de ces ouvrages relate les entrées au temple. Dont l’entrée de moine Shōzan, qui est en réalité Yoshiaki(義昭), frère cadet du Shogun Ashikaga, celui-ci a choisi la vie monastique pour échapper aux intrigues de la cour.
Nous pourrions tenter de le convaincre de revenir à la vie politique, en lui promettant un soutien militaire et financier et faire un Shogun fantoche.
Nous n’avons pas pu consulter la totalité des registres, il nous faudrait y retourner, pour en apprendre plus sur les entrées au temple des moines de Shikoku.',
        6, False,
        (SELECT ID FROM {prefix}zones WHERE name = 'Plaines du Kansai'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Ashikaga (足利)')
    ),
    -- Temple de Nara archives de Kukai, qui était en réalité un Chosokabe
    (
        'Temple de Nara-ji - archives secrètes',
        'Dans les collines boisées entourant l’ancienne capitale de Nara, nous avons pu accéder aux archives secrètes du vieux temple isolé, abrité au milieu des statues de Bouddha.
Ce temple sert de sanctuaire à des moines, qui sont les archivistes de la secte Tendai.
Nous avons pu consulter les registres, dont celui sur les entrées au temple des moines de Shikoku.
Nous avons découvert que le véritable nom de Kūkai (空海) Kōbō-Daishi (弘法大師) — Kūkai le Grand Instructeur, était Makoto Sakana (眞魚) — Mao le « Poisson de vérité ».
Il est le troisième fils de Katsushika(葛飾) Chōsokabe (長宗我部), le conquérant de Shikoku, ce qui en fait le grand-oncle de Motochika (元親) Chōsokabe et le frère de Kanetsugu (兼続) Chōsokabe.',
        7, False,
        (SELECT ID FROM {prefix}zones WHERE name = 'Plaines du Kansai'),
        NULL
    ),
    -- Temple du Jodo-Shinshu de Kobe, les bléssée de la bataille de Kyoto
    (
        'Temple de Kobe',
        'Dans les ruelles calmes de la petite ville de Kobe, un temple discret des Jōdo-shinshū abrite un attroupement étrange.
Sous le regard bienveillant d’une statue du Bouddha Amida, des hommes et des femmes, vêtus de haillons, d’armures Ashigaru (paysannes) et de tenus de moines-soldats  du Jōdo-shinshū, se soignent mutuellement.
Ils portent les cicatrices des batailles récentes, et certains sont gravement blessés.
Ils racontent qu’ils sont des survivants de la déroute des plaines de Kyōto du printemps 1555, mais sans préciser pour quel camp.
Il nous faut gagner leur confiance, et enquêter sur leur identité et leurs intentions.',
        6, False,
        (SELECT ID FROM {prefix}zones WHERE name = 'Plaines du Kansai'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
    ),
    -- Temple du Jodo-Shinshu de Kobe, les archives secrètes 
    (
        'Temple des Jōdo-shinshū de Kobe',
        'Dans les ruelles calmes de la petite ville de Kobe, un temple discret des Jōdo-shinshū abrite un attroupement étrange de blessés de guerre.
Nous avons pu nous infiltrer parmis leur rangs de fanatiques et découvrir que les forces Jōdo-shinshū sous la bannière des Ikko-ikki ont bien affronter les troupes Chōsokabe (長宗我部) aux cotées des Takeda (武田) lors de la bataille des plaines de Kyōto au printemps 1555.
Ayant gagné leur confiance nous avons pu accéder aux archives locales de l’arbre familial de Rennyo (蓮如) huitième abbé du mouvement.
Son fils adoptifs Ren-jō (連城) n’est autre que Harumoto (晴元) Hosokawa (細川), un allié des Takeda et un ennemi du Shogun exilé il y a 5 ans après sa défaite et désormais devenu moine.',
        8, False,
        (SELECT ID FROM {prefix}zones WHERE name = 'Plaines du Kansai'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
    ),
    -- Temple du pelerinage de Shikoku de la Secte Tendai
    (
        'Ishizuchi-jinja (石鎚神社), le 64ᵉ temple du pèlerinage',
        'Situé au pied du mont Ishizuchi, le plus haut sommet de Shikoku, ce temple est le 64ᵉ des 88 temples du pèlerinage de Shikoku.
Il est l’une des résidences de Kūkai (空海) Kōbō-Daishi (弘法大師) — Kūkai le Grand Instructeur, fondateur de la secte bouddhiste Shingon, et de Yūbien (宥辡 ‘Yū biàn‘) Shinnen (真念) – apaisement sincère, moine érudit qui a compilé le guide le plus complet du pèlerinage.
Nous avons découvert dans les archives du temple qu’avant de prendre le nom de Yūbien, le jeune homme venu se faire moine se nommait Michinao (通直) Kōno (河野).',
        7,  False,
        (SELECT ID FROM {prefix}zones WHERE name = 'Montagnes d’Iyo'),  
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)')
    ),
    -- Ōyamazumi-jinja (大山祇神社) -- sanctuaire shinto
    (
        'Ōyamazumi-jinja (大山祇神社) -- sanctuaire shinto', 
        'Ce sanctuaire, situé sur l’île d’Ōmishima dans la mer intérieure de Seto, au nord de Saijō, fait partie des vingt temples mineurs du pèlerinage de Shikoku.
Sanctuaire ancestral du clan Kōno (河野), descendants d’Iyo-shinnō, fils de l’empereur Kanmu (781–806), qui fonda la province d’Iyo sur l’île de Shikoku.
Le sanctuaire est dédié aux dieux qui protègent les marins et les soldats. Pour cette raison, de nombreux daimyōs viennent y faire des offrandes dans l’espoir de succès, ou en remerciement de leurs victoires.
Les derniers membres du clan Kōno (河野) s’y retrouvent parfois pour parler, à voix basse et triste, de leur héritage disparu et de leurs terres confisquées.
(Pour explorer davantage ce lieu, allez voir un orga !)',
        7, False,
        (SELECT ID FROM {prefix}zones WHERE name = 'Côte Ouest d’Iyo'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Kōno (河野)')
    )
;

-- Lieux secrets
INSERT INTO {prefix}locations (name, description, discovery_diff, can_be_destroyed, zone_id, controller_id, activate_json) VALUES
    -- Geôles impériales de Kyoto
    (
        'Geôles impériales',
        'Sous les fondations de la Cité impériale, ces geôles étouffantes résonnent des cris affaiblis des oubliés du Shogun. 
L’air y est moite, chargé de remords et d’encre séchée — là où les sentences furent calligraphiées avant d’être exécutées.
Peu en ressortent, et ceux qui le font ne parlent plus.',
        8, True,
        (SELECT ID FROM {prefix}zones WHERE name = 'Cité Impériale de Kyoto'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Ashikaga (足利)'),
        '{"indestructible" : "TRUE"}'
    ),

    -- Geôles des pirates (Shōdoshima)
    (
        'Geôles des Kaizokushū', 
        'Creusées dans la falaise même, ces cavernes humides servent de prison aux captifs des Wako. 
Des chaînes rouillées pendent aux murs, et l’eau salée suinte sans cesse, rongeant la volonté des enfermés. 
Le silence n’y est troublé que par les pas des geôliers — ou les rires des pirates.',
        8, True,
        (SELECT ID FROM {prefix}zones WHERE name = 'Ile de Shōdoshima'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Wako (和光)'),
        '{"update_location": {"name": "Geôles en ruines", "discovery_diff": 5, "save_to_json": "TRUE",
        "can_be_destroyed": 0, "can_be_repaired": 1,
        "description": "Creusées dans la falaise même, ces cavernes humides servaient de refuge aux pirates Wako, mais tout ce qui avait une valeur a été pillé."
        }}'
    ),

    -- Retraite secrete des Chosokabe (cape sud de Tosa)
    (
        'Retraite secrète des Chōsokabe', 
        'Caché sur les flancs escarpés du cap sud de Kōchi, un pavillon de chasse sert de lieu de villégiature à une étrange concentration de serviteurs Chōsokabe.
On y trouve des armes et des provisions, tout le nécessaire pour qu’un membre de la famille puisse s’y cacher.',
        8, True,
        (SELECT ID FROM {prefix}zones WHERE name = 'Cap sud de Tosa'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Chōsokabe (長宗我部)'),
        '{"update_location": {"name": "Ruines d’un pavillon de chasse", "discovery_diff": 7,
        "can_be_destroyed": 0, "can_be_repaired": 1,
        "description": "Caché sur les flancs escarpés du cap sud de Kōchi, se tien les ruines d’un pavillon de chasse. Une fouille sommaire montre quelque objects apparentant aux Chōsokabe. Ce lieu semble avoir été le théatre de combats récents, il n’y a plus personne pour en raconter l’histoire.",
        "future_location": {"name": "Retraite secrète des Chōsokabe", "discovery_diff": 8,
        "can_be_destroyed": 1, "can_be_repaired": 0, "save_to_json": "TRUE",
        "description": "Perché sur les flancs escarpés du cap sud de Tosa, le pavillon de chasse des Chōsokabe a été reconstruit avec soin et élégance. Les murs de bois tiédissent au soleil, les provisions et les armes sont à nouveau rangées avec ordre. La retraite offre un refuge paisible, mêlant confort discret et préparation guerrière, fidèle à l’esprit vigilant du clan."
        }
        }}'
    )
    -- Ajouter un secret sur la présence du christianisme et du pretre Luís Fróis Japonologue et Jésuite -- https://fr.wikipedia.org/wiki/Lu%C3%ADs_Fr%C3%B3is 
    , (
        'Sanctuaire clandestin du Port de Tokushima',
        'Dans les ruelles du port de Tokushima, à l’écart des marchés, une maison basse aux volets clos abrite un hôte peu commun : Luís Fróis, prêtre jésuite portugais et érudit des mœurs japonaises.
Il y aurait établi un sanctuaire clandestin, enseignant les paroles du Christ à quelques convertis du clan Miyoshi.
Ce lieu sert également de relais discret pour faire entrer armes, livres et messagers depuis Nagasaki.
Sa présence confirme l’implantation secrète du christianisme à Tokushima et menace de faire basculer les équilibres religieux et politiques de Shikoku.'
        , 8, False,
        (SELECT ID FROM {prefix}zones WHERE name = 'Côte Est d’Awa'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Miyoshi (三好)'),
        '{}'
    )
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- Temples des Yokais
INSERT INTO {prefix}locations (name, discovery_diff, can_be_destroyed, zone_id, controller_id, description, activate_json) VALUES
     -- Feu - Teppō
    ('Vieux temple des colines de Kubokawa', 8, True, (SELECT ID FROM {prefix}zones WHERE name = 'Cap sud de Tosa'),  (SELECT ID FROM {prefix}controllers WHERE lastname = 'Shikoku (四国)'),
        'Accroché aux flancs escarpés de la côte sud de Kōchi, un petit sanctuaire noircit repose au bord d’une ancienne veine de fer oubliée.
Au loin, dans la vallée, les marteaux des forgerons résonnent comme une prière sourde.
Mais chaque nuit, une odeur de poudre flotte dans l’air, et un claquement sec — sec comme un tir — fait sursauter les corbeaux.
(Pour explorer davantage ce lieu, allez voir un orga !)',
    '{}'
    )
    -- Vent - Tessen
    , ('Vieux temple de la falaise d’Esaki', 8, True, (SELECT ID FROM {prefix}zones WHERE name = 'Ile d’Awaji'),  (SELECT ID FROM {prefix}controllers WHERE lastname = 'Shikoku (四国)'),
        'Perché au sommet d’une falaise d’Awaji, un petit pavillon de bois battu par les vents se dresse, fragile et silencieux.
La porte ne ferme plus, et le papier des lanternes s’effiloche. Pourtant, nul grain de poussière ne s’y pose.
Lorsque l’on entre, l’air se fait soudain glacé, et un bruissement court dans les chevrons — comme si un éventail invisible fendait l’air avec colère.
(Pour explorer davantage ce lieu, allez voir un orga !)',
    '{}'
    )
     -- Paresse - Biwa
    , ('Vieux temple du vallon de Tengu-Iwa', 8, True, (SELECT ID FROM {prefix}zones WHERE name = 'Ile de Shōdoshima'),  (SELECT ID FROM {prefix}controllers WHERE lastname = 'Shikoku (四国)'),
        'Ce temple oublié, dissimulé dans un vallon brumeux de Shōdoshima, semble abandonné depuis des décennies.
Pourtant, chaque crépuscule, les accords las d’un biwa résonnent sous les poutres vermoulues, portés par une brise douce où flotte un parfum de saké tiède.
Pourtant nul prêtre et nul pèlerin en vue.
(Pour explorer davantage ce lieu, allez voir un orga !)',
    '{}'
    )
     -- Roche - Chigiriki
    , ('Vieux temple du Mont Ishizuchi', 8, True, (SELECT ID FROM {prefix}zones WHERE name = 'Montagnes d’Iyo'),  (SELECT ID FROM {prefix}controllers WHERE lastname = 'Shikoku (四国)'),
        'Perché sur un piton rocheux des montagnes d’Ehimé, un ancien temple taillé à même la pierre repose, figé comme un souvenir.
Nul vent n’y souffle, nul oiseau n’y niche.
Parfois, on y entend cliqueter une chaîne sur la pierre nue, comme si une arme traînait seule sur le sol.
(Pour explorer davantage ce lieu, allez voir un orga !)',
    '{}'
    )
;

-- Ressources
INSERT INTO {prefix}locations (name, description, discovery_diff, zone_id) VALUES
    -- Thé d’Oboké
    ('Vallée fertile d’Iya', 
    'Dans la vallée profonde d’Iya, où le bruit de la rivière est permanent, poussent à flanc de roche de rares théiers.
Leurs feuilles, amères et puissantes, sont cueillies à la main par les familles montagnardes, suspendues au-dessus du grondement des eaux.
Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare, sinon nous pouvons toujours tenter de négocier avec le clan qui contrôle ce territoire.
    '
    , 6, (SELECT ID FROM {prefix}zones WHERE name = 'Vallée d’Iya et d’Oboké d’Awa')
    ),

    -- Armure en fer de Tosa
    ('Mine de fer de Kubokawa',
    'Dans les profondeurs du cap sud de Tosa, des veines de fer noir sont extraites à la force des bras puis forgées en cuirasses robustes dans les forges voisines.
Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare, sinon nous pouvons toujours tenter de négocier avec le clan qui contrôle ce territoire.',
    6, (SELECT ID FROM {prefix}zones WHERE name = 'Cap sud de Tosa')),

    -- Cheval de Sanuki
    ('Écuries de Takamastu',
    'Les vastes pâturages de Sanuki forment l’écrin idéal pour l’élevage de chevaux endurants, prisés tant pour la guerre que pour les grandes caravanes.
Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare, sinon nous pouvons toujours tenter de négocier avec le clan qui contrôle ce territoire.
    ',
    6, (SELECT ID FROM {prefix}zones WHERE name = 'Province de Sanuki')),

    -- Encens coréen
    ('Port marchand de Matsuyama',
    'Des voiliers venus de la péninsule coréenne accostent à Matsuyama, chargés de résines rares dont les parfums servent aux temples autant qu’aux intrigues.
Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare, sinon nous pouvons toujours tenter de négocier avec le clan qui contrôle ce territoire.',
    6, (SELECT ID FROM {prefix}zones WHERE name = 'Côte Ouest d’Iyo'));

-- Fluff
INSERT INTO {prefix}locations (name, description, discovery_diff, zone_id) VALUES
    ('Port d’Uwajima',
     'Un port animé aux quais denses et bruyants, où s’échangent riz, bois, et rumeurs en provenance de Kyūshū comme de Corée.
Les marins disent que la brume y reste plus longtemps qu’ailleurs.',
     6, (SELECT ID FROM {prefix}zones WHERE name = 'Côte Ouest d’Iyo')),

    ('Mt Ishizuchi',
     'Plus haut sommet de l’île, le mont Ishizuchi domine les vallées alentour comme un sabre dressé vers le ciel.
On dit qu’un pèlerinage ancien y conduit à une dalle sacrée où les esprits s’expriment lorsque les vents tournent.',
     6, (SELECT ID FROM {prefix}zones WHERE name = 'Montagnes d’Iyo')),

    ('Port de Kochi',
     'Protégé par une anse naturelle, ce port militaire et marchand voit passer jonques, bateaux de guerre et pirates repenti.
Son arsenal est surveillé nuit et jour par des ashigaru au Mon des 7 fleurs.
On dit que le clan Chōsokabe y cache des objets illégaux importés d’ailleurs.',
     6, (SELECT ID FROM {prefix}zones WHERE name = 'Grande Baie de Kochi')),

    ('Village d’Oboke',
     'Petit village de montagne aux maisons de bois noircies par le temps.
Les voyageurs s’y arrêtent pour goûter un saké réputé, brassé à l’eau des gorges profondes qui serpentent en contrebas.',
     6, (SELECT ID FROM {prefix}zones WHERE name = 'Vallée d’Iya et d’Oboké d’Awa')),

    ('Port de Naruto',
     'Carrefour maritime entre Honshū et Shikoku, le port de Naruto bruisse de dialectes et de voiles étrangères.
Dans les ruelles proches du marché, on parle parfois espagnol, ou latin, à voix basse.',
     6, (SELECT ID FROM {prefix}zones WHERE name = 'Côte Est d’Awa')),

    ('Grande route et relais de poste',
     'Relie Tokushima à Kōchi en serpentant à travers les plaines fertiles du nord.
À chaque relais, les montures peuvent être changées, et les messagers impériaux y trouvent toujours une couche et un bol chaud.',
     6, (SELECT ID FROM {prefix}zones WHERE name = 'Province de Sanuki')),

    ('Rumeurs de la bataille',
     'Les pêcheurs d’Awaji parlent encore d’un combat féroce dans les collines du Kansai sur Honshu, entre troupes en fuite et rebelles aux visages peints. Certains affirment avoir vu le ciel s’embraser au-dessus du phare abandonné d’Esaki.',
     6, (SELECT ID FROM {prefix}zones WHERE name = 'Ile d’Awaji')),

    ('Détroit d’Okayama',
     'Étroit et venteux, ce détroit aux eaux traîtresses sépare Shikoku de Honshū.
Difficile de tenter cette traversée sans être épié par les habitants de l’île de Shōdoshima.
Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d’être intercepté par les Kaizokushū.',
     6, (SELECT ID FROM {prefix}zones WHERE name = 'Ile de Shōdoshima')),

    ('Suzaku Mon',
     'Grande artère pavée de la capitale impériale, menant tout droit au palais. Sous ses tuiles rouges, l’ombre des complots se mêle aux parfums de thé, et les bannières flottent dans un silence cérémoniel.',
     6, (SELECT ID FROM {prefix}zones WHERE name = 'Cité Impériale de Kyoto'))
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- Fake News :
INSERT INTO {prefix}locations (name, discovery_diff, zone_id, description) VALUES
    ('Maison close de Marugame', 5,  (SELECT ID FROM {prefix}zones WHERE name = 'Province de Sanuki'),
        'À Marugame, dans une maison close réputée pour son saké sucré et ses éventails peints à la main, des courtisanes murmurent entre deux chansons.
L’une d’elles prétend avoir lu une lettre scellée, confiée par un émissaire enivré, annonçant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre éclair contre les Chōsokabe.'
    ),
    ('Crique de Tonoshō', 5, (SELECT ID FROM {prefix}zones WHERE name = 'Ile de Shōdoshima'),
        'Cette crique isolée, souvent balayée par les vents, est connue des contrebandiers comme des pêcheurs.
Depuis quelques jours, un bruit court : un important émissaire impérial aurait été intercepté par les pirates Wako et détenu dans une grotte voisine, en attendant rançon ou silence.'
    ),
    ('Temple Daihō-ji (大宝寺)', 5, (SELECT ID FROM {prefix}zones WHERE name = 'Montagnes d’Iyo'),
        'Dans un ancien sanctuaire shintō, aux abords du Daihō-ji (大宝寺) 44eme temple du pèlerinage de Shikoku, dont les piliers carbonisés résistent au temps, des pèlerins affirment avoir vu un artefact étrange caché sous l’autel — une croix d’argent sertie d’inscriptions latines.
Les paysans parlent d’un prêtre chrétien, et de l’Inquisition jésuite elle-même. Mais les recherches menées par les yamabushi locaux n’ont rien révélé de probant.'
    ),
    ('Maison de thé "Lune d’Or"', 5, (SELECT ID FROM {prefix}zones WHERE name = 'Cité Impériale de Kyoto'),
     -- Un dementi est présent sur l'info 'Camp des éclaireur Takeda'
        'Située à l’écart de la Suzaku Mon, la "Lune d’Or" attire les lettrés, les poètes… et les oreilles curieuses.
On dit qu’un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.
Selon une geisha, il serait en réalité un espion du clan Takeda (武田), infiltré pour sonder la loyauté des daimyōs de l’Est.
Il aurait même été vu avec un membre de la famille Chōsokabe (長宗我部).
Pourtant, nul ne peut confirmer cette histoire, et certains prétendent qu’il n’est en réalité qu’un veuf mélancolique, égaré dans ses souvenirs.
Mais à Kyōto, les apparences mentent plus souvent qu’elles ne révèlent.'
    ),
    ('Phare abandonné d`Esaki', 5,
    -- Un démenti est existant sur le 'vieux temple d'Esaki', il est possible de le trouver dans les rumeurs
        (SELECT ID FROM {prefix}zones WHERE name = 'Ile d’Awaji'),
        'Disséminé au bout d’une presqu’île battue par les vents, le vieux phare d’Esaki n’est plus qu’un squelette de pierre rongé par le sel.
Pourtant, certains pêcheurs affirment y voir passer des silhouettes armées à la tombée de la nuit.
La rumeur court qu’un prisonnier de valeur y est gardé en secret par le clan Chōsokabe, un traître capturé lors des affrontements récents.'
    ),
    ('Sanctuaire brisé de Nahari', 5,
    -- Un démenti est existant sur la 'congrégation du calvaire de Nahari', il est possible de le trouver dans les rumeurs
    (SELECT ID FROM {prefix}zones WHERE name = 'Grande Baie de Kochi'),
        'Surplombant la mer, les ruines du sanctuaire de Nahari sont battues par les embruns.
On dit que des prêtres étrangers y ont été aperçus de nuit, en compagnie d’émissaires du clan Chōsokabe.
La rumeur parle d’un pacte impie : en échange d’armes à feu venues de Nagasaki, le clan accepterait d’abriter des convertis clandestins.'
    ),
    ('La congrégation du Calvaire de Nahari', 7,
        -- démenti du sanctuaire brisé de Nahari
        (SELECT ID FROM {prefix}zones WHERE name = 'Grande Baie de Kochi'),
        'Surplombant la mer, les ruines du sanctuaire bouddhiste de Nahari, battues par les embruns, abritaient un calvaire chrétien.
Le prêtre chrétien en a été chassé par les moines bouddhistes de la secte Tendai.
Mais aucune arme à feu n’a été aperçue, et les moines affirment que les rumeurs de pacte avec les chrétiens sont infondées.'
    ),
    ('Comptoir d`Hiwasa', 5,
    --  démenti par la rumeur du vaisseau noir au comptoir d'Hiwasa
    (SELECT ID FROM {prefix}zones WHERE name = 'Côte Est d’Awa'),
        'Ce modeste comptoir marchand, adossé à une crique discrète, connaît une activité étrange depuis quelques semaines.
Des jonques aux voiles noires y accostent en silence, et leurs capitaines refusent de dire d’où ils viennent.
Certains affirment que les Wako auraient reçu des fonds d’un clan du Nord — peut-être les Hosokawa — pour saboter les entrepôts du port de Tokushima.
D’autres n’y voient qu’un simple commerce de sel et de fer… Mais alors, pourquoi tant de discrétion ? Et pourquoi autant de lames prêtes à jaillir à la moindre question ?'
    ),
    ('Un vaisseau noir au Comptoir d`Hiwasa', 8,
    -- démenti de la rumeur du comptoir d'Hiwasa
    (SELECT ID FROM {prefix}zones WHERE name = 'Côte Est d’Awa'),
        'Ce modeste comptoir marchand, adossé à une crique discrète, a été le théâtre de la plus étrange des scènes.
Les rapports précédents faisant état de jonques aux voiles noires, de capitaines Kaizokushū œuvrant à saboter les entrepôts du port de Tokushima, sont entièrement faux.
En réalité, nous avons découvert qu’un vaisseau noir, immense, aux voiles carrées, a été aperçu au large, et les marins affirment qu’il s’agit d’un navire de guerre portugais.
Le commerce, mené dans un secret relatif, consiste en un échange de sel et d’argent du clan Miyoshi contre des armes à feu européennes.
Le comptoir est sous le contrôle de moines chrétiens, et les rumeurs ne sont qu’un écran de fumée.'
    )
;

-- démenti de la fausse piste de la Lune D’Or
INSERT INTO {prefix}locations (name, description, discovery_diff, is_base, can_be_destroyed, can_be_repaired, zone_id, controller_id, activate_json) VALUES
    ('Camp des éclaireurs Takeda (武田)', 
    'On trouve, caché dans un bosquet, entre deux collines, un camp qui fait clairement partie des forces Takeda (武田). Ils ont l’air d’avoir été battus lors de l’affrontement du printemps 1555 et fait de multiples prisonniers dans les forces Chōsokabe (長宗我部).
Il est clair que les rumeurs d’alliances entre les Chōsokabe et les Takeda sont sans fondement, mais cette simple constatation ne sera pas suffisante pour convaincre.
La défaite des Takeda n’a pas réduit leurs intentions belliqueuses envers le Shogun.
(Si vous voulez entrer en contact avec les Takeda, allez voir un orga ! )',
    5, TRUE, TRUE, FALSE, (SELECT ID FROM {prefix}zones WHERE name = 'Plaines du Kansai'),
    (SELECT ID FROM {prefix}controllers WHERE lastname = 'Takeda (武田)'),
    '{"update_location": {
        "name": "Ruines du Camp Takeda (武田)", "discovery_diff": 5,
        "is_base": 0,
        "can_be_destroyed": 0, "can_be_repaired": 1,
        "description": "Les ruines d’un camp militaire autrefois occupé par les forces Takeda (武田). Sa destruction semble nettement plus récente que la bataille du printemps 1555. Des ninjas nous ont proposé, contre une somme conséquente, d’organiser une rencontre avec Shingen Takeda (武田). (Pour explorer davantage ce lieu, adressez-vous à un orga !)",
        "future_location": {
            "name": "Camp de la révolte Takeda (武田)", "discovery_diff": 7,
            "is_base": 1,
            "can_be_destroyed": 0, "can_be_repaired": 1, "save_to_json": "TRUE",
            "description": "Un camp militaire de la révolte Takeda (武田), clairement établi sur la plaine du Kansai. Reste à déterminer s’ils nourrissent encore des intentions belliqueuses envers le Shōgun. (Si vous souhaitez entrer en contact avec le clan Takeda, adressez-vous à un orga !)"
        }
    }}'
);

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- https://fr.wikipedia.org/wiki/P%C3%A8lerinage_de_Shikoku
INSERT INTO {prefix}locations (name, description, discovery_diff, can_be_destroyed, zone_id, controller_id) VALUES
    -- Le chemin de l'éveil (AWA)
    ('Dainichi-ji (大日寺) -- Le chemin de l’éveil', 
    'Niché entre les forêts brumeuses d’Iya, ce temple vibre encore du souffle ancien des premiers pas du pèlerin. 
On dit que les pierres du sentier y murmurent des prières oubliées à ceux qui s’y attardent. 
Le silence y est si pur qu’on entend le battement de son propre cœur.
(Pour explorer davantage ce lieu, allez voir un orga !)', 
    7, True,
    (SELECT ID FROM {prefix}zones WHERE name = 'Vallée d’Iya et d’Oboké d’Awa'),  
    (SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)'))

    -- Le chemin de l'ascèse (TOSA) 
    ,('Chikurin-ji (竹林寺) -- Le chemin de l’ascèse', 
    'Perché au sommet d’une colline surplombant la baie, le temple veille parmi les bambous. 
Les moines y pratiquent une ascèse rigoureuse, veillant jour et nuit face à l’océan sans fin. 
Le vent porte leurs chants jusqu’aux barques des pêcheurs, comme des prières salées.
(Pour explorer davantage ce lieu, allez voir un orga !)', 
    7, True,
    (SELECT ID FROM {prefix}zones WHERE name = 'Grande Baie de Kochi'),  
    (SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)'))

    -- Le chemin de l'illumination (Iyo) 
    ,('Ryūkō-ji (竜光寺) -- Le chemin de l’illumination', 
    'Suspendu à flanc de montagne, le Ryūkō-ji contemple la mer intérieure comme un dragon endormi. 
On raconte qu’au lever du soleil, les brumes se déchirent et révèlent un éclat doré émanant de l’autel. 
Les sages disent que ceux qui y méditent peuvent entrevoir la lumière véritable.
(Pour explorer davantage ce lieu, allez voir un orga !)', 
    7,  True,
    (SELECT ID FROM {prefix}zones WHERE name = 'Côte Ouest d’Iyo'),  
    (SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)'))

    -- Le chemin du Nirvana (Sanuki) 
    ,('Yashima-ji (屋島寺) -- Le chemin du Nirvana', 
    'Ancien bastion surplombant les flots, Yashima-ji garde la mémoire des batailles et des ermites. 
Les brumes de l’aube y voilent statues et stupas, comme pour dissimuler les mystères du Nirvana. 
Certains pèlerins affirment y avoir senti l’oubli du monde descendre sur eux comme une paix.
(Pour explorer davantage ce lieu, allez voir un orga !)', 
    7,  True,
    (SELECT ID FROM {prefix}zones WHERE name = 'Province de Sanuki'),  
    (SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)'))
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
INSERT INTO {prefix}artefacts (name, description, full_description, location_id) VALUES
    (
        'Fujitaka (藤孝) Hosokawa (細川) le daimyô prisonnier',
        'Nous avons découvert que cet homme que tous pensent mort est en réalité enfermé dans une geôle oubliée, gardée par ceux qui craignent son retour ou cherchent à exploiter sa valeur politique.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', (SELECT ID FROM {prefix}locations WHERE name = 'Geôles impériales')
    ), (
        'Kunichika (国親) Chōsokabe (長宗我部) blessé, brisé, il vit toujours',
        'L’ancien seigneur de Shikoku n’est pas tombé à la guerre — il est retenu en otage, par ceux qui craignent son retour ou cherchent à exploiter sa valeur politique.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', (SELECT ID FROM {prefix}locations WHERE name = 'Geôles des Kaizokushū')
    ), (
        'Chikayasu (親泰) Chōsokabe (長宗我部), le fils caché',
        '3eme Fils de Kunichika (国親) et Shōhō (初歩), la charge de l’héritage lui as été épargnée jusqu’ici, mais il est un otage politique important.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', (SELECT ID FROM {prefix}locations WHERE name = 'Retraite secrète des Chōsokabe')
    ), (
        'Shigemasa (重存) Sogō (十河), l’héritier du clan Sogo',
        'Fils de Kazumasa (一存) et de Kujo (九条) Sogo (十河), l’héritier du clan Sogo et détenteur du sang des Miyoshi est désormais un otage politique important.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', NULL
    ), (
        'Yoshioki (義興) Miyoshi (三好), l’enfant maudit',
        'Fils de Nagayoshi (長慶) Miyoshi (三好), l’héritier du clan Miyoshi est un enfant malade maintenu en vie par les potions et les prières. Il n’en reste pas moins un otage politique important aux yeux de son père.',
        'Nous sommes libres de décider de sa destinée (aller voir un orga)!', NULL
    )
;
