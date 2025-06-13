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

-- First, get origin_id and zone_id once
-- Then add the names
WITH origin_zone AS (
    SELECT
        (SELECT id FROM worker_origins WHERE name = 'Shikoku - Shödoshima') AS origin_id,
        (SELECT id FROM zones WHERE name = 'Ile de Shödoshima') AS zone_id
)
INSERT INTO workers (firstname, lastname, origin_id, zone_id)
SELECT firstname, lastname, origin_id, zone_id
FROM origin_zone,
     (VALUES
        ('Kosagi', 'Kotatsu'),
        ('Iwao', 'Jizane'),
        ('Kazusa', 'Noayame'),
        ('Hiuchi', 'Kagaribi')
     ) AS names(firstname, lastname);

-- Now, get controller ID once
-- Then add the links
WITH controller AS (
    SELECT id AS controller_id FROM controllers WHERE lastname = 'Shikoku (四国)'
),
worker_ids AS (
    SELECT id AS worker_id FROM workers WHERE lastname IN ('Kotatsu', 'Jizane', 'Noayame', 'Kagaribi')
)
INSERT INTO controller_worker (controller_id, worker_id)
SELECT controller.controller_id, worker_ids.worker_id
FROM controller, worker_ids;

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
    entry.action_params::json
FROM (
    SELECT 'Kotatsu' AS lastname, 'passive' AS action_choice, '{}' AS action_params
    UNION ALL SELECT 'Jizane', 'passive', '{}'
    UNION ALL SELECT  'Noayame', 'passive', '{}'
    UNION ALL SELECT  'Kagaribi', 'passive', '{}'
) AS entry
JOIN workers w ON w.lastname = entry.lastname
JOIN controller_worker cw ON cw.worker_id = w.id;
;

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