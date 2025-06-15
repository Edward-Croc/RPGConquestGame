-- TODO Build the Shogun and Yokai character sheets 
-- TODO Add Controller action JSON activatable => Affect held zone if agent present and change claimer
    -- Needs parametrable name, 
    -- Needs result text,
    -- Make possible for Ikko-ikki, Monks and Chritians
-- 


UPDATE config SET value = '1,2,3,4,5,6,7' WHERE name = 'recrutement_origin_list';
UPDATE config SET value =  '1,2,3,4,5,6' WHERE name = 'local_origin_list';
UPDATE config SET value =  '1' WHERE name = 'recrutement_disciplines';
UPDATE config SET value =  '{"age": ["1","2"]}' WHERE name = 'age_discipline';

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
    ('妖怪 de', 'Shikoku (四国)', 'passif', -- https://fr.wikipedia.org/wiki/Y%C5%8Dkai#:~:text=Le%20terme%20y%C5%8Dkai%20(%E5%A6%96%E6%80%AA%2C%20%C2%AB,la%20culture%20orale%20au%20Japon.
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
          Et dans votre forteresse cachée de Shōdoshima, il vaut plus que n’importe quel trésor.
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
      ('Côte Ouest d’Ehime', 'La porte vers l’île de Kyūshū, cette bande littorale est animée par les flux incessants de navires marchands, pêcheurs et patrouilleurs. Les criques cachent parfois des comptoirs discrets ou des avant-postes de contrebandiers. Les brumes marines y sont fréquentes, rendant les approches aussi incertaines que les intentions de ses habitants.')
    , ('Montagnes d’Ehime', 'Entourant le redouté mont Ishizuchi, plus haut sommet de Shikoku, ces montagnes sacrées sont le domaine des ascètes, des yamabushi et des esprits anciens. Les chemins escarpés sont peuplés de temples isolés, de cascades énigmatiques, et d’histoires transmises à demi-mot. Nul ne traverse ces hauteurs sans y laisser un peu de son âme.')
    , ('Cap sud de Kochi', 'Battue par les vents de l’océan Pacifique, cette pointe rocheuse est riche en minerai de fer, extrait dans la sueur et le sel. Le paysage austère dissuade les faibles, mais attire les clans ambitieux. Les tempêtes y sont violentes, et même les dragons du ciel semblent redouter ses falaises noires.')
    , ('Grande Baie de Kochi', 'Centre de pouvoir du clan Chōsokabe, cette baie est à la fois un havre de paix et un verrou stratégique. Bordée de rizières fertiles et de ports animés, elle est défendue par des flottes aguerries et des forteresses discrètes. On dit que ses eaux reflètent les ambitions de ceux qui la contrôlent.')
    , ('Vallées d’Iya et d’Oboké de Tokushima', 'Ces vallées profondes, creusées par les torrents et le temps, abritent des plantations de thé précieuses et des villages suspendus au flanc des falaises. Peu accessibles, elles sont le refuge de ceux qui fuient la guerre, la loi ou le destin. Le thé qui y pousse a le goût amer des secrets oubliés.')
    , ('Côte Est de Tokushima', 'Sur cette façade tournée vers le large, le clan Miyoshi établit son pouvoir entre les ports et les postes fortifiés. Bien que prospère, la région est sous tension : les vassaux y sont fiers, les ambitions grandes, et les flottes ennemies jamais loin. La mer y apporte autant de trésors que de périls.')
    , ('Prefecture de Kagawa', 'Plaine fertile dominée par les haras impériaux et les sanctuaires oubliés, Kagawa est renommée pour ses chevaux rapides et robustes. Les émissaires s’y rendent pour négocier montures de guerre, messagers ou montures sacrées. C’est aussi une terre de festivals éclatants et de compétitions féroces.')
    , ('Ile d’Awaji', 'Pont vivant entre Shikoku et Honshū, Awaji est stratégiquement vitale et toujours convoitée. Les vents y sont brutaux, les détroits traîtres, et les seigneurs prudents. Ses collines cachent des fortins, ses criques des repaires, et ses chemins sont surveillés par des yeux invisibles.')
    , ('Ile de Shōdoshima', 'Ile montagneuse et sauvage, jadis sanctuaire, aujourd’hui repaire des pirates Wako. Ses ports semblent paisibles, mais ses criques abritent des embarcations rapides prêtes à fondre sur les convois marchands. Les autorités ferment souvent les yeux, car même le vice paie tribut.')
    , ('Cité Impériale de Kyoto', 'Capitale impériale, centre des arts, des lettres et des poisons subtils. Les palais y cachent les plus anciennes lignées, les ruelles les complots les plus jeunes. Kyōto ne brandit pas l’épée, mais ceux qui y règnent peuvent faire plier des provinces entières par un sourire ou un silence.')
;

-- Controle des zones au départ
UPDATE zones SET
    claimer_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Chōsokabe (長宗我部)'),
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Chōsokabe (長宗我部)')
    WHERE name = 'Grande Baie de Kochi';
UPDATE zones SET
    claimer_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Miyoshi (三好)'),
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Miyoshi (三好)')
    WHERE name = 'Côte Est de Tokushima';
UPDATE zones SET
    claimer_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Hosokawa (細川)'),
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Hosokawa (細川)')
    WHERE name = 'Prefecture de Kagawa';
UPDATE zones SET
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)')
    WHERE name = 'Ile de Shōdoshima';
UPDATE zones SET
    claimer_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Kōbō-Daishi (弘法大師)'),
    holder_controller_id = (SELECT ID FROM controllers WHERE lastname = 'Kōbō-Daishi (弘法大師)')
    WHERE name = 'Montagnes d’Ehime';

-- Secrets scénario
INSERT INTO locations (name, discovery_diff, zone_id, controller_id, description) Values
    -- Ajouter un secret sur l'arrivée des rebels Ikko-ikki sur l'ile par petits groupes
    ('Plaine d’Uwajima', 8, (SELECT ID FROM zones WHERE name = 'Côte Ouest d’Ehime'), (SELECT ID FROM controllers WHERE lastname = 'Rennyo (蓮如)')
        , 'Les vastes plaines d’Uwajima semblent paisibles sous le soleil, entre cultures clairsemées et sentiers oubliés.
        Mais depuis plusieurs semaines, des groupes d’hommes en haillons, armés de fourches, de bâtons ou de sabres grossiers, y ont été aperçus.
        Ces paysans ne sont pas d’ici : ils avancent discrètement, se regroupent à la tombée du jour, et prêchent un discours de révolte contre les samouraïs.
        Ce sont les avant-gardes des Ikko-ikki, infiltrés depuis le continent par voie maritime.
        Découvrir quel est le chef qui les unis pourrait permettre d’agir avant qu’il ne soit trop tard.'
    )
    -- Ajouter un secret sur l'arrivée de Rennyo déposée par les Kaizokushū Wako il y a quelques semaines à peinne
    , ('Port de Matsuyama', 8, (SELECT ID FROM zones WHERE name = 'Montagnes d’Ehime'), (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)')
        , 'Le port de Matsuyama est d’ordinaire animé par les pêcheurs locaux et les petits marchands.
        Mais depuis peu, les anciens disent avoir vu, au crépuscule, un navire étrange accoster sans bannière, escorté par des pirates tatoués.
        Un moine en est descendu, maigre, vieux, au regard brûlant de ferveur : Rennyo lui-même, leader spirituel des Ikko-ikki.
        Selon certains, il s’est enfoncé dans les montagnes d’Ehime avec une poignée de fidèles.
        Ce secret, s’il venait à être révélé, pourrait changer l’équilibre religieux de toute l’île.'
    )
    -- Ajouter un secret sur la présence du christianisme et du pretre Luís Fróis Japonologue et Jésuite -- https://fr.wikipedia.org/wiki/Lu%C3%ADs_Fr%C3%B3is 
    , ('Port de Tokushima', 8, (SELECT ID FROM zones WHERE name = 'Côte Est de Tokushima'), (SELECT ID FROM controllers WHERE lastname = 'Miyoshi (三好)')
        ,'Dans les ruelles du port de Tokushima, à l’écart des marchés, une maison basse aux volets clos abrite un hôte peu commun : Luís Fróis, prêtre jésuite portugais, érudit des mœurs japonaises.
        Il y aurait établi un sanctuaire clandestin, enseignant les paroles du Christ à quelques convertis du clan Miyoshi.
        Ce lieu sert également de relais discret pour faire entrer armes, livres et messagers depuis Nagasaki.
        Sa présence confirme l’implantation secrète du christianisme à Tokushima, et menace de faire basculer les équilibres religieux et politiques de Shikoku.'
    )
    -- Ajouter un secret sur l’alliance maritale entre les Chosokabe Motochika et les Hosokawa Tama
    , ('Post relai du courrier de Kagawa', 8, (SELECT ID FROM zones WHERE name = 'Prefecture de Kagawa'), NULL
        , 'Une auberge modeste près de la grande route de Kagawa reçoit parfois, à l’aube, des cavaliers fatigués portant des missives cachetées.
        L’une d’elles, récemment interceptée, contenait une promesse de mariage scellée entre Motochika Chōsokabe et Tama Hosokawa, fille de Fujitaka.
        Si elle venait à se concrétiser, cette alliance unirait deux grandes maisons sur Shikoku et changerait les rapports de pouvoir de toute la région.
        Pour l’instant, l’information est gardée secrète, mais les rumeurs montent.'
    )
    -- Ajouter un secret sur Awaji a propos de la bataille de Kunichika contre les ikko-ikki, permettant de lever la rumeur sur sa couardise
    , ('Camp de deserteurs', 8, (SELECT ID FROM zones WHERE name = 'Ile d’Awaji'), NULL
        , 'Dans les bois humides d’Awaji, un vieux temple en ruines abrite depuis peu des hommes au regard hanté et aux vêtements déchirés : des déserteurs de la bataille d’Ishizuchi.
        Ils murmurent une autre version des faits : Kunichika Chōsokabe n’a pas fui par lâcheté, mais son armée as été défaite par la prise en tenaille organisé par les Ikko-ikki alliées aux Takedas.
        Ses actions ont été étouffé par ses rivaux et par la honte des survivants. Si ce témoignage était rendu public, l’honneur du clan Chōsokabe pourrait être réhabilité.'  
    )
    -- Ajouter un secret sur Shōdoshima à propos de la fuite des forces de Fujitaka face a l'avant garde Takedas alliées aux Ikko-ikki. Permettant de lever la rumeur sur sa couardise et de confirmé sa capture par un général du Shogun
    , ('Camp de deserteurs', 8, (SELECT ID FROM zones WHERE name = 'Ile de Shōdoshima'), NULL
        , 'Dans une gorge dissimulée parmi les pins tordus de Shōdoshima, quelques hommes efflanqués vivent en silence, fuyant le regard des pêcheurs et des samouraïs.
            Ce sont des survivants de la déroute d’Ishizuchi, dont ils racontent une version bien différente de celle propagée à la cour : l’avant-garde des Chōsokabe, commandée par Fujitaka Hosokawa, se serait retrouvée face aux fanatiques Ikko-ikki, qui auraient écrasé ses lignes avant même que l’ordre de retraite ne puisse être donné.
            Fujitaka, séparé de la force principale, aurait fui précipitamment vers Kyoto, mais aurait été aperçu capturé par un général des forces du shogun Ashikaga. Ces aveux, étouffés sous le fracas des récits officiels, pourraient bien réhabiliter l’honneur du daimyō déchu — ou bouleverser les équilibres fragiles entre les clans.'
    )
    -- Ajouter un secret sur Kyoto a propos de l’inimitié du Shogun contre les Chosokabe suit a sa débandade et fuite honteuse devant l'armée des takedas.
    , ('Cour impériale', 8, (SELECT ID FROM zones WHERE name = 'Cité Impériale de Kyoto'), NULL
        , 'Au sein des couloirs feutrés de la cour impériale, on ne parle plus qu’à demi-mot des récents affrontements.
        Le nom des Chōsokabe y est devenu tabou, soufflé avec mépris : leur armée, jadis fière, aurait fui sans gloire devant l’avant-garde Takeda.
        Le Shogun Ashikaga, humilié par leur débâcle, aurait juré de ne plus leur accorder confiance ni territoire.
        Ce ressentiment pourrait être exploité — ou au contraire, désamorcé — selon les preuves et récits qu’on parvient à faire émerger de l’ombre.'
    )
;

INSERT INTO locations (name, description, discovery_diff, can_be_destroyed, zone_id, controller_id, activate_json) VALUES
    -- Geôles impériales de Kyoto
    (
        'Geôles impériales', 
        'Sous les fondations de la Cité impériale, ces geôles étouffantes résonnent des cris étouffés des oubliés du Shogun. 
        L’air y est moite, chargé de remords et d’encre séchée — là où les sentences furent calligraphiées avant d’être exécutées.
        Peu en ressortent, et ceux qui le font ne parlent plus.',
        10, True,
        (SELECT ID FROM zones WHERE name = 'Cité Impériale de Kyoto'),
        (SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)'),
        '{"indestructible" : "TRUE"}'
    ),

    -- Geôles des pirates (Shōdoshima)
    (
        'Geôles des Kaizokushū', 
        'Creusées dans la falaise même, ces cavernes humides servent de prison aux captifs des Wako. 
        Des chaînes rouillées pendent aux murs, et l’eau salée suinte sans cesse, rongeant la volonté des enfermés. 
        Le silence n’y est troublé que par les pas des geôliers — ou les rires des pirates.',
        10, True,
        (SELECT ID FROM zones WHERE name = 'Ile de Shōdoshima'),
        (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)'),
        '{"indestructible" : "TRUE"}'
    )
;

-- Temples des Yokais
INSERT INTO locations (name, discovery_diff, can_be_destroyed, zone_id, controller_id, description) VALUES
     -- Feu - Teppō
    ('Vieux temple', 9, True, (SELECT ID FROM zones WHERE name = 'Cap sud de Kochi'),  (SELECT ID FROM controllers WHERE lastname = 'Shikoku (四国)'),
        'Accroché aux flancs escarpés de la côte sud de Kōchi, un petit sanctuaire noircit repose au bord d’une ancienne veine de fer oubliée.
        Au loin, dans la vallée, les marteaux des forgerons résonnent comme une prière sourde.
        Mais chaque nuit, une odeur de poudre flotte dans l’air, et un claquement sec — sec comme un tir — fait sursauter les corbeaux.
        (Pour explorer davantage ce lieu, allez voir un orga !)')
    -- Vent - Tessen
    , ('Vieux temple', 9, True, (SELECT ID FROM zones WHERE name = 'Ile d’Awaji'),  (SELECT ID FROM controllers WHERE lastname = 'Shikoku (四国)'),
        'Perché au sommet d’une falaise d’Awaji, un petit pavillon de bois battu par les vents se dresse, fragile et silencieux.
        La porte ne ferme plus, et le papier des lanternes s’effiloche. Pourtant, nul grain de poussière ne s’y pose.
        Lorsque l’on entre, l’air se fait soudain glacé, et un bruissement court dans les chevrons — comme si un éventail invisible fendait l’air avec colère.
        (Pour explorer davantage ce lieu, allez voir un orga !)')
     -- Paresse - Biwa
    , ('Vieux temple', 9, True, (SELECT ID FROM zones WHERE name = 'Ile de Shōdoshima'),  (SELECT ID FROM controllers WHERE lastname = 'Shikoku (四国)'),
        'Ce temple oublié, dissimulé dans un vallon brumeux de Shōdoshima, semble abandonné depuis des décennies.
         Pourtant, chaque crépuscule, les accords las d’un biwa résonnent sous les poutres vermoulues, portés par une brise douce où flotte un parfum de saké tiède.
         Pourtant nul prêtre et nul pèlerin en vue.
        (Pour explorer davantage ce lieu, allez voir un orga !)')
     -- Roche - Chigiriki
    , ('Vieux temple', 9, True, (SELECT ID FROM zones WHERE name = 'Montagnes d’Ehime'),  (SELECT ID FROM controllers WHERE lastname = 'Shikoku (四国)'),
        'Perché sur un piton rocheux des montagnes d’Ehimé, un ancien temple taillé à même la pierre repose, figé comme un souvenir.
        Nul vent n’y souffle, nul oiseau n’y niche.
        Parfois, on y entend cliqueter une chaîne sur la pierre nue, comme si une arme traînait seule sur le sol.
        (Pour explorer davantage ce lieu, allez voir un orga !)')
;

-- Ressources
INSERT INTO locations (name, description, discovery_diff, zone_id) VALUES
    -- Thé d’Oboké
    ('Vallée fertile d’Oboké', 
    'Dans la vallée profonde d’Oboké, où le bruit de la rivière est permanent, poussent à flanc de roche de rares théiers.
    Leurs feuilles, amères et puissantes, sont cueillies à la main par les familles montagnardes, suspendues au-dessus du grondement des eaux.
    Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare.'
    , 7, (SELECT ID FROM zones WHERE name = 'Vallées d’Iya et d’Oboké de Tokushima')
    ),

    -- Armure en fer de Kochi
    ('Mine de fer de Kubokawa',
    'Dans les profondeurs du cap sud de Kōchi, des veines de fer noir sont extraites à la force des bras puis forgées en cuirasses robustes dans les forges voisines.
    Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare.',
    7, (SELECT ID FROM zones WHERE name = 'Cap sud de Kochi')),

    -- Cheval de Kagawa
    ('Écuries de Kagawa',
    'Les vastes pâturages de Kagawa forment l’écrin idéal pour l’élevage de chevaux endurants, prisés tant pour la guerre que pour les grandes caravanes.
    Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare.',
    7, (SELECT ID FROM zones WHERE name = 'Prefecture de Kagawa')),

    -- Encens coréen
    ('Port marchand d’Uwajima',
    'Des voiliers venus de la péninsule coréenne accostent à Uwajima, chargés de résines rares dont les parfums servent aux temples autant qu’aux intrigues.
    Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare.',
    7, (SELECT ID FROM zones WHERE name = 'Côte Ouest d’Ehime'));

-- Fluff
INSERT INTO locations (name, description, discovery_diff, zone_id) VALUES
    ('Port d’Uwajima',
     'Un port animé aux quais denses et bruyants, où s’échangent riz, bois, et rumeurs en provenance de Kyūshū comme de Corée.
     Les marins disent que la brume y reste plus longtemps qu’ailleurs.',
     6, (SELECT ID FROM zones WHERE name = 'Côte Ouest d’Ehime')),

    ('Mt Ishizuchi',
     'Plus haut sommet de l’île, le mont Ishizuchi domine les vallées alentour comme un sabre dressé vers le ciel.
     On dit qu’un pèlerinage ancien y conduit à une dalle sacrée où les esprits s’expriment lorsque les vents tournent.',
     6, (SELECT ID FROM zones WHERE name = 'Montagnes d’Ehime')),

    ('Port de Kochi',
     'Protégé par une anse naturelle, ce port militaire et marchand voit passer jonques, bateaux de guerre et pirates repenti.
      Son arsenal est surveillé nuit et jour par des ashigaru en armure sombre.',
     6, (SELECT ID FROM zones WHERE name = 'Grande Baie de Kochi')),

    ('Ikeda',
     'Petit village de montagne aux maisons de bois noircies par le temps.
     Les voyageurs s’y arrêtent pour goûter un saké réputé, brassé à l’eau des gorges profondes qui serpentent en contrebas.',
     6, (SELECT ID FROM zones WHERE name = 'Vallées d’Iya et d’Oboké de Tokushima')),

    ('Port de Tokushima',
     'Carrefour maritime entre Honshū et Shikoku, le port de Tokushima bruisse de dialectes et de voiles étrangères.
     Dans les ruelles proches du marché, on parle parfois espagnol, ou latin, à voix basse.',
     6, (SELECT ID FROM zones WHERE name = 'Côte Est de Tokushima')),

    ('Grande route et relais de poste',
     'Relie Tokushima à Kōchi en serpentant à travers les plaines fertiles du nord.
     À chaque relais, les montures peuvent être changées, et les messagers impériaux y trouvent toujours une couche et un bol chaud.',
     6, (SELECT ID FROM zones WHERE name = 'Prefecture de Kagawa')),

    ('Rumeur de la bataille',
     'Les pêcheurs d’Awaji parlent encore d’un combat féroce dans les collines, entre troupes en fuite et rebelles aux visages peints. Certains affirment avoir vu le ciel s’embraser au-dessus du temple abandonné.',
     6, (SELECT ID FROM zones WHERE name = 'Ile d’Awaji')),

    ('Détroit d’Okayama',
     'Étroit et venteux, ce détroit aux eaux traîtresses sépare Shikoku de Honshū.
     Difficile de tenter cette traversée sans être épié par les habitants de l’île de Shōdoshima.
     Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d’être intercepté par les Kaizokushū.',
     6, (SELECT ID FROM zones WHERE name = 'Ile de Shōdoshima')),

    ('Suzaku Mon',
     'Grande artère pavée de la capitale impériale, menant tout droit au palais. Sous ses tuiles rouges, l’ombre des complots se mêle aux parfums de thé, et les bannières flottent dans un silence cérémoniel.',
     6, (SELECT ID FROM zones WHERE name = 'Cité Impériale de Kyoto'))
;

-- https://fr.wikipedia.org/wiki/P%C3%A8lerinage_de_Shikoku
INSERT INTO locations (name, description, discovery_diff, can_be_destroyed, zone_id, controller_id) VALUES
    -- Le chemin de l'éveil (Tokushima)
    ('Dainichi-ji (大日寺) -- Le chemin de l’éveil', 
    'Niché entre les forêts brumeuses d’Iya, ce temple vibre encore du souffle ancien des premiers pas du pèlerin. 
    On dit que les pierres du sentier y murmurent des prières oubliées à ceux qui s’y attardent. 
    Le silence y est si pur qu’on entend le battement de son propre cœur.
    (Pour explorer davantage ce lieu, allez voir un orga !)', 
    7, True,
    (SELECT ID FROM zones WHERE name = 'Vallées d’Iya et d’Oboké de Tokushima'),  
    (SELECT ID FROM controllers WHERE lastname = 'Kōbō-Daishi (弘法大師)'))

    -- Le chemin de l'ascèse (Kōchi) 
    ,('Chikurin-ji (竹林寺) -- Le chemin de l’ascèse', 
    'Perché au sommet d’une colline surplombant la baie, le temple veille parmi les bambous. 
    Les moines y pratiquent une ascèse rigoureuse, veillant jour et nuit face à l’océan sans fin. 
    Le vent porte leurs chants jusqu’aux barques des pêcheurs, comme des prières salées.', 
    7, True,
    (SELECT ID FROM zones WHERE name = 'Grande Baie de Kochi'),  
    (SELECT ID FROM controllers WHERE lastname = 'Kōbō-Daishi (弘法大師)'))

    -- Le chemin de l'illumination (Ehime) 
    ,('Ryūkō-ji (竜光寺) -- Le chemin de l’illumination', 
    'Suspendu à flanc de montagne, Ryūkō-ji contemple la mer intérieure comme un dragon endormi. 
    On raconte qu’au lever du soleil, les brumes se déchirent et révèlent un éclat doré émanant de l’autel. 
    Les sages disent que ceux qui y méditent peuvent entrevoir la lumière véritable.', 
    7,  True,
    (SELECT ID FROM zones WHERE name = 'Côte Ouest d’Ehime'),  
    (SELECT ID FROM controllers WHERE lastname = 'Kōbō-Daishi (弘法大師)'))

    -- Le chemin du Nirvana (Kagawa) 
    ,('Yashima-ji (屋島寺) -- Le chemin du Nirvana', 
    'Ancien bastion surplombant les flots, Yashima-ji garde la mémoire des batailles et des ermites. 
    Les brumes de l’aube y voilent statues et stupas, comme pour dissimuler les mystères du Nirvana. 
    Certains pèlerins affirment y avoir senti l’oubli du monde descendre sur eux comme une paix.', 
    7,  True,
    (SELECT ID FROM zones WHERE name = 'Prefecture de Kagawa'),  
    (SELECT ID FROM controllers WHERE lastname = 'Kōbō-Daishi (弘法大師)'))
;


INSERT INTO artefacts (name, description, full_description, location_id) VALUES
    (
        'Fujitaka (藤孝) Hosokawa (細川) le daimyô prisonnier',
        'Nous avons découvert que cet homme que tous pensent mort est en réalité enfermé dans une geôle oubliée, gardée par ceux qui craignent son retour.',
        'Nous sommes libres de décidé de sa destinée (aller voir un orga)!', (SELECT ID FROM locations WHERE name = 'Geôles impériales')
    ), (
        'Kunichika(国親) Chōsokabe(長宗我部) blessé, brisé, il vit toujours',
        'L’ancien seigneur de Shikoku n’est pas tombé à la guerre — il est retenu ici, gardée par ceux qui craignent son retour.',
        'Nous sommes libres de décidé de sa destinée (aller voir un orga)!', (SELECT ID FROM locations WHERE name = 'Geôles des Kaizokushū')
    ), (
        'Motochika (元親) Chōsokabe(長宗我部) daimyô en devenir',
        'Fils de Kunichika, encore trop jeune pour gouverner, il est la clef d’un fragile héritage.',
        'Nous sommes libres de décidé de sa destinée (aller voir un orga)!', NULL
    ), (
        'Tama (玉) Hosokawa (細川), fille de Fujitaka(藤孝), petite soeur de Tadaoki',
        'Jeune noble éduquée aux arts de la poésie et de l’étiquette, elle est certainment le pion d’un jeu politique.',
        'Nous sommes libres de décidé de sa destinée (aller voir un orga)!', NULL
    ), (
        'Fudžisan(富士山) Miyoshi(三好), petit soeur du daimyô Nagayoshi (長慶).',
        'Promise à un mariage d’alliance, elle demeure énigmatique, pieuse, et bien plus rusée que son sourire ne le laisse paraître.',
        'Nous sommes libres de décidé de sa destinée (aller voir un orga)!', NULL
    )
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
    , ('Encens Coréen', 1, 0,0, '{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Côte Ouest d’Ehime", "worker_in_zone": "Côte Ouest d’Ehime"} }')
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

