
UPDATE config SET value = 'Shikoku (四国) 1555' WHERE name = 'TITLE';
-- https://fr.wikipedia.org/wiki/%C3%89poque_Sengoku
UPDATE config SET
    value = '<p> En plein Sengoku Jidai, les turbulences sociales, intrigues politiques et conflits militaires divisent le Japon.
        Les guerres fratricides font rage sur l’archipel nippon, et le Shogunat Ashikaga fragilisé peine à rétablir la paix.
        Au printemps 1555, les forces du Daïmyo de Shikoku, sire Kunichika Chōsokabe, accompagné de ses vassaux Fujitaka Hosokawa et Motonaga Miyoshi,
         sont partis sur Honshu défendre Kyoto contre les forces de du clan Takeda, espérant ainsi s’attirer les faveurs du Shogun Ashikaga.
        Les rares survivants rentrés de la campagne parlent d’une défaite cuisante, d’une rébellion paysanne et du déshonneur du Daïmyo et de ses vassaux.
        Le controle du clan Chōsokabe vacille sur Shikoku et les vassaux même du clan voient la disparition de Kunichika Chōsokabe comme une opportunité sans précédent.
        Celui qui pourra s’octroyer l’allégeance de la majorité des 4 provinces sera maitre de l’ile de Shikoku.
        </p>'
    WHERE name = 'PRESENTATION';

INSERT INTO config (name, value, description)
VALUES
 ('textRecrutementJobHobby', 'Est un.e %5$s avec un.e %4$s', 'string to present hobby %4$s and job %5$s on recrutement')
 ,('textViewWorkerJobHobby','c’est un.e %2$s avec un.e %3$s ', 'string to present hobby %2$s and job %3$s view of worker')
 ,('textViewWorkerDisciplines', 'Ses disciplines développées sont : %s <br />', 'Texts for worker view page disciplines')
 ,('textViewWorkerTransformations', 'Iel a été équipé de : %s <br />', 'Texts for worker view page transformations')
 ,('texteBaseRepaire', '
        Nous avons trouvé la forteresse de %1$s. Ses serviteurs ne semblent pas avoir fini de remettre en place les défenses. En attaquant ce lieu nous pourrions lui porter un coup fatal.
        Sa disparition causerait certainement quelques questions à la coure, mais un joueur en moins sur l’échiquier politique est toujours bénéfique.
        Nous ne devons pas tarder à prendre notre décision, ses défenses se renforcent.
    ','Texts for worker view page transformations')
;

INSERT INTO config (name, value, description)
VALUES
 ('textControlerActionCreateBase', 'Crée une forteresse dans la région :', 'create base texte in controler view actions')
 ,('textControlerRecrutmentNeedsBase', 'Nous ne pouvons pas recruter sans avoir une forteresse.', 'needed base for recrutment')
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
        "J’ai des raisons de penser qu’il est natif de %s. ",
        "En plus, il est originaire de %s. ",
        "Je m’en méfie, il vient de %s. "
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
        "%2$sEn plus, sa famille a des liens avec le réseau %1$s. %3$s",
        "Iel fait partie du réseau %1$s. %3$s %2$s ",
        "%2$sEn creusant, iel est rattaché.e au réseau %1$s. %3$s ",
        "%3$s Iel reçoit un soutien financier du réseau %1$s. %2$s",
        "%2$sIel travaille avec le réseau %1$s. %3$s"
    ]', 'Texts for search results level 2'),
--  Diff 3
-- %1$s - found_controler_name
-- %2$s - found_controler_faction
(
    'textesDiff3', '[
        "Ce réseau répond à %1$s. ",
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
    " et nous concluons que c’est un %s",
    ", ce qui laisse penser que c’est un %s"
]', 'Texts for transformation level 1')
,('textesTransformationDiff2', '[
    "C’est probablement un %s mais les preuves nous manquent encore. ",
    "Il n’est clairement pas normal, peut-être un %s. "
]', 'Texts for transformation level 2')
;


INSERT INTO config (name, value, description)
VALUES
-- Observers of a **failed** violent claim
('textesClaimFailViewArray', '[
    "J’ai vu %1$s tenter de prendre le contrôle du quartier %2$s, mais la défense l’a repoussé.e brutalement.",
    "L’assaut de %1$s sur le quartier %2$s a échoué ; c’était un vrai carnage.",
    "%1$s a voulu s’imposer au %2$s, sans succès. Iel a été forcé.e de battre en retraite.",
    "Je pense que %1$s pensait avoir une chance au %2$s. C’était mal calculé."
]', 'Texts the workers observing the failed violent claiming of a zone'),

-- Observers of a **successful** violent claim
('textesClaimSuccessViewArray', '[
    "J’ai vu %1$s renverser l’autorité sur %2$s. La zone a changé de mains.",
    "%2$s appartient désormais au maitre de %1$s. Iel a balayé toute résistance.",
    "L’opération de %1$s sur %2$s a été une réussite totale, malgré les dégats.",
    "%1$s a pris %2$s par la force. Iel n’a laissé aucune chance."
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
    "Nous avons pris le contrôle du quartier %2$s avec succès. Félicitations vous en êtes désormais le maitre.",
    "Notre offensive sur la zone %2$s a porté ses fruits. Elle est maintenant à vous.",
    "Nous avons su imposer votre autorité sur %2$s. La zone vous obéit désormais.",
    "%2$s est tombé.e sous votre coupe."
]', 'Texts for the success report of the claiming worker');
