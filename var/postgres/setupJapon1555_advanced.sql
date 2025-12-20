-- Warning: If you read this file, you will no longer be eligible to participate as a player.

-- Create the Yōkai start workers
/*
Yōkai de la Paresse (Biwa, vallon, saké)
('Kazusa', 'Kotatsu')
Kazusa (風佐 — « celui qui suit le vent »), Kotatsu renvoie à la chaleur paresseuse d’un foyer endormi.

Yōkai de la Roche (Chigiriki, montagne, chaînes)
('Iwao', 'Jizane')
Iwao (岩男 — « homme-rocher ») souligne la solidité minérale, Jizane est un nom inventé à consonance ancienne, proche de « jizō » et « zane » (chaîne/bruit de métal).

Yōkai du Vent (Tessen, pavillon d’Awaji, froid)
('Kosagi', 'Noayame')
Kosagi (小鷺 — « petite héronne »), Noayame combine no (champs) et ayame (iris — souvent liés aux esprits et au printemps), donnant un nom éthéré et flottant.

Yōkai du Feu (Teppō, cap sud de Kōchi, forge, poudre)
('Hiuchi', 'Kagaribi')
Hiuchi (火打 — « pierre à feu »), Kagaribi (篝火 — « feu de signalisation / brasier »), ensemble ils forment un duo sonore sec et évocateur.
*/
WITH names_data(firstname, lastname, origin_name, zone_name) AS (
    VALUES
        ('Kazusa (風佐)', 'Kotatsu', 'Shikoku - Shōdoshima', 'Ile de Shōdoshima'),
        ('Iwao (岩男)', 'Jizane', 'Shikoku - Iyo', 'Montagnes d’Iyo'),
        ('Kosagi (小鷺)', 'Noayame', 'Shikoku - Awaji', 'Ile d’Awaji'),
        ('Hiuchi (火打)', 'Kagaribi', 'Shikoku - Tosa', 'Cap sud de Tosa')
)
INSERT INTO {prefix}workers (firstname, lastname, origin_id, zone_id)
SELECT
    nd.firstname,
    nd.lastname,
    wo.id AS origin_id,
    z.id AS zone_id
FROM names_data nd
JOIN {prefix}worker_origins wo ON wo.name = nd.origin_name
JOIN {prefix}zones z ON z.name = nd.zone_name;


-- Now, get controller ID once
-- Then add the links
WITH controller AS (
    SELECT id AS controller_id FROM {prefix}controllers WHERE lastname = 'Shikoku (四国)'
),
worker_ids AS (
    SELECT id AS worker_id FROM {prefix}workers WHERE lastname IN ('Kotatsu', 'Jizane', 'Noayame', 'Kagaribi')
)
INSERT INTO {prefix}controller_worker (controller_id, worker_id)
SELECT controller.controller_id, worker_ids.worker_id
FROM controller, worker_ids;

-- Add actions to the workers :
INSERT INTO {prefix}worker_actions (
    worker_id, controller_id, turn_number, zone_id, action_choice, action_params, report
)
SELECT
    w.id,
    cw.controller_id,
    0,
    w.zone_id,
    entry.action_choice,
    entry.action_params::json,
    entry.report::json
FROM (
    SELECT 'Kotatsu' AS lastname, 'passive' AS action_choice, '{}' AS action_params, 
        '{"life_report":"Depuis des siècles moi Kazusa (風佐 — « celui qui suit le vent ») des Yōkai (妖怪) hante les vallons de Shōdoshima."}' AS report
    UNION ALL SELECT 'Jizane', 'passive', '{}',
        '{"life_report":"Depuis des siecles moi Iwao (岩男 — « homme-rocher ») des Yōkai (妖怪) arpente les flancs des montagnes d’Iyo."}'
    UNION ALL SELECT  'Noayame', 'passive', '{}',
        '{"life_report":"Depuis des siecles moi Kosagi (小鷺 — « petite héronne ») des Yōkai (妖怪) hante les falaises d’Awaji."}'
    UNION ALL SELECT  'Kagaribi', 'passive', '{}',
        '{"life_report":"Depuis des siecles moi Hiuchi (火打 — « pierre à feu ») des Yōkai (妖怪) je terrorise le cap sud de Tosa."}'
) AS entry
JOIN {prefix}workers w ON w.lastname = entry.lastname
JOIN {prefix}controller_worker cw ON cw.worker_id = w.id;
;

-- Add powers to the workers :
INSERT INTO {prefix}worker_powers (worker_id, link_power_type_id)
SELECT
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM
    workers w
JOIN (
    SELECT 'Kotatsu' AS lastname, 'Biwa Hōshi (琵琶法師) – Conteur aveugle itinérant' AS power_name
    UNION ALL SELECT 'Kotatsu', 'Tokkuri (徳利) – Bouteille à saké'
    UNION ALL SELECT 'Kotatsu', 'Kōdō (香道) – Voie de l’encens'
    UNION ALL SELECT 'Kotatsu', 'Kagenkō (影言講) – L’art de la parole de l’ombre'
    UNION ALL SELECT 'Kotatsu', 'Kagekui-ryū (影喰流) – École du Mange-Ombre'
    UNION ALL SELECT 'Jizane', 'Kannushi (神主) – Prêtre shintō'
    UNION ALL SELECT 'Jizane', 'Chigiriki (契木) – Masse à chaîne'
    UNION ALL SELECT 'Jizane', 'Kōdō (香道) – Voie de l’encens'
    UNION ALL SELECT 'Jizane', 'Kadō / Ikebana (華道 / 生け花) – Art floral'
    UNION ALL SELECT 'Jizane', 'Kagenkō (影言講) – L’art de la parole de l’ombre'
    UNION ALL SELECT 'Jizane', 'Kagekui-ryū (影喰流) – École du Mange-Ombre'
    UNION ALL SELECT 'Noayame', 'Kagemusha (影武者) – Sosie du seigneur'
    UNION ALL SELECT 'Noayame', 'Tessen (鉄扇) – Éventail de fer'
    UNION ALL SELECT 'Noayame', 'Kōdō (香道) – Voie de l’encens'
    UNION ALL SELECT 'Noayame', 'Kadō / Ikebana (華道 / 生け花) – Art floral'
    UNION ALL SELECT 'Noayame', 'Kagenkō (影言講) – L’art de la parole de l’ombre'
    UNION ALL SELECT 'Noayame', 'Kagekui-ryū (影喰流) – École du Mange-Ombre'
    UNION ALL SELECT 'Kagaribi', 'Kōsaku (工作) – Saboteur'
    UNION ALL SELECT 'Kagaribi', 'Teppō (鉄砲) – Un mousquet'
    UNION ALL SELECT 'Kagaribi', 'Kōdō (香道) – Voie de l’encens'
    UNION ALL SELECT 'Kagaribi', 'Kadō / Ikebana (華道 / 生け花) – Art floral'
    UNION ALL SELECT 'Kagaribi', 'Kagenkō (影言講) – L’art de la parole de l’ombre'
    UNION ALL SELECT 'Kagaribi', 'Kagekui-ryū (影喰流) – École du Mange-Ombre'
    UNION ALL SELECT 'Kagaribi', 'Encens Coréen'
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;


-- Create the start workers for Jōdo-shinshū (浄土真宗)
WITH names_data(firstname, lastname, origin_name, zone_name) AS (
    VALUES
        ('Renjun (蓮淳)', 'fils de Rennyo (蓮如)', 'Honshu - Kyoto', 'Montagnes d’Iyo')
)
INSERT INTO {prefix}workers (firstname, lastname, origin_id, zone_id)
SELECT
    nd.firstname,
    nd.lastname,
    wo.id AS origin_id,
    z.id AS zone_id
FROM names_data nd
JOIN {prefix}worker_origins wo ON wo.name = nd.origin_name
JOIN {prefix}zones z ON z.name = nd.zone_name;

-- Now, get controller ID once
-- Then add the links
WITH controller AS (
    SELECT id AS controller_id FROM {prefix}controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)'
),
worker_ids AS (
    SELECT id AS worker_id FROM {prefix}workers WHERE lastname IN ('fils de Rennyo (蓮如)')
)
INSERT INTO {prefix}controller_worker (controller_id, worker_id)
SELECT controller.controller_id, worker_ids.worker_id
FROM controller, worker_ids;

-- Add actions to the workers :
INSERT INTO {prefix}worker_actions (
    worker_id, controller_id, turn_number, zone_id, action_choice, action_params, report
)
SELECT
    w.id,
    cw.controller_id,
    0,
    w.zone_id,
    entry.action_choice,
    entry.action_params::json,
    entry.report::json
FROM (
    SELECT 'fils de Rennyo (蓮如)' AS lastname, 'passive' AS action_choice, '{}' AS action_params, 
        '{"life_report":"Je suis Renjun (蓮淳), un des fils de Rennyo (蓮如) et l’oncle d’Ennyo (円如), venu du nord de Kyoto pour soutenir ma famille et subvertir la religion Tendai dans les Montagnes d’Iyo."}' AS report
) AS entry
JOIN {prefix}workers w ON w.lastname = entry.lastname
JOIN {prefix}controller_worker cw ON cw.worker_id = w.id;
;

-- Add powers to the workers :
INSERT INTO {prefix}worker_powers (worker_id, link_power_type_id)
SELECT
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM
    workers w
JOIN (
    SELECT 'fils de Rennyo (蓮如)' AS lastname, 'Reishi (霊師) – Médium ou exorciste' AS power_name
    UNION ALL SELECT 'fils de Rennyo (蓮如)', 'Tokkuri (徳利) – Bouteille à saké'
    UNION ALL SELECT 'fils de Rennyo (蓮如)', 'Reiki / Kujikiri (霊気 / 九字切り) – Pratiques ésotériques'
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;


-- Create the Shougunat start workers
/*
Asakura Mitsunao-dono (朝倉 光直)
Tsūshi (通使) – Diplomate ou émissaire
Go-ban (碁盤) – Plateau de Go

Ibara-dono  (茨の紅)
Ninja-kahō (忍者家法) – Membre d’une lignée de ninjas
Shamisen (三味線) – Instrument à cordes

Takeda Renryū-dono  (武田 蓮竜)
Kuro-hatamoto (黒旗本) – Garde d’élite
Katana (刀) – L’arme emblématique du samouraï

Sōen-dono  (僧円)
Biwa Hōshi (琵琶法師) – Conteur aveugle itinérant
Chadōgu (茶道具) – Ustensiles du thé

*/
WITH names_data(firstname, lastname, origin_name, zone_name) AS (
    VALUES
        ('Asakura(朝倉)', 'Mitsunao-dono(光直-殿)', 'Honshu - Kyoto', 'Cité Impériale de Kyoto'),
        ('', 'Ibara-dono(茨の紅-殿)', 'Honshu - Kyoto', 'Cité Impériale de Kyoto'),
        ('Takeda(武田)', 'Renryū-dono(蓮竜-殿)', 'Honshu - Kyoto', 'Cité Impériale de Kyoto'),
        ('', 'Sōen-dono(僧円-殿)', 'Honshu - Kyoto', 'Cité Impériale de Kyoto')
)
INSERT INTO {prefix}workers (firstname, lastname, origin_id, zone_id)
SELECT
    nd.firstname,
    nd.lastname,
    wo.id AS origin_id,
    z.id AS zone_id
FROM names_data nd
JOIN {prefix}worker_origins wo ON wo.name = nd.origin_name
JOIN {prefix}zones z ON z.name = nd.zone_name;


-- Now, get controller ID once
-- Then add the links
WITH controller AS (
    SELECT id AS controller_id FROM {prefix}controllers WHERE lastname = 'Ashikaga (足利)'
),
worker_ids AS (
    SELECT id AS worker_id FROM {prefix}workers WHERE lastname IN ('Mitsunao-dono(光直-殿)', 'Ibara-dono(茨の紅-殿)', 'Renryū-dono(蓮竜-殿)', 'Sōen-dono(僧円-殿)')
)
INSERT INTO {prefix}controller_worker (controller_id, worker_id)
SELECT controller.controller_id, worker_ids.worker_id
FROM controller, worker_ids;

-- Add actions to the workers :
INSERT INTO {prefix}worker_actions (
    worker_id, controller_id, turn_number, zone_id, action_choice, action_params, report
)
SELECT
    w.id,
    cw.controller_id,
    0,
    w.zone_id,
    entry.action_choice,
    entry.action_params::json,
    entry.report::json
FROM (
    SELECT 'Mitsunao-dono(光直-殿)' AS lastname, 'passive' AS action_choice, '{}' AS action_params, 
        '{"life_report":"Je Mitsunao-dono(光直-殿) du clan Asakura(朝倉), conseiller personnel du Shogun au palais impérial de Kyoto."}' AS report
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'passive', '{}',
        '{"life_report":"Je suis Ibara-dono(茨の紅-殿), la première courtisane de Kyoto, mon nom est connu du Shogun lui-même."}' AS report
    UNION ALL SELECT  'Renryū-dono(蓮竜-殿)', 'passive', '{}',
        '{"life_report":"Je suis Renryū-dono(蓮竜-殿), un cousin du clan Takeda (武田), révolté contre les actions de Takeda Shingen (信玄), je suis venu de l’est pour protéger le Shogun."}' AS report
    UNION ALL SELECT  'Sōen-dono(僧円-殿)', 'passive', '{}',
        '{"life_report":"Je suis Sōen-dono(僧円-殿), maitre moine Tendai au service du Shogun au palais impérial de Kyoto."}'
) AS entry
JOIN {prefix}workers w ON w.lastname = entry.lastname
JOIN {prefix}controller_worker cw ON cw.worker_id = w.id;
;

-- Add powers to the workers :
INSERT INTO {prefix}worker_powers (worker_id, link_power_type_id)
SELECT
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM
    workers w
JOIN (
    SELECT 'Sōen-dono(僧円-殿)' AS lastname, 'Kannushi (神主) – Prêtre shintō' AS power_name
    UNION ALL SELECT 'Sōen-dono(僧円-殿)', 'Chadōgu (茶道具) – Ustensiles du thé'
    UNION ALL SELECT 'Sōen-dono(僧円-殿)', 'Kadō / Ikebana (華道 / 生け花) – Art floral'
    UNION ALL SELECT 'Sōen-dono(僧円-殿)', 'Chadō (茶道) – Voie du thé'
    UNION ALL SELECT 'Sōen-dono(僧円-殿)', 'Bugaku (舞楽) – Danse de cour'
    UNION ALL SELECT 'Sōen-dono(僧円-殿)', 'Encens Coréen'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Kuro-hatamoto (黒旗本) – Garde d’élite'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Katana (刀) – L’arme emblématique du samouraï'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Chadō (茶道) – Voie du thé'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Iaijutsu (居合術) – Art du sabre'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Bajutsu (馬術) – Art de l’équitation militaire'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Cheval Sanuki'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Ninja-kahō (忍者家法) – Membre d’une lignée de ninjas'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Shamisen (三味線) – Instrument à cordes'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Kyūjutsu (弓術) – Art du tir à l’arc'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Shodō (書道) – Calligraphie'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Bugaku (舞楽) – Danse de cour'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Chadō (茶道) – Voie du thé'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Thé d’Oboké'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Tsūshi (通使) – Diplomate ou émissaire'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Go-ban (碁盤) – Plateau de Go'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Heihō (兵法) – Stratégie militaire'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Bugaku (舞楽) – Danse de cour'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Shodō (書道) – Calligraphie'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Armure en fer de Tosa'
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;