-- Insert names into worker_origins
INSERT INTO worker_origins (name) VALUES
    ('Firenze'),
    ('Roma'),
    ('Venezia'),
    ('Napoli'),
    ('Milano'),
    ('Suede'),
    ('France'),
    ('Allemagne'),
    ('Angleterre'),
    ('Espagne'),
    ('Autriche'),
    ('Roumanie');

-- Insert names into worker_names
INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Amerigo', 'Attilio', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Marco', 'Martino', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Benvenuto', 'Braulio', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Cirrillo', 'Cajetan', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Donato', 'Demarco', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Eriberto', 'Ettore', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Flavio', 'Fortino', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Indro', 'Lombardi', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Massimo', 'Maury', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Carlotta', 'Cara', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Donatella', 'Domani', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Fabiana', 'Fiorella', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Graziella', 'Giordana', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Ilaria', 'Itala', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Justina', 'Lanza', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Liona', 'Lave', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Marietta', 'Mila', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Natalia', 'Neroli', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Ornella', 'Prima', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Quorra', 'Ricarda', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Rocio', 'Sidonia', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Teressa', 'Trilby', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Mercury', 'Messala', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Nino', 'Nek', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Othello', 'Pancrazio', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Primo', 'Proculeius', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Romeo', 'Rocco', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Saverio', 'Santo', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Silvano', 'Solanio', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Taddeo', 'Ugo', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Vitalian', 'Vittorio', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Zanebono', 'Zanipolo', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Uberta', 'Vedette', (SELECT ID FROM worker_origins WHERE name = 'Firenze')),
    ('Venecia', 'Zola', (SELECT ID FROM worker_origins WHERE name = 'Firenze'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Luciana', 'Marsala', (SELECT ID FROM worker_origins WHERE name = 'Roma')),
    ('Michelangelo', 'Belluchi', (SELECT ID FROM worker_origins WHERE name = 'Roma')),
    ('Umberto', 'Venezio', (SELECT ID FROM worker_origins WHERE name = 'Roma')),
    ('Venturo', 'Vesuvio', (SELECT ID FROM worker_origins WHERE name = 'Roma')),
    ('Gino', 'Giancarlo', (SELECT ID FROM worker_origins WHERE name = 'Venezia')),
    ('Hortensio', 'Honorius', (SELECT ID FROM worker_origins WHERE name = 'Venezia')),
    ('Bianca', 'Abriana', (SELECT ID FROM worker_origins WHERE name = 'Venezia')),
    ('Paolo', 'Pisano', (SELECT ID FROM worker_origins WHERE name = 'Venezia'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Antonio', 'Esposito', (SELECT ID FROM worker_origins WHERE name = 'Napoli')),
    ('Giuseppe', 'Russo', (SELECT ID FROM worker_origins WHERE name = 'Napoli')),
    ('Maria', 'Marotta', (SELECT ID FROM worker_origins WHERE name = 'Napoli')),
    ('Vincenzo', 'Romano', (SELECT ID FROM worker_origins WHERE name = 'Napoli')),
    ('Luigi', 'Coppola', (SELECT ID FROM worker_origins WHERE name = 'Napoli'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Marco', 'Rossi',  (SELECT ID FROM worker_origins WHERE name = 'Milano')),
    ('Matteo', 'Brambilla',  (SELECT ID FROM worker_origins WHERE name = 'Milano')),
    ('Alessandro', 'Ferrari',  (SELECT ID FROM worker_origins WHERE name = 'Milano')),
    ('Francesca', 'Colombo',  (SELECT ID FROM worker_origins WHERE name = 'Milano')),
    ('Luca', 'Bianchi',  (SELECT ID FROM worker_origins WHERE name = 'Milano'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Lars', 'Johansson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Anna', 'Andersson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Johan', 'Karlsson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Erik', 'Nilsson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Anders', 'Eriksson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Maria', 'Larsson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Karin', 'Olsson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Per', 'Persson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Fredrik', 'Svensson', (SELECT ID FROM worker_origins WHERE name = 'Suede')),
    ('Emma', 'Gustafsson', (SELECT ID FROM worker_origins WHERE name = 'Suede'));

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

    INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Hans', 'Müller', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Anna', 'Schmidt', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Klaus', 'Schneider', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Peter', 'Fischer', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Karl', 'Weber', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Maria', 'Meyer', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Heinrich', 'Wagner', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Helga', 'Becker', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Wolfgang', 'Schulz', (SELECT ID FROM worker_origins WHERE name = 'Allemagne')),
    ('Erika', 'Hoffmann', (SELECT ID FROM worker_origins WHERE name = 'Allemagne'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('James', 'Smith', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Mary', 'Johnson', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('John', 'Williams', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Elizabeth', 'Brown', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('William', 'Jones', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Sarah', 'Miller', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('George', 'Davis', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Emma', 'Wilson', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Thomas', 'Moore', (SELECT ID FROM worker_origins WHERE name = 'Angleterre')),
    ('Charlotte', 'Taylor', (SELECT ID FROM worker_origins WHERE name = 'Angleterre'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Antonio', 'García', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('María', 'Fernández', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Manuel', 'González', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Carmen', 'Rodríguez', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('José', 'López', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Ana', 'Martínez', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Francisco', 'Sánchez', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Laura', 'Pérez', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Juan', 'Gómez', (SELECT ID FROM worker_origins WHERE name = 'Espagne')),
    ('Isabel', 'Martín', (SELECT ID FROM worker_origins WHERE name = 'Espagne'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Maximilian', 'Gruber', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Anna', 'Huber', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Lukas', 'Bauer', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Sophia', 'Wagner', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Elias', 'Müller', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Emma', 'Steiner', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Jakob', 'Mayer', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Lena', 'Schmidt', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Tobias', 'Hofer', (SELECT ID FROM worker_origins WHERE name = 'Autriche')),
    ('Laura', 'Lehner', (SELECT ID FROM worker_origins WHERE name = 'Autriche'));

INSERT INTO worker_names (firstname, lastname, origin_id) VALUES
    ('Maria', 'Ionescu', (SELECT ID FROM worker_origins WHERE name = 'Roumanie')),
    ('Ioan', 'Stan', (SELECT ID FROM worker_origins WHERE name = 'Roumanie')),
    ('Elena', 'Dumitru', (SELECT ID FROM worker_origins WHERE name = 'Roumanie')),
    ('Alexandru', 'Gheorghe', (SELECT ID FROM worker_origins WHERE name = 'Roumanie'));
