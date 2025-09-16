-- Warning: If you read this file, you will no longer be eligible to participate as a player.

-- Create the Yōkai start workers
/*
Yōkai de la Paresse (Biwa, vallon, saké)
('Kosagi', 'Kotatsu')
Kosagi (« petite héronne ») évoque la tranquillité trompeuse, Kotatsu renvoie à la chaleur paresseuse d’un foyer endormi.

Yōkai de la Roche (Chigiriki, montagne, chaînes)
('Iwao', 'Jizane')
Iwao (岩男 — « homme-rocher ») souligne la solidité minérale, Jizane est un nom inventé à consonance ancienne, proche de « jizō » et « zane » (chaîne/bruit de métal).

Yōkai du Vent (Tessen, pavillon d’Awaji, froid)
('Kazusa', 'Noayame')
Kazusa (風佐 — « celui qui suit le vent »), Noayame combine no (champs) et ayame (iris — souvent liés aux esprits et au printemps), donnant un nom éthéré et flottant.

Yōkai du Feu (Teppō, cap sud de Kōchi, forge, poudre)
('Hiuchi', 'Kagaribi')
Hiuchi (火打 — « pierre à feu »), Kagaribi (篝火 — « feu de signalisation / brasier »), ensemble ils forment un duo sonore sec et évocateur.
*/
INSERT INTO workers (firstname, lastname, origin_id, zone_id)
SELECT
    nd.firstname,
    nd.lastname,
    wo.id AS origin_id,
    z.id AS zone_id
FROM (
    SELECT 'Kosagi' AS firstname, 'Kotatsu' AS lastname, 'Shikoku - Shōdoshima' AS origin_name, 'Ile de Shōdoshima' AS zone_name
    UNION ALL SELECT 'Iwao', 'Jizane', 'Shikoku - Iyo', 'Montagnes d’Iyo'
    UNION ALL SELECT 'Kazusa', 'Noayame', 'Shikoku - Awaji', 'Ile dAwaji'
    UNION ALL SELECT 'Hiuchi', 'Kagaribi', 'Shikoku - Tosa', 'Cap sud de Tosa'
) AS nd
JOIN worker_origins wo ON wo.name = nd.origin_name
JOIN zones z ON z.name = nd.zone_name;


-- Now, get controller ID once
-- Then add the links
INSERT INTO controller_worker (controller_id, worker_id)
SELECT c.id AS controller_id, w.id AS worker_id
FROM controllers c, workers w
WHERE c.lastname = 'Shikoku (四国)'
AND w.lastname IN ('Kotatsu', 'Jizane', 'Noayame', 'Kagaribi');

-- Add actions to the workers :
INSERT INTO worker_actions (
    worker_id, controller_id, turn_number, zone_id, action_choice, action_params
)
SELECT
    w.id,
    cw.controller_id,
    0,
    w.zone_id,
    entry.action_choice,
    entry.action_params
FROM (
    SELECT 'Kotatsu' AS lastname, 'passive' AS action_choice, '{}' AS action_params
    UNION ALL SELECT 'Jizane', 'passive', '{}'
    UNION ALL SELECT  'Noayame', 'passive', '{}'
    UNION ALL SELECT  'Kagaribi', 'passive', '{}'
) AS entry
JOIN workers w ON w.lastname = entry.lastname
JOIN controller_worker cw ON cw.worker_id = w.id;

-- Add powers to the workers :
INSERT INTO worker_powers (worker_id, link_power_type_id)
SELECT
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM
    workers w
JOIN (
    SELECT 'Kotatsu' AS lastname, 'Biwa Hōshi (琵琶法師) – Conteur aveugle itinérant' AS power_name
    UNION ALL SELECT 'Kotatsu', 'Tokkuri (徳利) – Bouteille à saké'
    UNION ALL SELECT 'Kotatsu', 'Kōdō (香道) – Voie de l’encens'
    UNION ALL SELECT 'Kotatsu', 'Kadō / Ikebana (華道 / 生け花) – Art floral'
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
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;


-- Create the start workers for Jōdo-shinshū (浄土真宗)
INSERT INTO workers (firstname, lastname, origin_id, zone_id)
SELECT
    nd.firstname,
    nd.lastname,
    wo.id AS origin_id,
    z.id AS zone_id
FROM (
    SELECT 'Shonyo' AS firstname, 'fils de Rennyo (蓮如)' AS lastname, 'Honshu - Kyoto' AS origin_name, 'Montagnes d’Iyo' AS zone_name
) AS nd
JOIN worker_origins wo ON wo.name = nd.origin_name
JOIN zones z ON z.name = nd.zone_name;

-- Now, get controller ID once
-- Then add the links
INSERT INTO controller_worker (controller_id, worker_id)
SELECT c.id AS controller_id, w.id AS worker_id
FROM controllers c, workers w
WHERE c.lastname = 'Jōdo-shinshū (浄土真宗)'
AND w.lastname IN ('fils de Rennyo (蓮如)');

-- Add actions to the workers :
INSERT INTO worker_actions (
    worker_id, controller_id, turn_number, zone_id, action_choice, action_params
)
SELECT
    w.id,
    cw.controller_id,
    0,
    w.zone_id,
    entry.action_choice,
    entry.action_params
FROM (
    SELECT 'fils de Rennyo (蓮如)' AS lastname, 'passive' AS action_choice, '{}' AS action_params
) AS entry
JOIN workers w ON w.lastname = entry.lastname
JOIN controller_worker cw ON cw.worker_id = w.id;

-- Add powers to the workers :
INSERT INTO worker_powers (worker_id, link_power_type_id)
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
INSERT INTO workers (firstname, lastname, origin_id, zone_id)
SELECT
    nd.firstname,
    nd.lastname,
    wo.id AS origin_id,
    z.id AS zone_id
FROM (
    SELECT 'Asakura(朝倉)' AS firstname, 'Mitsunao-dono(光直-殿)' AS lastname, 'Honshu - Kyoto' AS origin_name, 'Cité Impériale de Kyoto' AS zone_name
    UNION ALL SELECT '', 'Ibara-dono(茨の紅-殿)', 'Honshu - Kyoto', 'Cité Impériale de Kyoto'
    UNION ALL SELECT 'Takeda(武田)', 'Renryū-dono(蓮竜-殿)', 'Honshu - Kyoto', 'Cité Impériale de Kyoto'
    UNION ALL SELECT '', 'Sōen-dono(僧円-殿)', 'Honshu - Kyoto', 'Cité Impériale de Kyoto'
) AS nd
JOIN worker_origins wo ON wo.name = nd.origin_name
JOIN zones z ON z.name = nd.zone_name;


-- Now, get controller ID once
-- Then add the links
INSERT INTO controller_worker (controller_id, worker_id)
SELECT c.id AS controller_id, w.id AS worker_id
FROM controllers c, workers w
WHERE c.lastname = 'Ashikaga (足利)'
AND w.lastname IN ('Mitsunao-dono(光直-殿)', 'Ibara-dono(茨の紅-殿)', 'Renryū-dono(蓮竜-殿)', 'Sōen-dono(僧円-殿)');

-- Add actions to the workers :
INSERT INTO worker_actions (
    worker_id, controller_id, turn_number, zone_id, action_choice, action_params
)
SELECT
    w.id,
    cw.controller_id,
    0,
    w.zone_id,
    entry.action_choice,
    entry.action_params
FROM (
    SELECT 'Mitsunao-dono(光直-殿)' AS lastname, 'passive' AS action_choice, '{}' AS action_params
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'passive', '{}'
    UNION ALL SELECT  'Renryū-dono(蓮竜-殿)', 'passive', '{}'
    UNION ALL SELECT  'Sōen-dono(僧円-殿)', 'passive', '{}'
) AS entry
JOIN workers w ON w.lastname = entry.lastname
JOIN controller_worker cw ON cw.worker_id = w.id;

-- Add powers to the workers :
INSERT INTO worker_powers (worker_id, link_power_type_id)
SELECT
    w.id AS worker_id,
    lpt.id AS link_power_type_id
FROM
    workers w
JOIN (
    SELECT 'Sōen-dono(僧円-殿)' AS lastname, 'Biwa Hōshi (琵琶法師) – Conteur aveugle itinérant' AS power_name
    UNION ALL SELECT 'Sōen-dono(僧円-殿)', 'Chadōgu (茶道具) – Ustensiles du thé'
    UNION ALL SELECT 'Sōen-dono(僧円-殿)', 'Kadō / Ikebana (華道 / 生け花) – Art floral'
    UNION ALL SELECT 'Sōen-dono(僧円-殿)', 'Chadō (茶道) – Voie du thé'
    UNION ALL SELECT 'Sōen-dono(僧円-殿)', 'Bugaku (舞楽) – Danse de cour'
    UNION ALL SELECT 'Sōen-dono(僧円-殿)', 'Encens Coréen'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Kuro-hatamoto (黒旗本) – Garde d’élite'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Katana (刀) – L’arme emblématique du samouraï'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Shodō (書道) – Calligraphie'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Iaijutsu (居合術) – Art du sabre'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Bajutsu (馬術) – Art de l’équitation militaire'
    UNION ALL SELECT 'Renryū-dono(蓮竜-殿)', 'Cheval Sanuki'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Ninja-kahō (忍者家法) – Membre d’une lignée de ninjas'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Shamisen (三味線) – Instrument à cordes'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Kyūjutsu (弓術) – Art du tir à l’arc'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Shodō (書道) – Calligraphie'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Bugaku (舞楽) – Danse de cour'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Chadō (茶道) – Voie du thé'
    UNION ALL SELECT 'Ibara-dono(茨の紅-殿)', 'Thé d’Oboké et d’Iya'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Tsūshi (通使) – Diplomate ou émissaire'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Go-ban (碁盤) – Plateau de Go'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Armure en fer de Tosa'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Heihō (兵法) – Stratégie militaire'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Bugaku (舞楽) – Danse de cour'
    UNION ALL SELECT 'Mitsunao-dono(光直-殿)', 'Shodō (書道) – Calligraphie'
) AS wp ON wp.lastname = w.lastname
JOIN powers p ON p.name = wp.power_name
JOIN link_power_type lpt ON lpt.power_id = p.id;