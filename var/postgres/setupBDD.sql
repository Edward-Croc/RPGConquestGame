-- Necessary base SQL setup
-- DROP DATABASE IF EXISTS RPGConquestGame;
-- CREATE DATABASE RPGConquestGame OWNER php_gamedev;
-- CREATE EXTENSION IF NOT EXISTS pgcrypto;
-- Minimal data seed rows (gm user, default config keys, starting mechanics
-- row, fixed power type ids) live in minimalData.sql and are loaded by the
-- same code path immediately after this file.

CREATE TABLE {prefix}mechanics (
    id SERIAL PRIMARY KEY,
    turncounter INTEGER DEFAULT 0,
    gamestate INTEGER DEFAULT 0,
    end_step TEXT DEFAULT ''
);


-- create configuration table
CREATE TABLE {prefix}config (
    id SERIAL PRIMARY KEY,
    name text UNIQUE NOT NULL, --name used key
    value text DEFAULT '', --value to be read
    description text -- explain configuration usage
);



-- player tables
CREATE TABLE {prefix}players (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    passwd VARCHAR(64) NOT NULL,
    url text,
    is_privileged BOOLEAN DEFAULT FALSE -- does player have god mode
);


-- faction tables
CREATE TABLE {prefix}factions (
    id SERIAL PRIMARY KEY,
    name text NOT NULL
);

-- controller / character tables
CREATE TABLE {prefix}controllers (
    id SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    url text,
    story text,
    can_build_base BOOLEAN DEFAULT TRUE, -- can build a base
    start_workers INT DEFAULT 1,
    recruited_workers INT DEFAULT 0,
    turn_recruited_workers INT DEFAULT 0,
    turn_firstcome_workers INT DEFAULT 0,
    ia_type text DEFAULT '',
    faction_id INT NOT NULL,
    fake_faction_id INT NOT NULL,
    secret_controller BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (faction_id) REFERENCES {prefix}factions (id),
    FOREIGN KEY (fake_faction_id) REFERENCES {prefix}factions (id)
);

-- player to controller link
CREATE TABLE {prefix}player_controller (
    controller_id INT NOT NULL,
    player_id INT NOT NULL,
    PRIMARY KEY (controller_id, player_id),
    FOREIGN KEY (controller_id) REFERENCES {prefix}controllers (id),
    FOREIGN KEY (player_id) REFERENCES {prefix}players (id)
);
-- Create indexes on the player_controller table
CREATE INDEX idx_player_controller_controller_id ON {prefix}player_controller (controller_id);
CREATE INDEX idx_player_controller_player_id ON {prefix}player_controller (player_id);

-- Create the zones and locations
CREATE TABLE {prefix}zones (
    id SERIAL PRIMARY KEY,
    name text NOT NULL,
    description text NOT NULL,
    defence_val INT DEFAULT 6, -- Base defence to claim the zone
    calculated_defence_val INT DEFAULT 6, -- Updated defence value when actively protected
    claimer_controller_id INT, -- id of controller officialy claiming the zone
    holder_controller_id INT,   -- id of controller defending the zone
    hide_turn_zero BOOLEAN DEFAULT FALSE, -- JSON storing the hide turns checks
    FOREIGN KEY (claimer_controller_id) REFERENCES {prefix}controllers (id),
    FOREIGN KEY (holder_controller_id) REFERENCES {prefix}controllers (id)
);
-- Create indexes on the zones table
CREATE INDEX idx_zones_claimer_controller_id ON {prefix}zones (claimer_controller_id);
CREATE INDEX idx_zones_holder_controller_id ON {prefix}zones (holder_controller_id);

CREATE TABLE {prefix}locations (
    id SERIAL PRIMARY KEY,
    name text NOT NULL,
    description text NOT NULL,
    hidden_description text DEFAULT NULL,
    zone_id INT,
    setup_turn INT DEFAULT 0, -- Turn in which the location was created
    discovery_diff INT DEFAULT 0,
    controller_id INT DEFAULT NULL, -- Owner of secret location
    can_be_destroyed BOOLEAN DEFAULT FALSE,
    can_be_repaired BOOLEAN DEFAULT FALSE,
    is_base BOOLEAN DEFAULT FALSE, -- Is a controllers Base
    activate_json JSON DEFAULT '{}'::json,
    FOREIGN KEY (zone_id) REFERENCES {prefix}zones (id),
    FOREIGN KEY (controller_id) REFERENCES {prefix}controllers (id)
);
-- Create indexes on the locations table
CREATE INDEX idx_locations_zone_id ON {prefix}locations (zone_id);
CREATE INDEX idx_locations_controller_id ON {prefix}locations (controller_id);

CREATE TABLE {prefix}artefacts (
    id SERIAL PRIMARY KEY,          -- Unique id of the artefact
    location_id INTEGER,            -- Foreign key referencing a location
    name TEXT NOT NULL,             -- Name of the artefact
    description TEXT NOT NULL,      -- Description of the artefact
    full_description TEXT NOT NULL,  -- Description of the artefact if the player controls the location
    FOREIGN KEY (location_id) REFERENCES {prefix}locations (id) -- Link to locations table
);
-- Create indexes on the artefacts table
CREATE INDEX idx_artefacts_location_id ON {prefix}artefacts (location_id);

CREATE TABLE {prefix}controller_known_locations (
    id SERIAL PRIMARY KEY,
    controller_id INT NOT NULL,
    location_id INT NOT NULL,
    found_secret BOOLEAN DEFAULT FALSE,
    first_discovery_turn INT NOT NULL, -- Turn number when discovery happened
    last_discovery_turn INT NOT NULL, -- Turn number when discovery happened
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (controller_id, location_id), -- Unicity constraint on controller/worker combo
    FOREIGN KEY (controller_id) REFERENCES {prefix}controllers (id), -- Link to controllers table
    FOREIGN KEY (location_id) REFERENCES {prefix}locations (id) -- Link to locations table
);
-- Create indexes on the controller_known_locations table
CREATE INDEX idx_controller_known_locations_controller_id ON {prefix}controller_known_locations (controller_id);
CREATE INDEX idx_controller_known_locations_location_id ON {prefix}controller_known_locations (location_id);

CREATE TABLE {prefix}location_attack_logs (
    id SERIAL PRIMARY KEY,
    location_name TEXT,
    target_controller_id INT REFERENCES {prefix}controllers(id), 
    attacker_id INT REFERENCES {prefix}controllers(id),
    attack_val INT DEFAULT 0,
    defence_val INT DEFAULT 0,
    turn INT NOT NULL,
    success BOOLEAN NOT NULL,
    target_result_text TEXT,
    attacker_result_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (target_controller_id) REFERENCES {prefix}controllers (id), -- Link to controllers table
    FOREIGN KEY (attacker_id) REFERENCES {prefix}controllers (id) -- Link to controllers table
);

-- Prepare the Worker Origins
CREATE TABLE {prefix}worker_origins (
    id SERIAL PRIMARY KEY,
    name text NOT NULL
);

-- Table storing the worker random names by origin
CREATE TABLE {prefix}worker_names (
    id SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    origin_id INT NOT NULL,
    FOREIGN KEY (origin_id) REFERENCES {prefix}worker_origins (id)
);

CREATE TABLE {prefix}workers (
    id SERIAL PRIMARY KEY,
    firstname text NOT NULL,
    lastname text NOT NULL,
    origin_id INT NOT NULL,
    zone_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (origin_id) REFERENCES {prefix}worker_origins (id),
    FOREIGN KEY (zone_id) REFERENCES {prefix}zones (id)
);
-- Create indexes on the workers table
CREATE INDEX idx_workers_origin_id ON {prefix}workers (origin_id);
CREATE INDEX idx_workers_zone_id ON {prefix}workers (zone_id);

CREATE TABLE {prefix}workers_trace_links (
    id SERIAL PRIMARY KEY,
    primary_worker_id INT NOT NULL,
    trace_worker_id INT NOT NULL,
    controller_id INT NOT NULL,
    FOREIGN KEY (primary_worker_id) REFERENCES {prefix}workers (id),
    FOREIGN KEY (trace_worker_id) REFERENCES {prefix}workers (id),
    FOREIGN KEY (controller_id) REFERENCES {prefix}controllers (id),
    UNIQUE (trace_worker_id)
);
-- Create indexes on the workers_trace_links table
CREATE INDEX idx_workers_trace_links_primary_worker_id ON {prefix}workers_trace_links (primary_worker_id);
CREATE INDEX idx_workers_trace_links_trace_worker_id ON {prefix}workers_trace_links (trace_worker_id);
CREATE INDEX idx_workers_trace_links_controller_id ON {prefix}workers_trace_links (controller_id);

CREATE TABLE {prefix}controller_worker (
    id SERIAL PRIMARY KEY,
    controller_id INT,
    worker_id INT,
    is_primary_controller BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Adding unique constraint
    UNIQUE (controller_id, worker_id),
    UNIQUE (worker_id, is_primary_controller),
    -- Adding FOREIGN KEY
    FOREIGN KEY (controller_id) REFERENCES {prefix}controllers (id),
    FOREIGN KEY (worker_id) REFERENCES {prefix}workers (id)
);
-- Create indexes on the controller_worker table
CREATE INDEX idx_controller_worker_controller_id ON {prefix}controller_worker (controller_id);
CREATE INDEX idx_controller_worker_worker_id ON {prefix}controller_worker (worker_id);

CREATE TABLE {prefix}power_types (
    id SERIAL PRIMARY KEY,
    name text NOT NULL,
    description text
);


CREATE TABLE {prefix}powers (
    id SERIAL PRIMARY KEY,
    name text NOT NULL,
    description text,
    enquete INT DEFAULT 0,
    attack INT DEFAULT 0,
    defence INT DEFAULT 0,
    other JSON DEFAULT '{}'::json
);

CREATE TABLE {prefix}link_power_type (
    id SERIAL PRIMARY KEY,
    power_type_id INT NOT NULL,
    power_id INT NOT NULL,
    UNIQUE (power_type_id, power_id),
    FOREIGN KEY (power_type_id) REFERENCES {prefix}power_types (id),
    FOREIGN KEY (power_id) REFERENCES {prefix}powers (id)
);
-- Create indexes on the link_power_type table
CREATE INDEX idx_link_power_type_power_type_id ON {prefix}link_power_type (power_type_id);
CREATE INDEX idx_link_power_type_power_id ON {prefix}link_power_type (power_id);

CREATE TABLE {prefix}worker_powers (
    id SERIAL PRIMARY KEY,
    worker_id INT NOT NULL,
    link_power_type_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (worker_id, link_power_type_id), -- Adding unique constraint
    FOREIGN KEY (worker_id) REFERENCES {prefix}workers (id),
    FOREIGN KEY (link_power_type_id) REFERENCES {prefix}link_power_type (id)
);
-- Create indexes on the worker_powers table
CREATE INDEX idx_worker_powers_worker_id ON {prefix}worker_powers (worker_id);
CREATE INDEX idx_worker_powers_link_power_type_id ON {prefix}worker_powers (link_power_type_id);

CREATE TABLE {prefix}faction_powers (
    id SERIAL PRIMARY KEY,
    faction_id INT NOT NULL,
    link_power_type_id INT NOT NULL,
    UNIQUE (faction_id, link_power_type_id), -- Adding unique constraint
    FOREIGN KEY (faction_id) REFERENCES {prefix}factions (id),
    FOREIGN KEY (link_power_type_id) REFERENCES {prefix}link_power_type (id)
);
-- Create indexes on the faction_powers table
CREATE INDEX idx_faction_powers_faction_id ON {prefix}faction_powers (faction_id);
CREATE INDEX idx_faction_powers_link_power_type_id ON {prefix}faction_powers (link_power_type_id);

CREATE TABLE {prefix}worker_actions (
    id SERIAL PRIMARY KEY,
    worker_id INT NOT NULL,
    turn_number INT NOT NULL DEFAULT 0,
    zone_id INT NOT NULL,
    controller_id INT NOT NULL,
    enquete_val INT DEFAULT 0,
    attack_val INT DEFAULT 0,
    defence_val INT DEFAULT 0,
    action_choice TEXT DEFAULT 'passive',
    action_params JSON DEFAULT '{}'::json,
    report JSON DEFAULT '{}'::json, -- Expected keys 'life_report', 'attack_report', 'investigate_report', 'claim_report', 'secrets_report'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (worker_id, turn_number), -- Adding unique constraint
    FOREIGN KEY (worker_id) REFERENCES {prefix}workers (id),
    FOREIGN KEY (zone_id) REFERENCES {prefix}zones (id),
    FOREIGN KEY (controller_id) REFERENCES {prefix}controllers (id)
);
-- Create indexes on the worker_actions table
CREATE INDEX idx_worker_actions_worker_id ON {prefix}worker_actions (worker_id);
CREATE INDEX idx_worker_actions_turn_number ON {prefix}worker_actions (turn_number);
CREATE INDEX idx_worker_actions_zone_id ON {prefix}worker_actions (zone_id);
CREATE INDEX idx_worker_actions_controller_id ON {prefix}worker_actions (controller_id);

CREATE TABLE {prefix}controllers_known_enemies (
    id SERIAL PRIMARY KEY,
    controller_id INT NOT NULL, -- controller A
    discovered_worker_id INT NOT NULL, -- id of the discovered worker
    discovered_controller_id INT, -- Optional id of their controller
    discovered_controller_name TEXT, -- Optional name of their controller
    zone_id INT NOT NULL, -- Zone of discovery
    first_discovery_turn INT NOT NULL, -- Turn number when discovery happened
    last_discovery_turn INT NOT NULL, -- Turn number when discovery happened
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (controller_id, discovered_worker_id), -- Unicity constraint on controller/worker combo
    FOREIGN KEY (controller_id) REFERENCES {prefix}controllers (id), -- Link to controllers table
    FOREIGN KEY (discovered_worker_id) REFERENCES {prefix}workers (id), -- Link to workers table
    FOREIGN KEY (discovered_controller_id) REFERENCES {prefix}controllers (id), -- Link to controllers table
    FOREIGN KEY (zone_id) REFERENCES {prefix}zones (id) -- Link to zones table
);
-- Create indexes on the controllers_known_enemies table
CREATE INDEX idx_controllers_known_enemies_controller_id ON {prefix}controllers_known_enemies (controller_id);
CREATE INDEX idx_controllers_known_enemies_discovered_worker_id ON {prefix}controllers_known_enemies (discovered_worker_id);
CREATE INDEX idx_controllers_known_enemies_discovered_controller_id ON {prefix}controllers_known_enemies (discovered_controller_id);
CREATE INDEX idx_controllers_known_enemies_zone_id ON {prefix}controllers_known_enemies (zone_id);

CREATE TABLE {prefix}ressources_config (
    id SERIAL PRIMARY KEY,
    ressource_name text NOT NULL,
    presentation text NOT NULL,
    stored_text text NOT NULL,
    is_rollable BOOLEAN DEFAULT FALSE,
    is_stored BOOLEAN DEFAULT FALSE,
    base_building_cost INT NOT NULL DEFAULT 0,
    base_moving_cost INT NOT NULL DEFAULT 0,
    location_repaire_cost INT NOT NULL DEFAULT 0,
    servant_first_come_cost INT NOT NULL DEFAULT 0,
    servant_recruitment_cost INT NOT NULL DEFAULT 0,
    extra_first_come_cost INT NOT NULL DEFAULT 0
);

CREATE TABLE {prefix}controller_ressources (
    id SERIAL PRIMARY KEY,
    controller_id INT NOT NULL,
    ressource_id INT NOT NULL,
    amount INT NOT NULL DEFAULT 0,
    amount_stored INT NOT NULL DEFAULT 0,
    end_turn_gain INT NOT NULL DEFAULT 0,
    FOREIGN KEY (controller_id) REFERENCES {prefix}controllers (id),
    FOREIGN KEY (ressource_id) REFERENCES {prefix}ressources_config (id)
);
-- Create indexes on the controller_ressources table
CREATE INDEX idx_controller_ressources_controller_id ON {prefix}controller_ressources (controller_id);
CREATE INDEX idx_controller_ressources_ressource_id ON {prefix}controller_ressources (ressource_id);