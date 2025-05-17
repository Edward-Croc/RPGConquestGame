
UPDATE config SET value = 'Firenze 1966' WHERE name = 'TITLE';
UPDATE config SET
    value = '<p>Le 6 novembre 1966, l’Arno inonde une grande partie du centre-ville, endommageant de nombreux chefs-d’œuvre et déplaçant la population du centre ville.
        Un grand mouvement de solidarité internationale naît à la suite de cet évènement et mobilise des milliers de volontaires, surnommés Les anges de la boue.
        Dans les jours suivant la catastrophe les forces surnaturelles reprennent doucement pied dans la ville. Retrouverez vous votre pouvoir d’antan ?
        </p>'
    WHERE name = 'PRESENTATION';

INSERT INTO config (name, value, description)
VALUES
 ('textRecrutementJobHobby', '%4$s, %5$s', 'string to present hobby %4$s and job %5$s on recrutement')
 ,('textViewWorkerJobHobby','c’était un.e %3$s et iel est un.e %2$s', 'string to present hobby %2$s and job %3$s view of worker')
 ,('textViewWorkerDisciplines', 'Ses disciplines développées sont : %s <br />', 'Texts for worker view page disciplines')
 ,('textViewWorkerTransformations', 'Iel a été transformé.e en : %s <br />', 'Texts for worker view page transformations')
  -- %1$s Fake Faction name
 ,('texteNameBase', 'Repaire %s', 'Text for Name of base')
 -- %1$s Controler name
 -- %2$s FakeFaction name
 -- %3$s Time values
 ,('texteDescriptionBase', '
        Nous avons trouvé le repaire de %1$s des %2$s. Ses serviteurs ne semblent pas avoir fini de remettre en place les défenses qui existaient avant la crue.
        En attaquant ce lieu nous pourrions lui porter un coup fatal.
        Sa disparition provoquerait certainement quelques questions à l’Elyséum, mais un joueur en moins sur l’échiquier politique est toujours bénéfique.
        Nous ne devons pas tarder à prendre notre décision, ses défenses se renforcent de %3$s en %3$s.
    ','Texts for description of base')
 -- %1$s Fake Faction name
 -- %2$s True Faction name
 ,('texteHiddenFactionBase', '
       Il nous apparait en fouillant le lieu que ce quelqu’un s’est donné beaucoup de mal pour que ce repaire donne l’impression d’être du clan %2$s, mais en réalité son propriétaire est du clan %1$s.
    ','Texts for secret faction description of base')
;

INSERT INTO config (name, value, description)
VALUES
 ('textControlerActionCreateBase', 'Créer un repaire dans le quartier :', 'create base texte in controler view actions')
 ,('textControlerActionMoveBase', 'Déplacer le repaire vers le quartier :', 'move base texte in controler view actions')
 ,('textControlerRecrutmentNeedsBase', 'Nous ne pouvons pas recruter sans avoir un repaire.', 'needed base for recrutment')
;

INSERT INTO config (name, value, description)
VALUES
 (
    'textesStartInvestigate', '<p> Nous avons mené l’enquête dans le quartier %s.</p>', 'Texts for start of investigation')
,(
    'textesFoundDisciplines',
    '[
        "Iel a de plus une maitrise de la discipline %s. ",
        "En plus, iel maitrise la discipline %s. ",
        "Nous avons également remarqué sa pratique de la discipline %s. ",
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
-- (metier) - %7$s/%2$s
-- (hobby) - %3$s
-- (action_ps) - %4$s
-- (action_inf) - %5$s
-- (discipline) - %6$s
-- (transformation1 or nothing) - %8$s
-- (origin_text) - %9$s
(
    'textesDiff01Array',
    '[
        [
            "J’ai vu un.e %2$s du nom de %1$s qui %4$s dans ma zone d’action. %9$s",
            "C’est à la base un.e %3$s mais je suis sûr qu’iel possède aussi la discipline de %6$s%8$s. "
        ],
        [
            "Nous avons repéré un.e %2$s du nom de %1$s qui %4$s dans notre quartier. %9$s",
            "En poussant nos recherches il s’avère qu’iel maitrise %6$s%8$s. Iel est aussi %3$s, mais cette information n’est pas si pertinente. "
        ],
        [
            "J’ai trouvé %1$s, qui n’est clairement pas un agent à nous, c’est un.e %2$s et un.e %3$s. ",
            "%9$sIel démontre une légère maitrise de la discipline %6$s%8$s. "
        ],
        [
            "Je me suis rendu compte que %1$s, que je prenais pour un.e simple %3$s, %4$s dans la région. %9$s",
            "C’était louche, alors j’ai enquêté et trouvé qu’iel a en réalité des pouvoirs de %6$s, ce qui en fait un.e %2$s un peu trop spécial.e%8$s. "
        ],
        [
            "On a suivi %1$s parce qu’on l’a repéré.e en train de %5$s, ce qui nous a mis la puce à l’oreille. C’est normalement un.e %2$s mais on a découvert qu’iel était aussi un.e %3$s. ",
            "%9$sCela dit, le vrai problème, c’est qu’iel semble maîtriser %6$s, au moins partiellement%8$s. "
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
-- (transformation0 or nothing) - %7$s
-- (transformation1 or nothing) - %8$s
-- (origin_text) - %9$s
(
    'textesDiff01TransformationDiff0Array',
    '[
        [
            "Nous avons repéré un.e %7$s du nom de %1$s qui %4$s dans notre quartier. %9$s",
            "En poussant nos recherches il s’avère qu’iel maitrise %6$s. Iel est aussi %3$s, mais cette information n’est pas pertinente. "
        ],
        [
            "J’ai trouvé %1$s, un.e %7$s qui n’est clairement pas un loyal serviteur à vous, c’est un.e %2$s et un.e %3$s. ",
            "%9$sIel démontre une légère maitrise de la discipline %6$s. "
        ],
        [
            "Je me suis rendu compte qu’un.e %7$s %4$s dans le coin. On l’a entendu.e se faire appeler %1$s. %9$s",
            "C’était louche, alors j’ai enquêté et trouvé qu’iel a des pouvoirs de %4$s, ce qui en fait un.e %2$s un peu trop spécial.e. "
        ]
    ]',
    'Variation text blocks for Diff01 transformation reports'
),
-- Diff 2
-- %1$s - (network id)
-- %2$s - (text transformation discovered diff 2 or nothing)
-- %3$s - (disciplines if more than one or nothing)
(
    'textesDiff2', '[
        "%2$sEn plus, sa famille a des liens avec le réseau %1$s. %3$s",
        "Iel fait partie du réseau %1$s. %3$s %2$s",
        "%2$sEn creusant, iel est rattaché.e au réseau %1$s. %3$s",
        "%3$s Iel reçoit un soutien financier du réseau %1$s. %2$s",
        "%2$sIel traîne avec le réseau %1$s. %3$s"
    ]', 'Texts for search results level 2'),
--  Diff 3
-- %1$s - found_controler_name
-- %2$s - found_controler_faction
(
    'textesDiff3', '[
        "Ce réseau répond à %1$s. ",
        "A partir de là on a pu remonter jusqu’à %1$s. ",
        "Du coup, iel travaille forcément pour %1$s. ",
        "Nous l’avons vu.e rencontrer en personne %1$s. ",
        "Ce qui veut dire que c’est un des agents de %1$s. "
    ]',
    'Texts for search results level 3'
);


INSERT INTO config (name, value, description)
VALUES
-- %s - transformation name
('textesTransformationDiff1', '[
    " et nous concluons que c’est un.e %s",
    ", ce qui laisse penser que c’est un.e %s"
]', 'Texts for transformation level 1'
)
-- %s - transformation name
,('textesTransformationDiff2', '[
    "C’est probablement un.e %s mais les preuves nous manquent encore. ",
    "Iel n’est clairement pas normal.e, peut-être un.e %s. "
]', 'Texts for transformation level 2')
;

INSERT INTO config (name, value, description)
VALUES
-- Observers of a **failed** violent claim
-- %1$s - worker name
-- %2$s - zone name
('textesClaimFailViewArray', '[
    "J’ai vu %1$s tenter de prendre le contrôle du quartier %2$s, mais la défense l’a repoussé.e brutalement.",
    "L’assaut de %1$s sur le quartier %2$s a échoué ; c’était un vrai carnage.",
    "%1$s a voulu s’imposer au %2$s, sans succès. Iel a été forcé.e de battre en retraite.",
    "Je pense que %1$s pensait avoir une chance au %2$s. C’était mal calculé, iel s’est planté.e."
]', 'Texts the workers observing the failed violent claiming of a zone'),

-- Observers of a **successful** violent claim
-- %1$s - worker name
-- %2$s - zone name
-- %2$s - new controler name
('textesClaimSuccessViewArray', '[
    "J’ai vu %1$s renverser l’autorité sur %2$s. La zone a changé de mains.",
    "%2$s appartient désormais au maitre de %1$s. Iel a balayé toute résistance.",
    "L’opération de %1$s sur %2$s a été une réussite totale, malgré les dégats.",
    "%1$s a pris %2$s par la force. Iel n’a laissé aucune chance aux défenseurs."
]', 'Texts the workers observing the successful violent claiming of a zone'),

-- Report to the **claiming worker** on failure
-- %1$s - worker name
-- %2$s - zone name
('textesClaimFailArray', '[
    "Notre tentative de prise de contrôle de %2$s a échoué. La défense était trop solide.",
    "Nous avons échoué à nous imposer en force sur %2$s. Il faudra retenter plus tard.",
    "Notre assaut sur %2$s a été un échec. Les forces en place ont tenu bon.",
    "La mission de domination de %2$s n’a pas abouti. Trop de résistance à notre autorité sur place."
]', 'Texts for the fail report of the claiming worker'),

-- Report to the **claiming worker** on success
-- %1$s - worker name
-- %2$s - zone name
('textesClaimSuccessArray', '[
    "Nous avons pris le contrôle du quartier %2$s avec succès. Félicitations, vous en êtes désormais le maitre.",
    "Notre offensive sur la zone %2$s a porté ses fruits. Elle est maintenant à vous.",
    "Nous avons su imposer votre autorité sur %2$s. Le quartier vous obéit désormais.",
    "%2$s est tombé.e sous votre coupe."
]', 'Texts for the success report of the claiming worker');

INSERT INTO config (name, value, description)
VALUES
-- %s - week number
('workerDisappearanceTexts', '[
    "<p>Cet agent a disparu sans laisser de trace à partir de la semaine %s.</p>",
    "<p>Depuis la semaine %s, plus aucun signal ni message de cet agent.</p>",
    "<p>La connexion avec l’agent s’est perdue la semaine %s, et nous ignorons où iel se trouve.</p>",
    "<p>À partir de la semaine %s, cet agent semble s’être volatilisé.e dans la nature.</p>",
    "<p>Nous avons perdu toute communication avec cet agent depuis la semaine %s.</p>",
    "<p>La dernière trace de cet agent remonte à la semaine %s, depuis iel est aux abonnés absents.</p>",
    "<p>La semaine %s marque la disparition totale de cet agent. Aucun indice sur sa situation actuelle.</p>",
    "<p>L’agent s’est évanoui dans la nature après la semaine %s. Aucune nouvelle depuis.</p>",
    "<p>Depuis la semaine %s, cet agent est un fantôme, insaisissable et introuvable.</p>",
    "<p>La semaine %s signe le début du silence radio complet de cet agent.</p>"
]', 'Templates used for worker disappearance text with a week number placeholder');

INSERT INTO config (name, value, description)
VALUES
-- %1$s - target name
('attackSuccessTexts', '[
    "<p>J’ai pu mener à bien ma mission sur %1$s, son silence est assuré.</p>",
    "<p>J’ai accompli l’attaque sur %1$s, iel a trouvé son repos final.</p>",
    "<p>Notre cible %1$s a été accompagnée à l’hôpital dans un état critique, nous n’avons plus rien à craindre.</p>",
    "<p>Un.e suicidé.e sera retrouvé dans l’Arno demain, %1$s n’est plus des nôtres.</p>",
    "<p>Je confirme que %1$s ne posera plus jamais problème, iel a rejoint le silence éternel.</p>",
    "<p>Le dossier %1$s est officiellement clos. Son existence appartient désormais au passé.</p>",
    "<p>Mission accomplie : %1$s est désormais une simple note dans les annales de l’histoire.</p>"
]', 'Templates for successful attack reports mentioning the target name');

INSERT INTO config (name, value, description)
VALUES
-- %1$s - target name
('captureSuccessTexts', '[
    "<p>La mission est un succès total : %1$s est désormais entre nos mains, et nous allons mener l’interrogatoire.</p>",
    "<p>La mission s’est déroulée comme prévu : %1$s est capturé.e et prêt.e à livrer ses secrets.</p>",
    "<p>Succès complet sur %1$s : iel est désormais sous notre garde et n’aura d’autre choix que de parler.</p>",
    "<p>Nous avons maîtrisé %1$s : iel est maintenant entre nos mains, prêt.e pour l’interrogatoire.</p>",
    "<p>Mission accomplie : %1$s est capturé.e et en sécurité pour un débriefing approfondi.</p>",
    "<p>L’objectif %1$s est neutralisé et sous notre contrôle. L’interrogatoire peut commencer.</p>",
    "<p>Nous avons intercepté %1$s sans heurt : iel est désormais à notre merci pour un échange d’informations.</p>",
    "<p>Le succès est total : %1$s est retenu.e, et ses révélations ne tarderont pas.</p>",
    "<p>Mission terminée avec brio : %1$s est capturé.e et ne nous échappera plus.</p>"
]', 'Inclusive templates for successful capture reports mentioning the target name');

INSERT INTO config (name, value, description)
VALUES
-- %1$s - target name
('failedAttackTextes', '[
    "<p>Malheureusement, %1$s a réussi à nous échapper et reste en vie.</p>",
    "<p>L’opération contre %1$s a échoué. La cible a survécu et demeure une menace.</p>",
    "<p>Notre tentative contre %1$s s’est soldée par un échec. Iel est toujours actif.ve.</p>",
    "<p>L’attaque n’a pas atteint son objectif : %1$s a survécu et garde sa liberté.</p>",
    "<p>Nous n’avons pas pu neutraliser %1$s. Iel reste introuvable après l’affrontement.</p>",
    "<p>La mission a été un revers : %1$s est toujours debout et hors de notre portée.</p>",
    "<p>Malgré nos efforts, %1$s s’est défendu.e avec succès et a réussi à fuir.</p>",
    "<p>Notre assaut n’a pas suffi : %1$s a survécu et continue d’agir.</p>",
    "<p>La cible %1$s s’est montrée plus résistante que prévu. Iel a échappé à notre emprise.</p>",
    "<p>Nous avons échoué à neutraliser %1$s. Iel demeure vivant.e et peut encore riposter.</p>"
]', 'Texts for failed attacks in inclusive language');

INSERT INTO config (name, value, description)
VALUES
-- %1$s - attacker name
('escapeTextes', '[
    "<p>J’ai été pris.e pour cible par %1$s, mais j’ai réussi à lui échapper de justesse.</p>",
    "<p>Une attaque orchestrée par %1$s a failli m’avoir, mais j’ai pu me faufiler hors de sa portée.</p>",
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
    "<p>Je pars mettre en route le plan d’assassinat de %s. [Le rapport n’a jamais été terminé·e.]</p>",
    "<p>Début de la mission : %s. [Le rapport n’a jamais été terminé·e.]</p>",
    "<p>Nous avons perdu contact avec l’agent juste après le début de l’opération sur %s.</p>",
    "<p>Le silence radio après le lancement de la mission contre %s est inquiétant…</p>",
    "<p>L’équipe envoyée pour neutraliser %s n’est jamais revenue.</p>"
]', 'Texts for missions that fail and result in counter-attack or disappearance');

INSERT INTO config (name, value, description)
VALUES
-- %s - Assaulter name 
('counterAttackTexts', '[
    "<p>%1$s m’a attaqué·e, j’ai survécu et ma riposte l’a anéanti·e. J’ai jeté son corps dans l’Arno.</p>",
    "<p>Après avoir été attaqué·e par %1$s, j’ai non seulement survécu, mais ma riposte nous assure qu’iel ne posera plus problème.</p>",
    "<p>%1$s a cru m’avoir, mais ma riposte a brisé ses espoirs et l’a détruit·e.</p>",
    "<p>Iel a tenté de me réduire au silence, mais après avoir survécu à l’attaque de %1$s, j’ai répondu par une riposte fatale.</p>",
    "<p>Malgré l’assaut de %1$s, ma riposte a non seulement sauvé ma vie, mais a mis fin à ses ambitions.</p>",
    "<p>Attaqué·e par %1$s, j’ai résisté et ma riposte l’a anéanti·e sans retour.</p>",
    "<p>Iels ont cherché à me faire tomber, mais ma riposte après l’attaque de %1$s a effacé toute menace.</p>",
    "<p>L’attaque de %1$s a échoué, et ma réponse a été rapide, fatale et décisive.</p>",
    "<p>Je me suis retrouvé·e face à %1$s, mais après avoir survécu à son attaque, ma riposte a scellé son destin.</p>",
    "<p>Après une attaque brutale de %1$s, ma survie et ma riposte ont fait en sorte qu’iel n’ait plus rien à revendiquer.</p>"
]', 'Texts for the worker who was atacked an the successfully countered');


INSERT INTO config (name, value, description)
VALUES
-- %s = nom de la localisation
('TEXT_LOCATION_DISCOVERED_NAME', '[
    "Nous avons identifié une installation appelée %s.",
    "Des signes pointent vers une présence suspecte à l’endroit de %s."
]', 'Phrases pour signaler qu’une localisation a été découverte (nom uniquement)'),

-- %s = description de la localisation
('TEXT_LOCATION_DISCOVERED_DESCRIPTION', '[
    " Description : %s.",
    " Détails révélés : %s"
]', 'Phrases pour décrire une localisation après enquête'),

-- Aucun paramètre : simple indication de la possibilité de destruction
('TEXT_LOCATION_CAN_BE_DESTROYED', '[
    " Cette localisation peut être ciblée pour destruction.",
    " Il est possible d’organiser une opération pour la neutraliser."
]', 'Phrases pour signaler qu’une localisation peut être détruite')
;

