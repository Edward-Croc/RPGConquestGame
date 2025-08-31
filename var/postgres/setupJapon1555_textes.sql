-- Warning: If you read this file, you will no longer be eligible to participate as a player.

UPDATE config SET value = 'Shikoku (四国) 1555' WHERE name = 'TITLE';
-- https://fr.wikipedia.org/wiki/%C3%89poque_Sengoku
UPDATE config SET
    value = '<p> En plein Sengoku Jidai(戦国時代), les turbulences sociales, intrigues politiques et conflits militaires divisent le Japon.
       Les guerres fratricides font rage sur l’archipel nippon, et le Shogunat Ashikaga(足利) fragilisé peine à rétablir la paix.
       Au printemps 1555, les forces du shugo (守護) de Shikoku(四国), composées du daimyô Kunichika(国親) Chōsokabe(長宗我部), accompagné de son vassal Fujitaka (藤孝) Hosokawa(細川) et en l’absence notable du daimyô du clan Miyoshi(三好),
        sont parties défendre Kyoto(京都市), sur l’ile principale de Honshu(本州), contre les forces du clan Takeda(武田), espérant ainsi s’attirer les faveurs du Shogun Ashikaga.
       Les rares survivants rentrés de la campagne parlent d’une défaite cuisante, d’une rébellion paysanne et du déshonneur du daimyô et de ses vassaux.
       Le contrôle du clan Chōsokabe vacille sur Shikoku et les vassaux même du clan voient la disparition de Kunichika Chōsokabe comme une opportunité sans précédent.
       Celui qui pourra s’octroyer l’allégeance de la majorité des 4 provinces sera maître de l’île de Shikoku.<br>
        <button onclick="window.open(''https://docs.google.com/document/d/1ibggeKiMASJFWr_BnAgUzgQj0bJpZkB2LPtQWVRKt3s'', ''_blank'')"> Document d’introduction Joueur !</button>        
        </p>'
    WHERE name = 'PRESENTATION';

UPDATE config SET
    value = ' <p>  <button onclick="window.open(''https://docs.google.com/document/d/1qrYEpObe6sVdp1egCMnOcGW9BNXebPp_PWiLrD4Lqb8'', ''_blank'')"> Documents Orga !</button> </p>'
    WHERE name = 'IntrigueOrga';

INSERT INTO config (name, value, description)
VALUES
 ('textRecrutementJobHobby', 'Est un.e %5$s avec un.e %4$s.', 'string to present hobby %4$s and job %5$s on recrutement')
 ,('textViewWorkerJobHobby','c’est un.e %2$s avec un.e %3$s.', 'string to present hobby %2$s and job %3$s view of worker')
 ,('textViewWorkerDisciplines', 'Ses disciplines développées sont : %s. <br />', 'Texts for worker view page disciplines')
 ,('textViewWorkerTransformations', 'Iel a été équipé de : %s. <br />', 'Texts for worker view page transformations')
 ,('texteNameBase', 'Forteresse des %s', 'Text for Name of base %1$s Fake Faction name ')
 ,(
    'texteDescriptionBase' 
    ,'Nous avons trouvé la forteresse de %1$s des %2$s. Les serviteurs de confiance leur manquent encore pour avoir des défenses solides.
    En attaquant ce lieu nous pourrions lui porter un coup fatal.
    L’attaque causerait certainement quelques questions à la cour du Shogun, mais un joueur affaibli sur l’échiquier politique est toujours bénéfique.
    Nous ne devons pas tarder à prendre notre décision, ses défenses se renforcent chaque %3$s.'
    ,'Texts for description of base,  -- %1$s controller name -- %2$s FakeFaction name -- %3$s Time values')
 ,(
    'texteHiddenFactionBase'
    , '
        Il nous apparait en fouillant le lieu que ce quelqu’un s’est donné beaucoup de mal pour que cette forteresse donne l’impression d’être liée aux %1$s, mais en réalité son propriétaire est des %2$s.'
    ,'Texts for secret faction description of base,  -- %1$s Fake Faction name,  -- %2$s True Faction name'
)
;

INSERT INTO config (name, value, description)
VALUES
 ('textControllerActionCreateBase', 'Créer une forteresse dans la région :', 'create base texte in controller view actions')
 ,('textControllerActionMoveBase', 'Déménager dans une forteresse de la région :', 'move base texte in controller view actions')
 ,('textcontrollerRecrutmentNeedsBase', 'Nous ne pouvons pas recruter sans avoir établi une forteresse.', 'needed base for recrutment')
;

INSERT INTO config (name, value, description)
VALUES
 (
    'textesStartInvestigate', '<p> Nous avons mené l’enquête dans le territoire %s.</p>', 'Texts for start of investigation')
,(
    'textesFoundDisciplines',
    '[
        "Iel a de plus une maitrise de la discipline %s. ",
        "En plus, iel maitrise l’art du %s. ",
        "Nous avons également remarqué sa pratique de l’art %s. ",
        "Ces observations se cumulent avec son utilisation de la discipline %s. "
    ]',
    'Texts for extra disciplines'
)
    ;
INSERT INTO config (name, value, description)
VALUES
(
    'textesOrigine',
    '[
        "J’ai des raisons de penser qu’iel est natif.ve de %s. ",
        "En plus, iel est originaire de %s. ",
        "Je m’en méfie, iel vient de %s. "
    ]',
    'Texts for origin detection'
);

INSERT INTO config (name, value, description)
VALUES
-- textesDiff01Array     
-- (nom(id)) - %1$s
-- (role) - %2$s
-- (objet) - %3$s
-- (action_ps) - %4$s
-- (action_inf) - %5$s
-- (discipline) - %6$s
-- (transformation1) - %8$s
-- (origin_text) - %9$s
(
    'textesDiff01Array',
    '[
        [
            "J’ai vu un.e %2$s du nom de %1$s qui %4$s dans ma zone d’action. %9$s",
            "J’ai remarqué qu’iel avait un.e %3$s mais je suis sûr qu’iel possède aussi l’art de %6$s%8$s."
        ],
        [
            "Nous avons repéré un.e %2$s du nom de %1$s qui %4$s dans notre région. %9$s",
            "En poussant nos recherches il s’avère qu’iel maitrise %6$s%8$s. Iel a aussi été vu.e avec un.e %3$s, mais cette information n’est pas si pertinente."
        ],
        [
            "J’ai trouvé %1$s, qui n’est clairement pas un agent à nous, c’est un.e %2$s avec un.e %3$s.",
            "%9$sIel démontre une légère maitrise de la discipline %6$s%8$s."
        ],
        [
            "Je me suis rendu compte que %1$s, que j’ai repéré avec un.e %3$s, %4$s dans le coin. %9$s",
            "C’était étrange, alors j’ai enquêté et trouvé qu’iel a en réalité des capacités de %6$s, ce qui en fait un.e %2$s un peu trop spécial.e%8$s."
        ],
        [
            "On a suivi %1$s parce qu’on l’a repéré.e en train de %5$s, ce qui nous a mis la puce à l’oreille. C’est normalement un.e %2$s mais on a découvert qu’il possédait aussi un.e %3$s.",
            "%9$sCela dit, le vrai problème, c’est qu’il semble maîtriser %6$s, au moins partiellement%8$s."
        ]
    ]',
    'Texts for search results level 1 (structured dialogue in pairs)'
),
-- textesDiff01TransformationDiff0Array
-- (nom(id)) - %1$s
-- (metier) - %7$s/%2$s
-- (hobby) - %3$s
-- (action_ps) - %4$s
-- (action_inf) - %5$s
-- (discipline) - %6$s
-- (transformation0) - %7$s
-- (transformation1) - %8$s
-- (origin_text) - %9$s
(
    'textesDiff01TransformationDiff0Array',
    '[
        [
            "Nous avons repéré le possesseur d’un.e %7$s du nom de %1$s qui %4$s dans notre région. %9$s",
            "En poussant nos recherches il s’avère qu’iel maitrise %6$s. Iel possède aussi un.e %3$s, mais cette information n’est pas pertinente. "
        ],
        [
            "J’ai trouvé %1$s, avec un.e %7$s, qui n’est clairement pas un.e de nos loyaux suivants, c’est un.e %2$s qui a également été vu.e avec un.e %3$s. %9$s",
            "Iel démontre une légère maitrise de l’art %6$s."
        ],
        [
            "Je me suis rendu compte que quelqu’un possédant un.e %7$s %4$s dans le coin. On l’a entendu.e se faire appeler %1$s. %9$s",
            "C’était étrange, alors j’ai enquêté et trouvé qu’iel a des capacités de %4$s, ce qui en fait un %2$s un peu trop spécial."
        ]
    ]',
    'Variation text blocks for Diff01 transformation reports'
),
-- Diff 2
-- %1$s - (réseau id)
-- %2$s - (transformation decouverte diff 2)
-- %3$s - (disciplines si plus d'une)
(
    'textesDiff2', '[
        "%2$s En plus, sa famille a des liens avec la faction %1$s. %3$s",
        "Iel fait partie de la faction %1$s. %3$s %2$s ",
        "%2$sEn creusant, iel est rattaché.e à la faction %1$s. %3$s",
        "%3$s Iel reçoit un soutien financier de la faction %1$s. %2$s",
        "%2$sIel travaille avec la faction %1$s. %3$s"
    ]', 'Texts for search results level 2'),
--  Diff 3
-- %1$s - found_controller_name
-- %2$s - found_controller_faction
(
    'textesDiff3', '[
        "Ce réseau d’informateurs répond à %1$s. ",
        "A partir de là nous avons pu remonter jusqu’à %1$s. ",
        "Cela signifie qu’iel travaille forcément pour %1$s. ",
        "Nous l’avons vu rencontrer en personne %1$s. ",
        "Ce qui veut dire que c’est un serviteur de %1$s. "
    ]',
    'Texts for search results level 3'
);


INSERT INTO config (name, value, description)
VALUES
('textesTransformationDiff1', '[
    " et nous observons qu’iel possède un.e %s",
    ", de plus iel laisse penser qu’iel a un.e %s"
]', 'Texts for transformation level 1')
,('textesTransformationDiff2', '[
    "Iel a probablement un.e %s mais les preuves nous manquent encore. ",
    "Iel est clairement atypique, iel possède un.e %s. "
]', 'Texts for transformation level 2')
;


INSERT INTO config (name, value, description)
VALUES
-- Observers of a **failed** violent claim
('textesClaimFailViewArray', '[
    "J’ai vu %1$s tenter de prendre le contrôle du territoire %2$s, mais la défense l’a repoussé.e brutalement.",
    "L’assaut de %1$s sur le territoire %2$s a échoué ; c’était un vrai carnage.",
    "%1$s a voulu s’imposer au %2$s, sans succès. Iel a été forcé.e de battre en retraite.",
    "Je pense que %1$s pensait avoir une chance au %2$s. C’était mal calculé, iel a échoué."
]', 'Texts the workers observing the failed violent claiming of a zone'),

-- Observers of a **successful** violent claim
('textesClaimSuccessViewArray', '[
    "J’ai vu %1$s renverser l’autorité sur %2$s. La zone a changé de mains.",
    "%2$s appartient désormais au maitre de %1$s. Iel a balayé toute résistance.",
    "L’opération de %1$s sur %2$s a été une réussite totale, malgré les dégats.",
    "%1$s a pris %2$s par la force. Iel n’a laissé aucune chance aux défenseurs."
]', 'Texts the workers observing the successful violent claiming of a zone'),

-- Report to the **claiming worker** on failure
('textesClaimFailArray', '[
    "Notre tentative de prise de contrôle de %2$s a échoué. La défense était trop solide.",
    "Nous avons échoué à nous imposer en force sur %2$s. Il faudra retenter plus tard.",
    "L’assaut sur %2$s a été un échec. Les forces en place ont tenu bon.",
    "La mission de domination de %2$s n’a pas abouti. Trop de résistance à notre autorité sur place."
]', 'Texts for the fail report of the claiming worker'),

-- Report to the **claiming worker** on success
('textesClaimSuccessArray', '[
    "Nous avons pris le contrôle du territoire %2$s avec succès. Félicitations vous en êtes désormais le maitre.",
    "Notre offensive sur la zone %2$s a porté ses fruits. Elle est maintenant à vous.",
    "Nous avons su imposer votre autorité sur %2$s. La région vous obéit désormais.",
    "%2$s est tombé.e sous votre coupe."
]', 'Texts for the success report of the claiming worker');

INSERT INTO config (name, value, description)
VALUES
-- %1$s - timeDenominatorThe lowercase
-- %2$s - timeDenominatorOf lowercase
-- %3$s - timeValue
-- %4$s - week number
('workerDisappearanceTexts', '[
    "<p>Cet agent a disparu sans laisser de traces à partir %2$s %3$s %4$s.</p>",
    "<p>Depuis %1$s %3$s %4$s, plus aucun signal ni message de cet agent.</p>",
    "<p>La connexion avec l’agent s’est perdue %1$s %3$s %4$s, et nous ignorons où iel se trouve.</p>",
    "<p>À partir %2$s %3$s %4$s, cet agent semble s’être volatilisé.e dans la nature.</p>",
    "<p>Nous avons perdu toute communication avec cet agent depuis %1$s %3$s %4$s.</p>",
    "<p>La dernière trace de cet agent remonte à %1$s %3$s %4$s, depuis iel est porté.e disparu.e.</p>",
    "<p>L’agent s’est évanoui dans la nature après %1$s %3$s %4$s. Aucune nouvelle depuis.</p>",
    "<p>Depuis %1$s %3$s %4$s, cet agent est un fantôme, insaisissable et introuvable.</p>",
    "<p>%1$s %3$s %4$s marque la disparition totale de cet agent. Aucun indice sur sa situation actuelle.</p>",
    "<p>%1$s %3$s %4$s signe le début du silence complet de cet agent.</p>"
]', 'Templates used for worker disappearance text with a week number placeholder');

INSERT INTO config (name, value, description)
VALUES
-- %1$s - target name
('attackSuccessTexts', '[
    "<p>J’ai pu mener à bien ma mission sur %1$s, son silence est assuré.</p>",
    "<p>J’ai accompli l’attaque sur %1$s, iel a trouvé son repos final.</p>",
    "<p>Notre cible %1$s a été accompagnée chez le médecin dans un état critique, nous n’avons plus rien à craindre.</p>",
    "<p>Un.e suicidé.e sera retrouvé.e dans la mer demain, %1$s n’est plus des nôtres.</p>",
    "<p>Je confirme que %1$s ne posera plus jamais problème, iel a rejoint le silence éternel.</p>",
    "<p>L’histoire de %1$s est officiellement terminée. Son existence appartient désormais au passé.</p>",
    "<p>Mission accomplie : %1$s est désormais une simple note dans les rouleaux de l’histoire.</p>"
]', 'Templates for successful attack reports mentioning the target name');

INSERT INTO config (name, value, description)
VALUES
-- %1$s - target name
('captureSuccessTexts', '[
    "<p>La mission est un succès total : %1$s est désormais entre nos mains, et nous allons mener l’interrogatoire.</p>",
    "<p>La mission s’est déroulée comme prévu : %1$s est capturé.e et prêt.e à livrer ses secrets.</p>",
    "<p>Succès complet sur %1$s : iel est désormais sous notre garde et n’aura d’autre choix que de parler.</p>",
    "<p>Nous avons maîtrisé %1$s : iel est maintenant entre nos mains, prêt.e pour l’interrogatoire.</p>",
    "<p>Mission accomplie : %1$s est capturé.e et en sécurité pour une conversation approfondie.</p>",
    "<p>L’objectif %1$s est neutralisé et sous notre contrôle. L’interrogatoire peut commencer.</p>",
    "<p>Nous avons intercepté %1$s sans heurt : iel est désormais à notre merci pour un échange d’informations.</p>",
    "<p>Le succès est total : %1$s est retenu.e, et ses révélations ne tarderont pas.</p>",
    "<p>Mission terminée brillamment : %1$s est capturé.e et ne nous échappera plus.</p>"
]', 'Inclusive templates for successful capture reports mentioning the target name');

INSERT INTO config (name, value, description)
VALUES
-- %1$s - target name
('failedAttackTextes', '[
    "<p>Malheureusement, %1$s a réussi à nous échapper et reste en vie.</p>",
    "<p>La conspiration contre %1$s a échoué. La cible a survécu et demeure une menace.</p>",
    "<p>Notre tentative contre %1$s s’est soldée par un échec. Iel est toujours actif.ve.</p>",
    "<p>L’attaque n’a pas atteint son objectif : %1$s a survécu et garde sa liberté.</p>",
    "<p>Nous n’avons pas pu neutraliser %1$s. Iel reste introuvable après l’affrontement.</p>",
    "<p>La mission a été un revers : %1$s est toujours debout et hors de notre portée.</p>",
    "<p>Malgré nos efforts, %1$s s’est défendu.e avec succès et a réussi à fuir.</p>",
    "<p>Notre assaut n’a pas suffi : %1$s a survécu et continue d’agir.</p>",
    "<p>La cible %1$s s’est montrée plus résistant.e que prévu. Iel a échappé à notre emprise.</p>",
    "<p>Nous avons échoué à neutraliser %1$s. Iel demeure vivant.e et peut encore riposter.</p>"
]', 'Texts for failed attacks in inclusive language');

INSERT INTO config (name, value, description)
VALUES
-- %1$s - attacker name
('escapeTextes', '[
    "<p>J’ai été pris.e pour cible par %1$s, mais j’ai réussi à lui échapper de justesse.</p>",
    "<p>Une attaque orchestrée par %1$s a failli avoir raison de moi, mais j’ai pu me faufiler hors de sa portée.</p>",
    "<p>L’embuscade tendue par %1$s n’a pas suffi à me retenir, j’ai pu m’échapper.</p>",
    "<p>J’ai croisé %1$s sur ma route, iel a tenté de m’intercepter, mais j’ai fui avant qu’il ne soit trop tard.</p>",
    "<p>L’attaque de %1$s a échoué, je suis sauf.ve et hors de danger.</p>",
    "<p>Un assaut surprise de %1$s m’a pris.e au dépourvu, mais j’ai échappé à ses griffes à temps.</p>",
    "<p>Malgré une attaque menée par %1$s, j’ai gardé mon calme et trouvé un chemin pour m’échapper.</p>",
    "<p>J’ai senti %1$s venir et, bien que surpris.e, j’ai su échapper à son piège.</p>",
    "<p>%1$s a tenté de me capturer, mais ma fuite a été rapide et efficace.</p>",
    "<p>L’assaut de %1$s n’a pas eu le résultat escompté, je suis parvenu.e à m’enfuir indemne.</p>"
]', 'Texts for successful escapes in inclusive language');

INSERT INTO config (name, value, description)
VALUES
-- %s - target name
('textesAttackFailedAndCountered', '[
    "<p>Je pars mettre en route le plan d’assassinat de %s. [Le rouleau s’arrête ici.]</p>",
    "<p>Début de la mission : %s. [Le rapport n’a jamais été terminé.]</p>",
    "<p>Nous avons perdu contact avec l’agent juste après le début de l’opération sur %s.</p>",
    "<p>Le silence total après le lancement de la mission contre %s est inquiétant…</p>",
    "<p>Le groupe envoyé pour neutraliser %s n’est jamais revenu.</p>"
]', 'Texts for missions that fail and result in counter-attack or disappearance');

INSERT INTO config (name, value, description)
VALUES
-- %s - Assaulter name 
('counterAttackTexts', '[
    "<p>%1$s m’a attaqué.e, j’ai survécu et ma riposte l’a anéanti.e. J’ai jeté son corps dans la mer.</p>",
    "<p>Après avoir été attaqué.e par %1$s, j’ai non seulement survécu, mais ma riposte nous assure qu’iel cesse définitivement ses activités.</p>",
    "<p>%1$s a cru m’avoir, mais ma riposte a brisé ses espoirs et l’a détruit.e.</p>",
    "<p>Iel a tenté de me réduire au silence, mais après avoir survécu à l’attaque de %1$s, j’ai répondu par une riposte fatale.</p>",
    "<p>Malgré l’assaut de %1$s, ma riposte a non seulement sauvé ma vie, mais a mis complètement fin à ses ambitions.</p>",
    "<p>Attaqué.e par %1$s, j’ai résisté et ma riposte l’a anéanti.e sans retour.</p>",
    "<p>Iels ont cherché à me faire tomber, mais ma riposte après l’attaque de %1$s a effacé toute menace.</p>",
    "<p>L’attaque de %1$s a échoué, et ma réponse a été rapide, fatale et décisive.</p>",
    "<p>Je me suis retrouvé.e face à %1$s, mais après avoir survécu à son attaque, ma riposte a scellé son destin.</p>",
    "<p>Après une attaque brutale de %1$s, ma survie et ma riposte ont fait en sorte qu’iel n’ait plus rien à revendiquer.</p>"
]', 'Texts for the worker who was atacked an the successfully countered');

INSERT INTO config (name, value, description)
VALUES
-- %s = nom de la localisation
('TEXT_LOCATION_DISCOVERED_NAME', '[
    "Nous avons identifié une information intéressante : un.e %s serait présent.e dans la zone.",
    "Des signes pointent vers la présence d’un.e %s, nous devons enquêter davantage à ce sujet.",
    "Il semblerait qu’un.e %s se trouve dans cette région, il faudra s’en assurer.",
    "Des rumeurs persistantes évoquent la présence d’un.e %s dans les environs.",
    "Nos informateurs.rices évoquent la découverte potentielle d’un.e %s dans cette zone.",
    "Certains indices laissent penser qu’un.e %s pourrait se cacher ici.",
    "Un rapport fragmentaire mentionne un.e %s comme étant caché.e dans ce territoire."
]', 'Phrases pour signaler qu’une localisation a été découverte (nom uniquement)'),

-- %s = nom de la localisation
-- %s = description de la localisation
('TEXT_LOCATION_DISCOVERED_DESCRIPTION', '[
    "Information intéressante : un.e %s est présent.e dans la zone. %s",
    "Nous avons confirmé la présence d’un.e %s. Nous avons enquêté davantage et découvert que : %s",
    "Après enquête, il s’avère qu’un.e %s est bien lié.e à cette localisation. %s",
    "Notre exploration confirme la présence d’un.e %s. Voici ce que nous avons appris : %s",
    "Nous avons vérifié les rumeurs : un.e %s est bien ici. %s",
    "Le mystère est levé : un.e %s se trouve dans cette zone. %s",
    "Les données concordent : un.e %s est bien associé.e à cet endroit. %s"
]', 'Phrases pour décrire une localisation après enquête'),

-- Aucun paramètre : simple indication de la possibilité de destruction
('TEXT_LOCATION_CAN_BE_DESTROYED', '[
    " Nous pouvons retourner cette information contre son maître et nous y attaquer.",
    " Il est possible d’organiser une mission pour faire disparaître ce problème."
]', 'Phrases pour signaler qu’une localisation peut être détruite')
;
