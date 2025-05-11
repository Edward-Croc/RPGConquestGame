
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
    ('Zudabukuro (頭陀袋) – Une besace de pèlerin', ', utile pour transporter discrètement messages, herbes médicinales ou objets de culte', 1,0,0),
    ('Juzu (数珠) – Un bracelet de perles bouddhistes', ', utilisé pour la prière mais aussi comme symbole d’appartenance à une école spirituelle', 1,0,0),
    ('Jirei (持鈴) – Une petite clochette', ', utilisée pour signaler sa présence dans les temples, ou détourner l’attention', 1,0,0),
    ('Wagesa (輪袈裟) – Une étole courte', ', portée par les pratiquants bouddhistes, pouvant dissimuler de petits objets plats', 1,0,0),
    ('Kongōzue (金剛杖) – Un bâton de pèlerin', ', à la fois soutien physique et arme d’autodéfense', 1,0,0),
    ('Nōkyōchō (納経帳) – Un carnet de pèlerinage', ', pouvant cacher des messages codés', 1,0,0),
    ('Tantō (短刀) – Un petit poignard', ', facile à dissimuler, souvent utilisé pour les assassinats silencieux ou le seppuku rituel', 1,0,0),
    ('Katana (刀) – L’arme emblématique du samouraï', ', symbole d’honneur et de rang, mais peu pratique pour les agents discrets', 1,0,0),
    ('Teppō (鉄砲) – Un mousquet', ', des prototypes artisanaux circulaient en version expérimentale dès la fin du 14e siècle', 1,0,0),
    ('Kiseru (煙管) – Une pipe à tabac', ', parfois modifiée pour dissimuler des messages roulés ou une poudre soporifique', 1,0,0),
    ('Inrō (印籠) – Une petite boîte à la ceinture', ', utilisée pour transporter des médicaments, du poison, ou de minuscules outils', 1,0,0),
    ('Ofuda (お札) – Talismans', ', parfois utilisés comme code de reconnaissance', 1,0,0),
    ('Sutegasa (捨笠) – Chapeau conique de pèlerin', ', masquant partiellement le visage, utile pour éviter d’être reconnu', 1,0,0),
    ('Mala-no-fuku (摩羅の服) – Vêtement d’ascète', ', permettant de dissimuler objets ou armes discrètes', 1,0,0),
    ('Kōro (香炉) – Petit encensoir de voyage', ', utilisé dans les rites mais aussi pour masquer d’autres odeurs', 1,0,0),
    ('Shikomi-zue (仕込み杖) – Canne-lame', ', idéale pour passer inaperçu', 1,0,0),
    ('Fukiya (吹き矢) – Sarbacane silencieuse', ', utilisée pour endormir ou empoisonner', 1,0,0),
    ('Uchitake (打竹) – Bâtons creux en bambou', ', utilisés pour transmettre des sons codés ou cacher de petits objets', 1,0,0),
    ('Chigiriki (契木) – Masse à chaîne', ', dissimulée dans un bâton, arme piégeuse', 1,0,0),
    ('Tessen (鉄扇) – Éventail de fer', ', à la fois accessoire et arme défensive ou offensive', 1,0,0),
    ('Jitte (十手) – Arme de police', ', utilisée pour parer les lames et capturer sans tuer', 1,0,0),
    ('Neko-te (猫手) – Griffes métalliques', ', portées aux doigts, utilisées par les agents féminins ou espions', 1,0,0),
    ('Wakizashi (脇差) – Sabre court', ', pratique en intérieur ou pour les duels rapprochés', 1,0,0),
    ('Shuriken (手裏剣) – Étoiles de lancer', ', utilisées pour distraire ou blesser', 1,0,0),
    ('Kunai (苦無) – Dague multi-usage', ', pouvant être lancée ou utilisée comme outil', 1,0,0),
    ('Kakute (角手) – Anneau à pointes', ', souvent empoisonnées, porté pour des attaques discrètes', 1,0,0),
    ('Kanzashi (簪) – Épingles à cheveux', ', parfois dotées de fonctions symboliques ou défensives', 1,0,0),
    ('Sensu (扇子) – Éventail pliable', ', utilisé pour transmettre des messages codés', 1,0,0),
    ('Hyōshigi (拍子木) – Claves en bois', ', utilisées dans les spectacles ou comme signal sonore', 1,0,0),
    ('Shamisen (三味線) – Instrument à cordes', ', central dans la musique des geishas', 1,0,0),
    ('Furushiki (風呂敷) – Carré de tissu', ', utilisé pour emballer ou transporter des objets', 1,0,0),
    ('Makimono (巻物) – Rouleau calligraphié', ', utilisé pour transmettre un poème ou un message esthétique', 1,0,0),
    ('Chadōgu (茶道具) – Ustensiles du thé', ', bol, fouet, cuillère, etc', 1,0,0),
    ('Go-ban (碁盤) – Plateau de Go', ', accompagné de pierres noires et blanches', 1,0,0),
    ('Koma (駒) – Pièces de shōgi', ', jeu de stratégie prisé des samouraïs', 1,0,0),
    ('Kōdōgu (香道具) – Ustensiles du kōdō', ', brûleurs, pinces, cendriers raffinés', 1,0,0),
    ('Etegami (絵手紙) – Cartes illustrées', ', accompagnées de haïkus ou proverbes', 1,0,0),
    ('Ukiyo-e (浮世絵) – Estampes artistiques', ', représentant des scènes de vie ou de la nature', 1,0,0),
    ('Shōkadō bentō (松花堂弁当) – Boîte à compartiments', ', utilisée lors de repas raffinés', 1,0,0),
    ('Tokkuri (徳利) – Bouteille à saké', ', en céramique pour servir le saké chaud', 1,0,0),
    ('Yakuyō bako (薬用箱) – Boîte à remèdes', ', contenant des plantes médicinales', 1,0,0),
    ('Shōyaku fukuro (生薬袋) – Sachets de plantes', ', à infuser ou brûler à des fins curatives', 1,0,0),
    ('Fude (筆) – Pinceau de calligraphie', ', souvent conservé dans un étui de bambou', 1,0,0)
;

/*
Zudabukuro (頭陀袋) – Une besace de pèlerin, utile pour transporter discrètement messages, herbes médicinales ou objets de culte.
Juzu (数珠) – Un bracelet de perles bouddhistes, utilisé pour la prière mais aussi comme symbole d'appartenance à une école spirituelle.
Jirei (持鈴) – Une petite clochette, utilisée pour signaler sa présence dans les temples, ou détourner l’attention.
Wagesa (輪袈裟) – Une étole courte portée par les pratiquants bouddhistes, pouvant dissimuler de petits objets plats.
Kongōzue (金剛杖) – Un bâton de pèlerin, à la fois soutien physique et arme d’autodéfense.
Nōkyōchō (納経帳) – Un carnet dans lequel les pèlerins font apposer les sceaux des temples visités — pouvant cacher des messages codés.
Tantō (短刀) – Un petit poignard, facile à dissimuler, souvent utilisé pour les assassinats silencieux ou le seppuku rituel.
Katana (刀) – L’arme emblématique du samouraï, symbole d’honneur et de rang, mais peu pratique pour les agents discrets.
Teppō (鉄砲) – Un mousquet introduit au Japon au 16e siècle, mais des prototypes artisanaux circulaient en version expérimentale dès la fin du 14e siècle.
Kiseru (煙管) – Une pipe à tabac, parfois modifiée pour dissimuler des messages roulés ou une poudre soporifique.
Inrō (印籠) – Une petite boîte portée à la ceinture, utilisée pour transporter des médicaments, du poison, ou de minuscules outils.
Ofuda (お札) – Talismans en papier ou bois portant des prières ou invocations, parfois utilisés comme code de reconnaissance.
Sutegasa (捨笠) – Chapeau conique de pèlerin, masquant partiellement le visage, utile pour éviter d’être reconnu.
Mala-no-fuku (摩羅の服) – Vêtement ample typique des ascètes, permettant de dissimuler objets ou armes discrètes.
Kōro (香炉) – Petit encensoir de voyage, utilisé dans les rites mais aussi pour masquer d'autres odeurs.
Shikomi-zue (仕込み杖) – Canne dissimulant une lame, idéale pour passer inaperçu.
Fukiya (吹き矢) – Sarbacane silencieuse utilisée pour endormir ou empoisonner.
Uchitake (打竹) – Bâtons de bambou creux utilisés pour transmettre des sons codés à distance (ou cacher de petits objets).

Chigiriki (契木) – Masse à chaîne dissimulée dans un bâton, arme piégeuse.
Tessen (鉄扇) – Éventail de fer, à la fois accessoire et arme défensive ou offensive.
Jitte (十手) – Arme de police, utilisée pour parer les lames et capturer sans tuer.
Neko-te (猫手) – Griffes métalliques portées aux doigts, utilisées par les agents féminins ou espions.
Wakizashi (脇差) – Sabre court porté en complément du katana, pratique en intérieur ou pour les duels rapprochés.

Shuriken (手裏剣) – Étoiles ou pointes de lancer, utilisées pour distraire ou blesser.
Kunai (苦無) – Dague multi-usage pouvant être lancée ou utilisée comme outil.
Kakute (角手) – Anneau à pointes souvent empoisonnées, porté sur le doigt pour des attaques discrètes.
Kanzashi (簪) – Épingles à cheveux décoratives, parfois dotées de fonctions symboliques ou défensives.

Sensu (扇子) – Éventail pliable en papier ou en soie, utilisé dans la danse, la conversation, ou pour transmettre des messages codés.
Hyōshigi (拍子木) – Claves de bois utilisées dans les spectacles ou comme signal sonore.
Shamisen (三味線) – Instrument à cordes pincées, central dans la musique des geishas et des spectacles.
Furushiki (風呂敷) – Carré de tissu utilisé pour emballer ou transporter des objets avec grâce.
Makimono (巻物) – Rouleau peint ou calligraphié, souvent offert en cadeau ou utilisé pour transmettre un poème ou un message esthétique.

Chadōgu (茶道具) – Ustensiles utilisés dans la cérémonie du thé : bol (chawan), fouet (chasen), cuillère (chashaku), etc.
Go-ban (碁盤) – Plateau de jeu de Go, souvent en bois de kaya, accompagné de pierres noires et blanches (go-ishi).
Koma (駒) – Pièces du jeu de shōgi (échecs japonais), jeu de stratégie prisé des samouraïs et des lettrés.
Kōdōgu (香道具) – Ustensiles du kōdō, l’art d’apprécier les encens (brûleurs, pinces, cendriers raffinés).
Etegami (絵手紙) – Petites cartes illustrées à la main accompagnées de haïkus ou de proverbes, échangées entre artistes ou lettrés.
Ukiyo-e (浮世絵) – Estampes artistiques représentant des scènes de la vie, de la nature ou des portraits d’acteurs et de geishas.

Shōkadō bentō (松花堂弁当) — Boîte laquée compartimentée utilisée lors de repas raffinés.
Tokkuri (徳利) — Bouteille en céramique pour servir le saké chaud.
Yakuyō bako (薬用箱) — Boîte à remèdes contenant des plantes médicinales séchées.
Shōyaku fukuro (生薬袋) — Petits sachets de plantes à infuser ou brûler à des fins curatives.
Fude (筆) — Pinceau de calligraphie, souvent conservé dans un étui de bambou.

*/