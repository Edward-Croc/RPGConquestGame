
-- Insert names into worker_origins
INSERT INTO worker_origins (name) VALUES
      ('Shikoku - Ehime')
    , ('Shikoku - Kochi')
    , ('Shikoku - Tokushima')
    , ('Shikoku - Kagawa')
    , ('Shikoku - Awaji')
    , ('Shikoku - Shōdoshima')
    , ('Honshu - Kyoto')
    , ('Honshu - Osaka')
    , ('Honshu - Okayama')
    , ('Honshu - Hiroshima')
    , ('Kyushu - Öita')
    , ('France')
    , ('Portugal')
;

-- Insert names into worker_names
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
-- Shikoku - Ehime
    ('Haruki', 'Takahashi', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Ehime')),
    ('Yui', 'Nakamura', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Ehime')),
    ('Ren', 'Sato', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Ehime')),
    ('Kaito', 'Yamamoto', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Ehime')),
    ('Aiko', 'Fujimoto', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Ehime')),
    ('Souta', 'Ishikawa', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Ehime')),
    ('Hina', 'Kobayashi', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Ehime')),
    ('Daichi', 'Tanaka', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Ehime')),
    ('Mei', 'Arai', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Ehime')),
    ('Takumi', 'Inoue', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Ehime')),

-- Shikoku - Kochi
    ('Yuto', 'Matsuda', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kochi')),
    ('Sakura', 'Hoshino', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kochi')),
    ('Kenta', 'Murakami', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kochi')),
    ('Riko', 'Shimizu', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kochi')),
    ('Tsubasa', 'Ueda', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kochi')),
    ('Miyu', 'Sakamoto', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kochi')),
    ('Hinata', 'Endo', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kochi')),
    ('Riku', 'Fukuda', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kochi')),
    ('Yuna', 'Hirano', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kochi')),
    ('Sho', 'Nakagawa', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kochi')),

-- Shikoku - Tokushima
    ('Ayaka', 'Terasaki', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Tokushima')),
    ('Tomo', 'Sugimoto', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Tokushima')),
    ('Rio', 'Kaneko', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Tokushima')),
    ('Shun', 'Okada', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Tokushima')),
    ('Airi', 'Noguchi', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Tokushima')),
    ('Mao', 'Hirano', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Tokushima')),
    ('Sena', 'Kawaguchi', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Tokushima')),
    ('Yuma', 'Tachibana', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Tokushima')),

-- Shikoku - Kagawa
    ('Rina', 'Yoshikawa', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kagawa')),
    ('Takashi', 'Kuroda', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kagawa')),
    ('Saki', 'Amano', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kagawa')),
    ('Itsuki', 'Hayashi', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kagawa')),
    ('Mai', 'Onishi', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kagawa')),
    ('Ryota', 'Mizuno', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kagawa')),
    ('Shiori', 'Ichikawa', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kagawa')),
    ('Hinako', 'Sakai', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kagawa')),
    ('Yuto', 'Kiriyama', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Kagawa')),

-- Shikoku - Awaji
    ('Keita', 'Nagano', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Emi', 'Morita', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Kazuki', 'Yamaguchi', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Sayaka', 'Hosokawa', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Miki', 'Kubota', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Taiga', 'Koga', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Haruna', 'Tani', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Koji', 'Arakawa', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Nanami', 'Iguchi', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Awaji'))
    
    
    , ('Nao', 'Miyamoto', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Koki', 'Maruyama', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Ren', 'Takada', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Naoki', 'Sano', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Riko', 'Himura', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Kanon', 'Matsumoto', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Rina', 'Nobunaga', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Kaori', 'Uchiha', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Natsuki', 'Nanami', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Soutaro', 'Kawaï', (SELECT ID FROM worker_origins WHERE name = 'Shikoku - Shōdoshima'))
;

-- Honshu - Kyoto
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Masaru', 'Yoshida', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Aya', 'Fukuda', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Shiro', 'Morimoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Jiro', 'Tominaga', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Hiroki', 'Noma', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Misaki', 'Kamiyama', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Kenji', 'Narita', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Ayumi', 'Tateishi', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto'));

-- Honshu - Osaka
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Shinji', 'Tsukamoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Mariko', 'Ogawa', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Yusuke', 'Okamoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Nozomi', 'Ichinose', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Rei', 'Furukawa', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Tomoya', 'Suda', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Takuto', 'Saeki', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Minami', 'Kurata', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Sosuke', 'Muraoka', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka'));

-- Honshu - Okayama
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Atsushi', 'Higashiyama', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Mami', 'Seto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Yuto', 'Ono', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Kaho', 'Mochizuki', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Naoya', 'Kurihara', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Arisa', 'Komatsu', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Soma', 'Morikawa', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Yuri', 'Inaba', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Ryunosuke', 'Takemoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama'));

-- Honshu - Hiroshima
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Shunpei', 'Hamamoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Sayuri', 'Kawano', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Takao', 'Oshiro', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Keisuke', 'Asano', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Chihiro', 'Nomura', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Haruto', 'Iwamoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Mizuki', 'Kudo', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Tetsuya', 'Ogino', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Hikari', 'Yokoyama', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima'));

-- Kyushu - Ōita
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Masaki', 'Tajima', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Naomi', 'Ebina', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Rena', 'Furuya', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Takahiro', 'Nishimoto', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Kana', 'Tachikawa', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Yuto', 'Baba', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Misao', 'Tokuda', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Hikaru', 'Shimoda', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Ami', 'Naruse', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita'));


INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Amerigo', 'Attilio', (SELECT ID FROM worker_origins WHERE name = 'Portugal')),
    ('Marco', 'Martino', (SELECT ID FROM worker_origins WHERE name = 'Portugal')),
    ('Luciana', 'Marsala', (SELECT ID FROM worker_origins WHERE name = 'Portugal')),
    ('Michelangelo', 'Belluchi', (SELECT ID FROM worker_origins WHERE name = 'Portugal')),
    ('Umberto', 'Venezio', (SELECT ID FROM worker_origins WHERE name = 'Portugal')),
    ('Venturo', 'Vesuvio', (SELECT ID FROM worker_origins WHERE name = 'Portugal')),
    ('Gino', 'Giancarlo', (SELECT ID FROM worker_origins WHERE name = 'Portugal')),
    ('Hortensio', 'Honorius', (SELECT ID FROM worker_origins WHERE name = 'Portugal')),
    ('Bianca', 'Abriana', (SELECT ID FROM worker_origins WHERE name = 'Portugal')),
    ('Paolo', 'Pisano', (SELECT ID FROM worker_origins WHERE name = 'Portugal'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Jean', 'Martin', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Marie', 'Bernard', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Pierre', 'Dubois', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Jacques', 'Thomas', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Michel', 'Robert', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Claude', 'Richard', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Nicolas', 'Petit', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Thomas', 'Durand', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Sophie', 'Leroy', (SELECT ID FROM worker_origins WHERE name = 'France')),
    ('Claire', 'Moreau', (SELECT ID FROM worker_origins WHERE name = 'France'));


-- Honshu - Kyoto
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Masaru', 'Yoshida', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Aya', 'Fukuda', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Shiro', 'Morimoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Kanon', 'Matsumoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Jiro', 'Tominaga', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Riko', 'Shimura', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Hiroki', 'Noma', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Misaki', 'Kamiyama', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Kenji', 'Narita', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Ayumi', 'Tateishi', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Kyoto'));

-- Honshu - Osaka
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Shinji', 'Tsukamoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Mariko', 'Ogawa', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Yusuke', 'Okamoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Nozomi', 'Ichinose', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Rei', 'Furukawa', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Tomoya', 'Suda', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Rina', 'Tokunaga', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Takuto', 'Saeki', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Minami', 'Kurata', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka')),
    ('Sosuke', 'Muraoka', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Osaka'));

-- Honshu - Okayama
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Atsushi', 'Higashiyama', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Mami', 'Seto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Yuto', 'Ono', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Kaho', 'Mochizuki', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Naoya', 'Kurihara', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Arisa', 'Komatsu', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Soma', 'Morikawa', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Yuri', 'Inaba', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Ryunosuke', 'Takemoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama')),
    ('Kaori', 'Uchida', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Okayama'));

-- Honshu - Hiroshima
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Shunpei', 'Hamamoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Sayuri', 'Kawano', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Takao', 'Oshiro', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Natsuki', 'Minami', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Keisuke', 'Asano', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Chihiro', 'Nomura', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Haruto', 'Iwamoto', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Mizuki', 'Kudo', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Tetsuya', 'Ogino', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Hikari', 'Yokoyama', (SELECT ID FROM worker_origins WHERE name = 'Honshu - Hiroshima'));

-- Kyushu - Ōita
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Masaki', 'Tajima', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Naomi', 'Ebina', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Soutaro', 'Kawai', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Rena', 'Furuya', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Takahiro', 'Nishimoto', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Kana', 'Tachikawa', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Yuto', 'Baba', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Misao', 'Tokuda', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Hikaru', 'Shimoda', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita')),
    ('Ami', 'Naruse', (SELECT ID FROM worker_origins WHERE name = 'Kyushu - Öita'));
