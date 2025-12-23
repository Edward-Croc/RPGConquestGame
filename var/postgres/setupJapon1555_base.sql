-- Warning: If you read this file, you will no longer be eligible to participate as a player.
UPDATE {prefix}config SET value =  '1,2,3,4,5,6,7,8,9,10,11' WHERE name = 'first_come_origin_list';
UPDATE {prefix}config SET value =  '1,2,3,4,5,6,7' WHERE name = 'recrutement_origin_list';
UPDATE {prefix}config SET value =  '1,2,3,4,5,6' WHERE name = 'local_origin_list';
UPDATE {prefix}config SET value =  '1' WHERE name = 'recrutement_disciplines';
UPDATE {prefix}config SET value =  '{"age": ["2","4","6"]}' WHERE name = 'age_discipline';
UPDATE {prefix}config SET value =  'revendique la province' WHERE name = 'txt_ps_claim';
UPDATE {prefix}config SET value =  'revendiquer la province' WHERE name = 'txt_inf_claim';

-- MAP INFO
UPDATE {prefix}config SET value =  'Carte_Shikoku_1555.jpg' WHERE name = 'map_file';
UPDATE {prefix}config SET value =  'Carte de Shikoku' WHERE name = 'map_alt';

-- Text time info
UPDATE {prefix}config SET value =  'le ' WHERE name = 'controllerNameDenominatorThe';
UPDATE {prefix}config SET value =  'du' WHERE name = 'controllerNameDenominatorOf';
UPDATE {prefix}config SET value =  'des' WHERE name = 'controllerLastNameDenominatorOf';
UPDATE {prefix}config SET value =  'territoire' WHERE name = 'textForZoneType';
UPDATE {prefix}config SET value =  'Trimestre' WHERE name = 'timeValue';
UPDATE {prefix}config SET value =  'ce' WHERE name = 'timeDenominatorThis';
UPDATE {prefix}config SET value =  'le' WHERE name = 'timeDenominatorThe';
UPDATE {prefix}config SET value =  'du' WHERE name = 'timeDenominatorOf';

UPDATE {prefix}config SET value =  'Vos protégé.es :' WHERE name = 'textOwnedArtefacts';

INSERT INTO {prefix}players (username, passwd, is_privileged, url) VALUES
    ('player0', 'yokai', False, ''),
    ('shingen', 'takeda', False, ''),
    ('yoshiteru', 'ashikaga', False, 'https://docs.google.com/document/d/1XWEAm-2-gFsGRPqPElp4qtxWfCXLaGOQaZgJbnuGTJ8'),
    ('yoshiaki', 'ashikaga', False, 'https://docs.google.com/document/d/1Qryg_9w8oGfKZ87wqtGlAVzwoMAh26dm92cwuOYSfmg'),
    ('yoshihide', 'ashikaga', False, 'https://docs.google.com/document/d/1Qryg_9w8oGfKZ87wqtGlAVzwoMAh26dm92cwuOYSfmg'),
    ('motochika', 'chosone', False, 'https://docs.google.com/document/d/1HZRuA8IYp4taWFqqZcK7fY5PhyKIBT9DZuzgYYBnWfA'),
    ('kanetsugu', 'chosone', False, 'https://docs.google.com/document/d/1YdDNPTEudj0YvysCxoiU6UdZPsHrFdHK5goCg88pjeQ'),
    ('shoho', 'chosone', False, 'https://docs.google.com/document/d/1NU7d51p--9oeaaN6nlCBr1a8990JJw4OMDBA77wbyVE'),
    ('nagayoshi', 'miytwo', False, 'https://docs.google.com/document/d/1W95lJ9bq0-KWRTCijgQ0Ua4koFsjTdLp3nvPTrnvCOc'),
    ('fudzisan', 'miytwo', False, 'https://docs.google.com/document/d/1s_i_H1q2s3lPN26UQTQODfED81XXgWWy0qkUvGvm8L8'),
    ('sogo', 'miytwo', False, 'https://docs.google.com/document/d/1qIumW_aa9LJAv7u2ie1MyEV4dblRuiTPKhcmuVmq2dY'),
    ('rennyo', 'renthree', False, 'https://docs.google.com/document/d/1eynG0_wLeCS_8Z6991qX2dGwxFbH7e0Ln-Zcpb6XKEA'),
    ('ren-jo', 'renthree', False, 'https://docs.google.com/document/d/1WC11-CiBHk1pkfxub39VdR7iS29n_tl7Krca9w4khxI'),
    ('renko', 'renthree', False, 'https://docs.google.com/document/d/1yI4IPxk5rHHWrtap5NBEAFRIX3SIF8TJkOrjzwcH0Hw'),
    ('tadaoki', 'hosfour', False, 'https://docs.google.com/document/d/1b-Vk3Pc7zhCORjOuNG968TNq-1YMcGxx8bmJE_chIzo'),
    ('tama', 'hosfour', False, 'https://docs.google.com/document/d/1O9_iUsfAbT_1AfUVaQxe9Ogrjont__mqWVdIROGUcAg'),
    ('joha', 'hosfour', False, 'https://docs.google.com/document/d/14dIXHkiLZ9LFnRPbr3WHfxuBhkQupkASIGoqLx6i3Ug'),
    ('murai', 'wakfive', False, 'https://docs.google.com/document/d/1phCVmNoAXUGi5ukGLwIQihu76DOMjLwaPdC_q5xBsV4'),
    ('tsuruhime', 'wakfive', False, 'https://docs.google.com/document/d/1eAjNsf8kSXhPeeymYpZgpBFvkkb-08SJtdhdWjB4CJI'),
    ('wang', 'wakfive', False, 'https://docs.google.com/document/d/1TWo7xseEmTo-S8x8qSfXCV1mtP42omzMR_FuMHOf9RI'),
    ('kukai', 'kobsix', False, 'https://docs.google.com/document/d/18n06xOJueWRKJ9lq2GbVgk3C7vC031YOxeIWB4lwlvc'),
    ('satomura', 'kobsix', False, 'https://docs.google.com/document/d/1YVUapPuI1lmko_BUjhlHnbU-ZvaZHgWNbdOtoSSXKtU'),
    ('yubien', 'kobsix', False, 'https://docs.google.com/document/d/1nORj-ibMjS0-vqIFV7cnmfW9-vur44TLEOwpZuPvHb0')
;

INSERT INTO {prefix}factions (name) VALUES
    ('Samouraïs Chōsokabe')
    ,('Samouraïs Miyoshi')
    ,('Samouraïs Hosokawa')
    ,('Samouraïs Ashikaga')
    ,('Samouraïs Kōno')
    ,('Samouraïs Takeda')
    ,('Moines Bouddhistes')
    ,('Ikkō-ikki') --https://fr.wikipedia.org/wiki/Ikk%C5%8D-ikki
    ,('Kaizokushū') -- (海賊衆)
    ,('Chrétiens') -- https://histoiredujapon.com/2021/04/05/etrangers-japon-ancien/#index_id1
    ,('Yōkai')
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
INSERT INTO {prefix}controllers (
    firstname, lastname, ia_type, secret_controller,
    url, story, can_build_base,
    faction_id, fake_faction_id
) VALUES
    ('妖怪 de', 'Shikoku (四国)', 'passif', True, -- https://fr.wikipedia.org/wiki/Y%C5%8Dkai#:~:text=Le%20terme%20y%C5%8Dkai%20(%E5%A6%96%E6%80%AA%2C%20%C2%AB,la%20culture%20orale%20au%20Japon.
        'https://docs.google.com/document/d/1gLcK961mCzDSAvaPVTy886JmRTpkPiACUyP8ArSkoPI', '',
        True,
        (SELECT ID FROM {prefix}factions WHERE name = 'Yōkai'),
        (SELECT ID FROM {prefix}factions WHERE name = 'Moines Bouddhistes')
    ),
    ('Shogun', 'Ashikaga (足利)', 'passif', True,
        'https://docs.google.com/document/d/1CMSbdrjJqZz_wabuMNKS1qSh6T7apqDq_Ag7NpI7Xx4', '', 
        True,
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Ashikaga'),
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Ashikaga')
    ), 
    ('Clan', 'Kōno (河野)', NULL, True, --https://it.wikipedia.org/wiki/Clan_K%C5%8Dno
        'https://docs.google.com/document/d/1SCqA_PNN6U_42t4FVYE_kIXBW1xUsS-eHwBszIKD5KI', '',
        False,
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Kōno'),
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Kōno')
    )
;

INSERT INTO {prefix}controllers (
    firstname, lastname,
    faction_id, fake_faction_id,
    url, 
    story
) VALUES
    (
        'Clan', 'Chōsokabe (長宗我部)', --https://fr.wikipedia.org/wiki/Clan_Ch%C5%8Dsokabe
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Chōsokabe' ),
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Chōsokabe' ),
        'https://docs.google.com/document/d/1P2Mz4PAkw00DMXXG4hgyod3FJNJkdXHU2JHbvkn327I',
        ''
    ),
    (
        'Clan', 'Hosokawa (細川)', -- https://fr.wikipedia.org/wiki/Clan_Hosokawa
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Hosokawa' ),
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Hosokawa' ),
        'https://docs.google.com/document/d/14R_8j-5zbjC8Wzm72SsHS9QC8KDQ8l3AbkW5ZNmECAg',
        ''
    ),
    (
        'Clan', 'Miyoshi (三好)',  --https://fr.wikipedia.org/wiki/Clan_Miyoshi
        (SELECT ID FROM {prefix}factions WHERE name = 'Chrétiens' ),
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Miyoshi' ),
        'https://docs.google.com/document/d/1EVtV5G1xr9O2GeOep8D3SmrEp1i7Fw5wOnuj3aGSui4',
        ''
    ),
    (
        'Temple', 'Jōdo-shinshū (浄土真宗)', -- https://fr.wikipedia.org/wiki/Rennyo
        (SELECT ID FROM {prefix}factions WHERE name = 'Ikkō-ikki' ),
        (SELECT ID FROM {prefix}factions WHERE name = 'Moines Bouddhistes' ),
        'https://docs.google.com/document/d/1xKYPslqDdxlps6A4ydFh_iUu6cvdP5VC9145goVmLrA',
        ''
    ),
    (
        'Temple', 'Tendai (天台宗)', -- https://en.wikipedia.org/wiki/K%C5%ABkai
        (SELECT ID FROM {prefix}factions WHERE name = 'Moines Bouddhistes'),
        (SELECT ID FROM {prefix}factions WHERE name = 'Moines Bouddhistes'),
        'https://docs.google.com/document/d/1bP2AGEA7grFw4k4CatLrTmeZkDDlczTqUEGg151GpQ8',
        ''
    ),
    (
        'Groupe', 'Wako (和光)', --
        (SELECT ID FROM {prefix}factions WHERE name = 'Kaizokushū' ),
        (SELECT ID FROM {prefix}factions WHERE name = 'Kaizokushū' ),
        'https://docs.google.com/document/d/1lgVjCyPTpzxA0nU649PyeDldVxCKtLSh9t7AJOmwREg',
        ''
    )
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
INSERT INTO {prefix}controllers (
    firstname, lastname, ia_type, secret_controller,
    url, story, can_build_base,
    start_workers, turn_recruited_workers, turn_firstcome_workers,
    faction_id, fake_faction_id
) VALUES
    ('Clan', 'Sogō (十河)', NULL, True, 
        NULL, '',
        False, 0, 1, 1,
        (SELECT ID FROM {prefix}factions WHERE name = 'Chrétiens'),
        (SELECT ID FROM {prefix}factions WHERE name = 'Chrétiens')
    ),
    ('Clan', 'Takeda (武田)', 'passif', True, 
        'https://docs.google.com/document/d/1xSeM0-AGy8TakF7F-XvjJKMUmZ76hZfqLRKAzZO5s9A/edit?tab=t.0#heading=h.v3wi0mldiz4e', '',
        False, 10, 0, 0,
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Takeda'),
        (SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Takeda')
    )
;

INSERT INTO {prefix}player_controller (controller_id, player_id)
    SELECT ID, (SELECT ID FROM {prefix}players WHERE username = 'gm')
    FROM {prefix}controllers;

INSERT INTO {prefix}player_controller (player_id, controller_id) VALUES
    (
        (SELECT ID FROM {prefix}players WHERE username = 'player0'),
        (SELECT ID FROM {prefix}controllers WHERE lastname in ('Shikoku (四国)'))
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'yoshiteru'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Ashikaga (足利)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'yoshiaki'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Ashikaga (足利)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'motochika'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Chōsokabe (長宗我部)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'shoho'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Chōsokabe (長宗我部)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'kanetsugu'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Chōsokabe (長宗我部)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'nagayoshi'),
        (SELECT ID FROM {prefix}controllers WHERE lastname in ('Miyoshi (三好)'))
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'fudzisan'),
        (SELECT ID FROM {prefix}controllers WHERE lastname in ('Miyoshi (三好)'))
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'sogo'),
        (SELECT ID FROM {prefix}controllers WHERE lastname in ('Miyoshi (三好)'))
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'sogo'),
        (SELECT ID FROM {prefix}controllers WHERE lastname in ('Sogō (十河)'))
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'rennyo'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'ren-jo'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'renko'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'tadaoki'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Hosokawa (細川)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'tama'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Hosokawa (細川)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'joha'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Hosokawa (細川)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'murai'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Wako (和光)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'tsuruhime'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Wako (和光)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'wang'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Wako (和光)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'kukai'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'satomura'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'yubien'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'tsuruhime'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Kōno (河野)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'yubien'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Kōno (河野)')
    ),
    (
        (SELECT ID FROM {prefix}players WHERE username = 'shingen'),
        (SELECT ID FROM {prefix}controllers WHERE lastname = 'Takeda (武田)')
    )
;

-- Table of Fixed Power Types used by code
INSERT INTO {prefix}power_types (id, name, description) VALUES
    (1, 'Hobby', 'Objet fétiche'),
    (2, 'Metier', 'Rôle'),
    (3, 'Discipline', 'Maitrise des Arts'),
    (4, 'Transformation', 'Equipements Rares');

-- Table of powers
-- other possible keys hidden, on_recrutment, on_transformation
INSERT INTO {prefix}powers ( name, enquete, attack, defence, other, description) VALUES
    ('Cheval Sanuki', 0, 1,1 
        ,'{"hidden" : "0", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Province de Sanuki"} }'
        , ', les meilleures montures de Shikoku ont le pied sûr et rapide'
    )
    , ('Armure en fer de Tosa', 0, 0,2
        ,'{"hidden" : "0", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Cap sud de Tosa"} }'
        , ', faite de l’acier sombre extrait du cœur des montagnes et forgé en écailles plus dures que celles des tortues'
    )
    , ('Thé d’Oboké', 1, 0,0
        ,'{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Vallée d’Iya et d’Oboké d’Awa"} }'
        ,', un breuvage rare qui élève l’âme'
    )
    , ('Encens Coréen', 1, 0,0
        ,'{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Côte Ouest d’Iyo"} }'
        ,', fabriqué à partir de résines et d’épices introuvables au Japon'
    )
    , ('Teppo Européen', -1, 2,0
        ,'{"hidden" : "0", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Côte Est d’Awa"} }'
        ,', armes à feu importées par les missionnaires chrétiens'
    )
;

INSERT INTO  {prefix}link_power_type ( power_type_id, power_id ) VALUES
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Transformation'),(SELECT ID FROM {prefix}powers WHERE name = 'Cheval Sanuki'))
    , ((SELECT ID FROM {prefix}power_types WHERE name = 'Transformation'),(SELECT ID FROM {prefix}powers WHERE name = 'Armure en fer de Tosa'))
    , ((SELECT ID FROM {prefix}power_types WHERE name = 'Transformation'),(SELECT ID FROM {prefix}powers WHERE name = 'Thé d’Oboké'))
    , ((SELECT ID FROM {prefix}power_types WHERE name = 'Transformation'),(SELECT ID FROM {prefix}powers WHERE name = 'Encens Coréen'))
;

UPDATE {prefix}config SET value = '''Sōjutsu (槍術) – Art de la lance (Yari)'', ''Kyūjutsu (弓術) – Art du tir à l’arc'', ''Shodō (書道) – Calligraphie'', ''Kadō / Ikebana (華道 / 生け花) – Art floral'''
WHERE name = 'basePowerNames';

-- insert Powers
INSERT INTO {prefix}powers ( name, enquete, attack, defence, description) VALUES
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

INSERT INTO {prefix}link_power_type (power_type_id, power_id) VALUES
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Sōjutsu (槍術) – Art de la lance (Yari)')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Kyūjutsu (弓術) – Art du tir à l’arc')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Shodō (書道) – Calligraphie')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Kadō / Ikebana (華道 / 生け花) – Art floral')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Kenjutsu (剣術) – Art du sabre')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Heihō (兵法) – Stratégie militaire')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Waka (和歌) – Poésie classique')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Hōjutsu (砲術) – Art des armes à feu (teppō)')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Bajutsu (馬術) – Art de l’équitation militaire')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Gagaku (雅楽) – Musique de cour')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Iaijutsu (居合術) – Art du sabre')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Bugaku (舞楽) – Danse de cour')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Chadō (茶道) – Voie du thé')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Jūjutsu (柔術) – Techniques de lutte à mains nues')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Ninjutsu (忍術) – Techniques d’espionnage et de guérilla')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Reiki / Kujikiri (霊気 / 九字切り) – Pratiques ésotériques')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Yawara (和) – Ancienne forme de techniques de soumission')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Naginatajutsu (薙刀術) – Art de la hallebarde')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Haikai / Haiku (俳諧 / 俳句) – Poésie courte')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Tantōjutsu (短刀術) – Combat au couteau')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Shigin (詩吟) – Chant poétique')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Tessenjutsu (鉄扇術) – L’art du combat à l’éventail de fer')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Kōdō (香道) – Voie de l’encens')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Kagenkō (影言講) – L’art de la parole de l’ombre')),
    ((SELECT ID FROM {prefix}power_types WHERE name = 'Discipline'), (SELECT ID FROM {prefix}powers WHERE name = 'Kagekui-ryū (影喰流) – École du Mange-Ombre'))
;

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
-- Add base powers to the factions :
-- Samouraïs Chōsokabe
INSERT INTO {prefix}faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Chōsokabe'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Kenjutsu (剣術) – Art du sabre'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Chōsokabe'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Heihō (兵法) – Stratégie militaire'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Chōsokabe'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Waka (和歌) – Poésie classique'
    ));

-- Samouraïs Miyoshi /Chrétiens 
INSERT INTO {prefix}faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Miyoshi'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Hōjutsu (砲術) – Art des armes à feu (teppō)'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Miyoshi'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Bajutsu (馬術) – Art de l’équitation militaire'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Miyoshi'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Gagaku (雅楽) – Musique de cour'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Chrétiens'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Hōjutsu (砲術) – Art des armes à feu (teppō)'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Chrétiens'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Bajutsu (馬術) – Art de l’équitation militaire'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Chrétiens'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Gagaku (雅楽) – Musique de cour'
    ));

-- Samouraïs Hosokawa
INSERT INTO {prefix}faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Hosokawa'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Iaijutsu (居合術) – Art du sabre'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Hosokawa'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Bugaku (舞楽) – Danse de cour'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Hosokawa'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Chadō (茶道) – Voie du thé'
    ));

-- Samouraïs Ashikaga
INSERT INTO {prefix}faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Ashikaga'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Iaijutsu (居合術) – Art du sabre'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Ashikaga'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Bugaku (舞楽) – Danse de cour'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Ashikaga'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Chadō (茶道) – Voie du thé'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Ashikaga'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Gagaku (雅楽) – Musique de cour'
    ));

-- Ikkō-ikki
INSERT INTO {prefix}faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM {prefix}factions WHERE name = 'Ikkō-ikki'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Jūjutsu (柔術) – Techniques de lutte à mains nues'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Ikkō-ikki'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Ninjutsu (忍術) – Techniques d’espionnage et de guérilla'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Ikkō-ikki'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Reiki / Kujikiri (霊気 / 九字切り) – Pratiques ésotériques'
    ));

-- Moines Bouddhistes
INSERT INTO {prefix}faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM {prefix}factions WHERE name = 'Moines Bouddhistes'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Yawara (和) – Ancienne forme de techniques de soumission'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Moines Bouddhistes'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Naginatajutsu (薙刀術) – Art de la hallebarde'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Moines Bouddhistes'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Haikai / Haiku (俳諧 / 俳句) – Poésie courte'
    ));

-- Kaizokushū Pirates
INSERT INTO {prefix}faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM {prefix}factions WHERE name = 'Kaizokushū'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Tantōjutsu (短刀術) – Combat au couteau'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Kaizokushū'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Shigin (詩吟) – Chant poétique'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Kaizokushū'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Tessenjutsu (鉄扇術) – L’art du combat à l’éventail de fer'
    ));

-- Samouraïs Kōno
INSERT INTO {prefix}faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Kōno'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers p ON p.ID = lpt.power_id
        WHERE p.name = 'Iaijutsu (居合術) – Art du sabre'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Kōno'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers AS p ON p.ID = lpt.power_id
        WHERE p.name = 'Haikai / Haiku (俳諧 / 俳句) – Poésie courte'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Kōno'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers AS p ON p.ID = lpt.power_id
        WHERE p.name = 'Tessenjutsu (鉄扇術) – L’art du combat à l’éventail de fer'
    ));

-- Yōkai
INSERT INTO {prefix}faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM {prefix}factions WHERE name = 'Yōkai'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers AS p ON p.ID = lpt.power_id
        WHERE p.name = 'Kōdō (香道) – Voie de l’encens'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Yōkai'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers AS p ON p.ID = lpt.power_id
        WHERE p.name = 'Kagenkō (影言講) – L’art de la parole de l’ombre'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Yōkai'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers AS p ON p.ID = lpt.power_id
        WHERE p.name = 'Kagekui-ryū (影喰流) – École du Mange-Ombre'
    ));

-- Takeda
INSERT INTO {prefix}faction_powers (faction_id, link_power_type_id) VALUES
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Takeda'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers AS p ON p.ID = lpt.power_id
        WHERE p.name = 'Bajutsu (馬術) – Art de l’équitation militaire'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Takeda'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers AS p ON p.ID = lpt.power_id
        WHERE p.name = 'Heihō (兵法) – Stratégie militaire'
    )),
    ((SELECT ID FROM {prefix}factions WHERE name = 'Samouraïs Takeda'), (
        SELECT lpt.ID FROM {prefix}link_power_type lpt JOIN {prefix}powers AS p ON p.ID = lpt.power_id
        WHERE p.name = 'Chadō (茶道) – Voie du thé'
    ));

-- Warning: If you read this file, you will no longer be eligible to participate as a player.
INSERT INTO {prefix}ressources_config (
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

INSERT INTO {prefix}controller_ressources (
    controller_id, ressource_id,
    amount, amount_stored, end_turn_gain
) VALUES 
    ((SELECT ID FROM {prefix}controllers WHERE lastname = 'Chōsokabe (長宗我部)'), (SELECT ID FROM {prefix}ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM {prefix}controllers WHERE lastname = 'Hosokawa (細川)'), (SELECT ID FROM {prefix}ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM {prefix}controllers WHERE lastname = 'Miyoshi (三好)'), (SELECT ID FROM {prefix}ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM {prefix}controllers WHERE lastname = 'Jōdo-shinshū (浄土真宗)'), (SELECT ID FROM {prefix}ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM {prefix}controllers WHERE lastname = 'Tendai (天台宗)'), (SELECT ID FROM {prefix}ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM {prefix}controllers WHERE lastname = 'Wako (和光)'), (SELECT ID FROM {prefix}ressources_config WHERE ressource_name = 'Koku')
        , 1000, 0, 1000),
    ((SELECT ID FROM {prefix}controllers WHERE lastname = 'Sogō (十河)'), (SELECT ID FROM {prefix}ressources_config WHERE ressource_name = 'Koku')
        , 0, 0, 0),
    ((SELECT ID FROM {prefix}controllers WHERE lastname = 'Kōno (河野)'), (SELECT ID FROM {prefix}ressources_config WHERE ressource_name = 'Koku')
        , 0, 0, 0),
    ((SELECT ID FROM {prefix}controllers WHERE lastname = 'Takeda (武田)'), (SELECT ID FROM {prefix}ressources_config WHERE ressource_name = 'Koku')
        , 2000, 0, 2000),
    ((SELECT ID FROM {prefix}controllers WHERE lastname = 'Ashikaga (足利)'), (SELECT ID FROM {prefix}ressources_config WHERE ressource_name = 'Koku')
        , 2000, 0, 2000)
;


