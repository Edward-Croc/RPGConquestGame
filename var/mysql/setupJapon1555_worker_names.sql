
-- Insert names into worker_origins
INSERT INTO {prefix}worker_origins (name) VALUES
      ('Shikoku - Iyo')
    , ('Shikoku - Tosa')
    , ('Shikoku - Awa')
    , ('Shikoku - Sanuki')
    , ('Shikoku - Awaji')
    , ('Shikoku - Shōdoshima')
    , ('Honshu - Kansai')
    , ('Honshu - Kyoto')
    , ('Honshu - Okayama')
    , ('Honshu - Hiroshima')
    , ('Kyushu - Öita')
    , ('Portugal')
    , ('France')
;

-- Insert names into worker_names
INSERT INTO {prefix}worker_names (firstname, lastname, origin_id) VALUES
-- Shikoku - Iyo
    ('Haruki', 'Takahashi', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Iyo')),
    ('Yui', 'Nakamura', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Iyo')),
    ('Ren', 'Sato', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Iyo')),
    ('Kaito', 'Yamamoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Iyo')),
    ('Aiko', 'Fujimoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Iyo')),
    ('Souta', 'Ishikawa', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Iyo')),
    ('Hina', 'Kobayashi', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Iyo')),
    ('Daichi', 'Tanaka', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Iyo')),
    ('Mei', 'Arai', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Iyo')),
    ('Takumi', 'Inoue', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Iyo')),

-- Shikoku - Tosa
    ('Yuto', 'Matsuda', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Tosa')),
    ('Sakura', 'Hoshino', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Tosa')),
    ('Kenta', 'Murakami', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Tosa')),
    ('Riko', 'Shimizu', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Tosa')),
    ('Tsubasa', 'Ueda', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Tosa')),
    ('Miyu', 'Sakamoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Tosa')),
    ('Hinata', 'Endo', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Tosa')),
    ('Riku', 'Fukuda', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Tosa')),
    ('Yuna', 'Hirano', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Tosa')),
    ('Sho', 'Sanuki', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Tosa')),

-- Shikoku - Awa
    ('Ayaka', 'Terasaki', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awa')),
    ('Tomo', 'Sugimoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awa')),
    ('Rio', 'Kaneko', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awa')),
    ('Shun', 'Okada', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awa')),
    ('Airi', 'Noguchi', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awa')),
    ('Mao', 'Hirano', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awa')),
    ('Sena', 'Kawaguchi', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awa')),
    ('Yuma', 'Tachibana', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awa')),
    ('Kawashima', 'Noriyasu', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awa')),
    ('Shigekatsu', 'Fujii', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awa')),

-- Shikoku - Sanuki
    ('Rina', 'Yoshikawa', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Sanuki')),
    ('Takashi', 'Kuroda', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Sanuki')),
    ('Saki', 'Amano', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Sanuki')),
    ('Itsuki', 'Hayashi', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Sanuki')),
    ('Mai', 'Onishi', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Sanuki')),
    ('Ryota', 'Mizuno', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Sanuki')),
    ('Shiori', 'Ichikawa', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Sanuki')),
    ('Hinako', 'Sakai', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Sanuki')),
    ('Yuto', 'Kiriyama', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Sanuki')),
    ('Kazuya', 'Fujikawa', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Sanuki')),

-- Shikoku - Awaji
    ('Keita', 'Nagano', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Emi', 'Morita', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Kazuki', 'Yamaguchi', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Sayaka', 'Gōno', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Miki', 'Kubota', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Taiga', 'Koga', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Haruna', 'Tani', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Koji', 'Arakawa', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Nanami', 'Iguchi', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awaji')),
    ('Masatsune', 'Miyaji', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Awaji'))

-- Shikoku - Shōdoshima
    , ('Nao', 'Miyamoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Koki', 'Maruyama', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Ren', 'Takada', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Naoki', 'Sano', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Riko', 'Himura', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Kanon', 'Matsumoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Rina', 'Nobunaga', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Kaori', 'Uchiha', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Natsuki', 'Nanami', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Shōdoshima'))
    , ('Soutaro', 'Kawaï', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Shikoku - Shōdoshima'))
;

-- Honshu - Kyoto
INSERT INTO {prefix}worker_names (firstname, lastname, origin_id) VALUES
    ('Masaru', 'Yoshida', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Aya', 'Fukuda', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Shiro', 'Morimoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Jiro', 'Tominaga', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Hiroki', 'Noma', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Misaki', 'Kamiyama', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Kenji', 'Narita', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kyoto')),
    ('Ayumi', 'Tateishi', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kyoto'));

-- Honshu - Kansai
INSERT INTO {prefix}worker_names (firstname, lastname, origin_id) VALUES
    ('Shinji', 'Tsukamoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kansai')),
    ('Mariko', 'Ogawa', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kansai')),
    ('Yusuke', 'Okamoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kansai')),
    ('Nozomi', 'Ichinose', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kansai')),
    ('Rei', 'Furukawa', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kansai')),
    ('Tomoya', 'Suda', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kansai')),
    ('Takuto', 'Saeki', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kansai')),
    ('Minami', 'Kurata', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kansai')),
    ('Sosuke', 'Muraoka', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Kansai'));

-- Honshu - Okayama
INSERT INTO {prefix}worker_names (firstname, lastname, origin_id) VALUES
    ('Atsushi', 'Higashiyama', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Okayama')),
    ('Mami', 'Seto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Okayama')),
    ('Yuto', 'Ono', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Okayama')),
    ('Kaho', 'Mochizuki', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Okayama')),
    ('Naoya', 'Kurihara', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Okayama')),
    ('Arisa', 'Komatsu', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Okayama')),
    ('Soma', 'Morikawa', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Okayama')),
    ('Yuri', 'Inaba', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Okayama')),
    ('Ryunosuke', 'Takemoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Okayama'));

-- Honshu - Hiroshima
INSERT INTO {prefix}worker_names (firstname, lastname, origin_id) VALUES
    ('Shunpei', 'Hamamoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Sayuri', 'Kawano', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Takao', 'Oshiro', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Keisuke', 'Asano', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Chihiro', 'Nomura', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Haruto', 'Iwamoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Mizuki', 'Kudo', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Tetsuya', 'Ogino', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Hiroshima')),
    ('Hikari', 'Yokoyama', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Honshu - Hiroshima'));

-- Kyushu - Ōita
INSERT INTO {prefix}worker_names (firstname, lastname, origin_id) VALUES
    ('Masaki', 'Tajima', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Kyushu - Öita')),
    ('Naomi', 'Ebina', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Kyushu - Öita')),
    ('Rena', 'Furuya', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Kyushu - Öita')),
    ('Takahiro', 'Nishimoto', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Kyushu - Öita')),
    ('Kana', 'Tachikawa', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Kyushu - Öita')),
    ('Yuto', 'Baba', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Kyushu - Öita')),
    ('Misao', 'Tokuda', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Kyushu - Öita')),
    ('Hikaru', 'Shimoda', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Kyushu - Öita')),
    ('Ami', 'Naruse', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Kyushu - Öita'));

-- Portugal
INSERT INTO {prefix}worker_names (firstname, lastname, origin_id) VALUES
    ('Amerigo', 'Attilio', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Portugal')),
    ('Marco', 'Martino', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Portugal')),
    ('Luciana', 'Marsala', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Portugal')),
    ('Michelangelo', 'Belluchi', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Portugal')),
    ('Umberto', 'Venezio', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Portugal')),
    ('Venturo', 'Dio', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Portugal')),
    ('Gino', 'Giancarlo', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Portugal')),
    ('Hortensio', 'Honorius', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Portugal')),
    ('Bianca', 'Abriana', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Portugal')),
    ('Paolo', 'Pisano', (SELECT ID FROM {prefix}worker_origins WHERE name = 'Portugal'));

-- France
INSERT INTO {prefix}worker_names (firstname, lastname, origin_id) VALUES
    ('Jean', 'Martin', (SELECT ID FROM {prefix}worker_origins WHERE name = 'France')),
    ('Pierre', 'Dubois', (SELECT ID FROM {prefix}worker_origins WHERE name = 'France')),
    ('Jacques', 'Thomas', (SELECT ID FROM {prefix}worker_origins WHERE name = 'France')),
    ('Michel', 'Robert', (SELECT ID FROM {prefix}worker_origins WHERE name = 'France')),
    ('Claude', 'Richard', (SELECT ID FROM {prefix}worker_origins WHERE name = 'France')),
    ('Nicolas', 'Petit', (SELECT ID FROM {prefix}worker_origins WHERE name = 'France')),
    ('Thomas', 'Durand', (SELECT ID FROM {prefix}worker_origins WHERE name = 'France')),
    ('Bernard', 'Leroy', (SELECT ID FROM {prefix}worker_origins WHERE name = 'France'));
