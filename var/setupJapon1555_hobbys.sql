    -- Suggested Hobbies
    -- Possible Values Based on +1 :
    -- ('', 1,0,0), ('', 0,1,0), ('', 0,0,1),
    -- ('', -1,1,1), ('', 1,-1,1), ('', 1,1,-1),
    -- ('', -1,2,0), ('', -1,0,2), ('', 2,-1,0), ('', 0,-1,2), ('', 2,0,-1), ('', 0,2,-1),
    -- Possible Values Based on +1 : With imbalance on defence
    -- ('', 1,0,0), ('', 0,1,1),
    -- ('', 1,-1,1), ('', 1,1,-1),
    -- ('', -1,0,2) ('', -1,2,1), ('', 2,-1,0),('', 2,0,-1), ('', 0,2,-1),
    
    -- ('', 1,0,0), => Enqueteurs
    -- ('', 0,1,1), => Combattants
    -- ('', 1,1,-1), => Glass cannons
    -- ('', 2,0,-1), => Maitres Enqueteurs
    -- ('', -1,2,1), => Maitres Combattants

INSERT INTO powers (name, description, enquete, attack, defence) VALUES
    -- ('', 1,0,0), => Enqueteurs
    ('Juzu (数珠) – Un bracelet de perles bouddhistes', ', utilisé pour la prière mais aussi comme symbole d’appartenance à une école spirituelle', 1,0,0),
    ('Jirei (持鈴) – Une petite clochette', ', utilisée pour signaler sa présence dans les temples, ou détourner l’attention', 1,0,0),
    ('Nōkyōchō (納経帳) – Un carnet de pèlerinage', ', pouvant cacher des messages codés', 1,0,0),
    ('Ofuda (お札) – Talismans', ', parfois utilisés comme code de reconnaissance', 1,0,0),
    ('Kōro (香炉) – Petit encensoir de voyage', ', utilisé dans les rites mais aussi pour masquer d’autres odeurs', 1,0,0),
    ('Uchitake (打竹) – Bâtons creux en bambou', ', utilisés pour transmettre des sons codés ou cacher de petits objets', 1,0,0),
    ('Sensu (扇子) – Éventail pliable', ', utilisé pour transmettre des messages codés', 1,0,0),
    ('Hyōshigi (拍子木) – Claves en bois', ', utilisées dans les spectacles ou comme signal sonore', 1,0,0),
    ('Shamisen (三味線) – Instrument à cordes', ', central dans la musique des geishas', 1,0,0),
    ('Furushiki (風呂敷) – Carré de tissu', ', utilisé pour emballer ou transporter des objets', 1,0,0),
    ('Makimono (巻物) – Rouleau calligraphié', ', utilisé pour transmettre un poème ou un message esthétique', 1,0,0),
    ('Go-ban (碁盤) – Plateau de Go', ', accompagné de pierres noires et blanches', 1,0,0),
    ('Koma (駒) – Pièces de shōgi', ', jeu de stratégie prisé des samouraïs', 1,0,0),
    ('Kōdōgu (香道具) – Ustensiles du kōdō', ', brûleurs, pinces, cendriers raffinés', 1,0,0),
    ('Etegami (絵手紙) – Cartes illustrées', ', accompagnées de haïkus ou proverbes', 1,0,0),
    ('Ukiyo-e (浮世絵) – Estampes artistiques', ', représentant des scènes de vie ou de la nature', 1,0,0),
    ('Fude (筆) – Pinceau de calligraphie', ', souvent conservé dans un étui de bambou', 1,0,0),
    -- ('Wagesa (輪袈裟) – Une étole courte', ', portée par les pratiquants bouddhistes, pouvant dissimuler de petits objets plats', 1,0,0),
    -- ('Sutegasa (捨笠) – Chapeau conique de pèlerin', ', masquant partiellement le visage, utile pour éviter d’être reconnu', 1,0,0),
    -- ('', 2,0,-1), => Maitres Enqueteurs
    ('Tokkuri (徳利) – Bouteille à saké', ', en céramique pour servir le saké chaud',  2,0,-1),
    ('Mala-no-fuku (摩羅の服) – Vêtement d’ascète', ', permettant de dissimuler objets ou armes discrètes',  2,0,-1),
    ('Shōkadō bentō (松花堂弁当) – Boîte à compartiments', ', utilisée lors de repas raffinés',  2,0,-1),
    ('Chadōgu (茶道具) – Ustensiles du thé', ', bol, fouet, cuillère, etc',  2,0,-1),

    -- ('', 0,1,1), => Combattants
    ('Kongōzue (金剛杖) – Un bâton de pèlerin', ', à la fois soutien physique et arme d’autodéfense', 0, 1,1),
    ('Tantō (短刀) – Un petit poignard', ', facile à dissimuler, souvent utilisé pour les assassinats silencieux ou le seppuku rituel', 0, 1,1),
    ('Fukiya (吹き矢) – Sarbacane silencieuse', ', utilisée pour endormir ou empoisonner', 0, 1,1),
    ('Shikomi-zue (仕込み杖) – Canne-lame', ', idéale pour passer inaperçu', 0, 1,1),
    ('Chigiriki (契木) – Masse à chaîne', ', dissimulée dans un bâton, arme piégeuse', 0, 1,1),
    ('Tessen (鉄扇) – Éventail de fer', ', à la fois accessoire et arme défensive ou offensive', 0, 1,1),
    ('Neko-te (猫手) – Griffes métalliques', ', portées aux doigts, utilisées par les agents féminins ou espions', 0, 1,1),
    ('Wakizashi (脇差) – Sabre court', ', pratique en intérieur ou pour les duels rapprochés', 0, 1,1),
    ('Shuriken (手裏剣) – Étoiles de lancer', ', utilisées pour distraire ou blesser', 0, 1,1),
    ('Kunai (苦無) – Dague multi-usage', ', pouvant être lancée ou utilisée comme outil', 0, 1,1),
    ('Kakute (角手) – Anneau à pointes', ', souvent empoisonnées, porté pour des attaques discrètes', 0, 1,1),
    ('Hankyū (半弓) — Arc court', ', pratique pour le combat rapproché ou en terrain dense, souvent utilisé par les fantassins', 0, 1,1),
    ('Kusarigama (鎖鎌) — Arme composée d’une faucille attachée à une chaîne lestée', ', utilisée pour désarmer et piéger l’ennemi', 0, 1,1),
    ('Jitte (十手) – Arme de police', ', utilisée pour parer les lames et capturer sans tuer', 0, 1,1),
    -- ('', -1,2,1), => Maitres Combattants
    ('Teppō (鉄砲) – Un mousquet', ', des prototypes artisanaux circulaient en version expérimentale dès la fin du 14e siècle', -1, 2,1),
    ('Katana (刀) – L’arme emblématique du samouraï', ', symbole d’honneur et de rang, mais peu pratique pour les agents discrets', -1, 2,1),
    ('Yumi (弓) — Grand arc asymétrique utilisé par les samouraïs', ', redouté pour sa portée et sa précision à cheval comme à pied', -1, 2,1),
    ('Yari (槍) — Lance droite à pointe effilée', ', polyvalente en formation comme en duel, arme principale de nombreux ashigaru', -1, 2,1),
    ('Naginata (薙刀) — Arme d’hast à lame courbe', ', maniée avec grâce et puissance, souvent associée aux femmes guerrières ou aux moines', -1, 2,1),
    ('Tetsubō (鉄棒) — Masse de guerre en fer', ', capable d’écraser les armures, prisée par les moines-soldats', -1, 2,1),

    -- ('', 1,1,-1), => Glass cannons
    ('Inrō (印籠) – Une petite boîte à la ceinture', ', utilisée pour transporter des médicaments, du poison, ou de minuscules outils', 1, 1,-1),
    ('Kanzashi (簪) – Épingles à cheveux', ', parfois dotées de fonctions symboliques ou défensives', 1, 1,-1),
    ('Yakuyō bako (薬用箱) – Boîte à remèdes', ', contenant des plantes médicinales', 1, 1,-1),
    ('Shōyaku fukuro (生薬袋) – Sachets de plantes', ', à infuser ou brûler à des fins curatives', 1, 1,-1),
    ('Zudabukuro (頭陀袋) – Une besace de pèlerin', ', utile pour transporter discrètement messages, herbes médicinales ou objets de culte', 1, 1,-1),
    ('Kiseru (煙管) – Une pipe à tabac', ', parfois modifiée pour dissimuler des messages roulés ou une poudre soporifique', 1, 1,-1)
;
