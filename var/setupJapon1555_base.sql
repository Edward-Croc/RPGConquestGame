
UPDATE config SET value = 'Shikoku 1555' WHERE name = 'TITLE';
-- https://fr.wikipedia.org/wiki/%C3%89poque_Sengoku
UPDATE config SET
    value = '<p> En plein Sengoku Jidai, les turbulences sociales, intrigues politiques et conflits militaires, divise le Japon.
        Les guerres fratricides font rage sur l''archipel nippon, et le Shoguna Ashikaga fragilisé peine à rétablir la paix.
        Au printemps 1555 les forces du Daïmyo de Shikoku Kunichika Chōsokabe, accompagné de ses vassaux Fujitaka Hosokawa et Motonaga Miyoshi,
         sont partis sur Honshu déféndre Kyoto contre les forces de du clan Takeda. Espérant s''attrirer les faveurs du Shogun Ashikaga.
        Les rares survivants rentrées de la campagne parlent d''une defaite cuisante, d''une rébélion paysanne et du déshonneur du Daïmyo et de ses vassaux.
        Le controle du clan Chōsokabe vassille sur Shikoku et les vassaux même du clan voyent la disparition de Kunichika comme une opportunité sans précédent.
        Celui qui pourra s''octroyer l''allégence de la majorité des 4 provinces sera Maitre de l''ile.
        </p>'
    WHERE name = 'PRESENTATION';

INSERT INTO config (name, value, description)
VALUES
    -- MAP INFO
    ('map_file', 'shikoku.png', 'Map file to use'),
    ('map_alt', 'Carte de Shikoku', 'Map alt'),
    ('time_value', 'Trimestre', 'Text for time span');


INSERT INTO players (username, passwd, is_privileged) VALUES
    ('player0', 'zero', false),
    ('player1', 'one', false),
    ('player2', 'two', false),
    ('player3', 'three', false),
    ('player4', 'four', false),
    ('player5', 'five', false),
    ('player6', 'six', false)
;

INSERT INTO factions (name) VALUES
    ('Samouraïl')
    ,('Moine')
    ,('Chrétien') -- https://histoiredujapon.com/2021/04/05/etrangers-japon-ancien/#index_id1
    ,('Ikkō-ikki') --https://fr.wikipedia.org/wiki/Ikk%C5%8D-ikki
    ,('Kaizokushū')
    ,('Yōkai')
;

-- players with start worker limits
INSERT INTO controlers (
    firstname, lastname,
    start_workers, recruted_workers, turn_recruted_workers,
    faction_id, fake_faction_id,
    story
) VALUES
    (
        'Régence Motochika', 'Chōsokabe', --https://fr.wikipedia.org/wiki/Clan_Ch%C5%8Dsokabe
        1, 0, 0,
        (SELECT ID FROM factions WHERE name = 'Samouraïl' ),
        (SELECT ID FROM factions WHERE name = 'Samouraïl' ),
        'Kunichika Chōsokabe est présumé mort.
        Le fils Motochika Chōsokabe n''as pas encore l''age pour être Daimyo.
        Vous vous devez de tenir la barre du clan durant cette période de transition.
        Malheureusement sans grande assistance vue que vos meilleurs éléments ne sont jamais rentrée de guerre.
        Heuresement vous avez un accord avec Miyoshi, Fudžisan la petit soeur de leur héritier, désormais devenu Daimyo, épousera Motochika.
        Les enfants, de Motochika et Fudžisan, du clan Chōsokabe reigneront sur l''ile et le clan Miyoshi en administrera la moitiée.
        Il suffit de tenir 2 ans que Motochika soit un adulte.
        '
    )
;

-- IA with start workers limits
INSERT INTO controlers (
    firstname, lastname, ia_type,
    start_workers, recruted_workers, turn_recruted_workers, turn_firstcome_workers,
    faction_id, fake_faction_id
) VALUES
    ('Yōkai', 'Shikoku', 'violent', -- https://fr.wikipedia.org/wiki/Y%C5%8Dkai#:~:text=Le%20terme%20y%C5%8Dkai%20(%E5%A6%96%E6%80%AA%2C%20%C2%AB,la%20culture%20orale%20au%20Japon.
        2, 1, 1, 0,
        (SELECT ID FROM factions WHERE name = 'Yōkai'),
        (SELECT ID FROM factions WHERE name = 'Moine')
    )
;

-- players with no start worker limits
INSERT INTO controlers (
    firstname, lastname,
    faction_id, fake_faction_id,
    story
) VALUES
    ('Daïmyo Nagayoshi', 'Miyoshi',  --https://fr.wikipedia.org/wiki/Clan_Miyoshi
        (SELECT ID FROM factions WHERE name = 'Chrétien' ),
        (SELECT ID FROM factions WHERE name = 'Samouraïl' ),
        'Motonaga Miyoshi votre père et Daimyo précédent est présumé mort.
        Vous êtes enfin Daimyo et libre de vos actes.

        Personnellement vous avez abandonnée le boudhisme pour une nouvelle religion, celle du christ precher par les moines accompagnant les vaisseaux noirs des portugais.
        Vous avez passer un marché avec eux et avez accès aux fusils à meches un avantage non négligeable et pour l''instant secret.
        Ces temps troubles sont peut-être le bon moment pour un changement religieux dans la région.

        Votre père avait un accord avec le clan Chōsokabe, Fudžisan votre petite soeur épousera Motochika Chōsokabe le futur Daimyo de Shikoku quand il sera majeur.
        Vos neuveux, les enfants de Motochika et Fudžisan, du clan Chōsokabe reigneront sur l''ile et vos enfants le clan Miyoshi en administrera la moitiée.
        Il suffit de tenir 2 ans que Motochika soit un adulte et que les noces ait lieu.
        Ou pas, car c''est temps troubles seraient le moment opportun pour vous éxtraire de la position de clan vassal et de devenir enfin maitre de l''ile.
        '
    ),
    ('Shinshō-in', 'Rennyo', -- https://fr.wikipedia.org/wiki/Rennyo
        (SELECT ID FROM factions WHERE name = 'Ikkō-ikki' ),
        (SELECT ID FROM factions WHERE name = 'Moine' ),
        'Vous êtes le 8eme abbé du mouvement boudique Jōdo shinshū (la Véritable école de la Terre pure).
        Les Ikkō-ikki sont la face visible du Jōdo shinshū composé des petits nobles-locaux, des paysans, des moines guerriers des pretres shintos qui se rebellent ouvertement contre la caste des samourails.
        
        Vous avez quitté Fukui à l''ouest de Honshu à la tête d''une armée Ikko-ikki bien décidé a boutter le Shougunat hors de Kyoto, et levé le joug des Samourails sur l''autorité de l''empereur.
        Mais les plaines autour de la capitale sont une zone dangereuse en ce moment et vos troupes ont été défaites par celles de Kunichika Chōsokabe.
        Nos sans que vous leur ayez donné du fil à retordre.

        Vus avez pu vous échapper et avez appris peut de temps plus tard que la force blessé de Chōsokabe avait affronter désastreusement la cavalerie Takeda.

        La disparitions de Kunichika Chōsokabe et ses vassaux va crée un confit de pouvoir entre les clans de l''ile de Shikoku. 
        Vous avez appelé certains de vos fils pour venir lancé le mouvement Ikkō-ikki dans la région.
        Et vous avez acheter votre passage sur l''ile auprès des Marins Wano.

        Désormais depuis votre repaire dans le mont Ishizuchi vous préparez votre conquête.
        Peut être qu''en eveillant quelques Yōkais vous pourrez rendre votre conquête plus facile.
        '
    ),
    ('Daïmyo Tadaoki', 'Hosokawa', -- https://fr.wikipedia.org/wiki/Clan_Hosokawa
        (SELECT ID FROM factions WHERE name = 'Samouraïl' ),
        (SELECT ID FROM factions WHERE name = 'Samouraïl' ),
        ' Fujitaka Hosokawa votre père et Daimyo précédent est présumé mort.
          Vous êtes désormais Daimyo. Vous vous devez de tenir la barre du clan durant cette période de troubles.
          Malheureusement sans grande assistance vue que vos meilleurs éléments ne sont jamais rentrée de guerre.
          Vous venez d''une illustre famille sur le déclin. Vous êtes capable de tracé vos racines jusqu''a la lignée de l''empereur Seiwa Gengi il y a 700 ans.
          Il y a peinne 100ans Votre clan dominait Tokushima, Awaji, Kawaga et même jusqu''e Settsu sur Honshu. Mais assassinats et guerres fratricides vous on fait perdre la face.
          Désormais vous êtes un clan vasal des Chōsokabe, mais plus pour longtemps la mort de Kunichika Chōsokabe, accompagné de son vassal Fujitaka Hosokawa ont laissé une faille.
          Il ne vous reste plus qu''à bien jouer vos cartes pour profiter de la faiblesse du clan Chōsokabe, vous avez 5 ans devant vous avant que Motochika soit asser agé pour gouverner seul.
          Peut-être est-ce le temps du renouveau.'
    ),
    ('Murai', 'Wako', --
        (SELECT ID FROM factions WHERE name = 'Kaizokushū' ),
        (SELECT ID FROM factions WHERE name = 'Kaizokushū' ),
        ''
    )
;

INSERT INTO player_controler (controler_id, player_id)
    SELECT ID, (SELECT ID FROM players WHERE username = 'gm')
    FROM controlers;

INSERT INTO player_controler (player_id, controler_id) VALUES
    (
        (SELECT ID FROM players WHERE username = 'player0'),
        (SELECT ID FROM controlers WHERE lastname in ('Shikoku'))
    ), -- player1 controls  Angelo Ricciotti/Antonio Mazzino,
    (
        (SELECT ID FROM players WHERE username = 'player1'),
        (SELECT ID FROM controlers WHERE lastname = 'Chōsokabe')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player2'),
        (SELECT ID FROM controlers WHERE lastname in ('Miyoshi'))
    ),
    (
        (SELECT ID FROM players WHERE username = 'player3'),
        (SELECT ID FROM controlers WHERE lastname = 'Rennyo')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player4'),
        (SELECT ID FROM controlers WHERE lastname = 'Hosokawa')
    ),
    (
        (SELECT ID FROM players WHERE username = 'player5'),
        (SELECT ID FROM controlers WHERE lastname = 'Wako')
    )
;

INSERT INTO zones (name, description) VALUES
      ('Cote Ouest d''Echime', 'La porte vers l''ile de Kyushu')
    , ('Montagnes d''Echime', 'Entourant le pic Ishizuchi cette chaine de montagne est sacrée.')
    , ('Cape sud de Kochi', 'Ressource Fer, gare à l''océan pacific')
    , ('Grande Baie de Kochi', 'Siege du clan Chōsokabe')
    , ('Vallées d''Iya et d''Oboké de Tokushima', 'Ressource Thé')
    , ('Cote Est de Tokushima', 'Siege du clan Miyoshi')
    , ('Prefecture de Kagawa', 'Ressource Cheval')
    , ('Ile de Awaji', 'La porte vers Honshu et la capitale, gare au vent')
    , ('Ile de Shödoshima', ' Refuge des pirates Wako, gare a la paresse ')
    , ('Cité Impériale de Kyoto', 'Parce que les intrigues de cours ne sont jamais loin')
;

/*
-- https://fr.wikipedia.org/wiki/P%C3%A8lerinage_de_Shikoku
-- Insert the data
INSERT INTO locations (name, description, discovery_diff, zone_id) VALUES
    ('Stazione ferroviaria', '', 0, (SELECT ID FROM zones WHERE name = 'Railway Station'))
    , ('Les anges de la boue', '', 6, (SELECT ID FROM zones WHERE name = 'Le Cascine'))
    , ('Cairn','', 6, (SELECT ID FROM zones WHERE name = 'Monticelli'))
    , ('Fortezza da Basso', '', 0, (SELECT ID FROM zones WHERE name = 'Fortezza Basso'))
    , ('Facolta di Ingegneria', '', 6, (SELECT ID FROM zones WHERE name = 'Fortezza Basso'))
    , ('Gare/Les anges de la boue', '', 6, (SELECT ID FROM zones WHERE name = 'Santa Maria Novella'))
    , ('Balistero','', 0, (SELECT ID FROM zones WHERE name = 'Santa Maria Novella'))
;
*/

-- Table of Power Types
INSERT INTO power_types (name) VALUES
    ('Hobby'),
    ('Metier'),
    ('Discipline'),
    ('Transformation');

-- Table of powers
-- other possible keys hidden, on_recrutment, on_transformation
INSERT INTO powers ( name, enquete, attack, defence, other) VALUES
    ('Cheval Kagawa', 1,1,1, '{"hidden" : "2", "on_recrutment": "TRUE", "on_transformation": {"worker_is_alive": "1", "age": "0", "turn": "0"} }')
    , ('Armure en fer de Kochi', 0,2,2, '{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "age": "2", "turn": "2"} }')
    , ('Thé d''Oboké et d''Iya', 2,0,1, '{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "age": "2", "turn": "2"} }')
;

INSERT INTO  link_power_type ( power_type_id, power_id ) VALUES
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Cheval'))
    , ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Armure'))
;

UPDATE config SET value = '''Yari (la lance)'', ''Yumi (l\''arc)'', ''Puissance'''
WHERE name = 'basePowerNames';
--https://fr.wikipedia.org/wiki/Ashigaru
INSERT INTO powers ( name, enquete, attack, defence) VALUES
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
    ('Kenjutsu', 1,1,1),
    ('Naginatajutsu', 1,1,1),
    ('Bajutsu', 1,1,1),
    ('Tantōjutsu', 1,1,1),
    -- ('', 0,1,2),
    ('Sōjutsu', 0,1,2),
    ('Iaijutsu', 0,1,2),
    -- ('', 0,2,1),
    ('Kyūjutsu', 0,2,1),
    
    -- ('', 2,0,0),
    ('Jūjutsu', 2,0,0),
    -- ('', 2,2,-1),
    ('Occultation', 2,1,-1),
    -- ('', 2,-1,1),
    ('Augure', 2,-1,1),
    -- Pouvoirs mortels et démons
    ('Hōjutsu', -1, 3,2), -- 
    ('Sentir le mal', 3, 1,1)
;
/*
1. Kenjutsu (剣術) – Art du sabre
2. Iaijutsu (居合術) – Art de dégainer et frapper en un mouvement
3. Sōjutsu (槍術) – Art de la lance (Yari)
4. Naginatajutsu (薙刀術) – Art de la hallebarde 
5. Kyūjutsu (弓術) – Art du tir à l’arc (ancien kyūdō)
6. Bajutsu (馬術) – Art de l'équitation militaire
7. Tantōjutsu (短刀術) – Combat au couteau
8. Jūjutsu (柔術) – Techniques de lutte à mains nues
9. Hōjutsu (砲術) – Art des armes à feu (teppō)
1. Chadō (茶道) – Voie du thé
2. Shodō (書道) – Calligraphie
3. Kadō / Ikebana (華道 / 生け花) – Art floral
4. Ninjutsu (忍術) – Techniques d’espionnage et de guérilla
5. Heihō (兵法) – Stratégie militaire
6. Yawara (和) – Ancienne forme de techniques de soumission, liée au jūjutsu
7. Reiki / Kujikiri (霊気 / 九字切り) – Pratiques ésotériques
1. Haikai / Haiku (俳諧 / 俳句) – Poésie courte
2. Waka (和歌) – Poésie classique
3. Shigin (詩吟) – Chant poétique
4. Gagaku (雅楽) – Musique de cour
5. Bugaku (舞楽) – Danse de cour
6. Kōdō (香道) – Voie de l'encens
*/
/*
INSERT INTO  link_power_type ( power_type_id, power_id ) VALUES
    ((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Naginata (lance)'))
    ,((SELECT ID FROM power_types WHERE name = 'Discipline'),(SELECT ID FROM powers WHERE name = 'Katana (sabre)'))
;
*/

/*
-- Add base powers to the factions :
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Brujah'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Célérité'
    )),
    ((SELECT ID FROM factions WHERE name = 'Brujah'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers on powers.ID = link_power_type.power_id
        WHERE powers.name = 'Puissance'
    ))
;
*/
