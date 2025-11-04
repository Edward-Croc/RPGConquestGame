-- Warning: If you read this file, you will no longer be eligible to participate as a player.

UPDATE config SET value = '1,2,3,4,5,6,7' WHERE name = 'recrutement_origin_list';
UPDATE config SET value =  '1,2,3,4,5,6' WHERE name = 'local_origin_list';
UPDATE config SET value =  '1' WHERE name = 'recrutement_disciplines';
UPDATE config SET value =  '{"age": ["2","4","6"]}' WHERE name = 'age_discipline';
UPDATE config SET value =  'revendique la province' WHERE name = 'txt_ps_claim';
UPDATE config SET value =  'revendiquer la province' WHERE name = 'txt_inf_claim';

-- MAP INFO
UPDATE config SET value =  'Carte_Shikoku_1555.jpg' WHERE name = 'map_file';
UPDATE config SET value =  'Carte de Shikoku' WHERE name = 'map_alt';

-- Text time info
UPDATE config SET value =  'le ' WHERE name = 'controllerNameDenominatorThe';
UPDATE config SET value =  'du' WHERE name = 'controllerNameDenominatorOf';
UPDATE config SET value =  'des' WHERE name = 'controllerLastNameDenominatorOf';
UPDATE config SET value =  'territoire' WHERE name = 'textForZoneType';
UPDATE config SET value =  'Trimestre' WHERE name = 'timeValue';
UPDATE config SET value =  'ce' WHERE name = 'timeDenominatorThis';
UPDATE config SET value =  'le' WHERE name = 'timeDenominatorThe';
UPDATE config SET value =  'du' WHERE name = 'timeDenominatorOf';

UPDATE config SET value =  'Vos protégé.es :' WHERE name = 'textOwnedArtefacts';

INSERT INTO players (username, passwd, is_privileged, url) VALUES
    ('player0', 'yokai', 0, ''),
    ('shingen', 'takeda', 0, ''),
    ('yoshiteru', 'ashizero', 0, 'https://docs.google.com/document/d/1XWEAm-2-gFsGRPqPElp4qtxWfCXLaGOQaZgJbnuGTJ8'),
    ('yoshiaki', 'ashizero', 0, 'https://docs.google.com/document/d/1Qryg_9w8oGfKZ87wqtGlAVzwoMAh26dm92cwuOYSfmg'),
    ('yoshihide', 'ashizero', 0, 'https://docs.google.com/document/d/1FcjTlNsO31TBnwXBQcF2BbuTKzH_gmMc-TGl6g-tW5Y'),
    ('motochika', 'chosone', 0, 'https://docs.google.com/document/d/1HZRuA8IYp4taWFqqZcK7fY5PhyKIBT9DZuzgYYBnWfA'),
    ('kanetsugu', 'chosone', 0, 'https://docs.google.com/document/d/1YdDNPTEudj0YvysCxoiU6UdZPsHrFdHK5goCg88pjeQ'),
    ('shoho', 'chosone', 0, 'https://docs.google.com/document/d/1NU7d51p--9oeaaN6nlCBr1a8990JJw4OMDBA77wbyVE'),
    ('nagayoshi', 'miytwo', 0, 'https://docs.google.com/document/d/1W95lJ9bq0-KWRTCijgQ0Ua4koFsjTdLp3nvPTrnvCOc'),
    ('fudzisan', 'miytwo', 0, 'https://docs.google.com/document/d/1s_i_H1q2s3lPN26UQTQODfED81XXgWWy0qkUvGvm8L8'),
    ('sogo', 'miytwo', 0, 'https://docs.google.com/document/d/1qIumW_aa9LJAv7u2ie1MyEV4dblRuiTPKhcmuVmq2dY'),
    ('rennyo', 'renthree', 0, 'https://docs.google.com/document/d/1eynG0_wLeCS_8Z6991qX2dGwxFbH7e0Ln-Zcpb6XKEA'),
    ('ren-jo', 'renthree', 0, 'https://docs.google.com/document/d/1WC11-CiBHk1pkfxub39VdR7iS29n_tl7Krca9w4khxI'),
    ('ennyo', 'renthree', 0, 'https://docs.google.com/document/d/1yI4IPxk5rHHWrtap5NBEAFRIX3SIF8TJkOrjzwcH0Hw'),
    ('tadaoki', 'hosfour', 0, 'https://docs.google.com/document/d/1b-Vk3Pc7zhCORjOuNG968TNq-1YMcGxx8bmJE_chIzo'),
    ('tama', 'hosfour', 0, 'https://docs.google.com/document/d/1O9_iUsfAbT_1AfUVaQxe9Ogrjont__mqWVdIROGUcAg'),
    ('joha', 'hosfour', 0, 'https://docs.google.com/document/d/14dIXHkiLZ9LFnRPbr3WHfxuBhkQupkASIGoqLx6i3Ug'),
    ('murai', 'wakfive', 0, 'https://docs.google.com/document/d/1phCVmNoAXUGi5ukGLwIQihu76DOMjLwaPdC_q5xBsV4'),
    ('tsuruhime', 'wakfive', 0, 'https://docs.google.com/document/d/1eAjNsf8kSXhPeeymYpZgpBFvkkb-08SJtdhdWjB4CJI'),
    ('wang', 'wakfive', 0, 'https://docs.google.com/document/d/1TWo7xseEmTo-S8x8qSfXCV1mtP42omzMR_FuMHOf9RI'),
    ('kukai', 'kobsix', 0, 'https://docs.google.com/document/d/18n06xOJueWRKJ9lq2GbVgk3C7vC031YOxeIWB4lwlvc'),
    ('satomura', 'kobsix', 0, 'https://docs.google.com/document/d/1YVUapPuI1lmko_BUjhlHnbU-ZvaZHgWNbdOtoSSXKtU'),
    ('yubien', 'kobsix', 0, 'https://docs.google.com/document/d/1nORj-ibMjS0-vqIFV7cnmfW9-vur44TLEOwpZuPvHb0')
;

INSERT INTO factions (name) VALUES
    ('Samouraïs Chōsokabe')
    ,('Samouraïs Miyoshi')
    ,('Samouraïs Hosokawa')
    ,('Samouraïs Ashikaga')
    ,('Samouraïs Kōno')
    ,('Samouraïs Takeda')
    ,('Moines Bouddhistes')
    ,('Ikkō-ikki') -- https://fr.wikipedia.org/wiki/Ikk%C5%8D-ikki
    ,('Kaizokushū') -- (海賊衆)
    ,('Chrétiens') -- https://histoiredujapon.com/2021/04/05/etrangers-japon-ancien/#index_id1
    ,('Yōkai')
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
INSERT INTO controllers (
    firstname, lastname, ia_type, secret_controller,
    url, story, can_build_base,
    faction_id, fake_faction_id
) VALUES
    ('妖怪 de', 'Shikoku (四国)', 'passif', 1, -- https://fr.wikipedia.org/wiki/Y%C5%8Dkai#:~:text=Le%20terme%20y%C5%8Dkai%20(%E5%A6%96%E6%80%AA%2C%20%C2%AB,la%20culture%20orale%20au%20Japon.
        'https://docs.google.com/document/d/1gLcK961mCzDSAvaPVTy886JmRTpkPiACUyP8ArSkoPI', '',
        1,
        (SELECT ID FROM factions WHERE name = 'Yōkai'),
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes')
    ),
    ('Shogunat', 'Ashikaga (足利)', 'passif', 1,
        'https://docs.google.com/document/d/1CMSbdrjJqZz_wabuMNKS1qSh6T7apqDq_Ag7NpI7Xx4', '', 
        1,
        (SELECT ID FROM factions WHERE name = 'Samouraïs Ashikaga'),
        (SELECT ID FROM factions WHERE name = 'Samouraïs Ashikaga')
    ), 
    ('Clan', 'Kōno (河野)', NULL, 1, -- https://it.wikipedia.org/wiki/Clan_K%C5%8Dno
        'https://docs.google.com/document/d/1SCqA_PNN6U_42t4FVYE_kIXBW1xUsS-eHwBszIKD5KI', '',
        0,
        (SELECT ID FROM factions WHERE name = 'Samouraïs Kōno'),
        (SELECT ID FROM factions WHERE name = 'Samouraïs Kōno')
    );

INSERT INTO controllers (
    firstname, lastname,
    faction_id, fake_faction_id,
    url, 
    story
) VALUES
    (
        'Clan', 'Chōsokabe (長宗我部)', -- https://fr.wikipedia.org/wiki/Clan_Ch%C5%8Dsokabe
        (SELECT ID FROM factions WHERE name = 'Samouraïs Chōsokabe' ),
        (SELECT ID FROM factions WHERE name = 'Samouraïs Chōsokabe' ),
        'https://docs.google.com/document/d/1P2Mz4PAkw00DMXXG4hgyod3FJNJkdXHU2JHbvkn327I',
        ''
    ),
    (
        'Clan', 'Hosokawa (細川)', -- https://fr.wikipedia.org/wiki/Clan_Hosokawa
        (SELECT ID FROM factions WHERE name = 'Samouraïs Hosokawa' ),
        (SELECT ID FROM factions WHERE name = 'Samouraïs Hosokawa' ),
        'https://docs.google.com/document/d/14R_8j-5zbjC8Wzm72SsHS9QC8KDQ8l3AbkW5ZNmECAg',
        ''
    ),
    (
        'Clan', 'Miyoshi (三好)',  -- https://fr.wikipedia.org/wiki/Clan_Miyoshi
        (SELECT ID FROM factions WHERE name = 'Chrétiens' ),
        (SELECT ID FROM factions WHERE name = 'Samouraïs Miyoshi' ),
        'https://docs.google.com/document/d/1EVtV5G1xr9O2GeOep8D3SmrEp1i7Fw5wOnuj3aGSui4',
        ''
    ),
    (
        'Temple', 'Jōdo-shinshū (浄土真宗)', -- https://fr.wikipedia.org/wiki/Rennyo
        (SELECT ID FROM factions WHERE name = 'Ikkō-ikki' ),
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes' ),
        'https://docs.google.com/document/d/1xKYPslqDdxlps6A4ydFh_iUu6cvdP5VC9145goVmLrA',
        ''
    ),
    (
        'Temple', 'Tendai (天台宗)', -- https://en.wikipedia.org/wiki/K%C5%ABkai
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes'),
        (SELECT ID FROM factions WHERE name = 'Moines Bouddhistes'),
        'https://docs.google.com/document/d/1bP2AGEA7grFw4k4CatLrTmeZkDDlczTqUEGg151GpQ8',
        ''
    ),
    (
        'Groupe', 'Wako (和光)',
        (SELECT ID FROM factions WHERE name = 'Kaizokushū' ),
        (SELECT ID FROM factions WHERE name = 'Kaizokushū' ),
        'https://docs.google.com/document/d/1lgVjCyPTpzxA0nU649PyeDldVxCKtLSh9t7AJOmwREg',
        ''
    )
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
INSERT INTO controllers (
    firstname, lastname, ia_type, secret_controller,
    url, story, can_build_base,
    turn_recruited_workers, turn_firstcome_workers,
    faction_id, fake_faction_id
) VALUES
    ('Clan', 'Sogō (十河)', NULL, 1, 
        NULL, '',
        0, 1, 1,
        (SELECT ID FROM factions WHERE name = 'Chrétiens'),
        (SELECT ID FROM factions WHERE name = 'Chrétiens')
    ),
    ('Clan', 'Takeda (武田)', 'passif', 1, 
        'https://docs.google.com/document/d/1xSeM0-AGy8TakF7F-XvjJKMUmZ76hZfqLRKAzZO5s9A/edit?tab=t.0#heading=h.v3wi0mldiz4e', '',
        0, 0, 0,
        (SELECT ID FROM factions WHERE name = 'Samouraïs Takeda'),
        (SELECT ID FROM factions WHERE name = 'Samouraïs Takeda')
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
        (SELECT ID FROM players WHERE username = 'yoshiteru'),
        (SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'yoshiaki'),
        (SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'yoshihide'),
        (SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'motochika'),
        (SELECT ID FROM controllers WHERE lastname = 'Chōsokabe (長宗我部)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'shoho'),
        (SELECT ID FROM controllers WHERE lastname = 'Chōsokabe (長宗我部)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'kanetsugu'),
        (SELECT ID FROM controllers WHERE lastname = 'Chōsokabe (長宗我部)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'nagayoshi'),
        (SELECT ID FROM controllers WHERE lastname in ('Miyoshi (三好)'))
    ),
    (
        (SELECT ID FROM players WHERE username = 'fudzisan'),
        (SELECT ID FROM controllers WHERE lastname in ('Miyoshi (三好)'))
    ),
    (
        (SELECT ID FROM players WHERE username = 'sogo'),
        (SELECT ID FROM controllers WHERE lastname in ('Miyoshi (三好)'))
    ),
    (
        (SELECT ID FROM players WHERE username = 'rennyo'),
        (SELECT ID FROM controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'ren-jo'),
        (SELECT ID FROM controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'ennyo'),
        (SELECT ID FROM controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'tadaoki'),
        (SELECT ID FROM controllers WHERE lastname = 'Hosokawa (細川)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'tama'),
        (SELECT ID FROM controllers WHERE lastname = 'Hosokawa (細川)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'joha'),
        (SELECT ID FROM controllers WHERE lastname = 'Hosokawa (細川)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'murai'),
        (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'tsuruhime'),
        (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'wang'),
        (SELECT ID FROM controllers WHERE lastname = 'Wako (和光)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'kukai'),
        (SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'satomura'),
        (SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'yubien'),
        (SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'tsuruhime'),
        (SELECT ID FROM controllers WHERE lastname = 'Kōno (河野)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'yubien'),
        (SELECT ID FROM controllers WHERE lastname = 'Kōno (河野)')
    ),
    (
        (SELECT ID FROM players WHERE username = 'shingen'),
        (SELECT ID FROM controllers WHERE lastname = 'Takeda (武田)')
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
INSERT INTO powers ( name, enquete, attack, defence, other, description) VALUES
    ('Cheval Sanuki', 0, 1,1 
        ,'{"hidden" : "0", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Province de Sanuki", "worker_in_zone": "Province de Sanuki" } }'
        , ', les meilleures montures de Shikoku ont le pied sûr et rapide'
    )
    , ('Armure en fer de Tosa', 0, 1,1
        ,'{"hidden" : "0", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Cap sud de Tosa", "worker_in_zone": "Cap sud de Tosa"  } }'
        , ', faite de l’acier sombre extrait du cœur des montagnes et forgé en écailles plus dures que celles des tortues
'
    )
    , ('Thé d’Oboké', 1, 0,0
        ,'{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Vallée d’Iya et d’Oboké d’Awa", "worker_in_zone": "Vallée d’Iya et d’Oboké d’Awa" } }'
        ,', un breuvage rare qui élève l’âme'
    )
    , ('Encens Coréen', 1, 0,0
        ,'{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Côte Ouest d’Iyo", "worker_in_zone": "Côte Ouest d’Iyo"} }'
        ,', fabriqué à partir de résines et d’épices introuvables au Japon'
    )
;

INSERT INTO  link_power_type ( power_type_id, power_id ) VALUES
    ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Cheval Sanuki'))
    , ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Armure en fer de Tosa'))
    , ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Thé d’Oboké'))
    , ((SELECT ID FROM power_types WHERE name = 'Transformation'),(SELECT ID FROM powers WHERE name = 'Encens Coréen'))
;

UPDATE config SET value = '''Sōjutsu (槍術) – Art de la lance (Yari)'', ''Kyūjutsu (弓術) – Art du tir à l’arc'', ''Shodō (書道) – Calligraphie'', ''Kadō / Ikebana (華道 / 生け花) – Art floral'''
WHERE name = 'basePowerNames';

-- insert Powers
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

    -- Samouraïs Chōsokabe
    ,('Kenjutsu (剣術) – Art du sabre', 0, 2,1,
      ', la pratique du katana en combat' )
    ,('Heihō (兵法) – Stratégie militaire', 1, 1,1,
      ', l’étude de la tactique, souvent influencée par les textes chinois comme L’Art de la Guerre de Sun Tzu' )
    ,('Waka (和歌) – Poésie classique', 2, 0,0,
      ', plus ancienne que le haïku, utilisée dans les échanges lettrés et parfois politiques' )

    -- Miyoshi Samouraïs
    ,('Hōjutsu (砲術) – Art des armes à feu (teppō)', -1, 2,2,
      ', développé après l’introduction des mousquets portugais vers 1543' )
    ,('Bajutsu (馬術) – Art de l’équitation militaire', 1, 1,1,
      ', inclut la cavalerie et le tir à l’arc monté' )
    ,('Gagaku (雅楽) – Musique de cour', 2, 0,0,
      ', peu courante chez les samouraïs de terrain, mais appréciée dans les cercles aristocratiques ou les familles cultivées' )

    -- Samouraïs Hosokawa
    ,('Iaijutsu (居合術) – Art du sabre', 0, 2,1,
      ' de dégainer et frapper en un mouvement' )
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
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Iaijutsu (居合術) – Art du sabre')),
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
    ((SELECT ID FROM power_types WHERE name = 'Discipline'), (SELECT ID FROM powers WHERE name = 'Kagekui-ryū (影喰流) – École du Mange-Ombre'))
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- Add base powers to the factions :
-- Samouraïs Chōsokabe
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Chōsokabe'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Kenjutsu (剣術) – Art du sabre'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Chōsokabe'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Heihō (兵法) – Stratégie militaire'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Chōsokabe'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Waka (和歌) – Poésie classique'
    ));

-- Samouraïs Miyoshi /Chrétiens 
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Miyoshi'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Hōjutsu (砲術) – Art des armes à feu (teppō)'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Miyoshi'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Bajutsu (馬術) – Art de l’équitation militaire'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Miyoshi'), (
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

-- Samouraïs Hosokawa
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Hosokawa'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Iaijutsu (居合術) – Art du sabre'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Hosokawa'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Bugaku (舞楽) – Danse de cour'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Hosokawa'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Chadō (茶道) – Voie du thé'
    ));

-- Samouraïs Ashikaga
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Ashikaga'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Iaijutsu (居合術) – Art du sabre'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Ashikaga'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Bugaku (舞楽) – Danse de cour'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Ashikaga'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Chadō (茶道) – Voie du thé'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Ashikaga'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Gagaku (雅楽) – Musique de cour'
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

-- Samouraïs Kōno
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Kōno'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Iaijutsu (居合術) – Art du sabre'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Kōno'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Haikai / Haiku (俳諧 / 俳句) – Poésie courte'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Kōno'), (
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

-- Takeda
INSERT INTO faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Takeda'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Bajutsu (馬術) – Art de l’équitation militaire'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Takeda'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Heihō (兵法) – Stratégie militaire'
    )),
    ((SELECT ID FROM factions WHERE name = 'Samouraïs Takeda'), (
        SELECT link_power_type.ID FROM link_power_type JOIN powers ON powers.ID = link_power_type.power_id
        WHERE powers.name = 'Chadō (茶道) – Voie du thé'
    ));

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
INSERT INTO ressources_config (
    ressource_name, 
    presentation,
    stored_text,
    is_rollable,
    is_stored,
    base_building_cost,
    base_moving_cost,
    location_repaire_cost
) VALUES (
    'Koku', 
        -- %$1s : amount, %$2s : ressource_name
    'La production excédentaire de votre clan qui sera investie dans son développement si elle n’est pas dépensée est égale à <strong>%s %s</strong>.',
    -- %$1s : amount_stored, %$2s : ressource_name
    '<strong>%s %s</strong> ont été investi dans le développement du clan depuis le printemps 1555.',
    FALSE,
    TRUE,
    1000,
    1000,
    1000
);

INSERT INTO controller_ressources (
    controller_id, ressource_id,
    amount, amount_stored, end_turn_gain
) VALUES 
    ((SELECT ID FROM controllers WHERE lastname = 'Chōsokabe (長宗我部)'), (SELECT ID FROM ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM controllers WHERE lastname = 'Hosokawa (細川)'), (SELECT ID FROM ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM controllers WHERE lastname = 'Miyoshi (三好)'), (SELECT ID FROM ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)'), (SELECT ID FROM ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM controllers WHERE lastname = 'Tendai (天台宗)'), (SELECT ID FROM ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM controllers WHERE lastname = 'Wako (和光)'), (SELECT ID FROM ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM controllers WHERE lastname = 'Sogō (十河)'), (SELECT ID FROM ressources_config WHERE ressource_name = 'Koku')
        , 0, 0, 0),
    ((SELECT ID FROM controllers WHERE lastname = 'Kōno (河野)'), (SELECT ID FROM ressources_config WHERE ressource_name = 'Koku')
        , 0, 0, 0),
    ((SELECT ID FROM controllers WHERE lastname = 'Takeda (武田)'), (SELECT ID FROM ressources_config WHERE ressource_name = 'Koku')
        , 2000, 0, 2000),
    ((SELECT ID FROM controllers WHERE lastname = 'Ashikaga (足利)'), (SELECT ID FROM ressources_config WHERE ressource_name = 'Koku')
        , 2000, 0, 2000)
;
