
UPDATE config SET value = 'Firenze 1966' WHERE name = 'TITLE';
UPDATE config SET
    value = '<p>Le 6 novembre 1966, l’Arno inonde une grande partie du centre-ville, endommageant de nombreux chefs-d’œuvre et deplacent la population du centre ville.
        Un grand mouvement de solidarité internationale naît à la suite de cet évènement et mobilise des milliers de volontaires, surnommés Les anges de la boue.
        Dans les jours suivant la catastrophe les forces surnaturelles reprennent doucement pied dans le la ville. Retrouverez vous votre pouvoir d’antant ?
        </p>'
    WHERE name = 'PRESENTATION';

INSERT INTO config (name, value, description)
VALUES
 ('textRecrutementJobHobby', '%4$s, %5$s', 'string to present hobby %4$s and job %5$s on recrutement')
 ,('textViewWorkerJobHobby','c’etait un.e %3$s et iel est un.e %2$s', 'string to present hobby %2$s and job %3$s view of worker')
 ,('textViewWorkerDisciplines', 'Ses disciplines développées sont : %s <br />', 'Texts for worker view page disciplines')
 ,('textViewWorkerTransformations', 'Iel a été transformé en : %s <br />', 'Texts for worker view page transformations')
;

INSERT INTO config (name, value, description)
VALUES
 (
    'textesStartInvestigate', '<p> Nous avons mené l’enquête dans le quartier %s.</p>', 'Texts for start of investigation')
,(
    'textesFoundDisciplines',
    '[
        "Et avec une maitrise de la discipline %s.",
        "Et maitrise de la discipline %s.",
        "En plus de maitrisé la discipline %s.",
        "Cumulant aussi la discipline %s."
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
-- (metier) - %7$s/%2$s
-- (hobby) - %3$s
-- (action_ps) - %4$s
-- (action_inf) - %5$s
-- (discipline) - %6$s
-- (transformation1) - %8$s
-- (origin_text) - %9$s
(
    'textesDiff01Array',
    '[
        [
            "J’ai vu un %2$s du nom de %1$s qui %4$s dans ma zone d’action. %9$s",
            "C’est à la base un %3$s mais je suis sûr qu’iel possède aussi la discipline de %6$s%8$s."
        ],
        [
            "Nous avons repéré un.e %2$s du nom de %1$s qui %4$s dans notre quartier. %9$s",
            "En poussant nos recherches il s’avère qu’iel maitrise %6$s%8$s. Iel est aussi %3$s, mais cette information n’est pas si pertinente."
        ],
        [
            "J’ai trouvé %1$s %7$s, qui n’est clairement pas un agent à nous, c’est un.e %2$s et un.e %3$s.",
            "%9$sIel démontre une légère maitrise de la discipline %6$s%8$s."
        ],
        [
            "Je me suis rendu compte que %1$s, que je prenais pour un simple %3$s, %4$s dans la région. %9$s",
            "C’était louche, alors j’ai enquêté et trouvé qu’iel a en réalité des pouvoirs de %6$s, ce qui en fait un.e %2$s un peu trop spécial.e%8$s."
        ],
        [
            "On a suivi %1$s parce qu’on l’a repéré.e en train de %5$s, ce qui nous a mis la puce à l’oreille. C’est normalement un.e %2$s mais on a découvert qu’il était aussi un.e %3$s.",
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
            "Nous avons repéré un %7$s du nom de %1$s qui %4$s dans notre quartier. %9$s",
            "En poussant nos recherches il s’avère qu’il maitrise %6$s. Il est aussi %3$s, mais cette information n’est pas pertinente."
        ],
        [
            "J’ai trouvé %1$s, un %7$s qui n’est clairement pas un loyal serviteur à vous, c’est un %2$s et un %3$s. %9$s",
            "Il démontre une légère maitrise de la discipline %6$s."
        ],
        [
            "Je me suis rendu compte qu’un %7$s %4$s dans le coin. On l’a entendu se faire appeler %1$s. %9$s",
            "C’était louche, alors j’ai enquêté et trouvé qu’il a des pouvoirs de %4$s, ce qui en fait un %2$s un peu trop spécial."
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
        "Il fait partie du réseau %1$s. %3$s %2$s ",
        "%2$sEn creusant, il est rattaché au réseau %1$s. %3$s ",
        "%3$s Il reçoit un soutien financier du réseau %1$s. %2$s",
        "%2$sIl traîne avec le réseau %1$s. %3$s"
    ]', 'Texts for search results level 2'),
--  Diff 3
-- %1$s - found_controler_name
(
    'textesDiff3', '[
        "Ce réseau répond à %1$s. ",
        "A partir de là on a pu remonter jusqu’à %1$s. ",
        "Du coup, il travaille forcément pour %1$s. ",
        "Nous l’avons vu rencontrer en personne %1$s. ",
        "Ce qui veut dire que c’est un des types de %1$s. "
    ]',
    'Texts for search results level 3'
);


INSERT INTO config (name, value, description)
VALUES
('textesTransformationDiff1', '[
    " et nous concluons que c’est un.e %s",
    ", ce qui laisse penser que c’est un.e %s"
]', 'Texts for transformation level 1'),

('textesTransformationDiff2', '[
    "C’est probablement un.e %s mais les preuves nous manquent encore. ",
    "Iel n’est clairement pas normal, peut-être un.e %s. "
]', 'Texts for transformation level 2');

INSERT INTO config (name, value, description)
VALUES
-- Observers of a **failed** violent claim
('textesClaimFailViewArray', '[
    "J’ai vu %1$s tenter de prendre le contrôle du quartier %2$s, mais la défense l’a repoussé brutalement.",
    "L’assaut de %1$s sur le quartier %2$s a échoué ; c’était un vrai carnage.",
    "%1$s a voulu s’imposer au %2$s, sans succès. Iel a été forcé.e de battre en retraite.",
    "Je pense que %1$s pensait avoir une chance au %2$s. C’était mal calculé."
]', 'Texts the workers observing the failed violent claiming of a zone'),

-- Observers of a **successful** violent claim
('textesClaimSuccessViewArray', '[
    "J’ai vu %1$s renverser l’autorité sur %2$s. La zone as changé de main.",
    "%2$s appartient désormais au maitre de %1$s. Iel a balayé toute résistance.",
    "L’opération de %1$s sur %2$s a été une réussite totale, malgrès les dégats.",
    "%1$s a pris %2$s par la force. Iel n’a laissé aucune chance."
]', 'Texts the workers observing the successful violent claiming of a zone'),

-- Report to the **claiming worker** on failure
('textesClaimFailArray', '[
    "Notre tentative de prise de contrôle de %2$s a échoué. La défense était trop solide.",
    "Nous avons échoué à imposer notre force sur %2$s. Il faudra retenter plus tard.",
    "L’assaut sur %2$s a été un échec. Les forces en place ont tenu bon.",
    "La mission de domination de %2$s n’a pas abouti. Trop de résistance à notre autorité sur place."
]', 'Texts for the fail report of the claiming worker'),

-- Report to the **claiming worker** on success
('textesClaimSuccessArray', '[
    "Nous avons pris le contrôle du quartier %2$s avec succès. Félicitations vous en êtes désormais le maitre.",
    "Notre offensive sur la zone %2$s a porté ses fruits. Elle est maintenant à vous.",
    "Nous avons su imposer votre autorité sur %2$s. La zone vous obéit désormais.",
    "%2$s est tombée sous votre coupe."
]', 'Texts for the success report of the claiming worker');
