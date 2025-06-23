--
-- PostgreSQL database dump
--

-- Dumped from database version 15.12 (Ubuntu 15.12-1.pgdg22.04+1)
-- Dumped by pg_dump version 15.12 (Ubuntu 15.12-1.pgdg22.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: artefacts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.artefacts (
    id integer NOT NULL,
    location_id integer,
    name text NOT NULL,
    description text NOT NULL,
    full_description text NOT NULL
);


ALTER TABLE public.artefacts OWNER TO postgres;

--
-- Name: artefacts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.artefacts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.artefacts_id_seq OWNER TO postgres;

--
-- Name: artefacts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.artefacts_id_seq OWNED BY public.artefacts.id;


--
-- Name: config; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.config (
    id integer NOT NULL,
    name text NOT NULL,
    value text DEFAULT ''::text,
    description text
);


ALTER TABLE public.config OWNER TO postgres;

--
-- Name: config_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.config_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.config_id_seq OWNER TO postgres;

--
-- Name: config_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.config_id_seq OWNED BY public.config.id;


--
-- Name: controller_known_locations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.controller_known_locations (
    id integer NOT NULL,
    controller_id integer NOT NULL,
    location_id integer NOT NULL,
    first_discovery_turn integer NOT NULL,
    last_discovery_turn integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.controller_known_locations OWNER TO postgres;

--
-- Name: controller_known_locations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.controller_known_locations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.controller_known_locations_id_seq OWNER TO postgres;

--
-- Name: controller_known_locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.controller_known_locations_id_seq OWNED BY public.controller_known_locations.id;


--
-- Name: controller_worker; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.controller_worker (
    id integer NOT NULL,
    controller_id integer,
    worker_id integer,
    is_primary_controller boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.controller_worker OWNER TO postgres;

--
-- Name: controller_worker_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.controller_worker_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.controller_worker_id_seq OWNER TO postgres;

--
-- Name: controller_worker_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.controller_worker_id_seq OWNED BY public.controller_worker.id;


--
-- Name: controllers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.controllers (
    id integer NOT NULL,
    firstname text NOT NULL,
    lastname text NOT NULL,
    url text,
    story text,
    start_workers integer DEFAULT 1,
    recruited_workers integer DEFAULT 0,
    turn_recruited_workers integer DEFAULT 0,
    turn_firstcome_workers integer DEFAULT 0,
    ia_type text DEFAULT ''::text,
    faction_id integer NOT NULL,
    fake_faction_id integer NOT NULL,
    secret_controller boolean DEFAULT false
);


ALTER TABLE public.controllers OWNER TO postgres;

--
-- Name: controllers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.controllers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.controllers_id_seq OWNER TO postgres;

--
-- Name: controllers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.controllers_id_seq OWNED BY public.controllers.id;


--
-- Name: controllers_known_enemies; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.controllers_known_enemies (
    id integer NOT NULL,
    controller_id integer NOT NULL,
    discovered_worker_id integer NOT NULL,
    discovered_controller_id integer,
    discovered_controller_name text,
    zone_id integer NOT NULL,
    first_discovery_turn integer NOT NULL,
    last_discovery_turn integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.controllers_known_enemies OWNER TO postgres;

--
-- Name: controllers_known_enemies_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.controllers_known_enemies_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.controllers_known_enemies_id_seq OWNER TO postgres;

--
-- Name: controllers_known_enemies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.controllers_known_enemies_id_seq OWNED BY public.controllers_known_enemies.id;


--
-- Name: faction_powers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.faction_powers (
    id integer NOT NULL,
    faction_id integer NOT NULL,
    link_power_type_id integer NOT NULL
);


ALTER TABLE public.faction_powers OWNER TO postgres;

--
-- Name: faction_powers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.faction_powers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.faction_powers_id_seq OWNER TO postgres;

--
-- Name: faction_powers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.faction_powers_id_seq OWNED BY public.faction_powers.id;


--
-- Name: factions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.factions (
    id integer NOT NULL,
    name text NOT NULL
);


ALTER TABLE public.factions OWNER TO postgres;

--
-- Name: factions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.factions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.factions_id_seq OWNER TO postgres;

--
-- Name: factions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.factions_id_seq OWNED BY public.factions.id;


--
-- Name: link_power_type; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.link_power_type (
    id integer NOT NULL,
    power_type_id integer NOT NULL,
    power_id integer NOT NULL
);


ALTER TABLE public.link_power_type OWNER TO postgres;

--
-- Name: link_power_type_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.link_power_type_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.link_power_type_id_seq OWNER TO postgres;

--
-- Name: link_power_type_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.link_power_type_id_seq OWNED BY public.link_power_type.id;


--
-- Name: location_attack_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.location_attack_logs (
    id integer NOT NULL,
    target_controller_id integer,
    attacker_id integer,
    attack_val integer DEFAULT 0,
    defence_val integer DEFAULT 0,
    turn integer NOT NULL,
    success boolean NOT NULL,
    target_result_text text,
    attacker_result_text text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.location_attack_logs OWNER TO postgres;

--
-- Name: location_attack_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.location_attack_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.location_attack_logs_id_seq OWNER TO postgres;

--
-- Name: location_attack_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.location_attack_logs_id_seq OWNED BY public.location_attack_logs.id;


--
-- Name: locations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.locations (
    id integer NOT NULL,
    name text NOT NULL,
    description text NOT NULL,
    zone_id integer,
    setup_turn integer DEFAULT 0,
    discovery_diff integer DEFAULT 0,
    controller_id integer,
    can_be_destroyed boolean DEFAULT false,
    is_base boolean DEFAULT false,
    activate_json json DEFAULT '{}'::json
);


ALTER TABLE public.locations OWNER TO postgres;

--
-- Name: locations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.locations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.locations_id_seq OWNER TO postgres;

--
-- Name: locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.locations_id_seq OWNED BY public.locations.id;


--
-- Name: mechanics; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.mechanics (
    id integer NOT NULL,
    turncounter integer DEFAULT 0,
    gamestate integer DEFAULT 0
);


ALTER TABLE public.mechanics OWNER TO postgres;

--
-- Name: mechanics_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mechanics_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.mechanics_id_seq OWNER TO postgres;

--
-- Name: mechanics_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mechanics_id_seq OWNED BY public.mechanics.id;


--
-- Name: player_controller; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.player_controller (
    controller_id integer NOT NULL,
    player_id integer NOT NULL
);


ALTER TABLE public.player_controller OWNER TO postgres;

--
-- Name: players; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.players (
    id integer NOT NULL,
    username character varying(50) NOT NULL,
    passwd character varying(64) NOT NULL,
    is_privileged boolean DEFAULT false
);


ALTER TABLE public.players OWNER TO postgres;

--
-- Name: players_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.players_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.players_id_seq OWNER TO postgres;

--
-- Name: players_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.players_id_seq OWNED BY public.players.id;


--
-- Name: power_types; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.power_types (
    id integer NOT NULL,
    name text NOT NULL,
    description text
);


ALTER TABLE public.power_types OWNER TO postgres;

--
-- Name: power_types_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.power_types_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.power_types_id_seq OWNER TO postgres;

--
-- Name: power_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.power_types_id_seq OWNED BY public.power_types.id;


--
-- Name: powers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.powers (
    id integer NOT NULL,
    name text NOT NULL,
    description text,
    enquete integer DEFAULT 0,
    attack integer DEFAULT 0,
    defence integer DEFAULT 0,
    other json DEFAULT '{}'::json
);


ALTER TABLE public.powers OWNER TO postgres;

--
-- Name: powers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.powers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.powers_id_seq OWNER TO postgres;

--
-- Name: powers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.powers_id_seq OWNED BY public.powers.id;


--
-- Name: worker_actions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.worker_actions (
    id integer NOT NULL,
    worker_id integer NOT NULL,
    turn_number integer DEFAULT 0 NOT NULL,
    zone_id integer NOT NULL,
    controller_id integer NOT NULL,
    enquete_val integer DEFAULT 0,
    attack_val integer DEFAULT 0,
    defence_val integer DEFAULT 0,
    action_choice text DEFAULT 'passive'::text,
    action_params json DEFAULT '{}'::json,
    report json DEFAULT '{}'::json,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.worker_actions OWNER TO postgres;

--
-- Name: worker_actions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.worker_actions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.worker_actions_id_seq OWNER TO postgres;

--
-- Name: worker_actions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.worker_actions_id_seq OWNED BY public.worker_actions.id;


--
-- Name: worker_names; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.worker_names (
    id integer NOT NULL,
    firstname text NOT NULL,
    lastname text NOT NULL,
    origin_id integer NOT NULL
);


ALTER TABLE public.worker_names OWNER TO postgres;

--
-- Name: worker_names_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.worker_names_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.worker_names_id_seq OWNER TO postgres;

--
-- Name: worker_names_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.worker_names_id_seq OWNED BY public.worker_names.id;


--
-- Name: worker_origins; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.worker_origins (
    id integer NOT NULL,
    name text NOT NULL
);


ALTER TABLE public.worker_origins OWNER TO postgres;

--
-- Name: worker_origins_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.worker_origins_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.worker_origins_id_seq OWNER TO postgres;

--
-- Name: worker_origins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.worker_origins_id_seq OWNED BY public.worker_origins.id;


--
-- Name: worker_powers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.worker_powers (
    id integer NOT NULL,
    worker_id integer NOT NULL,
    link_power_type_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.worker_powers OWNER TO postgres;

--
-- Name: worker_powers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.worker_powers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.worker_powers_id_seq OWNER TO postgres;

--
-- Name: worker_powers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.worker_powers_id_seq OWNED BY public.worker_powers.id;


--
-- Name: workers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.workers (
    id integer NOT NULL,
    firstname text NOT NULL,
    lastname text NOT NULL,
    origin_id integer NOT NULL,
    zone_id integer NOT NULL,
    is_alive boolean DEFAULT true,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.workers OWNER TO postgres;

--
-- Name: workers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.workers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.workers_id_seq OWNER TO postgres;

--
-- Name: workers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.workers_id_seq OWNED BY public.workers.id;


--
-- Name: zones; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.zones (
    id integer NOT NULL,
    name text NOT NULL,
    description text NOT NULL,
    defence_val integer DEFAULT 6,
    calculated_defence_val integer DEFAULT 6,
    claimer_controller_id integer,
    holder_controller_id integer
);


ALTER TABLE public.zones OWNER TO postgres;

--
-- Name: zones_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.zones_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.zones_id_seq OWNER TO postgres;

--
-- Name: zones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.zones_id_seq OWNED BY public.zones.id;


--
-- Name: artefacts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.artefacts ALTER COLUMN id SET DEFAULT nextval('public.artefacts_id_seq'::regclass);


--
-- Name: config id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.config ALTER COLUMN id SET DEFAULT nextval('public.config_id_seq'::regclass);


--
-- Name: controller_known_locations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_known_locations ALTER COLUMN id SET DEFAULT nextval('public.controller_known_locations_id_seq'::regclass);


--
-- Name: controller_worker id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_worker ALTER COLUMN id SET DEFAULT nextval('public.controller_worker_id_seq'::regclass);


--
-- Name: controllers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers ALTER COLUMN id SET DEFAULT nextval('public.controllers_id_seq'::regclass);


--
-- Name: controllers_known_enemies id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers_known_enemies ALTER COLUMN id SET DEFAULT nextval('public.controllers_known_enemies_id_seq'::regclass);


--
-- Name: faction_powers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.faction_powers ALTER COLUMN id SET DEFAULT nextval('public.faction_powers_id_seq'::regclass);


--
-- Name: factions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factions ALTER COLUMN id SET DEFAULT nextval('public.factions_id_seq'::regclass);


--
-- Name: link_power_type id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.link_power_type ALTER COLUMN id SET DEFAULT nextval('public.link_power_type_id_seq'::regclass);


--
-- Name: location_attack_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.location_attack_logs ALTER COLUMN id SET DEFAULT nextval('public.location_attack_logs_id_seq'::regclass);


--
-- Name: locations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.locations ALTER COLUMN id SET DEFAULT nextval('public.locations_id_seq'::regclass);


--
-- Name: mechanics id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mechanics ALTER COLUMN id SET DEFAULT nextval('public.mechanics_id_seq'::regclass);


--
-- Name: players id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.players ALTER COLUMN id SET DEFAULT nextval('public.players_id_seq'::regclass);


--
-- Name: power_types id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.power_types ALTER COLUMN id SET DEFAULT nextval('public.power_types_id_seq'::regclass);


--
-- Name: powers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.powers ALTER COLUMN id SET DEFAULT nextval('public.powers_id_seq'::regclass);


--
-- Name: worker_actions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_actions ALTER COLUMN id SET DEFAULT nextval('public.worker_actions_id_seq'::regclass);


--
-- Name: worker_names id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_names ALTER COLUMN id SET DEFAULT nextval('public.worker_names_id_seq'::regclass);


--
-- Name: worker_origins id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_origins ALTER COLUMN id SET DEFAULT nextval('public.worker_origins_id_seq'::regclass);


--
-- Name: worker_powers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_powers ALTER COLUMN id SET DEFAULT nextval('public.worker_powers_id_seq'::regclass);


--
-- Name: workers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workers ALTER COLUMN id SET DEFAULT nextval('public.workers_id_seq'::regclass);


--
-- Name: zones id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.zones ALTER COLUMN id SET DEFAULT nextval('public.zones_id_seq'::regclass);


--
-- Data for Name: artefacts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.artefacts (id, location_id, name, description, full_description) FROM stdin;
1	8	Fujitaka (藤孝) Hosokawa (細川) le daimyô prisonnier	Nous avons découvert que cet homme que tous pensent mort est en réalité enfermé dans une geôle oubliée, gardée par ceux qui craignent son retour.	Nous sommes libres de décidé de sa destinée (aller voir un orga)!
2	9	Kunichika(国親) Chōsokabe(長宗我部) blessé, brisé, il vit toujours	L’ancien seigneur de Shikoku n’est pas tombé à la guerre — il est retenu ici, gardée par ceux qui craignent son retour.	Nous sommes libres de décidé de sa destinée (aller voir un orga)!
3	41	Motochika (元親) Chōsokabe(長宗我部) daimyô en devenir	Fils de Kunichika, encore trop jeune pour gouverner, il est la clef d’un fragile héritage.	Nous sommes libres de décidé de sa destinée (aller voir un orga)!
4	40	Tama (玉) Hosokawa (細川), fille de Fujitaka(藤孝), petite soeur de Tadaoki	Jeune noble éduquée aux arts de la poésie et de l’étiquette, elle est certainment le pion d’un jeu politique.	Nous sommes libres de décidé de sa destinée (aller voir un orga)!
5	39	Fudžisan(富士山) Miyoshi(三好), petit soeur du daimyô Nagayoshi (長慶).	Promise à un mariage d’alliance, elle demeure énigmatique, pieuse, et bien plus rusée que son sourire ne le laisse paraître.	Nous sommes libres de décidé de sa destinée (aller voir un orga)!
\.


--
-- Data for Name: config; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.config (id, name, value, description) FROM stdin;
1	DEBUG	FALSE	Activates the Debugging texts
2	DEBUG_REPORT	FALSE	Activates the Debugging texts for the investigation report
3	DEBUG_ATTACK	FALSE	Activates the Debugging texts for the attack report mechanics
4	DEBUG_TRANSFORM	FALSE	Activates the Debugging texts for the attack report mechanics
5	ACTIVATE_TESTS	TRUE	Activates the insertion of tests values
10	turn_recrutable_workers	1	Number of workers recrutable per turn
11	turn_firstcome_workers	1	Number of worker recrutable by firstcome pick per turn
12	first_come_nb_choices	1	Number of worker options presented for 1st come recrutment
13	first_come_origin_list	rand	Origins used for worker generation
14	recrutement_nb_choices	3	Number of choices presented for recrutment
18	recrutement_transformation	{"action": "check"}	Json string calibrating transformations allowed on recrutment
20	age_transformation	{"action": "check"}	If transformation can be gained with AGE
21	MINROLL	1	Minimum Roll for an active worker
22	MAXROLL	6	Maximum Roll for a an active worker
23	PASSIVEVAL	3	Value for passive actions
24	ENQUETE_ZONE_BONUS	0	Bonus à la valeur enquete si le worker est dans une zone contrôlée
25	ATTACK_ZONE_BONUS	0	Bonus à la valeur attaque si le worker est dans une zone contrôlée
26	DEFENCE_ZONE_BONUS	1	Bonus à la valeur défense si le worker est dans une zone contrôlée
27	passiveInvestigateActions	'passive','attack','captured'	Liste of passive investigation actions
28	activeInvestigateActions	'investigate','claim'	Liste of active investigation actions
29	passiveAttackActions	'passive','investigate'	Liste of passive attack actions
30	activeAttackActions	'attack','claim'	Liste of active attack actions
31	passiveDefenceActions	'passive','investigate','attack','claim','captured'	Liste of passive defence actions
32	activeDefenceActions		Liste of active defense actions
33	REPORTDIFF0	0	Value for Level 0 information
34	REPORTDIFF1	1	Value for Level 1 information
35	REPORTDIFF2	2	Value for Level 2 information
36	REPORTDIFF3	3	Value for Level 3 information
37	LOCATIONNAMEDIFF	0	Value for Location Name
38	LOCATIONINFORMATIONDIFF	1	Value for Location Information
39	LOCATIONARTEFACTSDIFF	2	Value for Location Artefact discovery
40	attackTimeWindow	1	Number of turns a discovered worker is attackable after being lost
41	canAttackNetwork	0	If 0 then only workers ar shown, > 0 then workers are sorted by networks when network is known = REPORTDIFF2 obtained 
42	LIMIT_ATTACK_BY_ZONE	0	If 0 then attack happens if worker leave zone, > 0 then attack is limited to workers in zone
43	ATTACKDIFF0	1	Value for Attack Success
44	ATTACKDIFF1	4	Value for Capture
45	RIPOSTACTIVE	1	Activate Ripost when attacked
46	RIPOSTDIFF	2	Value for Successful Ripost
47	DISCRETECLAIMDIFF	2	Value for discrete claim
48	VIOLENTCLAIMDIFF	0	Value for violent claim
49	txt_ps_passive	surveille	Text for passive action
50	txt_ps_investigate	enquete	Text for investigate action
51	txt_ps_attack	attaque	Text for attack action
52	txt_ps_claim	revendique le quartier	Text for claim action
53	txt_ps_captured	a disparu	Text for captured action
54	txt_ps_dead	a disparu	Text for dead action
55	txt_ps_prisoner	est un.e agent de %s que nous avons fait.e prisonnier.e	Text for beeing prisoner
56	txt_ps_double_agent	a infiltré le réseau de %s 	Text for being infiltrator
57	txt_inf_passive	surveiller	Text for passive action
58	txt_inf_investigate	enqueter	Text for investigate action
59	txt_inf_attack	attaquer	Text for attack action
60	txt_inf_claim	revendiquer le quartier	Text for claim action
61	txt_inf_captured	as été capturer	Text for captured action
62	txt_inf_dead	est mort	Text for dead action
63	continuing_investigate_action	false	Does the investigate action stay active
64	continuing_claimed_action	false	Does the claim action stay active
65	baseDiscoveryDiff	3	Base discovery value for bases
66	baseDiscoveryDiffAddPowers	1	Base discovery value Power presence ponderation 0 for no
67	baseDiscoveryDiffAddWorkers	1	Base discovery value worker presence ponderation 0 for no
68	baseDiscoveryDiffAddTurns	1	Base discovery value base age presence ponderation 0 for no
69	maxBonusDiscoveryDiffPowers	5	Maximum bonus obtainable from power presence
70	maxBonusDiscoveryDiffWorkers	4	Maximum bonus obtainable from worker presence
71	maxBonusDiscoveryDiffTurns	2	Maximum bonus obtainable from age of base
72	baseAttack	0	Base defence value for bases
73	baseAttackAddPowers	1	Base defence value Power presence ponderation 0 for no
74	baseAttackAddWorkers	1	Base defence value worker presence ponderation 0 for no
75	baseDefence	0	Base defence value for bases
76	baseDefenceAddPowers	1	Base defence value Power presence ponderation 0 for no
77	baseDefenceAddWorkers	1	Base defence value worker presence ponderation 0 for no
78	baseDefenceAddTurns	1	Base defence value base age presence ponderation 0 for no
79	maxBonusDefenceTurns	3	Maximum bonus obtainable from age of base
80	attackLocationDiff	1	Difficulty to destroy a Location
6	TITLE	Shikoku (四国) 1555	Name of game
8	IntrigueOrga	 <p>  <button onclick="window.open('https://docs.google.com/document/d/1qrYEpObe6sVdp1egCMnOcGW9BNXebPp_PWiLrD4Lqb8', '_blank')"> Documents Orga !</button> </p>	Organisation info
81	textLocationDestroyed	Le lieu %s a été détruit selon votre bon vouloir	Text for location destroyed
82	textLocationPillaged	Le lieu %s a été pillée.	Text for location pillaged
83	textLocationNotDestroyed	Le lieu %s n’a pas été détruit, nos excuses	Text for location not destroyed
15	recrutement_origin_list	1,2,3,4,5,6,7	Origins used for worker generation
16	local_origin_list	1,2,3,4,5,6	Spécific list of local origins for investigations texts
17	recrutement_disciplines	1	Number of disciplines allowed on recrutment
19	age_discipline	{"age": ["1","2"]}	If disciplines can be gained with AGE
84	map_file	shikoku.png	Map file to use
85	map_alt	Carte de Shikoku	Map alt
86	textForZoneType	territoire	Text for the type of zone
87	timeValue	Trimestre	Text for time span
88	timeDenominatorThis	ce	Denominator ’this’ for time text
9	basePowerNames	'Sōjutsu (槍術) – Art de la lance (Yari)', 'Kyūjutsu (弓術) – Art du tir à l’arc', 'Shodō (書道) – Calligraphie', 'Kadō / Ikebana (華道 / 生け花) – Art floral'	List of Powers accessible to all workers
7	PRESENTATION	<p> En plein Sengoku Jidai(戦国時代), les turbulences sociales, intrigues politiques et conflits militaires divisent le Japon.\r\n       Les guerres fratricides font rage sur l’archipel nippon, et le Shogunat Ashikaga(足利) fragilisé peine à rétablir la paix.\r\n       Au printemps 1555, les forces du shugo (守護) de Shikoku(四国), composées du daimyô Kunichika(国親) Chōsokabe(長宗我部), accompagné de son vassal Fujitaka (藤孝) Hosokawa(細川) et en l’absence notable du daimyô du clan Miyoshi(三好),\r\n        sont parties défendre Kyoto(京都市), sur l’ile principale de Honshu(本州), contre les forces du clan Takeda(武田), espérant ainsi s’attirer les faveurs du Shogun Ashikaga.\r\n       Les rares survivants rentrés de la campagne parlent d’une défaite cuisante, d’une rébellion paysanne et du déshonneur du daimyô et de ses vassaux.\r\n       Le contrôle du clan Chōsokabe vacille sur Shikoku et les vassaux même du clan voient la disparition de Kunichika Chōsokabe comme une opportunité sans précédent.\r\n       Celui qui pourra s’octroyer l’allégeance de la majorité des 4 provinces sera maître de l’île de Shikoku.<br>\r\n        <button onclick="window.open('https://docs.google.com/document/d/1ibggeKiMASJFWr_BnAgUzgQj0bJpZkB2LPtQWVRKt3s', '_blank')"> Document d’introduction Joueur !</button>        \r\n        </p>	Name of game
89	textRecrutementJobHobby	Est un.e %5$s avec un.e %4$s	string to present hobby %4$s and job %5$s on recrutement
90	textViewWorkerJobHobby	c’est un.e %2$s avec un.e %3$s 	string to present hobby %2$s and job %3$s view of worker
91	textViewWorkerDisciplines	Ses disciplines développées sont : %s <br />	Texts for worker view page disciplines
92	textViewWorkerTransformations	Iel a été équipé de : %s <br />	Texts for worker view page transformations
93	texteNameBase	Forteresse des %s	Text for Name of base
94	texteDescriptionBase	\r\n        Nous avons trouvé la forteresse de %1$s des %2$s. Les serviteurs de confiance leur manquent encore pour avoir des défenses solides.\r\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\r\n        L’attaque causerait certainement quelques questions à la cour du Shogun, mais un joueur affaibli sur l’échiquier politique est toujours bénéfique.\r\n        Nous ne devons pas tarder à prendre notre décision, ses défenses se renforcent chaque %3$s.\r\n    	Texts for description of base
95	texteHiddenFactionBase	\r\n       Il nous apparait en fouillant le lieu que ce quelqu’un s’est donné beaucoup de mal pour que cette forteresse donne l’impression d’être liée aux %1$s, mais en réalité son propriétaire est des %2$s.\r\n    	Texts for secret faction description of base
96	textControllerActionCreateBase	Créer une forteresse dans la région :	create base texte in controller view actions
97	textControllerActionMoveBase	Déménager dans une forteresse de la région :	move base texte in controller view actions
98	textcontrollerRecrutmentNeedsBase	Nous ne pouvons pas recruter sans avoir établi une forteresse.	needed base for recrutment
99	textesStartInvestigate	<p> Nous avons mené l’enquête dans le territoire %s.</p>	Texts for start of investigation
100	textesFoundDisciplines	[\r\n        "Iel a de plus une maitrise de la discipline %s. ",\r\n        "En plus, iel maitrise l’art du %s. ",\r\n        "Nous avons également remarqué sa pratique de l’art %s. ",\r\n        "Ces observations se cumulent avec son utilisation de la discipline %s. "\r\n    ]	Texts for extra disciplines
101	textesOrigine	[\r\n        "J’ai des raisons de penser qu’iel est natif.ve de %s. ",\r\n        "En plus, iel est originaire de %s. ",\r\n        "Je m’en méfie, iel vient de %s. "\r\n    ]	Texts for origin detection
102	textesDiff01Array	[\r\n        [\r\n            "J’ai vu un.e %2$s du nom de %1$s qui %4$s dans ma zone d’action. %9$s",\r\n            "J’ai remarqué qu’iel avait un.e %3$s mais je suis sûr qu’iel possède aussi l’art de %6$s%8$s."\r\n        ],\r\n        [\r\n            "Nous avons repéré un.e %2$s du nom de %1$s qui %4$s dans notre région. %9$s",\r\n            "En poussant nos recherches il s’avère qu’iel maitrise %6$s%8$s. Iel a aussi été vu.e avec un.e %3$s, mais cette information n’est pas si pertinente."\r\n        ],\r\n        [\r\n            "J’ai trouvé %1$s, qui n’est clairement pas un agent à nous, c’est un.e %2$s avec un.e %3$s.",\r\n            "%9$sIel démontre une légère maitrise de la discipline %6$s%8$s."\r\n        ],\r\n        [\r\n            "Je me suis rendu compte que %1$s, que j’ai repéré avec un.e %3$s, %4$s dans le coin. %9$s",\r\n            "C’était étrange, alors j’ai enquêté et trouvé qu’iel a en réalité des capacités de %6$s, ce qui en fait un.e %2$s un peu trop spécial.e%8$s."\r\n        ],\r\n        [\r\n            "On a suivi %1$s parce qu’on l’a repéré.e en train de %5$s, ce qui nous a mis la puce à l’oreille. C’est normalement un.e %2$s mais on a découvert qu’il possédait aussi un.e %3$s.",\r\n            "%9$sCela dit, le vrai problème, c’est qu’il semble maîtriser %6$s, au moins partiellement%8$s."\r\n        ]\r\n    ]	Texts for search results level 1 (structured dialogue in pairs)
103	textesDiff01TransformationDiff0Array	[\r\n        [\r\n            "Nous avons repéré le possesseur d’un.e %7$s du nom de %1$s qui %4$s dans notre région. %9$s",\r\n            "En poussant nos recherches il s’avère qu’iel maitrise %6$s. Iel possède aussi un.e %3$s, mais cette information n’est pas pertinente. "\r\n        ],\r\n        [\r\n            "J’ai trouvé %1$s, avec un.e %7$s, qui n’est clairement pas un.e de nos loyaux suivants, c’est un.e %2$s qui a également été vu.e avec un.e %3$s. %9$s",\r\n            "Iel démontre une légère maitrise de l’art %6$s."\r\n        ],\r\n        [\r\n            "Je me suis rendu compte que quelqu’un possédant un.e %7$s %4$s dans le coin. On l’a entendu.e se faire appeler %1$s. %9$s",\r\n            "C’était étrange, alors j’ai enquêté et trouvé qu’iel a des capacités de %4$s, ce qui en fait un %2$s un peu trop spécial."\r\n        ]\r\n    ]	Variation text blocks for Diff01 transformation reports
104	textesDiff2	[\r\n        "%2$sEn plus, sa famille a des liens avec la faction %1$s. %3$s",\r\n        "Iel fait partie de la faction %1$s. %3$s %2$s ",\r\n        "%2$sEn creusant, iel est rattaché.e à la faction %1$s. %3$s ",\r\n        "%3$s Iel reçoit un soutien financier de la faction %1$s. %2$s",\r\n        "%2$sIel travaille avec la faction %1$s. %3$s"\r\n    ]	Texts for search results level 2
105	textesDiff3	[\r\n        "Ce réseau d’informateurs répond à %1$s. ",\r\n        "A partir de là nous avons pu remonter jusqu’à %1$s. ",\r\n        "Cela signifie qu’iel travaille forcément pour %1$s. ",\r\n        "Nous l’avons vu rencontrer en personne %1$s. ",\r\n        "Ce qui veut dire que c’est un serviteur de %1$s. "\r\n    ]	Texts for search results level 3
106	textesTransformationDiff1	[\r\n    " et nous observons qu’iel possède un.e %s",\r\n    ", de plus iel laisse penser qu’iel a un.e %s"\r\n]	Texts for transformation level 1
107	textesTransformationDiff2	[\r\n    "Iel a probablement un.e %s mais les preuves nous manquent encore. ",\r\n    "Iel est clairement atypique, iel possède un.e %s. "\r\n]	Texts for transformation level 2
108	textesClaimFailViewArray	[\r\n    "J’ai vu %1$s tenter de prendre le contrôle du territoire %2$s, mais la défense l’a repoussé.e brutalement.",\r\n    "L’assaut de %1$s sur le territoire %2$s a échoué ; c’était un vrai carnage.",\r\n    "%1$s a voulu s’imposer au %2$s, sans succès. Iel a été forcé.e de battre en retraite.",\r\n    "Je pense que %1$s pensait avoir une chance au %2$s. C’était mal calculé, iel a échoué."\r\n]	Texts the workers observing the failed violent claiming of a zone
109	textesClaimSuccessViewArray	[\r\n    "J’ai vu %1$s renverser l’autorité sur %2$s. La zone a changé de mains.",\r\n    "%2$s appartient désormais au maitre de %1$s. Iel a balayé toute résistance.",\r\n    "L’opération de %1$s sur %2$s a été une réussite totale, malgré les dégats.",\r\n    "%1$s a pris %2$s par la force. Iel n’a laissé aucune chance aux défenseurs."\r\n]	Texts the workers observing the successful violent claiming of a zone
110	textesClaimFailArray	[\r\n    "Notre tentative de prise de contrôle de %2$s a échoué. La défense était trop solide.",\r\n    "Nous avons échoué à nous imposer en force sur %2$s. Il faudra retenter plus tard.",\r\n    "L’assaut sur %2$s a été un échec. Les forces en place ont tenu bon.",\r\n    "La mission de domination de %2$s n’a pas abouti. Trop de résistance à notre autorité sur place."\r\n]	Texts for the fail report of the claiming worker
111	textesClaimSuccessArray	[\r\n    "Nous avons pris le contrôle du territoire %2$s avec succès. Félicitations vous en êtes désormais le maitre.",\r\n    "Notre offensive sur la zone %2$s a porté ses fruits. Elle est maintenant à vous.",\r\n    "Nous avons su imposer votre autorité sur %2$s. La région vous obéit désormais.",\r\n    "%2$s est tombé.e sous votre coupe."\r\n]	Texts for the success report of the claiming worker
112	workerDisappearanceTexts	[\r\n    "<p>Cet agent a disparu sans laisser de traces à partir de la semaine %s.</p>",\r\n    "<p>Depuis la semaine %s, plus aucun signal ni message de cet agent.</p>",\r\n    "<p>La connexion avec l’agent s’est perdue la semaine %s, et nous ignorons où iel se trouve.</p>",\r\n    "<p>À partir de la semaine %s, cet agent semble s’être volatilisé.e dans la nature.</p>",\r\n    "<p>Nous avons perdu toute communication avec cet agent depuis la semaine %s.</p>",\r\n    "<p>La dernière trace de cet agent remonte à la semaine %s, depuis iel est porté.e disparu.e.</p>",\r\n    "<p>La semaine %s marque la disparition totale de cet agent. Aucun indice sur sa situation actuelle.</p>",\r\n    "<p>L’agent s’est évanoui dans la nature après la semaine %s. Aucune nouvelle depuis.</p>",\r\n    "<p>Depuis la semaine %s, cet agent est un fantôme, insaisissable et introuvable.</p>",\r\n    "<p>La semaine %s signe le début du silence complet de cet agent.</p>"\r\n]	Templates used for worker disappearance text with a week number placeholder
113	attackSuccessTexts	[\r\n    "<p>J’ai pu mener à bien ma mission sur %1$s, son silence est assuré.</p>",\r\n    "<p>J’ai accompli l’attaque sur %1$s, iel a trouvé son repos final.</p>",\r\n    "<p>Notre cible %1$s a été accompagnée chez le médecin dans un état critique, nous n’avons plus rien à craindre.</p>",\r\n    "<p>Un.e suicidé.e sera retrouvé.e dans la mer demain, %1$s n’est plus des nôtres.</p>",\r\n    "<p>Je confirme que %1$s ne posera plus jamais problème, iel a rejoint le silence éternel.</p>",\r\n    "<p>L’histoire de %1$s est officiellement terminée. Son existence appartient désormais au passé.</p>",\r\n    "<p>Mission accomplie : %1$s est désormais une simple note dans les rouleaux de l’histoire.</p>"\r\n]	Templates for successful attack reports mentioning the target name
114	captureSuccessTexts	[\r\n    "<p>La mission est un succès total : %1$s est désormais entre nos mains, et nous allons mener l’interrogatoire.</p>",\r\n    "<p>La mission s’est déroulée comme prévu : %1$s est capturé.e et prêt.e à livrer ses secrets.</p>",\r\n    "<p>Succès complet sur %1$s : iel est désormais sous notre garde et n’aura d’autre choix que de parler.</p>",\r\n    "<p>Nous avons maîtrisé %1$s : iel est maintenant entre nos mains, prêt.e pour l’interrogatoire.</p>",\r\n    "<p>Mission accomplie : %1$s est capturé.e et en sécurité pour une conversation approfondie.</p>",\r\n    "<p>L’objectif %1$s est neutralisé et sous notre contrôle. L’interrogatoire peut commencer.</p>",\r\n    "<p>Nous avons intercepté %1$s sans heurt : iel est désormais à notre merci pour un échange d’informations.</p>",\r\n    "<p>Le succès est total : %1$s est retenu.e, et ses révélations ne tarderont pas.</p>",\r\n    "<p>Mission terminée brillamment : %1$s est capturé.e et ne nous échappera plus.</p>"\r\n]	Inclusive templates for successful capture reports mentioning the target name
115	failedAttackTextes	[\r\n    "<p>Malheureusement, %1$s a réussi à nous échapper et reste en vie.</p>",\r\n    "<p>La conspiration contre %1$s a échoué. La cible a survécu et demeure une menace.</p>",\r\n    "<p>Notre tentative contre %1$s s’est soldée par un échec. Iel est toujours actif.ve.</p>",\r\n    "<p>L’attaque n’a pas atteint son objectif : %1$s a survécu et garde sa liberté.</p>",\r\n    "<p>Nous n’avons pas pu neutraliser %1$s. Iel reste introuvable après l’affrontement.</p>",\r\n    "<p>La mission a été un revers : %1$s est toujours debout et hors de notre portée.</p>",\r\n    "<p>Malgré nos efforts, %1$s s’est défendu.e avec succès et a réussi à fuir.</p>",\r\n    "<p>Notre assaut n’a pas suffi : %1$s a survécu et continue d’agir.</p>",\r\n    "<p>La cible %1$s s’est montrée plus résistant.e que prévu. Iel a échappé à notre emprise.</p>",\r\n    "<p>Nous avons échoué à neutraliser %1$s. Iel demeure vivant.e et peut encore riposter.</p>"\r\n]	Texts for failed attacks in inclusive language
116	escapeTextes	[\r\n    "<p>J’ai été pris.e pour cible par %1$s, mais j’ai réussi à lui échapper de justesse.</p>",\r\n    "<p>Une attaque orchestrée par %1$s a failli avoir raison de moi, mais j’ai pu me faufiler hors de sa portée.</p>",\r\n    "<p>L’embuscade tendue par %1$s n’a pas suffi à me retenir, j’ai pu m’échapper.</p>",\r\n    "<p>J’ai croisé %1$s sur ma route, iel a tenté de m’intercepter, mais j’ai fui avant qu’il ne soit trop tard.</p>",\r\n    "<p>L’attaque de %1$s a échoué, je suis sauf.ve et hors de danger.</p>",\r\n    "<p>Un assaut surprise de %1$s m’a pris.e au dépourvu, mais j’ai échappé à ses griffes à temps.</p>",\r\n    "<p>Malgré une attaque menée par %1$s, j’ai gardé mon calme et trouvé un chemin pour m’échapper.</p>",\r\n    "<p>J’ai senti %1$s venir et, bien que surpris.e, j’ai su échapper à son piège.</p>",\r\n    "<p>%1$s a tenté de me capturer, mais ma fuite a été rapide et efficace.</p>",\r\n    "<p>L’assaut de %1$s n’a pas eu le résultat escompté, je suis parvenu.e à m’enfuir indemne.</p>"\r\n]	Texts for successful escapes in inclusive language
117	textesAttackFailedAndCountered	[\r\n    "<p>Je pars mettre en route le plan d’assassinat de %s. [Le rouleau s’arrête ici.]</p>",\r\n    "<p>Début de la mission : %s. [Le rapport n’a jamais été terminé.]</p>",\r\n    "<p>Nous avons perdu contact avec l’agent juste après le début de l’opération sur %s.</p>",\r\n    "<p>Le silence total après le lancement de la mission contre %s est inquiétant…</p>",\r\n    "<p>Le groupe envoyé pour neutraliser %s n’est jamais revenu.</p>"\r\n]	Texts for missions that fail and result in counter-attack or disappearance
118	counterAttackTexts	[\r\n    "<p>%1$s m’a attaqué.e, j’ai survécu et ma riposte l’a anéanti.e. J’ai jeté son corps dans la mer.</p>",\r\n    "<p>Après avoir été attaqué.e par %1$s, j’ai non seulement survécu, mais ma riposte nous assure qu’iel cesse définitivement ses activités.</p>",\r\n    "<p>%1$s a cru m’avoir, mais ma riposte a brisé ses espoirs et l’a détruit.e.</p>",\r\n    "<p>Iel a tenté de me réduire au silence, mais après avoir survécu à l’attaque de %1$s, j’ai répondu par une riposte fatale.</p>",\r\n    "<p>Malgré l’assaut de %1$s, ma riposte a non seulement sauvé ma vie, mais a mis complètement fin à ses ambitions.</p>",\r\n    "<p>Attaqué.e par %1$s, j’ai résisté et ma riposte l’a anéanti.e sans retour.</p>",\r\n    "<p>Iels ont cherché à me faire tomber, mais ma riposte après l’attaque de %1$s a effacé toute menace.</p>",\r\n    "<p>L’attaque de %1$s a échoué, et ma réponse a été rapide, fatale et décisive.</p>",\r\n    "<p>Je me suis retrouvé.e face à %1$s, mais après avoir survécu à son attaque, ma riposte a scellé son destin.</p>",\r\n    "<p>Après une attaque brutale de %1$s, ma survie et ma riposte ont fait en sorte qu’iel n’ait plus rien à revendiquer.</p>"\r\n]	Texts for the worker who was atacked an the successfully countered
119	TEXT_LOCATION_DISCOVERED_NAME	[\r\n    "Nous avons identifié une information intéressante : un.e %s serait présent.e dans la zone.",\r\n    "Des signes pointent vers la présence d’un.e %s, nous devons enquêter davantage à ce sujet.",\r\n    "Il semblerait qu’un.e %s se trouve dans cette région, il faudra s’en assurer.",\r\n    "Des rumeurs persistantes évoquent la présence d’un.e %s dans les environs.",\r\n    "Nos informateurs.rices évoquent la découverte potentielle d’un.e %s dans cette zone.",\r\n    "Certains indices laissent penser qu’un.e %s pourrait se cacher ici.",\r\n    "Un rapport fragmentaire mentionne un.e %s comme étant caché.e dans ce territoire."\r\n]	Phrases pour signaler qu’une localisation a été découverte (nom uniquement)
120	TEXT_LOCATION_DISCOVERED_DESCRIPTION	[\r\n    "Information intéressante : un.e %s est présent.e dans la zone. %s",\r\n    "Nous avons confirmé la présence d’un.e %s. Nous avons enquêté davantage et découvert que : %s",\r\n    "Après enquête, il s’avère qu’un.e %s est bien lié.e à cette localisation. %s",\r\n    "Notre exploration confirme la présence d’un.e %s. Voici ce que nous avons appris : %s",\r\n    "Nous avons vérifié les rumeurs : un.e %s est bien ici. %s",\r\n    "Le mystère est levé : un.e %s se trouve dans cette zone. %s",\r\n    "Les données concordent : un.e %s est bien associé.e à cet endroit. %s"\r\n]	Phrases pour décrire une localisation après enquête
121	TEXT_LOCATION_CAN_BE_DESTROYED	[\r\n    " Nous pouvons retourner cette information contre son maître et nous y attaquer.",\r\n    " Il est possible d’organiser une mission pour faire disparaître ce problème."\r\n]	Phrases pour signaler qu’une localisation peut être détruite
\.


--
-- Data for Name: controller_known_locations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.controller_known_locations (id, controller_id, location_id, first_discovery_turn, last_discovery_turn, created_at) FROM stdin;
38	4	18	2	3	2025-06-22 13:35:57.403585
68	8	37	3	3	2025-06-22 14:46:43.384017
69	8	27	3	3	2025-06-22 14:46:43.387206
70	8	23	3	3	2025-06-22 14:46:43.390123
71	8	16	3	3	2025-06-22 14:46:43.393364
72	8	4	3	3	2025-06-22 14:46:43.396664
73	5	34	3	3	2025-06-22 14:46:43.400633
74	5	21	3	3	2025-06-22 14:46:43.403084
16	5	7	1	1	2025-06-22 12:42:26.189729
75	5	14	3	3	2025-06-22 14:46:43.405591
76	4	28	3	3	2025-06-22 14:46:43.409349
18	8	20	1	1	2025-06-22 12:42:26.200273
77	4	25	3	3	2025-06-22 14:46:43.412585
14	2	30	0	3	2025-06-22 12:08:14.220682
9	2	26	0	3	2025-06-22 12:08:14.211082
2	2	7	0	3	2025-06-22 12:08:14.200076
43	6	37	2	3	2025-06-22 13:35:57.436737
21	5	26	1	1	2025-06-22 12:42:26.216109
10	6	27	0	3	2025-06-22 12:08:14.214698
19	6	23	1	3	2025-06-22 12:42:26.203962
44	6	16	2	3	2025-06-22 13:35:57.442154
78	7	37	3	3	2025-06-22 14:46:43.434194
67	7	27	3	3	2025-06-22 14:46:43.378629
79	7	23	3	3	2025-06-22 14:46:43.439218
80	7	16	3	3	2025-06-22 14:46:43.441589
24	5	30	1	1	2025-06-22 12:42:26.236419
81	7	4	3	3	2025-06-22 14:46:43.443934
82	6	22	3	3	2025-06-22 14:46:43.446336
83	1	15	3	3	2025-06-22 14:46:43.449052
49	1	35	3	3	2025-06-22 14:46:43.302445
50	1	32	3	3	2025-06-22 14:46:43.305919
51	1	20	3	3	2025-06-22 14:46:43.308754
84	4	39	3	3	2025-06-22 14:46:43.458359
25	8	22	2	2	2025-06-22 13:35:57.368357
26	4	30	2	2	2025-06-22 13:35:57.370972
27	4	26	2	2	2025-06-22 13:35:57.372799
28	4	7	2	2	2025-06-22 13:35:57.3746
29	5	15	2	2	2025-06-22 13:35:57.376697
30	5	10	2	2	2025-06-22 13:35:57.378578
31	5	33	2	2	2025-06-22 13:35:57.380829
32	5	22	2	2	2025-06-22 13:35:57.382499
34	6	38	2	2	2025-06-22 13:35:57.39014
12	6	29	0	2	2025-06-22 12:08:14.217104
4	6	19	0	2	2025-06-22 12:08:14.205044
35	6	13	2	2	2025-06-22 13:35:57.396099
36	6	2	2	2	2025-06-22 13:35:57.397891
37	4	36	2	2	2025-06-22 13:35:57.400172
39	4	17	2	2	2025-06-22 13:35:57.405396
40	4	1	2	2	2025-06-22 13:35:57.407099
15	1	31	0	2	2025-06-22 12:08:14.227657
7	1	24	0	2	2025-06-22 12:08:14.208734
11	1	28	0	2	2025-06-22 12:08:14.215919
8	1	25	0	2	2025-06-22 12:08:14.210011
3	1	9	0	2	2025-06-22 12:08:14.203302
1	1	6	0	2	2025-06-22 12:08:14.197562
85	4	33	3	3	2025-06-22 14:46:43.460934
86	4	22	3	3	2025-06-22 14:46:43.463571
41	8	30	2	2	2025-06-22 13:35:57.425126
87	4	3	3	3	2025-06-22 14:46:43.466228
88	7	30	3	3	2025-06-22 14:46:43.469508
89	7	26	3	3	2025-06-22 14:46:43.472606
6	7	22	0	2	2025-06-22 12:08:14.20753
42	7	18	2	2	2025-06-22 13:35:57.434808
45	5	41	2	2	2025-06-22 13:35:57.44418
46	5	35	2	2	2025-06-22 13:35:57.446211
47	5	32	2	2	2025-06-22 13:35:57.448464
48	5	20	2	2	2025-06-22 13:35:57.450677
90	7	7	3	3	2025-06-22 14:46:43.475628
91	4	37	3	3	2025-06-22 14:46:43.478391
52	8	36	3	3	2025-06-22 14:46:43.31168
53	8	18	3	3	2025-06-22 14:46:43.314585
54	8	17	3	3	2025-06-22 14:46:43.31754
55	8	1	3	3	2025-06-22 14:46:43.32054
22	8	28	1	3	2025-06-22 12:42:26.224274
20	8	25	1	3	2025-06-22 12:42:26.210052
56	8	12	3	3	2025-06-22 14:46:43.329913
57	8	6	3	3	2025-06-22 14:46:43.332369
58	5	28	3	3	2025-06-22 14:46:43.335713
59	5	25	3	3	2025-06-22 14:46:43.338614
60	5	12	3	3	2025-06-22 14:46:43.341605
61	5	9	3	3	2025-06-22 14:46:43.344716
62	5	6	3	3	2025-06-22 14:46:43.349295
63	5	36	3	3	2025-06-22 14:46:43.35601
64	5	18	3	3	2025-06-22 14:46:43.35951
65	5	17	3	3	2025-06-22 14:46:43.362125
66	5	1	3	3	2025-06-22 14:46:43.364639
23	8	29	1	3	2025-06-22 12:42:26.230066
17	8	19	1	3	2025-06-22 12:42:26.197539
92	4	27	3	3	2025-06-22 14:46:43.480961
93	4	23	3	3	2025-06-22 14:46:43.483949
94	4	16	3	3	2025-06-22 14:46:43.486746
33	1	38	2	3	2025-06-22 13:35:57.384311
13	1	29	0	3	2025-06-22 12:08:14.218271
5	1	19	0	3	2025-06-22 12:08:14.206237
\.


--
-- Data for Name: controller_worker; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.controller_worker (id, controller_id, worker_id, is_primary_controller, created_at) FROM stdin;
1	1	1	t	2025-06-22 11:19:06.71051
2	1	2	t	2025-06-22 11:19:06.71051
3	1	3	t	2025-06-22 11:19:06.71051
4	1	4	t	2025-06-22 11:19:06.71051
5	6	5	t	2025-06-22 11:19:06.71051
6	2	6	t	2025-06-22 11:19:06.71051
7	2	7	t	2025-06-22 11:19:06.71051
8	2	8	t	2025-06-22 11:19:06.71051
9	2	9	t	2025-06-22 11:19:06.71051
10	6	10	t	2025-06-22 11:44:52.951924
11	5	11	t	2025-06-22 11:45:36.057346
12	8	12	t	2025-06-22 11:45:40.779141
13	7	13	t	2025-06-22 11:46:10.05608
14	4	14	t	2025-06-22 11:46:24.695496
15	8	15	t	2025-06-22 11:52:21.361392
16	4	16	t	2025-06-22 11:56:49.788039
17	6	17	t	2025-06-22 11:56:55.049045
18	5	18	t	2025-06-22 11:57:55.104385
19	7	19	t	2025-06-22 12:01:26.804129
20	8	20	t	2025-06-22 12:10:56.508487
21	8	21	t	2025-06-22 12:11:33.992065
22	5	22	t	2025-06-22 12:12:03.395176
23	5	23	t	2025-06-22 12:13:32.572415
24	6	24	t	2025-06-22 12:14:07.698473
25	4	25	t	2025-06-22 12:23:15.896573
26	4	26	t	2025-06-22 12:24:37.108194
27	5	26	f	2025-06-22 12:24:37.119647
28	7	27	t	2025-06-22 12:25:19.344686
29	7	28	t	2025-06-22 12:31:04.140524
30	6	29	t	2025-06-22 12:34:34.060167
31	5	30	t	2025-06-22 12:49:04.884609
32	4	31	t	2025-06-22 12:50:06.930757
33	5	32	t	2025-06-22 12:50:29.058294
34	8	33	t	2025-06-22 12:51:54.863281
35	4	34	t	2025-06-22 12:52:05.007034
36	6	35	t	2025-06-22 12:52:27.229319
37	8	36	t	2025-06-22 12:52:42.684136
38	6	37	t	2025-06-22 12:53:16.236426
39	7	38	t	2025-06-22 13:21:42.594103
40	8	39	t	2025-06-22 13:41:58.563856
41	4	40	t	2025-06-22 13:57:37.702257
42	4	41	t	2025-06-22 13:59:18.293622
43	5	41	f	2025-06-22 13:59:18.305633
44	7	42	t	2025-06-22 14:04:20.890436
45	1	43	t	2025-06-22 14:15:56.6955
46	5	44	t	2025-06-22 14:16:57.304966
48	6	45	t	2025-06-22 14:23:47.649372
49	5	45	f	2025-06-22 14:23:47.66191
50	1	46	t	2025-06-22 14:29:02.016869
\.


--
-- Data for Name: controllers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.controllers (id, firstname, lastname, url, story, start_workers, recruited_workers, turn_recruited_workers, turn_firstcome_workers, ia_type, faction_id, fake_faction_id, secret_controller) FROM stdin;
2	Yoshiteru (義輝)	Ashikaga (足利)	https://docs.google.com/document/d/1CMSbdrjJqZz_wabuMNKS1qSh6T7apqDq_Ag7NpI7Xx4/edit?tab=t.0#heading=h.8idxv32d4hct		1	0	0	0	passif	4	4	t
3	Kūkai (空海)	Kōbō-Daishi (弘法大師)	https://docs.google.com/document/d/1bP2AGEA7grFw4k4CatLrTmeZkDDlczTqUEGg151GpQ8		1	0	0	0	passif	5	5	t
1	妖怪 de	Shikoku (四国)	https://docs.google.com/document/d/1gLcK961mCzDSAvaPVTy886JmRTpkPiACUyP8ArSkoPI		1	0	0	0	passif	9	5	f
5	Daïmyo Nagayoshi (長慶)	Miyoshi (三好)	https://docs.google.com/document/d/1W95lJ9bq0-KWRTCijgQ0Ua4koFsjTdLp3nvPTrnvCOc	 Depuis 5 ans vous êtes le Daimyō du clan Miyoshi(三好), comme votre père Motonaga avant vous et son père avant lui.\r\n          Mais vous, vous avez secrètement abandonné le bouddhisme pour embrasser la foi chrétienne, inspiré par les missionnaires venus avec les vaisseaux noirs portugais.\r\n          En échange de votre protection et de votre conversion, ils vous ont offert un cadeau inestimable : le secret des fusils à mèche occidentaux.\r\n          En cette fin de printemps, le parfum du sang flotte encore sur les rizières. Le Japon est à feu et à sang.\r\n          Deux daimyō sont morts à la guerre et les croyances vacillent.\r\n          Peut-être est-ce là l’heure bénie pour purger Shikoku(四国) des anciennes superstitions... faire de l’île la première terre chrétienne du Japon et du clan Miyoshi un clan majeur.\r\n        	1	0	0	0		8	2	f
7	Daïmyo Tadaoki (忠興)	Hosokawa (細川)	https://docs.google.com/document/d/14R_8j-5zbjC8Wzm72SsHS9QC8KDQ8l3AbkW5ZNmECAg	 Le parfum du sang flotte encore sur les rizières.\r\n          L’arrivée de l’été aurait dû annoncer la victoire, mais il n’apporte que les échos d’une défaite humiliante.\r\n          Votre père, Fujitaka(藤孝) Hosokawa, a disparu durant la désastreuse campagne de Kyoto. Tous le croient mort.\r\n          Vous, non. Vous avez toujours senti son esprit battre, disparu, en fuite, ou peut-être captif… Mais vivant.\r\n          En attendant, le pouvoir du clan est désormais entre vos mains. Vous êtes jeune, ambitieux, et les chaînes de l’obéissance vous pèsent.\r\n          Votre sœur, Tama (玉), est promise au jeune Motochika(元親) Chōsokabe(長宗我部), héritier encore trop jeune pour gouverner.\r\n          Une alliance utile, risquée si elle venait à être dévoilée trop tôt. Une chaîne de plus que votre père vous a laissé.\r\n          Deux ans. Voilà ce qu’il vous reste pour jouer vos cartes. Servir, trahir ou renaître. Le choix vous appartient.\r\n          	1	0	0	0		3	3	f
6	Shinshō-in (信証院)	Rennyo (蓮如)	https://docs.google.com/document/d/1xKYPslqDdxlps6A4ydFh_iUu6cvdP5VC9145goVmLrA	 Vous êtes Rennyo (蓮如) le Shinshō-in, huitième abbé du mouvement Jōdo Shinshū(浄土真宗) — la véritable école de la Terre pure.\r\n          Votre foi ne prêche pas seulement la voie du salut : elle appelle à la révolution. Les Ikkō-ikki, bras armé de cette croyance, rassemblent les paysans révoltés, les petits seigneurs opprimés, les moines guerriers et les prêtres shintō brisés par le joug des samouraïs et du Shogun.\r\n          C’est vos manigances tordues qui ont mené à la mort des Daimyô de Shikoku(四国), il ne vous reste qu’à terminer le travail de conquête de l’île.\r\n        	1	0	0	0		6	5	f
4	La Régence	Chōsokabe (長宗我部)	https://docs.google.com/document/d/1P2Mz4PAkw00DMXXG4hgyod3FJNJkdXHU2JHbvkn327I	Le parfum du sang flotte encore sur les rizières.\r\n          L’arrivée de l’été aurait dû annoncer la victoire, mais il n’apporte que les échos d’une défaite humiliante.\r\n          Kunichika (国親) Chōsokabe(長宗我部) est présumé mort, tombé sur les terres de Honshu(本州) aux côtés de ses vassaux, dans une guerre qu’il aurait dû gagner.\r\n          À présent, c’est vous qui devez gouverner. Le jeune héritier, Motochika (元親), n’a que treize ans. Trop jeune pour régner, trop précieux pour tomber.\r\n          Les regards se tournent vers vous, régent.e du clan, gardien.ne d’un pouvoir vacillant.\r\n          Vos ennemis vous observent. Vos alliés hésitent. Mais l’avenir n’est pas encore écrit.\r\n          Dans deux ans, Motochika atteindra l’âge de la majorité, et si vous parvenez à le protéger jusque-là, l’accord scellé avec le clan Hosokawa(細川) pourrait garantir une ère de stabilité.\r\n          Saurez-vous protéger l’héritier de votre clan ou vous couvrirez vous de honte?\r\n        	1	0	0	0		1	1	f
8	Murai	Wako (和光)	https://docs.google.com/document/d/1lgVjCyPTpzxA0nU649PyeDldVxCKtLSh9t7AJOmwREg	 Vous êtes Murai (村井), capitaine des Wako (和光), et maître incontesté d’un archipel sans lois.\r\n          Vous ne croyez ni aux daimyōs, ni aux dieux, ni aux rêves de paix. Ce que vous servez, c’est le vent, l’or, et l’opportunité.\r\n\t      Depuis la guerre d’Ōnin (応仁の乱, Ōnin no ran?) et l’affaiblissement du Shogunat, les vôtres pillent, commercent, et manipulent les seigneurs des côtes de la mer intérieure de Seto, de la baie de Tokushima et même jusqu’en Corée.\r\n          Le chaos actuel est une bénédiction.\r\n          À la faveur d’une embuscade habile, vos hommes ont capturé Kunichika Chōsokabe, le daimyō de Shikoku. Blessé, brisé, il vit toujours.\r\n          Et dans votre forteresse cachée de Shōdoshima, il vaut plus que n’importe quel trésor.\r\n\t      Vous pourriez le vendre à ses ennemis. Le rançonner à son clan. L’utiliser comme monnaie d’échange pour garantir votre place dans le futur de l’île. Ou simplement le laisser moisir jusqu’à ce qu’il ne reste rien de son nom.\r\n          Une chose est sûre : Si l’île s’unifie, votre liberté prendra fin. Mais tant que la guerre fait rage, les Wako régneront sur les brumes.\r\n        	1	0	0	0		7	7	f
\.


--
-- Data for Name: controllers_known_enemies; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.controllers_known_enemies (id, controller_id, discovered_worker_id, discovered_controller_id, discovered_controller_name, zone_id, first_discovery_turn, last_discovery_turn, created_at) FROM stdin;
6	6	18	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	3	0	3	2025-06-22 12:08:14.1459
32	4	29	6	\N	6	1	3	2025-06-22 12:42:26.118258
53	4	38	7	\N	1	2	2	2025-06-22 13:35:57.116959
8	4	2	\N	\N	3	0	0	2025-06-22 12:08:14.156491
9	7	11	\N	\N	6	0	0	2025-06-22 12:08:14.16787
10	1	16	\N	\N	3	1	1	2025-06-22 12:42:25.983421
2	1	18	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	3	0	3	2025-06-22 12:08:14.124115
11	8	5	6	Shinshō-in (信証院) Rennyo (蓮如)	2	1	3	2025-06-22 12:42:26.009728
13	8	27	7	\N	4	1	1	2025-06-22 12:42:26.020118
14	6	23	\N	\N	5	1	1	2025-06-22 12:42:26.024093
16	8	11	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	2	1	1	2025-06-22 12:42:26.031882
17	1	11	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	2	1	1	2025-06-22 12:42:26.035532
18	6	11	\N	\N	2	1	1	2025-06-22 12:42:26.039233
19	5	6	\N	\N	10	1	1	2025-06-22 12:42:26.042219
21	5	7	\N	\N	10	1	1	2025-06-22 12:42:26.04703
22	5	8	2	Yoshiteru (義輝) Ashikaga (足利)	10	1	1	2025-06-22 12:42:26.049873
23	6	25	\N	\N	6	1	1	2025-06-22 12:42:26.053602
77	4	15	8	Murai Wako (和光)	9	3	3	2025-06-22 14:46:42.669085
61	6	12	\N	\N	4	2	3	2025-06-22 13:35:57.20343
80	1	24	6	Shinshō-in (信証院) Rennyo (蓮如)	4	3	3	2025-06-22 14:46:42.711499
67	7	32	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	1	2	2	2025-06-22 13:35:57.237954
79	1	32	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	4	3	3	2025-06-22 14:46:42.69528
27	2	22	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	10	1	1	2025-06-22 12:42:26.086426
28	8	24	\N	\N	4	1	1	2025-06-22 12:42:26.093284
68	7	17	6	\N	1	2	2	2025-06-22 13:35:57.24373
26	7	18	\N	\N	6	1	1	2025-06-22 12:42:26.081791
33	4	18	\N	\N	6	1	1	2025-06-22 12:42:26.32407
36	8	29	6	Shinshō-in (信証院) Rennyo (蓮如)	6	2	2	2025-06-22 13:35:57.052739
38	8	25	4	\N	6	2	2	2025-06-22 13:35:57.057254
40	4	8	2	Yoshiteru (義輝) Ashikaga (足利)	10	2	2	2025-06-22 13:35:57.067602
41	4	7	\N	\N	10	2	2	2025-06-22 13:35:57.070496
42	4	20	8	Murai Wako (和光)	10	2	2	2025-06-22 13:35:57.072301
43	4	6	2	\N	10	2	2	2025-06-22 13:35:57.075381
44	5	35	6	Shinshō-in (信証院) Rennyo (蓮如)	3	2	2	2025-06-22 13:35:57.078279
45	5	2	1	妖怪 de Shikoku (四国)	3	2	2	2025-06-22 13:35:57.081119
46	5	29	6	Shinshō-in (信証院) Rennyo (蓮如)	6	2	2	2025-06-22 13:35:57.088413
47	5	19	7	Daïmyo Tadaoki (忠興) Hosokawa (細川)	6	2	2	2025-06-22 13:35:57.091809
48	5	25	4	La Régence Chōsokabe (長宗我部)	6	2	2	2025-06-22 13:35:57.094953
49	5	36	8	Murai Wako (和光)	9	2	3	2025-06-22 13:35:57.097652
50	1	37	6	Shinshō-in (信証院) Rennyo (蓮如)	2	2	3	2025-06-22 13:35:57.100577
1	1	5	6	\N	2	0	3	2025-06-22 12:08:14.117671
56	1	30	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	8	2	2	2025-06-22 13:35:57.128635
5	6	1	1	\N	2	0	2	2025-06-22 12:08:14.14279
69	6	2	\N	\N	3	2	2	2025-06-22 13:35:57.247838
73	5	31	4	La Régence Chōsokabe (長宗我部)	4	2	3	2025-06-22 13:35:57.285446
29	7	26	\N	\N	7	1	3	2025-06-22 12:42:26.096664
4	1	15	8	Murai Wako (和光)	9	0	2	2025-06-22 12:08:14.136972
57	1	21	8	Murai Wako (和光)	9	2	2	2025-06-22 13:35:57.140062
66	7	31	\N	\N	4	2	2	2025-06-22 13:35:57.233191
59	8	8	2	\N	10	2	2	2025-06-22 13:35:57.152963
7	6	13	7	Daïmyo Tadaoki (忠興) Hosokawa (細川)	7	0	3	2025-06-22 12:08:14.150053
25	6	28	7	Daïmyo Tadaoki (忠興) Hosokawa (細川)	7	1	3	2025-06-22 12:42:26.078685
74	8	31	\N	\N	4	2	3	2025-06-22 13:35:57.545712
58	2	20	8	Murai Wako (和光)	10	2	2	2025-06-22 13:35:57.146182
30	6	26	4	La Régence Chōsokabe (長宗我部)	7	1	3	2025-06-22 12:42:26.101609
85	7	10	\N	\N	7	3	3	2025-06-22 14:46:42.801668
31	7	29	6	Shinshō-in (信証院) Rennyo (蓮如)	6	1	2	2025-06-22 12:42:26.110555
24	7	25	4	\N	6	1	2	2025-06-22 12:42:26.056218
12	6	27	\N	\N	4	1	2	2025-06-22 12:42:26.017785
70	5	12	8	Murai Wako (和光)	4	2	2	2025-06-22 13:35:57.271263
52	6	33	8	Murai Wako (和光)	2	2	2	2025-06-22 13:35:57.109622
64	7	12	\N	\N	4	2	2	2025-06-22 13:35:57.227449
65	7	24	\N	\N	4	2	2	2025-06-22 13:35:57.230078
71	5	27	7	Daïmyo Tadaoki (忠興) Hosokawa (細川)	4	2	2	2025-06-22 13:35:57.276237
72	5	24	6	Shinshō-in (信証院) Rennyo (蓮如)	4	2	2	2025-06-22 13:35:57.28078
39	6	32	5	\N	4	2	3	2025-06-22 13:35:57.06132
54	4	32	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	4	2	3	2025-06-22 13:35:57.120268
34	4	28	7	Daïmyo Tadaoki (忠興) Hosokawa (細川)	7	1	3	2025-06-22 12:42:26.343333
35	5	28	\N	\N	7	1	2	2025-06-22 12:42:26.347211
75	4	45	\N	\N	4	3	3	2025-06-22 14:46:42.603412
76	8	45	\N	\N	4	3	3	2025-06-22 14:46:42.617983
3	1	12	8	Murai Wako (和光)	4	0	3	2025-06-22 12:08:14.131896
81	1	40	4	La Régence Chōsokabe (長宗我部)	4	3	3	2025-06-22 14:46:42.717745
83	2	42	7	Daïmyo Tadaoki (忠興) Hosokawa (細川)	10	3	3	2025-06-22 14:46:42.736015
84	2	38	7	\N	10	3	3	2025-06-22 14:46:42.743544
60	2	34	4	\N	10	2	3	2025-06-22 13:35:57.181719
86	6	3	\N	\N	3	3	3	2025-06-22 14:46:42.810584
62	6	31	\N	\N	4	2	3	2025-06-22 13:35:57.208958
87	6	46	1	妖怪 de Shikoku (四国)	3	3	3	2025-06-22 14:46:42.819941
88	6	39	8	Murai Wako (和光)	3	3	3	2025-06-22 14:46:42.82765
55	4	17	6	Shinshō-in (信証院) Rennyo (蓮如)	1	2	3	2025-06-22 13:35:57.123659
89	6	40	4	La Régence Chōsokabe (長宗我部)	4	3	3	2025-06-22 14:46:42.860003
90	8	26	4	\N	7	3	3	2025-06-22 14:46:42.866374
91	8	28	7	Daïmyo Tadaoki (忠興) Hosokawa (細川)	7	3	3	2025-06-22 14:46:42.874314
37	8	19	\N	\N	7	2	3	2025-06-22 13:35:57.055544
15	8	1	\N	\N	2	1	3	2025-06-22 12:42:26.026408
20	5	17	6	Shinshō-in (信証院) Rennyo (蓮如)	1	1	3	2025-06-22 12:42:26.044653
82	1	39	8	Murai Wako (和光)	3	3	3	2025-06-22 14:46:42.730181
63	4	13	7	\N	7	2	3	2025-06-22 13:35:57.214103
92	8	13	7	Daïmyo Tadaoki (忠興) Hosokawa (細川)	7	3	3	2025-06-22 14:46:42.890557
93	8	10	6	\N	7	3	3	2025-06-22 14:46:42.897515
94	8	17	6	Shinshō-in (信証院) Rennyo (蓮如)	1	3	3	2025-06-22 14:46:42.903814
95	8	14	4	La Régence Chōsokabe (長宗我部)	1	3	3	2025-06-22 14:46:42.914346
96	8	41	4	La Régence Chōsokabe (長宗我部)	9	3	3	2025-06-22 14:46:42.927239
97	5	15	8	Murai Wako (和光)	9	3	3	2025-06-22 14:46:42.940006
98	5	41	4	La Régence Chōsokabe (長宗我部)	9	3	3	2025-06-22 14:46:42.947271
99	5	21	\N	\N	1	3	3	2025-06-22 14:46:42.959991
100	5	14	4	La Régence Chōsokabe (長宗我部)	1	3	3	2025-06-22 14:46:42.965697
101	8	40	4	La Régence Chōsokabe (長宗我部)	4	3	3	2025-06-22 14:46:42.979564
102	8	37	6	Shinshō-in (信証院) Rennyo (蓮如)	2	3	3	2025-06-22 14:46:42.99345
103	6	44	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	6	3	3	2025-06-22 14:46:43.004721
104	1	35	\N	\N	3	3	3	2025-06-22 14:46:43.057968
105	4	44	5	Daïmyo Nagayoshi (長慶) Miyoshi (三好)	6	3	3	2025-06-22 14:46:43.121276
106	7	7	\N	\N	10	3	3	2025-06-22 14:46:43.129143
107	7	6	2	\N	10	3	3	2025-06-22 14:46:43.133638
108	7	8	2	Yoshiteru (義輝) Ashikaga (足利)	10	3	3	2025-06-22 14:46:43.144196
109	4	10	\N	\N	7	3	3	2025-06-22 14:46:43.166325
51	1	33	8	Murai Wako (和光)	2	2	3	2025-06-22 13:35:57.103772
110	8	18	\N	\N	3	3	3	2025-06-22 14:46:43.648083
111	8	32	\N	\N	4	3	3	2025-06-22 14:46:43.66827
78	1	31	4	\N	4	3	3	2025-06-22 14:46:42.686846
\.


--
-- Data for Name: faction_powers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.faction_powers (id, faction_id, link_power_type_id) FROM stdin;
1	1	9
2	1	10
3	1	11
4	2	12
5	2	13
6	2	14
7	8	12
8	8	13
9	8	14
10	3	15
11	3	16
12	3	17
13	4	15
14	4	16
15	4	17
16	6	18
17	6	19
18	6	20
19	5	21
20	5	22
21	5	23
22	7	24
23	7	25
24	7	26
25	9	27
26	9	28
27	9	29
\.


--
-- Data for Name: factions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.factions (id, name) FROM stdin;
1	Samouraï Chōsokabe
2	Samouraï Miyoshi
3	Samouraï Hosokawa
4	Samouraï Ashikaga
5	Moines Bouddhistes
6	Ikkō-ikki
7	Kaizokushū
8	Chrétiens
9	Yōkai
\.


--
-- Data for Name: link_power_type; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.link_power_type (id, power_type_id, power_id) FROM stdin;
1	4	1
2	4	2
3	4	3
4	4	4
5	3	5
6	3	6
7	3	7
8	3	8
9	3	9
10	3	10
11	3	11
12	3	12
13	3	13
14	3	14
15	3	15
16	3	16
17	3	17
18	3	18
19	3	19
20	3	20
21	3	21
22	3	22
23	3	23
24	3	24
25	3	25
26	3	26
27	3	27
28	3	28
29	3	29
30	1	30
31	1	31
32	1	32
33	1	33
34	1	34
35	1	35
36	1	36
37	1	37
38	1	38
39	1	39
40	1	40
41	1	41
42	1	42
43	1	43
44	1	44
45	1	45
46	1	46
47	1	47
48	1	48
49	1	49
50	1	50
51	1	51
52	1	52
53	1	53
54	1	54
55	1	55
56	1	56
57	1	57
58	1	58
59	1	59
60	1	60
61	1	61
62	1	62
63	1	63
64	1	64
65	1	65
66	1	66
67	1	67
68	1	68
69	1	69
70	1	70
71	1	71
72	1	72
73	1	73
74	1	74
75	1	75
76	1	76
77	2	77
78	2	78
79	2	79
80	2	80
81	2	81
82	2	82
83	2	83
84	2	84
85	2	85
86	2	86
87	2	87
88	2	88
89	2	89
90	2	90
91	2	91
92	2	92
93	2	93
94	2	94
95	2	95
96	2	96
97	2	97
98	2	98
99	2	99
100	2	100
101	2	101
102	2	102
103	2	103
104	2	104
105	2	105
106	2	106
107	2	107
108	2	108
109	2	109
110	2	110
111	2	111
112	2	112
113	2	113
114	2	114
115	2	115
116	2	116
117	2	117
118	2	118
119	2	119
120	2	120
121	2	121
122	2	122
\.


--
-- Data for Name: location_attack_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.location_attack_logs (id, target_controller_id, attacker_id, attack_val, defence_val, turn, success, target_result_text, attacker_result_text, created_at) FROM stdin;
1	3	6	3	6	3	f	Notre Yashima-ji (屋島寺) -- Le chemin du Nirvana as été attaquer, par des agents du réseau 6. Heureusement, ils ne semblent pas avoir atteint leur objectif.	Le lieu Yashima-ji (屋島寺) -- Le chemin du Nirvana n’a pas été détruit, nos excuses	2025-06-22 14:37:52.368779
2	4	5	5	8	3	f	Notre Forteresse des Samouraï Chōsokabe as été attaquer, par des agents du réseau 5. Heureusement, ils ne semblent pas avoir atteint leur objectif.	Le lieu Forteresse des Samouraï Chōsokabe n’a pas été détruit, nos excuses	2025-06-22 14:44:29.593259
3	3	5	5	6	3	f	Notre Chikurin-ji (竹林寺) -- Le chemin de l’ascèse as été attaquer, par des agents du réseau 5. Heureusement, ils ne semblent pas avoir atteint leur objectif.	Le lieu Chikurin-ji (竹林寺) -- Le chemin de l’ascèse n’a pas été détruit, nos excuses	2025-06-22 14:44:55.022569
\.


--
-- Data for Name: locations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.locations (id, name, description, zone_id, setup_turn, discovery_diff, controller_id, can_be_destroyed, is_base, activate_json) FROM stdin;
1	Plaine d’Uwajima	Les vastes plaines d’Uwajima semblent paisibles sous le soleil, entre cultures clairsemées et sentiers oubliés.\r\n        Mais depuis plusieurs semaines, des groupes d’hommes en haillons, armés de fourches, de bâtons ou de sabres grossiers, y ont été aperçus.\r\n        Ces paysans ne sont pas d’ici : ils avancent discrètement, se regroupent à la tombée du jour, et prêchent un discours de révolte contre les samouraïs.\r\n        Ce sont les avant-gardes des Ikko-ikki, infiltrés depuis le continent par voie maritime.\r\n        Découvrir quel est le chef qui les unis pourrait permettre d’agir avant qu’il ne soit trop tard.	1	0	8	6	f	f	{}
2	Port de Matsuyama	Le port de Matsuyama est d’ordinaire animé par les pêcheurs locaux et les petits marchands.\r\n        Mais depuis peu, les anciens disent avoir vu, au crépuscule, un navire étrange accoster sans bannière, escorté par des pirates tatoués.\r\n        Un moine en est descendu, maigre, vieux, au regard brûlant de ferveur : Rennyo lui-même, leader spirituel des Ikko-ikki.\r\n        Selon certains, il s’est enfoncé dans les montagnes d’Ehime avec une poignée de fidèles.\r\n        Ce secret, s’il venait à être révélé, pourrait changer l’équilibre religieux de toute l’île.	2	0	8	8	f	f	{}
3	Port de Tokushima	Dans les ruelles du port de Tokushima, à l’écart des marchés, une maison basse aux volets clos abrite un hôte peu commun : Luís Fróis, prêtre jésuite portugais, érudit des mœurs japonaises.\r\n        Il y aurait établi un sanctuaire clandestin, enseignant les paroles du Christ à quelques convertis du clan Miyoshi.\r\n        Ce lieu sert également de relais discret pour faire entrer armes, livres et messagers depuis Nagasaki.\r\n        Sa présence confirme l’implantation secrète du christianisme à Tokushima, et menace de faire basculer les équilibres religieux et politiques de Shikoku.	6	0	8	5	f	f	{}
4	Post relai du courrier de Kagawa	Une auberge modeste près de la grande route de Kagawa reçoit parfois, à l’aube, des cavaliers fatigués portant des missives cachetées.\r\n        L’une d’elles, récemment interceptée, contenait une promesse de mariage scellée entre Motochika Chōsokabe et Tama Hosokawa, fille de Fujitaka.\r\n        Si elle venait à se concrétiser, cette alliance unirait deux grandes maisons sur Shikoku et changerait les rapports de pouvoir de toute la région.\r\n        Pour l’instant, l’information est gardée secrète, mais les rumeurs montent.	7	0	8	\N	f	f	{}
5	Camp de deserteurs	Dans les bois humides d’Awaji, un vieux temple en ruines abrite depuis peu des hommes au regard hanté et aux vêtements déchirés : des déserteurs de la bataille d’Ishizuchi.\r\n        Ils murmurent une autre version des faits : Kunichika Chōsokabe n’a pas fui par lâcheté, mais son armée as été défaite par la prise en tenaille organisé par les Ikko-ikki alliées aux Takedas.\r\n        Ses actions ont été étouffé par ses rivaux et par la honte des survivants. Si ce témoignage était rendu public, l’honneur du clan Chōsokabe pourrait être réhabilité.	8	0	8	\N	f	f	{}
6	Camp de deserteurs	Dans une gorge dissimulée parmi les pins tordus de Shōdoshima, quelques hommes efflanqués vivent en silence, fuyant le regard des pêcheurs et des samouraïs.\r\n            Ce sont des survivants de la déroute d’Ishizuchi, dont ils racontent une version bien différente de celle propagée à la cour : l’avant-garde des Chōsokabe, commandée par Fujitaka Hosokawa, se serait retrouvée face aux fanatiques Ikko-ikki, qui auraient écrasé ses lignes avant même que l’ordre de retraite ne puisse être donné.\r\n            Fujitaka, séparé de la force principale, aurait fui précipitamment vers Kyoto, mais aurait été aperçu capturé par un général des forces du shogun Ashikaga. Ces aveux, étouffés sous le fracas des récits officiels, pourraient bien réhabiliter l’honneur du daimyō déchu — ou bouleverser les équilibres fragiles entre les clans.	9	0	8	\N	f	f	{}
7	Cour impériale	Au sein des couloirs feutrés de la cour impériale, on ne parle plus qu’à demi-mot des récents affrontements.\r\n        Le nom des Chōsokabe y est devenu tabou, soufflé avec mépris : leur armée, jadis fière, aurait fui sans gloire devant l’avant-garde Takeda.\r\n        Le Shogun Ashikaga, humilié par leur débâcle, aurait juré de ne plus leur accorder confiance ni territoire.\r\n        Ce ressentiment pourrait être exploité — ou au contraire, désamorcé — selon les preuves et récits qu’on parvient à faire émerger de l’ombre.	10	0	8	\N	f	f	{}
8	Geôles impériales	Sous les fondations de la Cité impériale, ces geôles étouffantes résonnent des cris étouffés des oubliés du Shogun. \r\n        L’air y est moite, chargé de remords et d’encre séchée — là où les sentences furent calligraphiées avant d’être exécutées.\r\n        Peu en ressortent, et ceux qui le font ne parlent plus.	10	0	10	2	t	f	{"indestructible" : "TRUE"}
9	Geôles des Kaizokushū	Creusées dans la falaise même, ces cavernes humides servent de prison aux captifs des Wako. \r\n        Des chaînes rouillées pendent aux murs, et l’eau salée suinte sans cesse, rongeant la volonté des enfermés. \r\n        Le silence n’y est troublé que par les pas des geôliers — ou les rires des pirates.	9	0	10	8	t	f	{"indestructible" : "TRUE"}
10	Vieux temple	Accroché aux flancs escarpés de la côte sud de Kōchi, un petit sanctuaire noircit repose au bord d’une ancienne veine de fer oubliée.\r\n        Au loin, dans la vallée, les marteaux des forgerons résonnent comme une prière sourde.\r\n        Mais chaque nuit, une odeur de poudre flotte dans l’air, et un claquement sec — sec comme un tir — fait sursauter les corbeaux.\r\n        (Pour explorer davantage ce lieu, allez voir un orga !)	3	0	9	1	t	f	{}
11	Vieux temple	Perché au sommet d’une falaise d’Awaji, un petit pavillon de bois battu par les vents se dresse, fragile et silencieux.\r\n        La porte ne ferme plus, et le papier des lanternes s’effiloche. Pourtant, nul grain de poussière ne s’y pose.\r\n        Lorsque l’on entre, l’air se fait soudain glacé, et un bruissement court dans les chevrons — comme si un éventail invisible fendait l’air avec colère.\r\n        (Pour explorer davantage ce lieu, allez voir un orga !)	8	0	9	1	t	f	{}
12	Vieux temple	Ce temple oublié, dissimulé dans un vallon brumeux de Shōdoshima, semble abandonné depuis des décennies.\r\n         Pourtant, chaque crépuscule, les accords las d’un biwa résonnent sous les poutres vermoulues, portés par une brise douce où flotte un parfum de saké tiède.\r\n         Pourtant nul prêtre et nul pèlerin en vue.\r\n        (Pour explorer davantage ce lieu, allez voir un orga !)	9	0	9	1	t	f	{}
13	Vieux temple	Perché sur un piton rocheux des montagnes d’Ehimé, un ancien temple taillé à même la pierre repose, figé comme un souvenir.\r\n        Nul vent n’y souffle, nul oiseau n’y niche.\r\n        Parfois, on y entend cliqueter une chaîne sur la pierre nue, comme si une arme traînait seule sur le sol.\r\n        (Pour explorer davantage ce lieu, allez voir un orga !)	2	0	9	1	t	f	{}
14	Vallée fertile d’Oboké	Dans la vallée profonde d’Oboké, où le bruit de la rivière est permanent, poussent à flanc de roche de rares théiers.\r\n    Leurs feuilles, amères et puissantes, sont cueillies à la main par les familles montagnardes, suspendues au-dessus du grondement des eaux.\r\n    Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare.	5	0	7	\N	f	f	{}
15	Mine de fer de Kubokawa	Dans les profondeurs du cap sud de Kōchi, des veines de fer noir sont extraites à la force des bras puis forgées en cuirasses robustes dans les forges voisines.\r\n    Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare.	3	0	7	\N	f	f	{}
16	Écuries de Kagawa	Les vastes pâturages de Kagawa forment l’écrin idéal pour l’élevage de chevaux endurants, prisés tant pour la guerre que pour les grandes caravanes.\r\n    Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare.	7	0	7	\N	f	f	{}
17	Port marchand d’Uwajima	Des voiliers venus de la péninsule coréenne accostent à Uwajima, chargés de résines rares dont les parfums servent aux temples autant qu’aux intrigues.\r\n    Contrôler ce territoire nous permettrait d’avoir accès à cette ressource rare.	1	0	7	\N	f	f	{}
18	Port d’Uwajima	Un port animé aux quais denses et bruyants, où s’échangent riz, bois, et rumeurs en provenance de Kyūshū comme de Corée.\r\n     Les marins disent que la brume y reste plus longtemps qu’ailleurs.	1	0	6	\N	f	f	{}
19	Mt Ishizuchi	Plus haut sommet de l’île, le mont Ishizuchi domine les vallées alentour comme un sabre dressé vers le ciel.\r\n     On dit qu’un pèlerinage ancien y conduit à une dalle sacrée où les esprits s’expriment lorsque les vents tournent.	2	0	6	\N	f	f	{}
20	Port de Kochi	Protégé par une anse naturelle, ce port militaire et marchand voit passer jonques, bateaux de guerre et pirates repenti.\r\n      Son arsenal est surveillé nuit et jour par des ashigaru en armure sombre.\r\n      On dit que le clan Chōsokabe y cache des objects illegaux importé d’ailleurs.	4	0	6	\N	f	f	{}
21	Ikeda	Petit village de montagne aux maisons de bois noircies par le temps.\r\n     Les voyageurs s’y arrêtent pour goûter un saké réputé, brassé à l’eau des gorges profondes qui serpentent en contrebas.	5	0	6	\N	f	f	{}
22	Port de Tokushima	Carrefour maritime entre Honshū et Shikoku, le port de Tokushima bruisse de dialectes et de voiles étrangères.\r\n     Dans les ruelles proches du marché, on parle parfois espagnol, ou latin, à voix basse.	6	0	6	\N	f	f	{}
23	Grande route et relais de poste	Relie Tokushima à Kōchi en serpentant à travers les plaines fertiles du nord.\r\n     À chaque relais, les montures peuvent être changées, et les messagers impériaux y trouvent toujours une couche et un bol chaud.	7	0	6	\N	f	f	{}
24	Rumeur de la bataille	Les pêcheurs d’Awaji parlent encore d’un combat féroce dans les collines, entre troupes en fuite et rebelles aux visages peints. Certains affirment avoir vu le ciel s’embraser au-dessus du temple abandonné.	8	0	6	\N	f	f	{}
25	Détroit d’Okayama	Étroit et venteux, ce détroit aux eaux traîtresses sépare Shikoku de Honshū.\r\n     Difficile de tenter cette traversée sans être épié par les habitants de l’île de Shōdoshima.\r\n     Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d’être intercepté par les Kaizokushū.	9	0	6	\N	f	f	{}
26	Suzaku Mon	Grande artère pavée de la capitale impériale, menant tout droit au palais. Sous ses tuiles rouges, l’ombre des complots se mêle aux parfums de thé, et les bannières flottent dans un silence cérémoniel.	10	0	6	\N	f	f	{}
27	Maison close de Marugame	À Marugame, dans une maison close réputée pour son saké sucré et ses éventails peints à la main, des courtisanes murmurent entre deux chansons.\r\n        L’une d’elles prétend avoir lu une lettre scellée, confiée par un émissaire enivré, annonçant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre éclair contre les Chōsokabe.	7	0	5	\N	f	f	{}
28	Crique de Funakoshi	Cette crique isolée, souvent balayée par les vents, est connue des contrebandiers comme des pêcheurs.\r\n        Depuis quelques jours, un bruit court : un important émissaire impérial aurait été intercepté par les pirates Wako et détenu dans une grotte voisine, en attendant rançon ou silence.	9	0	5	\N	f	f	{}
29	Sanctuaire des Pins Brûlés	Dans un ancien sanctuaire shintō dont les piliers carbonisés résistent au temps, des pèlerins affirment avoir vu un artefact étrange caché sous l’autel — une croix d’argent sertie d’inscriptions latines.\r\n        Les paysans parlent d’un prêtre chrétien, et de l’Inquisition jésuite elle-même. Mais les recherches menées par les yamabushi locaux n’ont rien révélé de probant.	2	0	5	\N	f	f	{}
30	Maison de thé "Lune d’Or"	Située à l’écart de Suzaku Mon, la "Lune d’Or" attire les lettrés, les poètes… et les oreilles curieuses.\r\n        On dit qu’un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\r\n        Selon une geisha, il serait en réalité un espion du clan Takeda, infiltré pour sonder la loyauté des daimyōs de l’est.\r\n        Il aurait même été vue avec un membre de la famille Chōsokabe.\r\n        Pourtant, nul ne peut confirmer son cette histoire, et certains prétendent qu’il n’est en réalité qu’un veuf mélancolique, égaré dans ses souvenirs.\r\n        Mais à Kyōto, les apparences mentent plus souvent qu’elles ne révèlent.	10	0	5	\N	f	f	{}
31	Phare abandonné de Minokoshi	Disséminé au bout d’une presqu’île battue par les vents, le vieux phare de Minokoshi n’est plus qu’un squelette de pierre rongé par le sel.\r\n        Pourtant, certains pêcheurs affirment y voir passer des silhouettes armées à la tombée de la nuit.\r\n        La rumeur court qu’un prisonnier de valeur y est gardé en secret par le clan Chōsokabe, un traitre capturé lors des affrontements récents.	8	0	6	\N	f	f	{}
32	Sanctuaire brisé de Hiwasa	Surplombant la mer, les ruines du sanctuaire de Hiwasa sont battues par les embruns.\r\n        On dit que des prêtres étrangers y ont été vus de nuit, en compagnie d’émissaires du clan Chōsokabe.\r\n        La rumeur parle d’un pacte impie : en échange d’armes à feu venues de Nagasaki, le clan accepterait d’abriter des convertis clandestins.	4	0	8	\N	f	f	{}
33	Comptoir de Kashiwa	Ce modeste comptoir marchand, adossé à une crique discrète, connaît une activité étrange depuis quelques semaines.\r\n        Des jonques aux voiles noires y accostent en silence, et les capitaines refusent de dire d’où ils viennent.\r\n        Certains affirment que les Wako y auraient reçu des fonds d’un clan du nord — peut-être les Hosokawa — pour saboter les entrepôts du port de Tokushima.\r\n        D’autres y voient simplement un commerce de sel et de fer… mais pourquoi alors tant de discrétion, et autant de lames prêtes à jaillir à la moindre question ?	6	0	8	\N	f	f	{}
34	Dainichi-ji (大日寺) -- Le chemin de l’éveil	Niché entre les forêts brumeuses d’Iya, ce temple vibre encore du souffle ancien des premiers pas du pèlerin. \r\n    On dit que les pierres du sentier y murmurent des prières oubliées à ceux qui s’y attardent. \r\n    Le silence y est si pur qu’on entend le battement de son propre cœur.\r\n    (Pour explorer davantage ce lieu, allez voir un orga !)	5	0	7	3	t	f	{}
35	Chikurin-ji (竹林寺) -- Le chemin de l’ascèse	Perché au sommet d’une colline surplombant la baie, le temple veille parmi les bambous. \r\n    Les moines y pratiquent une ascèse rigoureuse, veillant jour et nuit face à l’océan sans fin. \r\n    Le vent porte leurs chants jusqu’aux barques des pêcheurs, comme des prières salées.\r\n    (Pour explorer davantage ce lieu, allez voir un orga !)	4	0	7	3	t	f	{}
36	Ryūkō-ji (竜光寺) -- Le chemin de l’illumination	Suspendu à flanc de montagne, Ryūkō-ji contemple la mer intérieure comme un dragon endormi. \r\n    On raconte qu’au lever du soleil, les brumes se déchirent et révèlent un éclat doré émanant de l’autel. \r\n    Les sages disent que ceux qui y méditent peuvent entrevoir la lumière véritable.\r\n    (Pour explorer davantage ce lieu, allez voir un orga !)	1	0	7	3	t	f	{}
37	Yashima-ji (屋島寺) -- Le chemin du Nirvana	Ancien bastion surplombant les flots, Yashima-ji garde la mémoire des batailles et des ermites. \r\n    Les brumes de l’aube y voilent statues et stupas, comme pour dissimuler les mystères du Nirvana. \r\n    Certains pèlerins affirment y avoir senti l’oubli du monde descendre sur eux comme une paix.\r\n    (Pour explorer davantage ce lieu, allez voir un orga !)	7	0	7	3	t	f	{}
41	Forteresse des Samouraï Chōsokabe	\r\n        Nous avons trouvé la forteresse de La Régence Chōsokabe (長宗我部) des Samouraï Chōsokabe. Les serviteurs de confiance leur manquent encore pour avoir des défenses solides.\r\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\r\n        L’attaque causerait certainement quelques questions à la cour du Shogun, mais un joueur affaibli sur l’échiquier politique est toujours bénéfique.\r\n        Nous ne devons pas tarder à prendre notre décision, ses défenses se renforcent chaque trimestre.\r\n    	4	0	11	4	t	t	{}
39	Forteresse des Samouraï Miyoshi	\r\n        Nous avons trouvé la forteresse de Daïmyo Nagayoshi (長慶) Miyoshi (三好) des Samouraï Miyoshi. Les serviteurs de confiance leur manquent encore pour avoir des défenses solides.\r\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\r\n        L’attaque causerait certainement quelques questions à la cour du Shogun, mais un joueur affaibli sur l’échiquier politique est toujours bénéfique.\r\n        Nous ne devons pas tarder à prendre notre décision, ses défenses se renforcent chaque trimestre.\r\n    \r\n       Il nous apparait en fouillant le lieu que ce quelqu’un s’est donné beaucoup de mal pour que cette forteresse donne l’impression d’être liée aux Samouraï Miyoshi, mais en réalité son propriétaire est des Chrétiens.\r\n    	6	0	9	5	t	t	{}
38	Forteresse des Kaizokushū	\r\n        Nous avons trouvé la forteresse de Murai Wako (和光) des Kaizokushū. Les serviteurs de confiance leur manquent encore pour avoir des défenses solides.\r\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\r\n        L’attaque causerait certainement quelques questions à la cour du Shogun, mais un joueur affaibli sur l’échiquier politique est toujours bénéfique.\r\n        Nous ne devons pas tarder à prendre notre décision, ses défenses se renforcent chaque trimestre.\r\n    	2	2	7	8	t	t	{}
42	Forteresse des Moines Bouddhistes	\r\n        Nous avons trouvé la forteresse de Shinshō-in (信証院) Rennyo (蓮如) des Moines Bouddhistes. Les serviteurs de confiance leur manquent encore pour avoir des défenses solides.\r\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\r\n        L’attaque causerait certainement quelques questions à la cour du Shogun, mais un joueur affaibli sur l’échiquier politique est toujours bénéfique.\r\n        Nous ne devons pas tarder à prendre notre décision, ses défenses se renforcent chaque trimestre.\r\n    \r\n       Il nous apparait en fouillant le lieu que ce quelqu’un s’est donné beaucoup de mal pour que cette forteresse donne l’impression d’être liée aux Moines Bouddhistes, mais en réalité son propriétaire est des Ikkō-ikki.\r\n    	5	0	8	6	t	t	{}
43	Forteresse des Moines Bouddhistes	\r\n        Nous avons trouvé la forteresse de 妖怪 de Shikoku (四国) des Moines Bouddhistes. Les serviteurs de confiance leur manquent encore pour avoir des défenses solides.\r\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\r\n        L’attaque causerait certainement quelques questions à la cour du Shogun, mais un joueur affaibli sur l’échiquier politique est toujours bénéfique.\r\n        Nous ne devons pas tarder à prendre notre décision, ses défenses se renforcent chaque trimestre.\r\n    \r\n       Il nous apparait en fouillant le lieu que ce quelqu’un s’est donné beaucoup de mal pour que cette forteresse donne l’impression d’être liée aux Moines Bouddhistes, mais en réalité son propriétaire est des Yōkai.\r\n    	2	0	9	1	t	t	{}
40	Forteresse des Samouraï Hosokawa	\r\n        Nous avons trouvé la forteresse de Daïmyo Tadaoki (忠興) Hosokawa (細川) des Samouraï Hosokawa. Les serviteurs de confiance leur manquent encore pour avoir des défenses solides.\r\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\r\n        L’attaque causerait certainement quelques questions à la cour du Shogun, mais un joueur affaibli sur l’échiquier politique est toujours bénéfique.\r\n        Nous ne devons pas tarder à prendre notre décision, ses défenses se renforcent chaque trimestre.\r\n    	7	0	12	7	t	t	{}
\.


--
-- Data for Name: mechanics; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.mechanics (id, turncounter, gamestate) FROM stdin;
1	4	1
\.


--
-- Data for Name: player_controller; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.player_controller (controller_id, player_id) FROM stdin;
1	1
2	1
3	1
4	1
5	1
6	1
7	1
8	1
1	2
4	3
5	4
6	5
7	6
8	7
3	8
2	9
\.


--
-- Data for Name: players; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.players (id, username, passwd, is_privileged) FROM stdin;
1	gm	orga	t
2	player0	zero	f
3	player1	one	f
4	player2	two	f
5	player3	three	f
6	player4	four	f
7	player5	five	f
8	player6	six	f
9	player7	seven	f
\.


--
-- Data for Name: power_types; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.power_types (id, name, description) FROM stdin;
1	Hobby	Objet fétiche
2	Metier	Rôle
3	Discipline	Maitrise des Arts
4	Transformation	Equipements Rares
\.


--
-- Data for Name: powers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.powers (id, name, description, enquete, attack, defence, other) FROM stdin;
1	Cheval Kagawa	\N	0	1	1	{"hidden" : "0", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Prefecture de Kagawa", "worker_in_zone": "Prefecture de Kagawa" } }
2	Armure en fer de Kochi	\N	0	1	1	{"hidden" : "0", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Cap sud de Kochi", "worker_in_zone": "Cap sud de Kochi"  } }
3	Thé d’Oboké et d’Iya	\N	1	0	0	{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Vallées d’Iya et d’Oboké de Tokushima", "worker_in_zone": "Vallées d’Iya et d’Oboké de Tokushima" } }
4	Encens Coréen	\N	1	0	0	{"hidden" : "1", "on_recrutment": "FALSE", "on_transformation": {"worker_is_alive": "1", "controller_has_zone": "Côte Ouest d’Ehime", "worker_in_zone": "Côte Ouest d’Ehime"} }
5	Sōjutsu (槍術) – Art de la lance (Yari)	, l’utilisation du yari à pied ou à cheval	0	1	1	{}
6	Kyūjutsu (弓術) – Art du tir à l’arc	, ancien kyūdō)	0	2	0	{}
7	Shodō (書道) – Calligraphie	, le maniement du pinceau, reflet de l’esprit	1	1	0	{}
8	Kadō / Ikebana (華道 / 生け花) – Art floral	, pratiqué pour l’harmonie intérieure	1	0	1	{}
9	Kenjutsu (剣術) – Art du sabre	, la pratique du katana en combat	0	2	1	{}
10	Heihō (兵法) – Stratégie militaire	, l’étude de la tactique, souvent influencée par les textes chinois comme le Sun Tzu	1	1	1	{}
11	Waka (和歌) – Poésie classique	, plus ancienne que le haïku, utilisée dans les échanges lettrés et parfois politiques	2	0	0	{}
12	Hōjutsu (砲術) – Art des armes à feu (teppō)	, développé après l’introduction des mousquets portugais vers 1543	-1	3	2	{}
13	Bajutsu (馬術) – Art de l’équitation militaire	, inclut la cavalerie et le tir à l’arc monté	1	1	1	{}
14	Gagaku (雅楽) – Musique de cour	, peu courante chez les samouraïs de terrain, mais appréciée dans les cercles aristocratiques ou les familles cultivées	2	0	0	{}
15	Iaijutsu (居合術) – Art de dégainer et frapper en un mouvement		0	2	1	{}
16	Bugaku (舞楽) – Danse de cour	, parfois pratiquée dans le cadre de cérémonies religieuses ou impériales	1	1	1	{}
17	Chadō (茶道) – Voie du thé	, cérémonie du thé comme forme de discipline spirituelle et esthétique	2	-1	1	{}
18	Jūjutsu (柔術) – Techniques de lutte à mains nues	, techniques de projection, immobilisation, étranglement ou désarmement	0	1	2	{}
19	Ninjutsu (忍術) – Techniques d’espionnage et de guérilla	moins honorable, mais parfois utilisé par certains samouraïs ou leurs agents	2	1	-1	{}
20	Reiki / Kujikiri (霊気 / 九字切り) – Pratiques ésotériques		1	0	2	{}
21	Yawara (和) – Ancienne forme de techniques de soumission	, liée au jūjutsu	0	2	1	{}
22	Naginatajutsu (薙刀術) – Art de la hallebarde		0	1	2	{}
23	Haikai / Haiku (俳諧 / 俳句) – Poésie courte	, forme brève, souvent liée à la nature et à la spiritualité zen	2	0	0	{}
24	Tantōjutsu (短刀術) – Combat au couteau	, surtout utilisé en combat rapproché ou en cas de désarmement	0	2	1	{}
25	Shigin (詩吟) – Chant poétique	, récitation chantée de poèmes chinois ou japonais, souvent associé à une posture noble et une pratique méditative	2	0	0	{}
26	Tessenjutsu (鉄扇術) – L’art du combat à l’éventail de fer	, pratiqué par les samouraïs, notamment lorsqu’ils étaient désarmés, en visite à la cour ou dans des lieux où le port du sabre était interdit	0	1	2	{}
27	Kōdō (香道) – Voie de l’encens	, art de « sentir » et d’apprécier les parfums rares dans des rituels très codifiés	2	0	0	{}
28	Kagenkō (影言講) – L’art de la parole de l’ombre	, art oratoire utilisé par les yōkai pour semer la confusion ou manipuler les humains en jouant avec les doubles sens, les murmures et les voix venues des ténèbres	1	1	1	{}
29	Kagekui-ryū (影喰流) – École du Mange-Ombre	, art martial occulte / discipline hybride entre ninjutsu et pratiques yōkai	0	2	2	{}
30	Juzu (数珠) – Un bracelet de perles bouddhistes	, utilisé pour la prière mais aussi comme symbole d’appartenance à une école spirituelle	1	0	0	{}
31	Jirei (持鈴) – Une petite clochette	, utilisée pour signaler sa présence dans les temples, ou détourner l’attention	1	0	0	{}
32	Nōkyōchō (納経帳) – Un carnet de pèlerinage	, pouvant cacher des messages codés	1	0	0	{}
33	Ofuda (お札) – Talismans	, parfois utilisés comme code de reconnaissance	1	0	0	{}
34	Kōro (香炉) – Petit encensoir de voyage	, utilisé dans les rites mais aussi pour masquer d’autres odeurs	1	0	0	{}
35	Uchitake (打竹) – Bâtons creux en bambou	, utilisés pour transmettre des sons codés ou cacher de petits objets	1	0	0	{}
36	Sensu (扇子) – Éventail pliable	, utilisé pour transmettre des messages codés	1	0	0	{}
37	Hyōshigi (拍子木) – Claves en bois	, utilisées dans les spectacles ou comme signal sonore	1	0	0	{}
38	Shamisen (三味線) – Instrument à cordes	, central dans la musique des geishas	1	0	0	{}
39	Furushiki (風呂敷) – Carré de tissu	, utilisé pour emballer ou transporter des objets	1	0	0	{}
40	Makimono (巻物) – Rouleau calligraphié	, utilisé pour transmettre un poème ou un message esthétique	1	0	0	{}
41	Go-ban (碁盤) – Plateau de Go	, accompagné de pierres noires et blanches	1	0	0	{}
42	Koma (駒) – Pièces de shōgi	, jeu de stratégie prisé des samouraïs	1	0	0	{}
43	Kōdōgu (香道具) – Ustensiles du kōdō	, brûleurs, pinces, cendriers raffinés	1	0	0	{}
44	Etegami (絵手紙) – Cartes illustrées	, accompagnées de haïkus ou proverbes	1	0	0	{}
45	Ukiyo-e (浮世絵) – Estampes artistiques	, représentant des scènes de vie ou de la nature	1	0	0	{}
46	Fude (筆) – Pinceau de calligraphie	, souvent conservé dans un étui de bambou	1	0	0	{}
47	Tokkuri (徳利) – Bouteille à saké	, en céramique pour servir le saké chaud	2	0	-1	{}
48	Mala-no-fuku (摩羅の服) – Vêtement d’ascète	, permettant de dissimuler objets ou armes discrètes	2	0	-1	{}
49	Shōkadō bentō (松花堂弁当) – Boîte à compartiments	, utilisée lors de repas raffinés	2	0	-1	{}
50	Chadōgu (茶道具) – Ustensiles du thé	, bol, fouet, cuillère, etc	2	0	-1	{}
51	Kongōzue (金剛杖) – Un bâton de pèlerin	, à la fois soutien physique et arme d’autodéfense	0	1	1	{}
52	Tantō (短刀) – Un petit poignard	, facile à dissimuler, souvent utilisé pour les assassinats silencieux ou le seppuku rituel	0	1	1	{}
53	Fukiya (吹き矢) – Sarbacane silencieuse	, utilisée pour endormir ou empoisonner	0	1	1	{}
54	Shikomi-zue (仕込み杖) – Canne-lame	, idéale pour passer inaperçu	0	1	1	{}
55	Chigiriki (契木) – Masse à chaîne	, dissimulée dans un bâton, arme piégeuse	0	1	1	{}
56	Tessen (鉄扇) – Éventail de fer	, à la fois accessoire et arme défensive ou offensive	0	1	1	{}
57	Neko-te (猫手) – Griffes métalliques	, portées aux doigts, utilisées par les agents féminins ou espions	0	1	1	{}
58	Wakizashi (脇差) – Sabre court	, pratique en intérieur ou pour les duels rapprochés	0	1	1	{}
59	Shuriken (手裏剣) – Étoiles de lancer	, utilisées pour distraire ou blesser	0	1	1	{}
60	Kunai (苦無) – Dague multi-usage	, pouvant être lancée ou utilisée comme outil	0	1	1	{}
61	Kakute (角手) – Anneau à pointes	, souvent empoisonnées, porté pour des attaques discrètes	0	1	1	{}
62	Hankyū (半弓) — Arc court	, pratique pour le combat rapproché ou en terrain dense, souvent utilisé par les fantassins	0	1	1	{}
63	Kusarigama (鎖鎌) — Arme composée d’une faucille attachée à une chaîne lestée	, utilisée pour désarmer et piéger l’ennemi	0	1	1	{}
64	Jitte (十手) – Arme de police	, utilisée pour parer les lames et capturer sans tuer	0	1	1	{}
65	Teppō (鉄砲) – Un mousquet	, des prototypes artisanaux circulaient en version expérimentale dès la fin du 14e siècle	-1	2	1	{}
66	Katana (刀) – L’arme emblématique du samouraï	, symbole d’honneur et de rang, mais peu pratique pour les agents discrets	-1	2	1	{}
67	Yumi (弓) — Grand arc asymétrique utilisé par les samouraïs	, redouté pour sa portée et sa précision à cheval comme à pied	-1	2	1	{}
68	Yari (槍) — Lance droite à pointe effilée	, polyvalente en formation comme en duel, arme principale de nombreux ashigaru	-1	2	1	{}
69	Naginata (薙刀) — Arme d’hast à lame courbe	, maniée avec grâce et puissance, souvent associée aux femmes guerrières ou aux moines	-1	2	1	{}
70	Tetsubō (鉄棒) — Masse de guerre en fer	, capable d’écraser les armures, prisée par les moines-soldats	-1	2	1	{}
71	Inrō (印籠) – Une petite boîte à la ceinture	, utilisée pour transporter des médicaments, du poison, ou de minuscules outils	1	1	-1	{}
72	Kanzashi (簪) – Épingles à cheveux	, parfois dotées de fonctions symboliques ou défensives	1	1	-1	{}
73	Yakuyō bako (薬用箱) – Boîte à remèdes	, contenant des plantes médicinales	1	1	-1	{}
74	Shōyaku fukuro (生薬袋) – Sachets de plantes	, à infuser ou brûler à des fins curatives	1	1	-1	{}
75	Zudabukuro (頭陀袋) – Une besace de pèlerin	, utile pour transporter discrètement messages, herbes médicinales ou objets de culte	1	1	-1	{}
76	Kiseru (煙管) – Une pipe à tabac	, parfois modifiée pour dissimuler des messages roulés ou une poudre soporifique	1	1	-1	{}
77	Kinjirō (金次郎) – Intendant financier	, chargé de la gestion des richesses et récoltes du domaine	1	0	0	{}
78	Kashi (歌師) – Poète officiel	, ou maître du chant, souvent présent pour les divertissements de cour	1	0	0	{}
79	Shikibu (式部) – Maître de cérémonie	, en charge du protocole et de l’organisation des audiences	1	0	0	{}
80	Bugyō (奉行) – Magistrat ou officier administratif	, responsable de fonctions judiciaires ou logistiques	1	0	0	{}
81	Koto-hime (琴姫) – Musicienne	, jouant du koto lors des banquets et cérémonies	1	0	0	{}
82	Kōshitsu (香師) – Spécialiste de l’art de l’encens	, en charge des parfums et de l’ambiance	1	0	0	{}
83	Kasō (花匠) – Artiste florale	, chargée des arrangements ikebana dans les salons du palais	1	0	0	{}
84	Shodō-ka (書道家) – Calligraphe raffinée	, pratiquant pour l’art et les documents officiels	1	0	0	{}
85	Hōin (法印) – Prêtresse ou religieuse conseillère	, parfois formée aux arts divinatoires	1	0	0	{}
86	Yūjo (遊女) – Courtisane cultivée	, parfois invitée lors de réceptions de haut rang	1	0	0	{}
87	Jōhō-gashi (情報頭) – Collecteur d’informations	, actif sur les routes ou dans les marchés	1	0	0	{}
88	Mimiiri (耳入) – Informateur discret	, placé parmi les serviteurs ou les marchands	1	0	0	{}
89	Kannushi (神主) – Prêtre shintō	, gardien des sanctuaires et officiant les rites sacrés	1	0	0	{}
90	Onmyōji (陰陽師) – Devin et exorciste	, spécialiste des arts occultes et des influences célestes	1	0	0	{}
91	Hōshi (法師) – Moine bouddhiste itinérant	, parfois proche conseiller du daimyō	1	0	0	{}
92	Reishi (霊師) – Médium ou exorciste	, intervenant lors de troubles spirituels	1	0	0	{}
93	Miko (巫女) – Servante shintō	, pratiquant la danse rituelle et les oracles	1	0	0	{}
94	Nōgakushi (能楽師) – Acteur de théâtre Nō	, maître de la performance codifiée mêlant chant, danse et spiritualité	1	0	0	{}
95	Tsūshi (通使) – Diplomate ou émissaire	, habile orateur chargé de négociations sensibles	1	0	0	{}
96	Karō (家老) – Conseiller principal	, souvent responsable de l’administration du domaine	2	0	-1	{}
97	Shōshō (少将) – Intendante ou gouvernante	, supervisant les affaires internes de la demeure	2	0	-1	{}
98	Chajin (茶人) – Maître de thé	, responsable des cérémonies du thé et de l’étiquette liée au chanoyu	2	0	-1	{}
99	Geisha (芸者) – Artiste raffinée	, experte en musique, danse, conversation et arts traditionnels	2	0	-1	{}
100	Biwa Hōshi (琵琶法師) – Conteur aveugle itinérant	, chantant les épopées avec son biwa	2	0	-1	{}
101	Shihan (師範) – Maître instructeur	, en arts martiaux ou lettrés, formant les jeunes samouraïs du domaine	0	1	1	{}
102	Kōsaku (工作) – Saboteur	, expert en pièges et manipulations de terrain	0	1	1	{}
103	Kuro-hatamoto (黒旗本) – Garde d’élite	, en mission secrète, loyal au daimyō	0	1	1	{}
104	Monomi (物見) – Éclaireur	, ou observateur posté en avant-garde	0	1	1	{}
105	Ninja-kahō (忍者家法) – Membre d’une lignée de ninjas	, liés par serment au daimyō	0	1	1	{}
106	Sodegarami (袖搦) – Garde spécialisé	, dans l’arrestation à l’aide d’armes non létales	0	1	1	{}
107	Hitokiri (人斬り) – Assassin redouté au sabre	, exécuteur discret souvent marqué par la vengeance	0	1	1	{}
108	Nyūdō (入道) – Samouraï retiré	, dans la voie monastique, servant comme conseiller sage	-1	2	1	{}
109	Onna-bugeisha (女武芸者) – Femme samouraï	, entraînée au combat, parfois en charge de la garde rapprochée	-1	2	1	{}
110	Monogashira (物頭) – Officier en chef	, d’un détachement armé, chargé de la sécurité ou de missions spéciales	-1	2	1	{}
111	Sōhei (僧兵) – Moine-soldat	, à la fois religieux et combattant pour le temple ou le seigneur	-1	2	1	{}
112	Naishi (内侍) – Dame de compagnie	, au service de l’épouse ou des filles du daimyō	1	1	-1	{}
113	Shinobi (忍び) – Espion et agent furtif	, maître de l’infiltration, du sabotage ou de l’assassinat	1	1	-1	{}
114	Tsukai (使い) – Messager rapide	, souvent à cheval, transmettant des ordres urgents	1	1	-1	{}
115	Kagemusha (影武者) – Sosie du seigneur	, utilisé pour leurrer l’ennemi ou éviter les attentats	1	1	-1	{}
116	Yamabushi (山伏) – Moine-guerrier itinérant	, parfois employé comme éclaireur ou conseiller spirituel	1	1	-1	{}
117	Nusutto (盗人) – Voleur agile et rusé	, opérant dans l’ombre, parfois espion déguisé ou informateur	1	1	-1	{}
118	Sarugakushi (猿楽師) – Artiste de rue ou comédien	, mêlant satire, acrobaties et théâtre populaire	1	1	-1	{}
119	Kusushi (薬師) – Médecin itinérant	, spécialiste des remèdes traditionnels à base de plantes	1	1	-1	{}
120	Ishitsukai (医使) – Médecin de cour	, parfois moine ou alchimiste, pratiquant acupuncture et médecine spirituelle	1	1	-1	{}
121	Prêtre chrétien	\N	1	1	1	{"on_recrutment": {"action": {"type":"go_traitor", "controller_lastname": "Miyoshi (三好)"} } }
122	Marin Portugais	\N	1	1	1	{"on_recrutment": {"action": {"type":"go_traitor", "controller_lastname": "Miyoshi (三好)"} } }
\.


--
-- Data for Name: worker_actions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.worker_actions (id, worker_id, turn_number, zone_id, controller_id, enquete_val, attack_val, defence_val, action_choice, action_params, report, created_at) FROM stdin;
19	19	0	6	7	7	2	4	passive	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Est de Tokushima. Ce.tte trimestre j'ai 7 en investigation et 2\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Sarugakushi (\\u733f\\u697d\\u5e2b) \\u2013 Artiste de rue ou com\\u00e9dien du nom de Miyu Hirano (11) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e K\\u014dd\\u014dgu (\\u9999\\u9053\\u5177) \\u2013 Ustensiles du k\\u014dd\\u014d, mais cette information n\\u2019est pas si pertinente.<\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p><p>Information int\\u00e9ressante : un.e Port de Tokushima est pr\\u00e9sent.e dans la zone. Carrefour maritime entre Honsh\\u016b et Shikoku, le port de Tokushima bruisse de dialectes et de voiles \\u00e9trang\\u00e8res.\\r\\n     Dans les ruelles proches du march\\u00e9, on parle parfois espagnol, ou latin, \\u00e0 voix basse.<\\/p><p>Il semblerait qu\\u2019un.e Forteresse des Samoura\\u00ef Miyoshi se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>"}	2025-06-22 12:01:26.800965
3	3	0	8	1	8	8	7	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 8\\/7 en attaque\\/d\\u00e9fense.","secrets_report":"<p>Dans le territoire Ile d\\u2019Awaji :<\\/p><p>Nous avons identifi\\u00e9 une information int\\u00e9ressante : un.e Camp de deserteurs serait pr\\u00e9sent.e dans la zone.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Rumeur de la bataille se trouve dans cette zone. Les p\\u00eacheurs d\\u2019Awaji parlent encore d\\u2019un combat f\\u00e9roce dans les collines, entre troupes en fuite et rebelles aux visages peints. Certains affirment avoir vu le ciel s\\u2019embraser au-dessus du temple abandonn\\u00e9.<\\/p><p>Information int\\u00e9ressante : un.e Phare abandonn\\u00e9 de Minokoshi est pr\\u00e9sent.e dans la zone. Diss\\u00e9min\\u00e9 au bout d\\u2019une presqu\\u2019\\u00eele battue par les vents, le vieux phare de Minokoshi n\\u2019est plus qu\\u2019un squelette de pierre rong\\u00e9 par le sel.\\r\\n        Pourtant, certains p\\u00eacheurs affirment y voir passer des silhouettes arm\\u00e9es \\u00e0 la tomb\\u00e9e de la nuit.\\r\\n        La rumeur court qu\\u2019un prisonnier de valeur y est gard\\u00e9 en secret par le clan Ch\\u014dsokabe, un traitre captur\\u00e9 lors des affrontements r\\u00e9cents.<\\/p>"}	2025-06-22 11:19:06.71051
8	8	0	10	2	4	11	8	passive	{}	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 11\\/8 en attaque\\/d\\u00e9fense.","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p>"}	2025-06-22 11:19:06.71051
13	13	0	7	7	5	5	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 5\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Il semblerait qu\\u2019un.e Maison close de Marugame se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>"}	2025-06-22 11:46:10.052498
14	14	0	3	4	7	5	5	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 7 en investigation et 5\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cap sud de Kochi.<\\/p>J\\u2019ai vu un.e K\\u014dsaku (\\u5de5\\u4f5c) \\u2013 Saboteur du nom de Hiuchi Kagaribi (2) qui surveille dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Tepp\\u014d (\\u9244\\u7832) \\u2013 Un mousquet mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral.<\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p><p>Nos informateurs.rices \\u00e9voquent la d\\u00e9couverte potentielle d\\u2019un.e Mine de fer de Kubokawa dans cette zone.<\\/p>"}	2025-06-22 11:46:24.692037
2	2	0	3	1	6	9	9	passive	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 9\\/9 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cap sud de Kochi.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p>"}	2025-06-22 11:19:06.71051
17	17	0	5	6	5	6	2	passive	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 6\\/2 en attaque\\/d\\u00e9fense.","secrets_report":"<p>Dans le territoire Vall\\u00e9es d\\u2019Iya et d\\u2019Obok\\u00e9 de Tokushima :<\\/p>"}	2025-06-22 11:56:55.046155
16	16	0	4	4	0	8	7	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 0 en investigation et 8\\/7 en attaque\\/d\\u00e9fense.","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p>"}	2025-06-22 11:56:49.785221
11	11	0	6	5	6	5	4	passive	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 5\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p><p>Nous avons identifi\\u00e9 une information int\\u00e9ressante : un.e Port de Tokushima serait pr\\u00e9sent.e dans la zone.<\\/p>"}	2025-06-22 11:45:36.054182
15	15	0	9	8	3	6	8	passive	{}	{"life_report":"Ce.tte trimestre j'ai 3 en investigation et 6\\/8 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p>"}	2025-06-22 11:52:21.357883
18	18	0	2	5	3	8	5	passive	{}	{"life_report":"Ce.tte trimestre j'ai 3 en investigation et 8\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p>"}	2025-06-22 11:57:55.101235
12	12	0	9	8	3	4	6	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 3 en investigation et 4\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p>"}	2025-06-22 11:45:40.775541
24	14	1	1	4	5	5	6	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Ouest d\\u2019Ehime. Ce.tte trimestre j'ai 5 en investigation et 5\\/6 en attaque\\/d\\u00e9fense.","secrets_report":"<p>Dans le territoire C\\u00f4te Ouest d\\u2019Ehime :<\\/p>"}	2025-06-22 12:08:14.276264
5	5	0	2	6	8	3	2	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 3\\/2 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p>Je me suis rendu compte que Iwao Jizane (1), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Chigiriki (\\u5951\\u6728) \\u2013 Masse \\u00e0 cha\\u00eene, surveille dans le coin. <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Kagemusha (\\u5f71\\u6b66\\u8005) \\u2013 Sosie du seigneur du nom de Ryota Yoshikawa (18) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise H\\u014djutsu (\\u7832\\u8853) \\u2013 Art des armes \\u00e0 feu (tepp\\u014d). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court, mais cette information n\\u2019est pas si pertinente.Iel travaille avec la faction 5. Nous l\\u2019avons vu rencontrer en personne Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p><p>Il semblerait qu\\u2019un.e Port de Matsuyama se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Mt Ishizuchi est bien li\\u00e9.e \\u00e0 cette localisation. Plus haut sommet de l\\u2019\\u00eele, le mont Ishizuchi domine les vall\\u00e9es alentour comme un sabre dress\\u00e9 vers le ciel.\\r\\n     On dit qu\\u2019un p\\u00e8lerinage ancien y conduit \\u00e0 une dalle sacr\\u00e9e o\\u00f9 les esprits s\\u2019expriment lorsque les vents tournent.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Sanctuaire des Pins Br\\u00fbl\\u00e9s est bien li\\u00e9.e \\u00e0 cette localisation. Dans un ancien sanctuaire shint\\u014d dont les piliers carbonis\\u00e9s r\\u00e9sistent au temps, des p\\u00e8lerins affirment avoir vu un artefact \\u00e9trange cach\\u00e9 sous l\\u2019autel \\u2014 une croix d\\u2019argent sertie d\\u2019inscriptions latines.\\r\\n        Les paysans parlent d\\u2019un pr\\u00eatre chr\\u00e9tien, et de l\\u2019Inquisition j\\u00e9suite elle-m\\u00eame. Mais les recherches men\\u00e9es par les yamabushi locaux n\\u2019ont rien r\\u00e9v\\u00e9l\\u00e9 de probant.<\\/p>"}	2025-06-22 11:19:06.71051
1	1	0	2	1	8	7	8	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 7\\/8 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p>On a suivi Ren-j\\u014d fils de Rennyo (\\u84ee\\u5982) (5) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Reishi (\\u970a\\u5e2b) \\u2013 M\\u00e9dium ou exorciste mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Tokkuri (\\u5fb3\\u5229) \\u2013 Bouteille \\u00e0 sak\\u00e9.<\\/p>Je me suis rendu compte que Ryota Yoshikawa (18), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court, surveille dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de H\\u014djutsu (\\u7832\\u8853) \\u2013 Art des armes \\u00e0 feu (tepp\\u014d), ce qui en fait un.e Kagemusha (\\u5f71\\u6b66\\u8005) \\u2013 Sosie du seigneur un peu trop sp\\u00e9cial.e.Iel fait partie de la faction 5.   Nous l\\u2019avons vu rencontrer en personne Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p><p>Il semblerait qu\\u2019un.e Port de Matsuyama se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Mt Ishizuchi. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Plus haut sommet de l\\u2019\\u00eele, le mont Ishizuchi domine les vall\\u00e9es alentour comme un sabre dress\\u00e9 vers le ciel.\\r\\n     On dit qu\\u2019un p\\u00e8lerinage ancien y conduit \\u00e0 une dalle sacr\\u00e9e o\\u00f9 les esprits s\\u2019expriment lorsque les vents tournent.<\\/p><p>Information int\\u00e9ressante : un.e Sanctuaire des Pins Br\\u00fbl\\u00e9s est pr\\u00e9sent.e dans la zone. Dans un ancien sanctuaire shint\\u014d dont les piliers carbonis\\u00e9s r\\u00e9sistent au temps, des p\\u00e8lerins affirment avoir vu un artefact \\u00e9trange cach\\u00e9 sous l\\u2019autel \\u2014 une croix d\\u2019argent sertie d\\u2019inscriptions latines.\\r\\n        Les paysans parlent d\\u2019un pr\\u00eatre chr\\u00e9tien, et de l\\u2019Inquisition j\\u00e9suite elle-m\\u00eame. Mais les recherches men\\u00e9es par les yamabushi locaux n\\u2019ont rien r\\u00e9v\\u00e9l\\u00e9 de probant.<\\/p>"}	2025-06-22 11:19:06.71051
10	10	0	7	6	6	5	4	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 5\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p>J\\u2019ai vu un.e Bugy\\u014d (\\u5949\\u884c) \\u2013 Magistrat ou officier administratif du nom de Marco Venezio (13) qui surveille dans ma zone d\\u2019action. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de Portugal. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Tessen (\\u9244\\u6247) \\u2013 \\u00c9ventail de fer mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Bugaku (\\u821e\\u697d) \\u2013 Danse de cour.<\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Des signes pointent vers la pr\\u00e9sence d\\u2019un.e Grande route et relais de poste, nous devons enqu\\u00eater davantage \\u00e0 ce sujet.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Maison close de Marugame se trouve dans cette zone. \\u00c0 Marugame, dans une maison close r\\u00e9put\\u00e9e pour son sak\\u00e9 sucr\\u00e9 et ses \\u00e9ventails peints \\u00e0 la main, des courtisanes murmurent entre deux chansons.\\r\\n        L\\u2019une d\\u2019elles pr\\u00e9tend avoir lu une lettre scell\\u00e9e, confi\\u00e9e par un \\u00e9missaire enivr\\u00e9, annon\\u00e7ant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre \\u00e9clair contre les Ch\\u014dsokabe.<\\/p>"}	2025-06-22 11:44:52.948498
4	4	0	9	1	11	6	5	passive	{}	{"life_report":"Ce.tte trimestre j'ai 11 en investigation et 6\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e N\\u014dgakushi (\\u80fd\\u697d\\u5e2b) \\u2013 Acteur de th\\u00e9\\u00e2tre N\\u014d du nom de Claire Richard (12) qui enquete dans notre r\\u00e9gion. Je m\\u2019en m\\u00e9fie, iel vient de France. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Hy\\u014dshigi (\\u62cd\\u5b50\\u6728) \\u2013 Claves en bois, mais cette information n\\u2019est pas si pertinente. Iel re\\u00e7oit un soutien financier de la faction 8. Ce qui veut dire que c\\u2019est un serviteur de Murai Wako (\\u548c\\u5149). <\\/p>On a suivi Ayaka Noguchi (15) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Chigiriki (\\u5951\\u6728) \\u2013 Masse \\u00e0 cha\\u00eene.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer, au moins partiellement.Iel travaille avec la faction 8. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Murai Wako (\\u548c\\u5149). <\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p><p>Information int\\u00e9ressante : un.e Camp de deserteurs est pr\\u00e9sent.e dans la zone. Dans une gorge dissimul\\u00e9e parmi les pins tordus de Sh\\u014ddoshima, quelques hommes efflanqu\\u00e9s vivent en silence, fuyant le regard des p\\u00eacheurs et des samoura\\u00efs.\\r\\n            Ce sont des survivants de la d\\u00e9route d\\u2019Ishizuchi, dont ils racontent une version bien diff\\u00e9rente de celle propag\\u00e9e \\u00e0 la cour : l\\u2019avant-garde des Ch\\u014dsokabe, command\\u00e9e par Fujitaka Hosokawa, se serait retrouv\\u00e9e face aux fanatiques Ikko-ikki, qui auraient \\u00e9cras\\u00e9 ses lignes avant m\\u00eame que l\\u2019ordre de retraite ne puisse \\u00eatre donn\\u00e9.\\r\\n            Fujitaka, s\\u00e9par\\u00e9 de la force principale, aurait fui pr\\u00e9cipitamment vers Kyoto, mais aurait \\u00e9t\\u00e9 aper\\u00e7u captur\\u00e9 par un g\\u00e9n\\u00e9ral des forces du shogun Ashikaga. Ces aveux, \\u00e9touff\\u00e9s sous le fracas des r\\u00e9cits officiels, pourraient bien r\\u00e9habiliter l\\u2019honneur du daimy\\u014d d\\u00e9chu \\u2014 ou bouleverser les \\u00e9quilibres fragiles entre les clans.<\\/p><p>Les donn\\u00e9es concordent : un.e Ge\\u00f4les des Kaizokush\\u016b est bien associ\\u00e9.e \\u00e0 cet endroit. Creus\\u00e9es dans la falaise m\\u00eame, ces cavernes humides servent de prison aux captifs des Wako. \\r\\n        Des cha\\u00eenes rouill\\u00e9es pendent aux murs, et l\\u2019eau sal\\u00e9e suinte sans cesse, rongeant la volont\\u00e9 des enferm\\u00e9s. \\r\\n        Le silence n\\u2019y est troubl\\u00e9 que par les pas des ge\\u00f4liers \\u2014 ou les rires des pirates. Il est possible d\\u2019organiser une mission pour faire dispara\\u00eetre ce probl\\u00e8me.<\\/p><p>Information int\\u00e9ressante : un.e D\\u00e9troit d\\u2019Okayama est pr\\u00e9sent.e dans la zone. \\u00c9troit et venteux, ce d\\u00e9troit aux eaux tra\\u00eetresses s\\u00e9pare Shikoku de Honsh\\u016b.\\r\\n     Difficile de tenter cette travers\\u00e9e sans \\u00eatre \\u00e9pi\\u00e9 par les habitants de l\\u2019\\u00eele de Sh\\u014ddoshima.\\r\\n     Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d\\u2019\\u00eatre intercept\\u00e9 par les Kaizokush\\u016b.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Crique de Funakoshi. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Cette crique isol\\u00e9e, souvent balay\\u00e9e par les vents, est connue des contrebandiers comme des p\\u00eacheurs.\\r\\n        Depuis quelques jours, un bruit court : un important \\u00e9missaire imp\\u00e9rial aurait \\u00e9t\\u00e9 intercept\\u00e9 par les pirates Wako et d\\u00e9tenu dans une grotte voisine, en attendant ran\\u00e7on ou silence.<\\/p>"}	2025-06-22 11:19:06.71051
9	9	0	10	2	12	3	4	passive	{}	{"life_report":"Ce.tte trimestre j'ai 12 en investigation et 3\\/4 en attaque\\/d\\u00e9fense.","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Cour imp\\u00e9riale se trouve dans cette zone. Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p><p>Les donn\\u00e9es concordent : un.e Suzaku Mon est bien associ\\u00e9.e \\u00e0 cet endroit. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" se trouve dans cette zone. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p>"}	2025-06-22 11:19:06.71051
126	40	3	4	4	2	6	7	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 2 en investigation et 6\\/7 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p><p><\\/p><p><\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p>","claim_report":"Hinako Ichikawa a pris Grande Baie de Kochi par la force. Iel n\\u2019a laiss\\u00e9 aucune chance aux d\\u00e9fenseurs.<br\\/>"}	2025-06-22 13:57:37.698692
7	7	0	10	2	9	7	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 9 en investigation et 7\\/6 en attaque\\/d\\u00e9fense.","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Les donn\\u00e9es concordent : un.e Cour imp\\u00e9riale est bien associ\\u00e9.e \\u00e0 cet endroit. Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Suzaku Mon se trouve dans cette zone. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Information int\\u00e9ressante : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" est pr\\u00e9sent.e dans la zone. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p>"}	2025-06-22 11:19:06.71051
6	6	0	10	2	8	7	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 7\\/6 en attaque\\/d\\u00e9fense.","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Un rapport fragmentaire mentionne un.e Cour imp\\u00e9riale comme \\u00e9tant cach\\u00e9.e dans ce territoire.<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Suzaku Mon est bien ici. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" est bien li\\u00e9.e \\u00e0 cette localisation. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p>"}	2025-06-22 11:19:06.71051
44	25	1	6	4	5	5	3	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 5\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p><p><\\/p><p><\\/p>Je me suis rendu compte que Arisa Komatsu (29), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Shamisen (\\u4e09\\u5473\\u7dda) \\u2013 Instrument \\u00e0 cordes, surveille dans le coin. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de Honshu - Okayama. <\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p>","claim_report":"Je pense que Ryota Yoshikawa pensait avoir une chance au C\\u00f4te Est de Tokushima. C\\u2019\\u00e9tait mal calcul\\u00e9, iel a \\u00e9chou\\u00e9.<br\\/>"}	2025-06-22 12:23:15.894634
30	18	1	6	5	6	7	7	claim	{"claim_controller_id":"5"}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Est de Tokushima. Ce.tte trimestre j'ai 6 en investigation et 7\\/7 en attaque\\/d\\u00e9fense.","claim_report":"Notre tentative de prise de contr\\u00f4le de C\\u00f4te Est de Tokushima a \\u00e9chou\\u00e9. La d\\u00e9fense \\u00e9tait trop solide."}	2025-06-22 12:08:14.276264
31	12	1	9	8	7	5	7	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 7 en investigation et 5\\/7 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p><p>Information int\\u00e9ressante : un.e D\\u00e9troit d\\u2019Okayama est pr\\u00e9sent.e dans la zone. \\u00c9troit et venteux, ce d\\u00e9troit aux eaux tra\\u00eetresses s\\u00e9pare Shikoku de Honsh\\u016b.\\r\\n     Difficile de tenter cette travers\\u00e9e sans \\u00eatre \\u00e9pi\\u00e9 par les habitants de l\\u2019\\u00eele de Sh\\u014ddoshima.\\r\\n     Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d\\u2019\\u00eatre intercept\\u00e9 par les Kaizokush\\u016b.<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Crique de Funakoshi est bien ici. Cette crique isol\\u00e9e, souvent balay\\u00e9e par les vents, est connue des contrebandiers comme des p\\u00eacheurs.\\r\\n        Depuis quelques jours, un bruit court : un important \\u00e9missaire imp\\u00e9rial aurait \\u00e9t\\u00e9 intercept\\u00e9 par les pirates Wako et d\\u00e9tenu dans une grotte voisine, en attendant ran\\u00e7on ou silence.<\\/p>"}	2025-06-22 12:08:14.276264
29	15	1	9	8	3	8	9	passive	{}	{"life_report":"Ce.tte trimestre j'ai 3 en investigation et 8\\/9 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p>"}	2025-06-22 12:08:14.276264
22	8	1	10	2	4	11	8	passive	{}	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 11\\/8 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p>"}	2025-06-22 12:08:14.276264
25	2	1	3	1	6	9	9	passive	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 9\\/9 en attaque\\/d\\u00e9fense.<p>Malgr\\u00e9 l\\u2019assaut de Kenji Morimoto(16), ma riposte a non seulement sauv\\u00e9 ma vie, mais a mis compl\\u00e8tement fin \\u00e0 ses ambitions.<\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p>"}	2025-06-22 12:08:14.276264
47	28	1	7	7	6	4	6	claim	{"claim_controller_id":"7"}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Prefecture de Kagawa. Ce.tte trimestre j'ai 6 en investigation et 4\\/6 en attaque\\/d\\u00e9fense.","claim_report":"La mission de domination de Prefecture de Kagawa n\\u2019a pas abouti. Trop de r\\u00e9sistance \\u00e0 notre autorit\\u00e9 sur place."}	2025-06-22 12:31:04.13721
128	42	3	10	7	2	8	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 2 en investigation et 8\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p><p><\\/p><p><\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p>"}	2025-06-22 14:04:20.886958
40	21	1	2	8	9	4	2	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 9 en investigation et 4\\/2 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p>J\\u2019ai vu un.e Reishi (\\u970a\\u5e2b) \\u2013 M\\u00e9dium ou exorciste du nom de Ren-j\\u014d fils de Rennyo (\\u84ee\\u5982) (5) qui enquete dans ma zone d\\u2019action. Je m\\u2019en m\\u00e9fie, iel vient de Honshu - Kyoto. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Tokkuri (\\u5fb3\\u5229) \\u2013 Bouteille \\u00e0 sak\\u00e9 mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de K\\u014dd\\u014d (\\u9999\\u9053) \\u2013 Voie de l\\u2019encens.Iel travaille avec la faction 6. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Kannushi (\\u795e\\u4e3b) \\u2013 Pr\\u00eatre shint\\u014d du nom de Iwao Jizane (1) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Chigiriki (\\u5951\\u6728) \\u2013 Masse \\u00e0 cha\\u00eene, mais cette information n\\u2019est pas si pertinente.<\\/p>Je me suis rendu compte que Miyu Hirano (11), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e K\\u014dd\\u014dgu (\\u9999\\u9053\\u5177) \\u2013 Ustensiles du k\\u014dd\\u014d, enquete dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral, ce qui en fait un.e Sarugakushi (\\u733f\\u697d\\u5e2b) \\u2013 Artiste de rue ou com\\u00e9dien un peu trop sp\\u00e9cial.e.Iel travaille avec la faction 5. Iel a de plus une maitrise de la discipline Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire. Nous l\\u2019avons vu rencontrer en personne Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p><p>Des rumeurs persistantes \\u00e9voquent la pr\\u00e9sence d\\u2019un.e Vieux temple dans les environs.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Mt Ishizuchi se trouve dans cette zone. Plus haut sommet de l\\u2019\\u00eele, le mont Ishizuchi domine les vall\\u00e9es alentour comme un sabre dress\\u00e9 vers le ciel.\\r\\n     On dit qu\\u2019un p\\u00e8lerinage ancien y conduit \\u00e0 une dalle sacr\\u00e9e o\\u00f9 les esprits s\\u2019expriment lorsque les vents tournent.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Sanctuaire des Pins Br\\u00fbl\\u00e9s. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Dans un ancien sanctuaire shint\\u014d dont les piliers carbonis\\u00e9s r\\u00e9sistent au temps, des p\\u00e8lerins affirment avoir vu un artefact \\u00e9trange cach\\u00e9 sous l\\u2019autel \\u2014 une croix d\\u2019argent sertie d\\u2019inscriptions latines.\\r\\n        Les paysans parlent d\\u2019un pr\\u00eatre chr\\u00e9tien, et de l\\u2019Inquisition j\\u00e9suite elle-m\\u00eame. Mais les recherches men\\u00e9es par les yamabushi locaux n\\u2019ont rien r\\u00e9v\\u00e9l\\u00e9 de probant.<\\/p>"}	2025-06-22 12:11:33.988436
41	22	1	10	5	9	3	2	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 9 en investigation et 3\\/2 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p>J\\u2019ai trouv\\u00e9 Lord Asakura(\\u671d\\u5009) Mitsunao(\\u5149\\u76f4) (6), avec un.e Armure en fer de Kochi, qui n\\u2019est clairement pas un.e de nos loyaux suivants, c\\u2019est un.e Ts\\u016bshi (\\u901a\\u4f7f) \\u2013 Diplomate ou \\u00e9missaire qui a \\u00e9galement \\u00e9t\\u00e9 vu.e avec un.e Go-ban (\\u7881\\u76e4) \\u2013 Plateau de Go. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de Honshu - Kyoto. Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de l\\u2019art Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.<\\/p>J\\u2019ai trouv\\u00e9 Lady Ibara(\\u8328\\u306e\\u7d05) (7), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas avec un.e Shamisen (\\u4e09\\u5473\\u7dda) \\u2013 Instrument \\u00e0 cordes.<\\/p>J\\u2019ai trouv\\u00e9 Renry\\u016b(\\u84ee\\u7adc) Takeda(\\u6b66\\u7530) (8), avec un.e Cheval Kagawa, qui n\\u2019est clairement pas un.e de nos loyaux suivants, c\\u2019est un.e Kuro-hatamoto (\\u9ed2\\u65d7\\u672c) \\u2013 Garde d\\u2019\\u00e9lite qui a \\u00e9galement \\u00e9t\\u00e9 vu.e avec un.e Katana (\\u5200) \\u2013 L\\u2019arme embl\\u00e9matique du samoura\\u00ef. Je m\\u2019en m\\u00e9fie, iel vient de Honshu - Kyoto. Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de l\\u2019art Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.Iel fait partie de la faction 2. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire. Iel a de plus une maitrise de la discipline Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement.   Ce qui veut dire que c\\u2019est un serviteur de Yoshiteru (\\u7fa9\\u8f1d) Ashikaga (\\u8db3\\u5229). <\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Cour imp\\u00e9riale se trouve dans cette zone. Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Suzaku Mon est bien li\\u00e9.e \\u00e0 cette localisation. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\". Voici ce que nous avons appris : Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p>"}	2025-06-22 12:12:03.392318
39	20	1	4	8	7	4	2	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 7 en investigation et 4\\/2 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p>Je me suis rendu compte que Shiori Kiriyama (27), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Juzu (\\u6570\\u73e0) \\u2013 Un bracelet de perles bouddhistes, enquete dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral, ce qui en fait un.e Shikibu (\\u5f0f\\u90e8) \\u2013 Ma\\u00eetre de c\\u00e9r\\u00e9monie un peu trop sp\\u00e9cial.e.En plus, sa famille a des liens avec la faction 7. <\\/p>Je me suis rendu compte que Emi Nagano (24), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e N\\u014dky\\u014dch\\u014d (\\u7d0d\\u7d4c\\u5e33) \\u2013 Un carnet de p\\u00e8lerinage, surveille dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Reiki \\/ Kujikiri (\\u970a\\u6c17 \\/ \\u4e5d\\u5b57\\u5207\\u308a) \\u2013 Pratiques \\u00e9sot\\u00e9riques, ce qui en fait un.e Miko (\\u5deb\\u5973) \\u2013 Servante shint\\u014d un peu trop sp\\u00e9cial.e.<\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p><p>Information int\\u00e9ressante : un.e Port de Kochi est pr\\u00e9sent.e dans la zone. Prot\\u00e9g\\u00e9 par une anse naturelle, ce port militaire et marchand voit passer jonques, bateaux de guerre et pirates repenti.\\r\\n      Son arsenal est surveill\\u00e9 nuit et jour par des ashigaru en armure sombre.\\r\\n      On dit que le clan Ch\\u014dsokabe y cache des objects illegaux import\\u00e9 d\\u2019ailleurs.<\\/p><p>Nous avons identifi\\u00e9 une information int\\u00e9ressante : un.e Chikurin-ji (\\u7af9\\u6797\\u5bfa) -- Le chemin de l\\u2019asc\\u00e8se serait pr\\u00e9sent.e dans la zone.<\\/p>"}	2025-06-22 12:10:56.505839
27	16	1	3	4	3	7	7	dead	[{"attackScope":"worker","attackID":2}]	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Cap sud de Kochi. Ce.tte trimestre j'ai 3 en investigation et 7\\/7 en attaque\\/d\\u00e9fense.<p>Depuis la semaine 1, cet agent est un fant\\u00f4me, insaisissable et introuvable.<\\/p>","attack_report":"<p>Le groupe envoy\\u00e9 pour neutraliser Hiuchi Kagaribi n\\u2019est jamais revenu.<\\/p>"}	2025-06-22 12:08:14.276264
43	24	1	4	6	6	3	5	passive	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 3\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Shikibu (\\u5f0f\\u90e8) \\u2013 Ma\\u00eetre de c\\u00e9r\\u00e9monie du nom de Shiori Kiriyama (27) qui enquete dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Juzu (\\u6570\\u73e0) \\u2013 Un bracelet de perles bouddhistes, mais cette information n\\u2019est pas si pertinente.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p><p>Certains indices laissent penser qu\\u2019un.e Port de Kochi pourrait se cacher ici.<\\/p>"}	2025-06-22 12:14:07.694982
35	4	1	9	1	11	6	5	passive	{}	{"life_report":"Ce.tte trimestre j'ai 11 en investigation et 6\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p>On a suivi Claire Richard (12) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de enqueter, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e N\\u014dgakushi (\\u80fd\\u697d\\u5e2b) \\u2013 Acteur de th\\u00e9\\u00e2tre N\\u014d mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Hy\\u014dshigi (\\u62cd\\u5b50\\u6728) \\u2013 Claves en bois.En plus, iel est originaire de France. Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), au moins partiellement.Ces observations se cumulent avec son utilisation de la discipline Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer.  Iel re\\u00e7oit un soutien financier de la faction 8. Ce qui veut dire que c\\u2019est un serviteur de Murai Wako (\\u548c\\u5149). <\\/p>On a suivi Ayaka Noguchi (15) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Chigiriki (\\u5951\\u6728) \\u2013 Masse \\u00e0 cha\\u00eene.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau, au moins partiellement.Ces observations se cumulent avec son utilisation de la discipline Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer.  Iel re\\u00e7oit un soutien financier de la faction 8. Nous l\\u2019avons vu rencontrer en personne Murai Wako (\\u548c\\u5149). <\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Camp de deserteurs est bien li\\u00e9.e \\u00e0 cette localisation. Dans une gorge dissimul\\u00e9e parmi les pins tordus de Sh\\u014ddoshima, quelques hommes efflanqu\\u00e9s vivent en silence, fuyant le regard des p\\u00eacheurs et des samoura\\u00efs.\\r\\n            Ce sont des survivants de la d\\u00e9route d\\u2019Ishizuchi, dont ils racontent une version bien diff\\u00e9rente de celle propag\\u00e9e \\u00e0 la cour : l\\u2019avant-garde des Ch\\u014dsokabe, command\\u00e9e par Fujitaka Hosokawa, se serait retrouv\\u00e9e face aux fanatiques Ikko-ikki, qui auraient \\u00e9cras\\u00e9 ses lignes avant m\\u00eame que l\\u2019ordre de retraite ne puisse \\u00eatre donn\\u00e9.\\r\\n            Fujitaka, s\\u00e9par\\u00e9 de la force principale, aurait fui pr\\u00e9cipitamment vers Kyoto, mais aurait \\u00e9t\\u00e9 aper\\u00e7u captur\\u00e9 par un g\\u00e9n\\u00e9ral des forces du shogun Ashikaga. Ces aveux, \\u00e9touff\\u00e9s sous le fracas des r\\u00e9cits officiels, pourraient bien r\\u00e9habiliter l\\u2019honneur du daimy\\u014d d\\u00e9chu \\u2014 ou bouleverser les \\u00e9quilibres fragiles entre les clans.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Ge\\u00f4les des Kaizokush\\u016b se trouve dans cette zone. Creus\\u00e9es dans la falaise m\\u00eame, ces cavernes humides servent de prison aux captifs des Wako. \\r\\n        Des cha\\u00eenes rouill\\u00e9es pendent aux murs, et l\\u2019eau sal\\u00e9e suinte sans cesse, rongeant la volont\\u00e9 des enferm\\u00e9s. \\r\\n        Le silence n\\u2019y est troubl\\u00e9 que par les pas des ge\\u00f4liers \\u2014 ou les rires des pirates. Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Les donn\\u00e9es concordent : un.e D\\u00e9troit d\\u2019Okayama est bien associ\\u00e9.e \\u00e0 cet endroit. \\u00c9troit et venteux, ce d\\u00e9troit aux eaux tra\\u00eetresses s\\u00e9pare Shikoku de Honsh\\u016b.\\r\\n     Difficile de tenter cette travers\\u00e9e sans \\u00eatre \\u00e9pi\\u00e9 par les habitants de l\\u2019\\u00eele de Sh\\u014ddoshima.\\r\\n     Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d\\u2019\\u00eatre intercept\\u00e9 par les Kaizokush\\u016b.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Crique de Funakoshi est bien li\\u00e9.e \\u00e0 cette localisation. Cette crique isol\\u00e9e, souvent balay\\u00e9e par les vents, est connue des contrebandiers comme des p\\u00eacheurs.\\r\\n        Depuis quelques jours, un bruit court : un important \\u00e9missaire imp\\u00e9rial aurait \\u00e9t\\u00e9 intercept\\u00e9 par les pirates Wako et d\\u00e9tenu dans une grotte voisine, en attendant ran\\u00e7on ou silence.<\\/p>"}	2025-06-22 12:08:14.276264
46	27	1	4	7	5	3	4	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Grande Baie de Kochi. Ce.tte trimestre j'ai 5 en investigation et 3\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p>"}	2025-06-22 12:25:19.3413
48	29	1	6	6	5	5	4	passive	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 5\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Kusushi (\\u85ac\\u5e2b) \\u2013 M\\u00e9decin itin\\u00e9rant du nom de Haruki Inoue (25) qui enquete dans notre r\\u00e9gion. <\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p>","claim_report":"J\\u2019ai vu Ryota Yoshikawa tenter de prendre le contr\\u00f4le du territoire C\\u00f4te Est de Tokushima, mais la d\\u00e9fense l\\u2019a repouss\\u00e9.e brutalement.<br\\/>"}	2025-06-22 12:34:34.05655
34	10	1	7	6	7	6	3	passive	{}	{"life_report":"Ce.tte trimestre j'ai 7 en investigation et 6\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p>Je me suis rendu compte que Marco Venezio (13), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Tessen (\\u9244\\u6247) \\u2013 \\u00c9ventail de fer, surveille dans le coin. Je m\\u2019en m\\u00e9fie, iel vient de Portugal. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Bugaku (\\u821e\\u697d) \\u2013 Danse de cour, ce qui en fait un.e Bugy\\u014d (\\u5949\\u884c) \\u2013 Magistrat ou officier administratif un peu trop sp\\u00e9cial.e.En plus, sa famille a des liens avec la faction 7. <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Onmy\\u014dji (\\u9670\\u967d\\u5e2b) \\u2013 Devin et exorciste du nom de Venturo Attilio (28) qui revendique le quartier au nom de Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd) dans notre r\\u00e9gion. En plus, iel est originaire de Portugal. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin, mais cette information n\\u2019est pas si pertinente.<\\/p>Je me suis rendu compte que Taiga Tani (26), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court, enquete dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire, ce qui en fait un.e Marin Portugais un peu trop sp\\u00e9cial.e.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 4.  Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Des signes pointent vers la pr\\u00e9sence d\\u2019un.e \\u00c9curies de Kagawa, nous devons enqu\\u00eater davantage \\u00e0 ce sujet.<\\/p><p>Information int\\u00e9ressante : un.e Grande route et relais de poste est pr\\u00e9sent.e dans la zone. Relie Tokushima \\u00e0 K\\u014dchi en serpentant \\u00e0 travers les plaines fertiles du nord.\\r\\n     \\u00c0 chaque relais, les montures peuvent \\u00eatre chang\\u00e9es, et les messagers imp\\u00e9riaux y trouvent toujours une couche et un bol chaud.<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Maison close de Marugame est bien ici. \\u00c0 Marugame, dans une maison close r\\u00e9put\\u00e9e pour son sak\\u00e9 sucr\\u00e9 et ses \\u00e9ventails peints \\u00e0 la main, des courtisanes murmurent entre deux chansons.\\r\\n        L\\u2019une d\\u2019elles pr\\u00e9tend avoir lu une lettre scell\\u00e9e, confi\\u00e9e par un \\u00e9missaire enivr\\u00e9, annon\\u00e7ant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre \\u00e9clair contre les Ch\\u014dsokabe.<\\/p><p>Certains indices laissent penser qu\\u2019un.e Yashima-ji (\\u5c4b\\u5cf6\\u5bfa) -- Le chemin du Nirvana pourrait se cacher ici.<\\/p>","claim_report":"Venturo Attilio a voulu s\\u2019imposer au Prefecture de Kagawa, sans succ\\u00e8s. Iel a \\u00e9t\\u00e9 forc\\u00e9.e de battre en retraite.<br\\/>"}	2025-06-22 12:08:14.276264
45	26	1	7	4	4	6	6	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 6\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p>","claim_report":"J\\u2019ai vu Venturo Attilio tenter de prendre le contr\\u00f4le du territoire Prefecture de Kagawa, mais la d\\u00e9fense l\\u2019a repouss\\u00e9.e brutalement.<br\\/>Je pense que Venturo Attilio pensait avoir une chance au Prefecture de Kagawa. C\\u2019\\u00e9tait mal calcul\\u00e9, iel a \\u00e9chou\\u00e9.<br\\/>"}	2025-06-22 12:24:37.104424
107	21	3	1	8	10	6	4	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Ouest d\\u2019Ehime. Ce.tte trimestre j'ai 10 en investigation et 6\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Ouest d\\u2019Ehime.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Tsukai (\\u4f7f\\u3044) \\u2013 Messager rapide du nom de Riko Hoshino (17) qui enquete dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Ukiyo-e (\\u6d6e\\u4e16\\u7d75) \\u2013 Estampes artistiques, mais cette information n\\u2019est pas si pertinente.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 6. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Ky\\u016bjutsu (\\u5f13\\u8853) \\u2013 Art du tir \\u00e0 l\\u2019arc. Iel a de plus une maitrise de la discipline Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.  Ce qui veut dire que c\\u2019est un serviteur de Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p><p><\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Kuro-hatamoto (\\u9ed2\\u65d7\\u672c) \\u2013 Garde d\\u2019\\u00e9lite du nom de Nanami Morita (14) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Sensu (\\u6247\\u5b50) \\u2013 \\u00c9ventail pliable, mais cette information n\\u2019est pas si pertinente.Iel fait partie de la faction 4. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire.   Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Ouest d\\u2019Ehime :<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Ry\\u016bk\\u014d-ji (\\u7adc\\u5149\\u5bfa) -- Le chemin de l\\u2019illumination est bien li\\u00e9.e \\u00e0 cette localisation. Suspendu \\u00e0 flanc de montagne, Ry\\u016bk\\u014d-ji contemple la mer int\\u00e9rieure comme un dragon endormi. \\r\\n    On raconte qu\\u2019au lever du soleil, les brumes se d\\u00e9chirent et r\\u00e9v\\u00e8lent un \\u00e9clat dor\\u00e9 \\u00e9manant de l\\u2019autel. \\r\\n    Les sages disent que ceux qui y m\\u00e9ditent peuvent entrevoir la lumi\\u00e8re v\\u00e9ritable.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Il est possible d\\u2019organiser une mission pour faire dispara\\u00eetre ce probl\\u00e8me.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Port d\\u2019Uwajima se trouve dans cette zone. Un port anim\\u00e9 aux quais denses et bruyants, o\\u00f9 s\\u2019\\u00e9changent riz, bois, et rumeurs en provenance de Ky\\u016bsh\\u016b comme de Cor\\u00e9e.\\r\\n     Les marins disent que la brume y reste plus longtemps qu\\u2019ailleurs.<\\/p><p>Information int\\u00e9ressante : un.e Port marchand d\\u2019Uwajima est pr\\u00e9sent.e dans la zone. Des voiliers venus de la p\\u00e9ninsule cor\\u00e9enne accostent \\u00e0 Uwajima, charg\\u00e9s de r\\u00e9sines rares dont les parfums servent aux temples autant qu\\u2019aux intrigues.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Plaine d\\u2019Uwajima se trouve dans cette zone. Les vastes plaines d\\u2019Uwajima semblent paisibles sous le soleil, entre cultures clairsem\\u00e9es et sentiers oubli\\u00e9s.\\r\\n        Mais depuis plusieurs semaines, des groupes d\\u2019hommes en haillons, arm\\u00e9s de fourches, de b\\u00e2tons ou de sabres grossiers, y ont \\u00e9t\\u00e9 aper\\u00e7us.\\r\\n        Ces paysans ne sont pas d\\u2019ici : ils avancent discr\\u00e8tement, se regroupent \\u00e0 la tomb\\u00e9e du jour, et pr\\u00eachent un discours de r\\u00e9volte contre les samoura\\u00efs.\\r\\n        Ce sont les avant-gardes des Ikko-ikki, infiltr\\u00e9s depuis le continent par voie maritime.\\r\\n        D\\u00e9couvrir quel est le chef qui les unis pourrait permettre d\\u2019agir avant qu\\u2019il ne soit trop tard.<\\/p>"}	2025-06-22 13:35:57.582192
122	36	3	9	8	10	3	5	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Ile de Sh\\u014ddoshima. Ce.tte trimestre j'ai 10 en investigation et 3\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p><p><\\/p>On a suivi Bianca Venezio (41) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de enqueter, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Pr\\u00eatre chr\\u00e9tien mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Mala-no-fuku (\\u6469\\u7f85\\u306e\\u670d) \\u2013 V\\u00eatement d\\u2019asc\\u00e8te.En plus, iel est originaire de Portugal. Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Waka (\\u548c\\u6b4c) \\u2013 Po\\u00e9sie classique, au moins partiellement.En plus, sa famille a des liens avec la faction 4. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p><p>Les donn\\u00e9es concordent : un.e Crique de Funakoshi est bien associ\\u00e9.e \\u00e0 cet endroit. Cette crique isol\\u00e9e, souvent balay\\u00e9e par les vents, est connue des contrebandiers comme des p\\u00eacheurs.\\r\\n        Depuis quelques jours, un bruit court : un important \\u00e9missaire imp\\u00e9rial aurait \\u00e9t\\u00e9 intercept\\u00e9 par les pirates Wako et d\\u00e9tenu dans une grotte voisine, en attendant ran\\u00e7on ou silence.<\\/p><p>Information int\\u00e9ressante : un.e D\\u00e9troit d\\u2019Okayama est pr\\u00e9sent.e dans la zone. \\u00c9troit et venteux, ce d\\u00e9troit aux eaux tra\\u00eetresses s\\u00e9pare Shikoku de Honsh\\u016b.\\r\\n     Difficile de tenter cette travers\\u00e9e sans \\u00eatre \\u00e9pi\\u00e9 par les habitants de l\\u2019\\u00eele de Sh\\u014ddoshima.\\r\\n     Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d\\u2019\\u00eatre intercept\\u00e9 par les Kaizokush\\u016b.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Vieux temple est bien li\\u00e9.e \\u00e0 cette localisation. Ce temple oubli\\u00e9, dissimul\\u00e9 dans un vallon brumeux de Sh\\u014ddoshima, semble abandonn\\u00e9 depuis des d\\u00e9cennies.\\r\\n         Pourtant, chaque cr\\u00e9puscule, les accords las d\\u2019un biwa r\\u00e9sonnent sous les poutres vermoulues, port\\u00e9s par une brise douce o\\u00f9 flotte un parfum de sak\\u00e9 ti\\u00e8de.\\r\\n         Pourtant nul pr\\u00eatre et nul p\\u00e8lerin en vue.\\r\\n        (Pour explorer davantage ce lieu, allez voir un orga !) Il est possible d\\u2019organiser une mission pour faire dispara\\u00eetre ce probl\\u00e8me.<\\/p><p>Information int\\u00e9ressante : un.e Camp de deserteurs est pr\\u00e9sent.e dans la zone. Dans une gorge dissimul\\u00e9e parmi les pins tordus de Sh\\u014ddoshima, quelques hommes efflanqu\\u00e9s vivent en silence, fuyant le regard des p\\u00eacheurs et des samoura\\u00efs.\\r\\n            Ce sont des survivants de la d\\u00e9route d\\u2019Ishizuchi, dont ils racontent une version bien diff\\u00e9rente de celle propag\\u00e9e \\u00e0 la cour : l\\u2019avant-garde des Ch\\u014dsokabe, command\\u00e9e par Fujitaka Hosokawa, se serait retrouv\\u00e9e face aux fanatiques Ikko-ikki, qui auraient \\u00e9cras\\u00e9 ses lignes avant m\\u00eame que l\\u2019ordre de retraite ne puisse \\u00eatre donn\\u00e9.\\r\\n            Fujitaka, s\\u00e9par\\u00e9 de la force principale, aurait fui pr\\u00e9cipitamment vers Kyoto, mais aurait \\u00e9t\\u00e9 aper\\u00e7u captur\\u00e9 par un g\\u00e9n\\u00e9ral des forces du shogun Ashikaga. Ces aveux, \\u00e9touff\\u00e9s sous le fracas des r\\u00e9cits officiels, pourraient bien r\\u00e9habiliter l\\u2019honneur du daimy\\u014d d\\u00e9chu \\u2014 ou bouleverser les \\u00e9quilibres fragiles entre les clans.<\\/p>"}	2025-06-22 13:35:57.582192
108	22	3	9	5	12	5	3	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Ile de Sh\\u014ddoshima. Ce.tte trimestre j'ai 12 en investigation et 5\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Mimiiri (\\u8033\\u5165) \\u2013 Informateur discret du nom de Tomo Okada (36) qui enquete dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Ukiyo-e (\\u6d6e\\u4e16\\u7d75) \\u2013 Estampes artistiques, mais cette information n\\u2019est pas si pertinente.Iel fait partie de la faction 8. En plus, iel maitrise l\\u2019art du Shigin (\\u8a69\\u541f) \\u2013 Chant po\\u00e9tique.   <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas du nom de Ayaka Noguchi (15) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Chigiriki (\\u5951\\u6728) \\u2013 Masse \\u00e0 cha\\u00eene, mais cette information n\\u2019est pas si pertinente.Iel fait partie de la faction 8. Iel a de plus une maitrise de la discipline Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer.   Nous l\\u2019avons vu rencontrer en personne Murai Wako (\\u548c\\u5149). <\\/p>Je me suis rendu compte que Bianca Venezio (41), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Mala-no-fuku (\\u6469\\u7f85\\u306e\\u670d) \\u2013 V\\u00eatement d\\u2019asc\\u00e8te, enquete dans le coin. Je m\\u2019en m\\u00e9fie, iel vient de Portugal. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Waka (\\u548c\\u6b4c) \\u2013 Po\\u00e9sie classique, ce qui en fait un.e Pr\\u00eatre chr\\u00e9tien un peu trop sp\\u00e9cial.e. Iel re\\u00e7oit un soutien financier de la faction 4. Ce qui veut dire que c\\u2019est un serviteur de La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Crique de Funakoshi est bien ici. Cette crique isol\\u00e9e, souvent balay\\u00e9e par les vents, est connue des contrebandiers comme des p\\u00eacheurs.\\r\\n        Depuis quelques jours, un bruit court : un important \\u00e9missaire imp\\u00e9rial aurait \\u00e9t\\u00e9 intercept\\u00e9 par les pirates Wako et d\\u00e9tenu dans une grotte voisine, en attendant ran\\u00e7on ou silence.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e D\\u00e9troit d\\u2019Okayama. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : \\u00c9troit et venteux, ce d\\u00e9troit aux eaux tra\\u00eetresses s\\u00e9pare Shikoku de Honsh\\u016b.\\r\\n     Difficile de tenter cette travers\\u00e9e sans \\u00eatre \\u00e9pi\\u00e9 par les habitants de l\\u2019\\u00eele de Sh\\u014ddoshima.\\r\\n     Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d\\u2019\\u00eatre intercept\\u00e9 par les Kaizokush\\u016b.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Vieux temple se trouve dans cette zone. Ce temple oubli\\u00e9, dissimul\\u00e9 dans un vallon brumeux de Sh\\u014ddoshima, semble abandonn\\u00e9 depuis des d\\u00e9cennies.\\r\\n         Pourtant, chaque cr\\u00e9puscule, les accords las d\\u2019un biwa r\\u00e9sonnent sous les poutres vermoulues, port\\u00e9s par une brise douce o\\u00f9 flotte un parfum de sak\\u00e9 ti\\u00e8de.\\r\\n         Pourtant nul pr\\u00eatre et nul p\\u00e8lerin en vue.\\r\\n        (Pour explorer davantage ce lieu, allez voir un orga !) Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Les donn\\u00e9es concordent : un.e Ge\\u00f4les des Kaizokush\\u016b est bien associ\\u00e9.e \\u00e0 cet endroit. Creus\\u00e9es dans la falaise m\\u00eame, ces cavernes humides servent de prison aux captifs des Wako. \\r\\n        Des cha\\u00eenes rouill\\u00e9es pendent aux murs, et l\\u2019eau sal\\u00e9e suinte sans cesse, rongeant la volont\\u00e9 des enferm\\u00e9s. \\r\\n        Le silence n\\u2019y est troubl\\u00e9 que par les pas des ge\\u00f4liers \\u2014 ou les rires des pirates. Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.Ce lieu contient : <ul><li><strong>Kunichika(\\u56fd\\u89aa) Ch\\u014dsokabe(\\u9577\\u5b97\\u6211\\u90e8) bless\\u00e9, bris\\u00e9, il vit toujours<\\/strong>: L\\u2019ancien seigneur de Shikoku n\\u2019est pas tomb\\u00e9 \\u00e0 la guerre \\u2014 il est retenu ici, gard\\u00e9e par ceux qui craignent son retour.<\\/li><\\/ul><\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Camp de deserteurs se trouve dans cette zone. Dans une gorge dissimul\\u00e9e parmi les pins tordus de Sh\\u014ddoshima, quelques hommes efflanqu\\u00e9s vivent en silence, fuyant le regard des p\\u00eacheurs et des samoura\\u00efs.\\r\\n            Ce sont des survivants de la d\\u00e9route d\\u2019Ishizuchi, dont ils racontent une version bien diff\\u00e9rente de celle propag\\u00e9e \\u00e0 la cour : l\\u2019avant-garde des Ch\\u014dsokabe, command\\u00e9e par Fujitaka Hosokawa, se serait retrouv\\u00e9e face aux fanatiques Ikko-ikki, qui auraient \\u00e9cras\\u00e9 ses lignes avant m\\u00eame que l\\u2019ordre de retraite ne puisse \\u00eatre donn\\u00e9.\\r\\n            Fujitaka, s\\u00e9par\\u00e9 de la force principale, aurait fui pr\\u00e9cipitamment vers Kyoto, mais aurait \\u00e9t\\u00e9 aper\\u00e7u captur\\u00e9 par un g\\u00e9n\\u00e9ral des forces du shogun Ashikaga. Ces aveux, \\u00e9touff\\u00e9s sous le fracas des r\\u00e9cits officiels, pourraient bien r\\u00e9habiliter l\\u2019honneur du daimy\\u014d d\\u00e9chu \\u2014 ou bouleverser les \\u00e9quilibres fragiles entre les clans.<\\/p>"}	2025-06-22 13:35:57.582192
129	43	3	4	1	10	4	3	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 10 en investigation et 4\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p>J\\u2019ai vu un.e Kas\\u014d (\\u82b1\\u5320) \\u2013 Artiste florale du nom de Nanami Koga (31) qui revendique le quartier au nom de Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd) dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Jitte (\\u5341\\u624b) \\u2013 Arme de police mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral.Iel fait partie de la faction 4. En plus, iel maitrise l\\u2019art du Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire.   <\\/p>On a suivi Hinako Ichikawa (32) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de revendiquer le quartier au nom de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d), ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Kuro-hatamoto (\\u9ed2\\u65d7\\u672c) \\u2013 Garde d\\u2019\\u00e9lite mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser H\\u014djutsu (\\u7832\\u8853) \\u2013 Art des armes \\u00e0 feu (tepp\\u014d), au moins partiellement, de plus iel laisse penser qu\\u2019iel a un.e Encens Cor\\u00e9en.Iel travaille avec la faction 5. Iel a de plus une maitrise de la discipline Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire. Ce qui veut dire que c\\u2019est un serviteur de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>J\\u2019ai vu un.e N\\u014dgakushi (\\u80fd\\u697d\\u5e2b) \\u2013 Acteur de th\\u00e9\\u00e2tre N\\u014d du nom de Claire Richard (12) qui surveille dans ma zone d\\u2019action. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de France. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Hy\\u014dshigi (\\u62cd\\u5b50\\u6728) \\u2013 Claves en bois mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari).Iel travaille avec la faction 8. Ces observations se cumulent avec son utilisation de la discipline Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau. Ces observations se cumulent avec son utilisation de la discipline Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Murai Wako (\\u548c\\u5149). <\\/p>Je me suis rendu compte que Emi Nagano (24), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e N\\u014dky\\u014dch\\u014d (\\u7d0d\\u7d4c\\u5e33) \\u2013 Un carnet de p\\u00e8lerinage, surveille dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Reiki \\/ Kujikiri (\\u970a\\u6c17 \\/ \\u4e5d\\u5b57\\u5207\\u308a) \\u2013 Pratiques \\u00e9sot\\u00e9riques, ce qui en fait un.e Miko (\\u5deb\\u5973) \\u2013 Servante shint\\u014d un peu trop sp\\u00e9cial.e.Iel travaille avec la faction 6. A partir de l\\u00e0 nous avons pu remonter jusqu\\u2019\\u00e0 Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>On a suivi Koji Nagano (40) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de enqueter, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Sodegarami (\\u8896\\u6426) \\u2013 Garde sp\\u00e9cialis\\u00e9 mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Kakute (\\u89d2\\u624b) \\u2013 Anneau \\u00e0 pointes.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), au moins partiellement.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 4.  Ce qui veut dire que c\\u2019est un serviteur de La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Chikurin-ji (\\u7af9\\u6797\\u5bfa) -- Le chemin de l\\u2019asc\\u00e8se. Voici ce que nous avons appris : Perch\\u00e9 au sommet d\\u2019une colline surplombant la baie, le temple veille parmi les bambous. \\r\\n    Les moines y pratiquent une asc\\u00e8se rigoureuse, veillant jour et nuit face \\u00e0 l\\u2019oc\\u00e9an sans fin. \\r\\n    Le vent porte leurs chants jusqu\\u2019aux barques des p\\u00eacheurs, comme des pri\\u00e8res sal\\u00e9es.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Sanctuaire bris\\u00e9 de Hiwasa. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Surplombant la mer, les ruines du sanctuaire de Hiwasa sont battues par les embruns.\\r\\n        On dit que des pr\\u00eatres \\u00e9trangers y ont \\u00e9t\\u00e9 vus de nuit, en compagnie d\\u2019\\u00e9missaires du clan Ch\\u014dsokabe.\\r\\n        La rumeur parle d\\u2019un pacte impie : en \\u00e9change d\\u2019armes \\u00e0 feu venues de Nagasaki, le clan accepterait d\\u2019abriter des convertis clandestins.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Port de Kochi est bien li\\u00e9.e \\u00e0 cette localisation. Prot\\u00e9g\\u00e9 par une anse naturelle, ce port militaire et marchand voit passer jonques, bateaux de guerre et pirates repenti.\\r\\n      Son arsenal est surveill\\u00e9 nuit et jour par des ashigaru en armure sombre.\\r\\n      On dit que le clan Ch\\u014dsokabe y cache des objects illegaux import\\u00e9 d\\u2019ailleurs.<\\/p>","claim_report":"Hinako Ichikawa a pris Grande Baie de Kochi par la force. Iel n\\u2019a laiss\\u00e9 aucune chance aux d\\u00e9fenseurs.<br\\/>Nanami Koga a voulu s\\u2019imposer au Grande Baie de Kochi, sans succ\\u00e8s. Iel a \\u00e9t\\u00e9 forc\\u00e9.e de battre en retraite.<br\\/>"}	2025-06-22 14:15:56.692069
32	5	1	2	6	6	3	2	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 3\\/2 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p><p><\\/p>J\\u2019ai vu un.e Sarugakushi (\\u733f\\u697d\\u5e2b) \\u2013 Artiste de rue ou com\\u00e9dien du nom de Miyu Hirano (11) qui enquete dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e K\\u014dd\\u014dgu (\\u9999\\u9053\\u5177) \\u2013 Ustensiles du k\\u014dd\\u014d mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p><p>Un rapport fragmentaire mentionne un.e Mt Ishizuchi comme \\u00e9tant cach\\u00e9.e dans ce territoire.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Sanctuaire des Pins Br\\u00fbl\\u00e9s se trouve dans cette zone. Dans un ancien sanctuaire shint\\u014d dont les piliers carbonis\\u00e9s r\\u00e9sistent au temps, des p\\u00e8lerins affirment avoir vu un artefact \\u00e9trange cach\\u00e9 sous l\\u2019autel \\u2014 une croix d\\u2019argent sertie d\\u2019inscriptions latines.\\r\\n        Les paysans parlent d\\u2019un pr\\u00eatre chr\\u00e9tien, et de l\\u2019Inquisition j\\u00e9suite elle-m\\u00eame. Mais les recherches men\\u00e9es par les yamabushi locaux n\\u2019ont rien r\\u00e9v\\u00e9l\\u00e9 de probant.<\\/p>"}	2025-06-22 12:08:14.276264
33	1	1	2	1	8	7	8	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 7\\/8 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p>J\\u2019ai vu un.e Reishi (\\u970a\\u5e2b) \\u2013 M\\u00e9dium ou exorciste du nom de Ren-j\\u014d fils de Rennyo (\\u84ee\\u5982) (5) qui enquete dans ma zone d\\u2019action. En plus, iel est originaire de Honshu - Kyoto. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Tokkuri (\\u5fb3\\u5229) \\u2013 Bouteille \\u00e0 sak\\u00e9 mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de K\\u014dd\\u014d (\\u9999\\u9053) \\u2013 Voie de l\\u2019encens.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 6.  <\\/p>On a suivi Miyu Hirano (11) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de enqueter, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Sarugakushi (\\u733f\\u697d\\u5e2b) \\u2013 Artiste de rue ou com\\u00e9dien mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e K\\u014dd\\u014dgu (\\u9999\\u9053\\u5177) \\u2013 Ustensiles du k\\u014dd\\u014d.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral, au moins partiellement.En plus, sa famille a des liens avec la faction 5. En plus, iel maitrise l\\u2019art du Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p><p>Nos informateurs.rices \\u00e9voquent la d\\u00e9couverte potentielle d\\u2019un.e Port de Matsuyama dans cette zone.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Mt Ishizuchi se trouve dans cette zone. Plus haut sommet de l\\u2019\\u00eele, le mont Ishizuchi domine les vall\\u00e9es alentour comme un sabre dress\\u00e9 vers le ciel.\\r\\n     On dit qu\\u2019un p\\u00e8lerinage ancien y conduit \\u00e0 une dalle sacr\\u00e9e o\\u00f9 les esprits s\\u2019expriment lorsque les vents tournent.<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Sanctuaire des Pins Br\\u00fbl\\u00e9s est bien ici. Dans un ancien sanctuaire shint\\u014d dont les piliers carbonis\\u00e9s r\\u00e9sistent au temps, des p\\u00e8lerins affirment avoir vu un artefact \\u00e9trange cach\\u00e9 sous l\\u2019autel \\u2014 une croix d\\u2019argent sertie d\\u2019inscriptions latines.\\r\\n        Les paysans parlent d\\u2019un pr\\u00eatre chr\\u00e9tien, et de l\\u2019Inquisition j\\u00e9suite elle-m\\u00eame. Mais les recherches men\\u00e9es par les yamabushi locaux n\\u2019ont rien r\\u00e9v\\u00e9l\\u00e9 de probant.<\\/p>"}	2025-06-22 12:08:14.276264
28	11	1	2	5	5	5	4	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Montagnes d\\u2019Ehime. Ce.tte trimestre j'ai 5 en investigation et 5\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p><p>Il semblerait qu\\u2019un.e Sanctuaire des Pins Br\\u00fbl\\u00e9s se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>"}	2025-06-22 12:08:14.276264
130	44	3	6	5	5	6	7	passive	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 6\\/7 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p>"}	2025-06-22 14:16:57.301338
23	13	1	7	7	5	5	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 5\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p><p><\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Marin Portugais du nom de Taiga Tani (26) qui enquete dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court, mais cette information n\\u2019est pas si pertinente.<\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Il semblerait qu\\u2019un.e Maison close de Marugame se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>"}	2025-06-22 12:08:14.276264
65	21	2	9	8	7	5	3	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Grande Baie de Kochi. J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Ile de Sh\\u014ddoshima. Ce.tte trimestre j'ai 7 en investigation et 5\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p><p>Les donn\\u00e9es concordent : un.e Crique de Funakoshi est bien associ\\u00e9.e \\u00e0 cet endroit. Cette crique isol\\u00e9e, souvent balay\\u00e9e par les vents, est connue des contrebandiers comme des p\\u00eacheurs.\\r\\n        Depuis quelques jours, un bruit court : un important \\u00e9missaire imp\\u00e9rial aurait \\u00e9t\\u00e9 intercept\\u00e9 par les pirates Wako et d\\u00e9tenu dans une grotte voisine, en attendant ran\\u00e7on ou silence.<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e D\\u00e9troit d\\u2019Okayama. Voici ce que nous avons appris : \\u00c9troit et venteux, ce d\\u00e9troit aux eaux tra\\u00eetresses s\\u00e9pare Shikoku de Honsh\\u016b.\\r\\n     Difficile de tenter cette travers\\u00e9e sans \\u00eatre \\u00e9pi\\u00e9 par les habitants de l\\u2019\\u00eele de Sh\\u014ddoshima.\\r\\n     Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d\\u2019\\u00eatre intercept\\u00e9 par les Kaizokush\\u016b.<\\/p>"}	2025-06-22 12:42:26.352538
81	33	2	2	8	2	5	6	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 2 en investigation et 5\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p>"}	2025-06-22 12:51:54.859037
21	3	1	8	1	8	8	7	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 8\\/7 en attaque\\/d\\u00e9fense.","secrets_report":"<p>Dans le territoire Ile d\\u2019Awaji :<\\/p><p>Un rapport fragmentaire mentionne un.e Camp de deserteurs comme \\u00e9tant cach\\u00e9.e dans ce territoire.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Rumeur de la bataille est bien li\\u00e9.e \\u00e0 cette localisation. Les p\\u00eacheurs d\\u2019Awaji parlent encore d\\u2019un combat f\\u00e9roce dans les collines, entre troupes en fuite et rebelles aux visages peints. Certains affirment avoir vu le ciel s\\u2019embraser au-dessus du temple abandonn\\u00e9.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Phare abandonn\\u00e9 de Minokoshi est bien li\\u00e9.e \\u00e0 cette localisation. Diss\\u00e9min\\u00e9 au bout d\\u2019une presqu\\u2019\\u00eele battue par les vents, le vieux phare de Minokoshi n\\u2019est plus qu\\u2019un squelette de pierre rong\\u00e9 par le sel.\\r\\n        Pourtant, certains p\\u00eacheurs affirment y voir passer des silhouettes arm\\u00e9es \\u00e0 la tomb\\u00e9e de la nuit.\\r\\n        La rumeur court qu\\u2019un prisonnier de valeur y est gard\\u00e9 en secret par le clan Ch\\u014dsokabe, un traitre captur\\u00e9 lors des affrontements r\\u00e9cents.<\\/p>"}	2025-06-22 12:08:14.276264
37	7	1	10	2	9	7	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 9 en investigation et 7\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Geisha (\\u82b8\\u8005) \\u2013 Artiste raffin\\u00e9e du nom de Natsuki Nobunaga (22) qui enquete dans notre r\\u00e9gion. <\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Les donn\\u00e9es concordent : un.e Cour imp\\u00e9riale est bien associ\\u00e9.e \\u00e0 cet endroit. Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p><p>Les donn\\u00e9es concordent : un.e Suzaku Mon est bien associ\\u00e9.e \\u00e0 cet endroit. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Information int\\u00e9ressante : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" est pr\\u00e9sent.e dans la zone. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p>"}	2025-06-22 12:08:14.276264
36	9	1	10	2	12	3	4	passive	{}	{"life_report":"Ce.tte trimestre j'ai 12 en investigation et 3\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p>J\\u2019ai vu un.e Geisha (\\u82b8\\u8005) \\u2013 Artiste raffin\\u00e9e du nom de Natsuki Nobunaga (22) qui enquete dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Hy\\u014dshigi (\\u62cd\\u5b50\\u6728) \\u2013 Claves en bois mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Gagaku (\\u96c5\\u697d) \\u2013 Musique de cour.Iel fait partie de la faction 5.   A partir de l\\u00e0 nous avons pu remonter jusqu\\u2019\\u00e0 Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Cour imp\\u00e9riale est bien li\\u00e9.e \\u00e0 cette localisation. Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p><p>Les donn\\u00e9es concordent : un.e Suzaku Mon est bien associ\\u00e9.e \\u00e0 cet endroit. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" se trouve dans cette zone. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p>"}	2025-06-22 12:08:14.276264
38	6	1	10	2	8	7	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 7\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Nous avons identifi\\u00e9 une information int\\u00e9ressante : un.e Cour imp\\u00e9riale serait pr\\u00e9sent.e dans la zone.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Suzaku Mon est bien li\\u00e9.e \\u00e0 cette localisation. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Information int\\u00e9ressante : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" est pr\\u00e9sent.e dans la zone. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p>"}	2025-06-22 12:08:14.276264
26	17	1	5	6	5	6	2	passive	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 6\\/2 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Vall\\u00e9es d\\u2019Iya et d\\u2019Obok\\u00e9 de Tokushima.<\\/p>Je me suis rendu compte que Hikari Kawano (23), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Jirei (\\u6301\\u9234) \\u2013 Une petite clochette, enquete dans le coin. En plus, iel est originaire de Honshu - Hiroshima. <\\/p>","secrets_report":"<p>Dans le territoire Vall\\u00e9es d\\u2019Iya et d\\u2019Obok\\u00e9 de Tokushima :<\\/p>"}	2025-06-22 12:08:14.276264
42	23	1	5	5	5	4	4	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 4\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Vall\\u00e9es d\\u2019Iya et d\\u2019Obok\\u00e9 de Tokushima.<\\/p>J\\u2019ai trouv\\u00e9 Riko Hoshino (17), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Tsukai (\\u4f7f\\u3044) \\u2013 Messager rapide avec un.e Ukiyo-e (\\u6d6e\\u4e16\\u7d75) \\u2013 Estampes artistiques.<\\/p>","secrets_report":"<p>Dans le territoire Vall\\u00e9es d\\u2019Iya et d\\u2019Obok\\u00e9 de Tokushima :<\\/p>"}	2025-06-22 12:13:32.569515
20	19	1	6	7	7	2	4	passive	{}	{"life_report":"Ce.tte trimestre j'ai 7 en investigation et 2\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p>On a suivi Haruki Inoue (25) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de enqueter, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Kusushi (\\u85ac\\u5e2b) \\u2013 M\\u00e9decin itin\\u00e9rant mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Kusarigama (\\u9396\\u938c) \\u2014 Arme compos\\u00e9e d\\u2019une faucille attach\\u00e9e \\u00e0 une cha\\u00eene lest\\u00e9e.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Waka (\\u548c\\u6b4c) \\u2013 Po\\u00e9sie classique, au moins partiellement. Iel re\\u00e7oit un soutien financier de la faction 4. <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Kagemusha (\\u5f71\\u6b66\\u8005) \\u2013 Sosie du seigneur du nom de Ryota Yoshikawa (18) qui revendique le quartier au nom de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d) dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise H\\u014djutsu (\\u7832\\u8853) \\u2013 Art des armes \\u00e0 feu (tepp\\u014d). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court, mais cette information n\\u2019est pas si pertinente.<\\/p>J\\u2019ai vu un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas du nom de Arisa Komatsu (29) qui surveille dans ma zone d\\u2019action. En plus, iel est originaire de Honshu - Okayama. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Shamisen (\\u4e09\\u5473\\u7dda) \\u2013 Instrument \\u00e0 cordes mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.Iel travaille avec la faction 6. <\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p><p>Information int\\u00e9ressante : un.e Port de Tokushima est pr\\u00e9sent.e dans la zone. Carrefour maritime entre Honsh\\u016b et Shikoku, le port de Tokushima bruisse de dialectes et de voiles \\u00e9trang\\u00e8res.\\r\\n     Dans les ruelles proches du march\\u00e9, on parle parfois espagnol, ou latin, \\u00e0 voix basse.<\\/p>","claim_report":"Ryota Yoshikawa a voulu s\\u2019imposer au C\\u00f4te Est de Tokushima, sans succ\\u00e8s. Iel a \\u00e9t\\u00e9 forc\\u00e9.e de battre en retraite.<br\\/>"}	2025-06-22 12:08:14.276264
131	45	3	4	6	6	7	3	dead	[{"attackScope":"worker","attackID":27},{"attackScope":"worker","attackID":31},{"attackScope":"worker","attackID":12}]	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 7\\/3 en attaque\\/d\\u00e9fense.<p>Depuis la semaine 3, plus aucun signal ni message de cet agent.<\\/p><p>Nous avons perdu toute communication avec cet agent depuis la semaine 3.<\\/p>","attack_report":"<p>Mission accomplie : Shiori Kiriyama est d\\u00e9sormais une simple note dans les rouleaux de l\\u2019histoire.<\\/p><p>D\\u00e9but de la mission : Nanami Koga. [Le rapport n\\u2019a jamais \\u00e9t\\u00e9 termin\\u00e9.]<\\/p><p>Le groupe envoy\\u00e9 pour neutraliser Claire Richard n\\u2019est jamais revenu.<\\/p>"}	2025-06-22 14:23:47.645954
77	16	2	3	4	0	0	0	dead	[{"attackScope":"worker","attackID":2}]	{}	2025-06-22 12:42:26.352538
79	31	2	4	4	6	5	6	claim	{"claim_controller_id":"7"}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 5\\/6 en attaque\\/d\\u00e9fense.","claim_report":"L\\u2019assaut sur Grande Baie de Kochi a \\u00e9t\\u00e9 un \\u00e9chec. Les forces en place ont tenu bon."}	2025-06-22 12:50:06.927577
50	2	2	3	1	6	9	9	passive	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 9\\/9 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cap sud de Kochi.<\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p>"}	2025-06-22 12:42:26.352538
78	30	2	8	5	4	5	5	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 5\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile d\\u2019Awaji.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Ile d\\u2019Awaji :<\\/p>"}	2025-06-22 12:49:04.881008
61	15	2	9	8	3	9	10	passive	{}	{"life_report":"Ce.tte trimestre j'ai 3 en investigation et 9\\/10 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p>"}	2025-06-22 12:42:26.352538
85	37	2	2	6	4	5	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 5\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p><p><\\/p>Je me suis rendu compte que Miki Yamaguchi (33), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Furushiki (\\u98a8\\u5442\\u6577) \\u2013 Carr\\u00e9 de tissu, enquete dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer, ce qui en fait un.e Shihan (\\u5e2b\\u7bc4) \\u2013 Ma\\u00eetre instructeur un peu trop sp\\u00e9cial.e.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 8.  <\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p>"}	2025-06-22 12:53:16.234826
74	28	2	7	7	4	8	6	claim	{"claim_controller_id":"7"}	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 8\\/6 en attaque\\/d\\u00e9fense.","claim_report":"La mission de domination de Prefecture de Kagawa n\\u2019a pas abouti. Trop de r\\u00e9sistance \\u00e0 notre autorit\\u00e9 sur place."}	2025-06-22 12:42:26.352538
76	29	2	6	6	4	5	4	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 5\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p><p><\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p>"}	2025-06-22 12:42:26.352538
80	32	2	1	5	3	9	7	claim	{"claim_controller_id":"5"}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Ouest d\\u2019Ehime. Ce.tte trimestre j'ai 3 en investigation et 9\\/7 en attaque\\/d\\u00e9fense.","claim_report":"C\\u00f4te Ouest d\\u2019Ehime est tomb\\u00e9.e sous votre coupe."}	2025-06-22 12:50:29.055092
55	7	2	10	2	9	7	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 9 en investigation et 7\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p>Je me suis rendu compte que Rina Ichinose (20), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Zudabukuro (\\u982d\\u9640\\u888b) \\u2013 Une besace de p\\u00e8lerin, enquete dans le coin. En plus, iel est originaire de Honshu - Osaka. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau, ce qui en fait un.e Mimiiri (\\u8033\\u5165) \\u2013 Informateur discret un peu trop sp\\u00e9cial.e.Iel fait partie de la faction 8. Iel a de plus une maitrise de la discipline Shigin (\\u8a69\\u541f) \\u2013 Chant po\\u00e9tique.   Nous l\\u2019avons vu rencontrer en personne Murai Wako (\\u548c\\u5149). <\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" se trouve dans cette zone. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Suzaku Mon est bien li\\u00e9.e \\u00e0 cette localisation. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Information int\\u00e9ressante : un.e Cour imp\\u00e9riale est pr\\u00e9sent.e dans la zone. Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p>"}	2025-06-22 12:42:26.352538
56	8	2	10	2	4	11	8	passive	{}	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 11\\/8 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p>"}	2025-06-22 12:42:26.352538
59	12	2	4	8	5	7	7	passive	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Grande Baie de Kochi. Ce.tte trimestre j'ai 5 en investigation et 7\\/7 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p><p><\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p>","claim_report":"Nanami Koga a voulu s\\u2019imposer au Grande Baie de Kochi, sans succ\\u00e8s. Iel a \\u00e9t\\u00e9 forc\\u00e9.e de battre en retraite.<br\\/>"}	2025-06-22 12:42:26.352538
82	34	2	10	4	10	3	3	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 10 en investigation et 3\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p><p><\\/p>Nous avons rep\\u00e9r\\u00e9 le possesseur d\\u2019un.e Cheval Kagawa du nom de Renry\\u016b(\\u84ee\\u7adc) Takeda(\\u6b66\\u7530) (8) qui surveille dans notre r\\u00e9gion. Je m\\u2019en m\\u00e9fie, iel vient de Honshu - Kyoto. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie. Iel poss\\u00e8de aussi un.e Katana (\\u5200) \\u2013 L\\u2019arme embl\\u00e9matique du samoura\\u00ef, mais cette information n\\u2019est pas pertinente. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire. Iel a de plus une maitrise de la discipline Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement.  Iel re\\u00e7oit un soutien financier de la faction 2. A partir de l\\u00e0 nous avons pu remonter jusqu\\u2019\\u00e0 Yoshiteru (\\u7fa9\\u8f1d) Ashikaga (\\u8db3\\u5229). <\\/p>Je me suis rendu compte que Lady Ibara(\\u8328\\u306e\\u7d05) (7), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Shamisen (\\u4e09\\u5473\\u7dda) \\u2013 Instrument \\u00e0 cordes, surveille dans le coin. En plus, iel est originaire de Honshu - Kyoto. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Ky\\u016bjutsu (\\u5f13\\u8853) \\u2013 Art du tir \\u00e0 l\\u2019arc, ce qui en fait un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas un peu trop sp\\u00e9cial.e et nous observons qu\\u2019iel poss\\u00e8de un.e Th\\u00e9 d\\u2019Obok\\u00e9 et d\\u2019Iya.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Mimiiri (\\u8033\\u5165) \\u2013 Informateur discret du nom de Rina Ichinose (20) qui enquete dans notre r\\u00e9gion. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de Honshu - Osaka. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Zudabukuro (\\u982d\\u9640\\u888b) \\u2013 Une besace de p\\u00e8lerin, mais cette information n\\u2019est pas si pertinente.En plus, sa famille a des liens avec la faction 8. Ces observations se cumulent avec son utilisation de la discipline Shigin (\\u8a69\\u541f) \\u2013 Chant po\\u00e9tique. Ce qui veut dire que c\\u2019est un serviteur de Murai Wako (\\u548c\\u5149). <\\/p>Je me suis rendu compte que quelqu\\u2019un poss\\u00e9dant un.e Armure en fer de Kochi surveille dans le coin. On l\\u2019a entendu.e se faire appeler Lord Asakura(\\u671d\\u5009) Mitsunao(\\u5149\\u76f4) (6). En plus, iel est originaire de Honshu - Kyoto. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a des capacit\\u00e9s de surveille, ce qui en fait un Ts\\u016bshi (\\u901a\\u4f7f) \\u2013 Diplomate ou \\u00e9missaire un peu trop sp\\u00e9cial.Iel travaille avec la faction 2. Iel a de plus une maitrise de la discipline Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire. Iel a de plus une maitrise de la discipline Bugaku (\\u821e\\u697d) \\u2013 Danse de cour. <\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Information int\\u00e9ressante : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" est pr\\u00e9sent.e dans la zone. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p><p>Information int\\u00e9ressante : un.e Suzaku Mon est pr\\u00e9sent.e dans la zone. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Nos informateurs.rices \\u00e9voquent la d\\u00e9couverte potentielle d\\u2019un.e Ge\\u00f4les imp\\u00e9riales dans cette zone.<\\/p><p>Information int\\u00e9ressante : un.e Cour imp\\u00e9riale est pr\\u00e9sent.e dans la zone. Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p>"}	2025-06-22 12:52:05.003505
109	23	3	1	5	11	5	5	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Ouest d\\u2019Ehime. Ce.tte trimestre j'ai 11 en investigation et 5\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Ouest d\\u2019Ehime.<\\/p>Je me suis rendu compte que Riko Hoshino (17), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Ukiyo-e (\\u6d6e\\u4e16\\u7d75) \\u2013 Estampes artistiques, enquete dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), ce qui en fait un.e Tsukai (\\u4f7f\\u3044) \\u2013 Messager rapide un peu trop sp\\u00e9cial.e.Iel travaille avec la faction 6. En plus, iel maitrise l\\u2019art du Ky\\u016bjutsu (\\u5f13\\u8853) \\u2013 Art du tir \\u00e0 l\\u2019arc. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Ishitsukai (\\u533b\\u4f7f) \\u2013 M\\u00e9decin de cour du nom de Kanon Takada (21) qui enquete dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Sensu (\\u6247\\u5b50) \\u2013 \\u00c9ventail pliable, mais cette information n\\u2019est pas si pertinente.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Kuro-hatamoto (\\u9ed2\\u65d7\\u672c) \\u2013 Garde d\\u2019\\u00e9lite du nom de Nanami Morita (14) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Sensu (\\u6247\\u5b50) \\u2013 \\u00c9ventail pliable, mais cette information n\\u2019est pas si pertinente.Iel travaille avec la faction 4. Ces observations se cumulent avec son utilisation de la discipline Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire. Nous l\\u2019avons vu rencontrer en personne La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Ouest d\\u2019Ehime :<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Ry\\u016bk\\u014d-ji (\\u7adc\\u5149\\u5bfa) -- Le chemin de l\\u2019illumination se trouve dans cette zone. Suspendu \\u00e0 flanc de montagne, Ry\\u016bk\\u014d-ji contemple la mer int\\u00e9rieure comme un dragon endormi. \\r\\n    On raconte qu\\u2019au lever du soleil, les brumes se d\\u00e9chirent et r\\u00e9v\\u00e8lent un \\u00e9clat dor\\u00e9 \\u00e9manant de l\\u2019autel. \\r\\n    Les sages disent que ceux qui y m\\u00e9ditent peuvent entrevoir la lumi\\u00e8re v\\u00e9ritable.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Il est possible d\\u2019organiser une mission pour faire dispara\\u00eetre ce probl\\u00e8me.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Port d\\u2019Uwajima. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Un port anim\\u00e9 aux quais denses et bruyants, o\\u00f9 s\\u2019\\u00e9changent riz, bois, et rumeurs en provenance de Ky\\u016bsh\\u016b comme de Cor\\u00e9e.\\r\\n     Les marins disent que la brume y reste plus longtemps qu\\u2019ailleurs.<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Port marchand d\\u2019Uwajima est bien ici. Des voiliers venus de la p\\u00e9ninsule cor\\u00e9enne accostent \\u00e0 Uwajima, charg\\u00e9s de r\\u00e9sines rares dont les parfums servent aux temples autant qu\\u2019aux intrigues.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Plaine d\\u2019Uwajima. Voici ce que nous avons appris : Les vastes plaines d\\u2019Uwajima semblent paisibles sous le soleil, entre cultures clairsem\\u00e9es et sentiers oubli\\u00e9s.\\r\\n        Mais depuis plusieurs semaines, des groupes d\\u2019hommes en haillons, arm\\u00e9s de fourches, de b\\u00e2tons ou de sabres grossiers, y ont \\u00e9t\\u00e9 aper\\u00e7us.\\r\\n        Ces paysans ne sont pas d\\u2019ici : ils avancent discr\\u00e8tement, se regroupent \\u00e0 la tomb\\u00e9e du jour, et pr\\u00eachent un discours de r\\u00e9volte contre les samoura\\u00efs.\\r\\n        Ce sont les avant-gardes des Ikko-ikki, infiltr\\u00e9s depuis le continent par voie maritime.\\r\\n        D\\u00e9couvrir quel est le chef qui les unis pourrait permettre d\\u2019agir avant qu\\u2019il ne soit trop tard.<\\/p>"}	2025-06-22 13:35:57.582192
73	11	2	3	5	11	6	4	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Cap sud de Kochi. Ce.tte trimestre j'ai 11 en investigation et 6\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cap sud de Kochi.<\\/p>J\\u2019ai vu un.e Naishi (\\u5185\\u4f8d) \\u2013 Dame de compagnie du nom de Mai Ichikawa (35) qui surveille dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Sensu (\\u6247\\u5b50) \\u2013 \\u00c9ventail pliable mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Ninjutsu (\\u5fcd\\u8853) \\u2013 Techniques d\\u2019espionnage et de gu\\u00e9rilla.Iel fait partie de la faction 6.   Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e K\\u014dsaku (\\u5de5\\u4f5c) \\u2013 Saboteur du nom de Hiuchi Kagaribi (2) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Tepp\\u014d (\\u9244\\u7832) \\u2013 Un mousquet, mais cette information n\\u2019est pas si pertinente.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 1. En plus, iel maitrise l\\u2019art du K\\u014dd\\u014d (\\u9999\\u9053) \\u2013 Voie de l\\u2019encens. Ces observations se cumulent avec son utilisation de la discipline Kagenk\\u014d (\\u5f71\\u8a00\\u8b1b) \\u2013 L\\u2019art de la parole de l\\u2019ombre. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Kagekui-ry\\u016b (\\u5f71\\u55b0\\u6d41) \\u2013 \\u00c9cole du Mange-Ombre.  Ce qui veut dire que c\\u2019est un serviteur de \\u5996\\u602a de Shikoku (\\u56db\\u56fd). <\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p><p>Information int\\u00e9ressante : un.e Mine de fer de Kubokawa est pr\\u00e9sent.e dans la zone. Dans les profondeurs du cap sud de K\\u014dchi, des veines de fer noir sont extraites \\u00e0 la force des bras puis forg\\u00e9es en cuirasses robustes dans les forges voisines.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Vieux temple. Voici ce que nous avons appris : Accroch\\u00e9 aux flancs escarp\\u00e9s de la c\\u00f4te sud de K\\u014dchi, un petit sanctuaire noircit repose au bord d\\u2019une ancienne veine de fer oubli\\u00e9e.\\r\\n        Au loin, dans la vall\\u00e9e, les marteaux des forgerons r\\u00e9sonnent comme une pri\\u00e8re sourde.\\r\\n        Mais chaque nuit, une odeur de poudre flotte dans l\\u2019air, et un claquement sec \\u2014 sec comme un tir \\u2014 fait sursauter les corbeaux.\\r\\n        (Pour explorer davantage ce lieu, allez voir un orga !) Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p>"}	2025-06-22 12:42:26.352538
71	18	2	5	5	8	13	6	claim	{"claim_controller_id":"null"}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Vall\\u00e9es d\\u2019Iya et d\\u2019Obok\\u00e9 de Tokushima. Ce.tte trimestre j'ai 8 en investigation et 13\\/6 en attaque\\/d\\u00e9fense.","claim_report":"Vall\\u00e9es d\\u2019Iya et d\\u2019Obok\\u00e9 de Tokushima est tomb\\u00e9.e sous votre coupe."}	2025-06-22 12:42:26.352538
92	6	3	10	2	8	7	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 7\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p><p><\\/p><p><\\/p>On a suivi Keita Tani (42) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Sodegarami (\\u8896\\u6426) \\u2013 Garde sp\\u00e9cialis\\u00e9 mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Tetsub\\u014d (\\u9244\\u68d2) \\u2014 Masse de guerre en fer.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement, au moins partiellement.Iel travaille avec la faction 7. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Information int\\u00e9ressante : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" est pr\\u00e9sent.e dans la zone. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Suzaku Mon est bien li\\u00e9.e \\u00e0 cette localisation. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Nous avons identifi\\u00e9 une information int\\u00e9ressante : un.e Cour imp\\u00e9riale serait pr\\u00e9sent.e dans la zone.<\\/p>"}	2025-06-22 13:35:57.582192
89	3	3	3	1	7	8	7	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Cap sud de Kochi. Ce.tte trimestre j'ai 7 en investigation et 8\\/7 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cap sud de Kochi.<\\/p>On a suivi Ryota Yoshikawa (18) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de revendiquer le quartier au nom de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d), ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Kagemusha (\\u5f71\\u6b66\\u8005) \\u2013 Sosie du seigneur mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court.<\\/p>Je me suis rendu compte que Mai Ichikawa (35), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Sensu (\\u6247\\u5b50) \\u2013 \\u00c9ventail pliable, surveille dans le coin. <\\/p>Je me suis rendu compte que Yuna Shimizu (39), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Zudabukuro (\\u982d\\u9640\\u888b) \\u2013 Une besace de p\\u00e8lerin, surveille dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer, ce qui en fait un.e Monogashira (\\u7269\\u982d) \\u2013 Officier en chef un peu trop sp\\u00e9cial.e. Iel re\\u00e7oit un soutien financier de la faction 8. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Murai Wako (\\u548c\\u5149). <\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p><p>Des signes pointent vers la pr\\u00e9sence d\\u2019un.e Mine de fer de Kubokawa, nous devons enqu\\u00eater davantage \\u00e0 ce sujet.<\\/p>","claim_report":"Ryota Yoshikawa a pris Cap sud de Kochi par la force. Iel n\\u2019a laiss\\u00e9 aucune chance aux d\\u00e9fenseurs.<br\\/>"}	2025-06-22 13:35:57.582192
132	46	3	3	1	4	8	3	passive	{}	{"life_report":"<br \\/> J'ai rejoint un nouveau maitre. Ce.tte trimestre j'ai 4 en investigation et 8\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cap sud de Kochi.<\\/p><p><\\/p><p><\\/p>Je me suis rendu compte que Yuna Shimizu (39), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Zudabukuro (\\u982d\\u9640\\u888b) \\u2013 Une besace de p\\u00e8lerin, surveille dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer, ce qui en fait un.e Monogashira (\\u7269\\u982d) \\u2013 Officier en chef un peu trop sp\\u00e9cial.e.<\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p>","claim_report":"J\\u2019ai vu Ryota Yoshikawa renverser l\\u2019autorit\\u00e9 sur Cap sud de Kochi. La zone a chang\\u00e9 de mains.<br\\/>"}	2025-06-22 14:29:02.014169
67	23	2	6	5	11	4	5	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Est de Tokushima. Ce.tte trimestre j'ai 11 en investigation et 4\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p>J\\u2019ai trouv\\u00e9 Arisa Komatsu (29), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas avec un.e Shamisen (\\u4e09\\u5473\\u7dda) \\u2013 Instrument \\u00e0 cordes.J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de Honshu - Okayama. Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie. Iel re\\u00e7oit un soutien financier de la faction 6. Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>J\\u2019ai vu un.e K\\u014dshitsu (\\u9999\\u5e2b) \\u2013 Sp\\u00e9cialiste de l\\u2019art de l\\u2019encens du nom de Miki Arakawa (19) qui surveille dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Sensu (\\u6247\\u5b50) \\u2013 \\u00c9ventail pliable mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Chad\\u014d (\\u8336\\u9053) \\u2013 Voie du th\\u00e9.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 7.  Nous l\\u2019avons vu rencontrer en personne Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Kusushi (\\u85ac\\u5e2b) \\u2013 M\\u00e9decin itin\\u00e9rant du nom de Haruki Inoue (25) qui enquete dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Kusarigama (\\u9396\\u938c) \\u2014 Arme compos\\u00e9e d\\u2019une faucille attach\\u00e9e \\u00e0 une cha\\u00eene lest\\u00e9e, mais cette information n\\u2019est pas si pertinente.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 4. Ces observations se cumulent avec son utilisation de la discipline Waka (\\u548c\\u6b4c) \\u2013 Po\\u00e9sie classique.  Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>Je me suis rendu compte que Tomo Okada (36), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Ukiyo-e (\\u6d6e\\u4e16\\u7d75) \\u2013 Estampes artistiques, enquete dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Shigin (\\u8a69\\u541f) \\u2013 Chant po\\u00e9tique, ce qui en fait un.e Mimiiri (\\u8033\\u5165) \\u2013 Informateur discret un peu trop sp\\u00e9cial.e.Iel fait partie de la faction 8.   Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Murai Wako (\\u548c\\u5149). <\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Comptoir de Kashiwa. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Ce modeste comptoir marchand, adoss\\u00e9 \\u00e0 une crique discr\\u00e8te, conna\\u00eet une activit\\u00e9 \\u00e9trange depuis quelques semaines.\\r\\n        Des jonques aux voiles noires y accostent en silence, et les capitaines refusent de dire d\\u2019o\\u00f9 ils viennent.\\r\\n        Certains affirment que les Wako y auraient re\\u00e7u des fonds d\\u2019un clan du nord \\u2014 peut-\\u00eatre les Hosokawa \\u2014 pour saboter les entrep\\u00f4ts du port de Tokushima.\\r\\n        D\\u2019autres y voient simplement un commerce de sel et de fer\\u2026 mais pourquoi alors tant de discr\\u00e9tion, et autant de lames pr\\u00eates \\u00e0 jaillir \\u00e0 la moindre question ?<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Port de Tokushima. Voici ce que nous avons appris : Carrefour maritime entre Honsh\\u016b et Shikoku, le port de Tokushima bruisse de dialectes et de voiles \\u00e9trang\\u00e8res.\\r\\n     Dans les ruelles proches du march\\u00e9, on parle parfois espagnol, ou latin, \\u00e0 voix basse.<\\/p>"}	2025-06-22 12:42:26.352538
49	1	2	2	1	8	7	8	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 7\\/8 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p>Je me suis rendu compte que Ami Tajima (37), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Kunai (\\u82e6\\u7121) \\u2013 Dague multi-usage, surveille dans le coin. Je m\\u2019en m\\u00e9fie, iel vient de Kyushu - \\u00d6ita. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de J\\u016bjutsu (\\u67d4\\u8853) \\u2013 Techniques de lutte \\u00e0 mains nues, ce qui en fait un.e H\\u014din (\\u6cd5\\u5370) \\u2013 Pr\\u00eatresse ou religieuse conseill\\u00e8re un peu trop sp\\u00e9cial.e.En plus, sa famille a des liens avec la faction 6. Ce qui veut dire que c\\u2019est un serviteur de Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p><p><\\/p>J\\u2019ai trouv\\u00e9 Miki Yamaguchi (33), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Shihan (\\u5e2b\\u7bc4) \\u2013 Ma\\u00eetre instructeur avec un.e Furushiki (\\u98a8\\u5442\\u6577) \\u2013 Carr\\u00e9 de tissu.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer.En plus, sa famille a des liens avec la faction 8. Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 Murai Wako (\\u548c\\u5149). <\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Forteresse des Kaizokush\\u016b est bien ici. \\r\\n        Nous avons trouv\\u00e9 la forteresse de Murai Wako (\\u548c\\u5149) des Kaizokush\\u016b. Les serviteurs de confiance leur manquent encore pour avoir des d\\u00e9fenses solides.\\r\\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\\r\\n        L\\u2019attaque causerait certainement quelques questions \\u00e0 la cour du Shogun, mais un joueur affaibli sur l\\u2019\\u00e9chiquier politique est toujours b\\u00e9n\\u00e9fique.\\r\\n        Nous ne devons pas tarder \\u00e0 prendre notre d\\u00e9cision, ses d\\u00e9fenses se renforcent chaque trimestre.\\r\\n     Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Sanctuaire des Pins Br\\u00fbl\\u00e9s se trouve dans cette zone. Dans un ancien sanctuaire shint\\u014d dont les piliers carbonis\\u00e9s r\\u00e9sistent au temps, des p\\u00e8lerins affirment avoir vu un artefact \\u00e9trange cach\\u00e9 sous l\\u2019autel \\u2014 une croix d\\u2019argent sertie d\\u2019inscriptions latines.\\r\\n        Les paysans parlent d\\u2019un pr\\u00eatre chr\\u00e9tien, et de l\\u2019Inquisition j\\u00e9suite elle-m\\u00eame. Mais les recherches men\\u00e9es par les yamabushi locaux n\\u2019ont rien r\\u00e9v\\u00e9l\\u00e9 de probant.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Mt Ishizuchi est bien li\\u00e9.e \\u00e0 cette localisation. Plus haut sommet de l\\u2019\\u00eele, le mont Ishizuchi domine les vall\\u00e9es alentour comme un sabre dress\\u00e9 vers le ciel.\\r\\n     On dit qu\\u2019un p\\u00e8lerinage ancien y conduit \\u00e0 une dalle sacr\\u00e9e o\\u00f9 les esprits s\\u2019expriment lorsque les vents tournent.<\\/p><p>Des rumeurs persistantes \\u00e9voquent la pr\\u00e9sence d\\u2019un.e Port de Matsuyama dans les environs.<\\/p>"}	2025-06-22 12:42:26.352538
53	5	2	2	6	10	4	3	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 10 en investigation et 4\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p>J\\u2019ai vu un.e Kannushi (\\u795e\\u4e3b) \\u2013 Pr\\u00eatre shint\\u014d du nom de Iwao Jizane (1) qui surveille dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Chigiriki (\\u5951\\u6728) \\u2013 Masse \\u00e0 cha\\u00eene mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral.Iel a de plus une maitrise de la discipline K\\u014dd\\u014d (\\u9999\\u9053) \\u2013 Voie de l\\u2019encens. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Kagenk\\u014d (\\u5f71\\u8a00\\u8b1b) \\u2013 L\\u2019art de la parole de l\\u2019ombre. En plus, iel maitrise l\\u2019art du Kagekui-ry\\u016b (\\u5f71\\u55b0\\u6d41) \\u2013 \\u00c9cole du Mange-Ombre.  Iel re\\u00e7oit un soutien financier de la faction 1. <\\/p>J\\u2019ai trouv\\u00e9 Miki Yamaguchi (33), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Shihan (\\u5e2b\\u7bc4) \\u2013 Ma\\u00eetre instructeur avec un.e Furushiki (\\u98a8\\u5442\\u6577) \\u2013 Carr\\u00e9 de tissu.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer.Iel fait partie de la faction 8.   Nous l\\u2019avons vu rencontrer en personne Murai Wako (\\u548c\\u5149). <\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Forteresse des Kaizokush\\u016b est bien ici. \\r\\n        Nous avons trouv\\u00e9 la forteresse de Murai Wako (\\u548c\\u5149) des Kaizokush\\u016b. Les serviteurs de confiance leur manquent encore pour avoir des d\\u00e9fenses solides.\\r\\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\\r\\n        L\\u2019attaque causerait certainement quelques questions \\u00e0 la cour du Shogun, mais un joueur affaibli sur l\\u2019\\u00e9chiquier politique est toujours b\\u00e9n\\u00e9fique.\\r\\n        Nous ne devons pas tarder \\u00e0 prendre notre d\\u00e9cision, ses d\\u00e9fenses se renforcent chaque trimestre.\\r\\n     Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Sanctuaire des Pins Br\\u00fbl\\u00e9s se trouve dans cette zone. Dans un ancien sanctuaire shint\\u014d dont les piliers carbonis\\u00e9s r\\u00e9sistent au temps, des p\\u00e8lerins affirment avoir vu un artefact \\u00e9trange cach\\u00e9 sous l\\u2019autel \\u2014 une croix d\\u2019argent sertie d\\u2019inscriptions latines.\\r\\n        Les paysans parlent d\\u2019un pr\\u00eatre chr\\u00e9tien, et de l\\u2019Inquisition j\\u00e9suite elle-m\\u00eame. Mais les recherches men\\u00e9es par les yamabushi locaux n\\u2019ont rien r\\u00e9v\\u00e9l\\u00e9 de probant.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Mt Ishizuchi. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Plus haut sommet de l\\u2019\\u00eele, le mont Ishizuchi domine les vall\\u00e9es alentour comme un sabre dress\\u00e9 vers le ciel.\\r\\n     On dit qu\\u2019un p\\u00e8lerinage ancien y conduit \\u00e0 une dalle sacr\\u00e9e o\\u00f9 les esprits s\\u2019expriment lorsque les vents tournent.<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Vieux temple. Voici ce que nous avons appris : Perch\\u00e9 sur un piton rocheux des montagnes d\\u2019Ehim\\u00e9, un ancien temple taill\\u00e9 \\u00e0 m\\u00eame la pierre repose, fig\\u00e9 comme un souvenir.\\r\\n        Nul vent n\\u2019y souffle, nul oiseau n\\u2019y niche.\\r\\n        Parfois, on y entend cliqueter une cha\\u00eene sur la pierre nue, comme si une arme tra\\u00eenait seule sur le sol.\\r\\n        (Pour explorer davantage ce lieu, allez voir un orga !) Il est possible d\\u2019organiser une mission pour faire dispara\\u00eetre ce probl\\u00e8me.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Port de Matsuyama est bien li\\u00e9.e \\u00e0 cette localisation. Le port de Matsuyama est d\\u2019ordinaire anim\\u00e9 par les p\\u00eacheurs locaux et les petits marchands.\\r\\n        Mais depuis peu, les anciens disent avoir vu, au cr\\u00e9puscule, un navire \\u00e9trange accoster sans banni\\u00e8re, escort\\u00e9 par des pirates tatou\\u00e9s.\\r\\n        Un moine en est descendu, maigre, vieux, au regard br\\u00fblant de ferveur : Rennyo lui-m\\u00eame, leader spirituel des Ikko-ikki.\\r\\n        Selon certains, il s\\u2019est enfonc\\u00e9 dans les montagnes d\\u2019Ehime avec une poign\\u00e9e de fid\\u00e8les.\\r\\n        Ce secret, s\\u2019il venait \\u00e0 \\u00eatre r\\u00e9v\\u00e9l\\u00e9, pourrait changer l\\u2019\\u00e9quilibre religieux de toute l\\u2019\\u00eele.<\\/p>"}	2025-06-22 12:42:26.352538
51	3	2	8	1	8	8	7	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 8\\/7 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile d\\u2019Awaji.<\\/p>J\\u2019ai trouv\\u00e9 Marco Giancarlo (30), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e K\\u014dsaku (\\u5de5\\u4f5c) \\u2013 Saboteur avec un.e Makimono (\\u5dfb\\u7269) \\u2013 Rouleau calligraphi\\u00e9.Je m\\u2019en m\\u00e9fie, iel vient de Portugal. Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire.En plus, sa famille a des liens avec la faction 5. Nous l\\u2019avons vu rencontrer en personne Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>","secrets_report":"<p>Dans le territoire Ile d\\u2019Awaji :<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Phare abandonn\\u00e9 de Minokoshi est bien li\\u00e9.e \\u00e0 cette localisation. Diss\\u00e9min\\u00e9 au bout d\\u2019une presqu\\u2019\\u00eele battue par les vents, le vieux phare de Minokoshi n\\u2019est plus qu\\u2019un squelette de pierre rong\\u00e9 par le sel.\\r\\n        Pourtant, certains p\\u00eacheurs affirment y voir passer des silhouettes arm\\u00e9es \\u00e0 la tomb\\u00e9e de la nuit.\\r\\n        La rumeur court qu\\u2019un prisonnier de valeur y est gard\\u00e9 en secret par le clan Ch\\u014dsokabe, un traitre captur\\u00e9 lors des affrontements r\\u00e9cents.<\\/p><p>Information int\\u00e9ressante : un.e Rumeur de la bataille est pr\\u00e9sent.e dans la zone. Les p\\u00eacheurs d\\u2019Awaji parlent encore d\\u2019un combat f\\u00e9roce dans les collines, entre troupes en fuite et rebelles aux visages peints. Certains affirment avoir vu le ciel s\\u2019embraser au-dessus du temple abandonn\\u00e9.<\\/p><p>Il semblerait qu\\u2019un.e Camp de deserteurs se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>"}	2025-06-22 12:42:26.352538
75	14	2	1	4	9	6	6	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 9 en investigation et 6\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Ouest d\\u2019Ehime.<\\/p>On a suivi Nao Nobunaga (38) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Biwa H\\u014dshi (\\u7435\\u7436\\u6cd5\\u5e2b) \\u2013 Conteur aveugle itin\\u00e9rant mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e K\\u014dro (\\u9999\\u7089) \\u2013 Petit encensoir de voyage.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie, au moins partiellement. Iel re\\u00e7oit un soutien financier de la faction 7. <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Kuro-hatamoto (\\u9ed2\\u65d7\\u672c) \\u2013 Garde d\\u2019\\u00e9lite du nom de Hinako Ichikawa (32) qui revendique le quartier au nom de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d) dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise H\\u014djutsu (\\u7832\\u8853) \\u2013 Art des armes \\u00e0 feu (tepp\\u014d). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin, mais cette information n\\u2019est pas si pertinente. Iel re\\u00e7oit un soutien financier de la faction 5. Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>J\\u2019ai vu un.e Tsukai (\\u4f7f\\u3044) \\u2013 Messager rapide du nom de Riko Hoshino (17) qui enquete dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Ukiyo-e (\\u6d6e\\u4e16\\u7d75) \\u2013 Estampes artistiques mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari).Iel fait partie de la faction 6. Iel a de plus une maitrise de la discipline Ky\\u016bjutsu (\\u5f13\\u8853) \\u2013 Art du tir \\u00e0 l\\u2019arc. Ces observations se cumulent avec son utilisation de la discipline Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.   Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Ouest d\\u2019Ehime :<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Ry\\u016bk\\u014d-ji (\\u7adc\\u5149\\u5bfa) -- Le chemin de l\\u2019illumination. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Suspendu \\u00e0 flanc de montagne, Ry\\u016bk\\u014d-ji contemple la mer int\\u00e9rieure comme un dragon endormi. \\r\\n    On raconte qu\\u2019au lever du soleil, les brumes se d\\u00e9chirent et r\\u00e9v\\u00e8lent un \\u00e9clat dor\\u00e9 \\u00e9manant de l\\u2019autel. \\r\\n    Les sages disent que ceux qui y m\\u00e9ditent peuvent entrevoir la lumi\\u00e8re v\\u00e9ritable.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Il est possible d\\u2019organiser une mission pour faire dispara\\u00eetre ce probl\\u00e8me.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Port d\\u2019Uwajima. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Un port anim\\u00e9 aux quais denses et bruyants, o\\u00f9 s\\u2019\\u00e9changent riz, bois, et rumeurs en provenance de Ky\\u016bsh\\u016b comme de Cor\\u00e9e.\\r\\n     Les marins disent que la brume y reste plus longtemps qu\\u2019ailleurs.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Port marchand d\\u2019Uwajima est bien li\\u00e9.e \\u00e0 cette localisation. Des voiliers venus de la p\\u00e9ninsule cor\\u00e9enne accostent \\u00e0 Uwajima, charg\\u00e9s de r\\u00e9sines rares dont les parfums servent aux temples autant qu\\u2019aux intrigues.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Plaine d\\u2019Uwajima. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Les vastes plaines d\\u2019Uwajima semblent paisibles sous le soleil, entre cultures clairsem\\u00e9es et sentiers oubli\\u00e9s.\\r\\n        Mais depuis plusieurs semaines, des groupes d\\u2019hommes en haillons, arm\\u00e9s de fourches, de b\\u00e2tons ou de sabres grossiers, y ont \\u00e9t\\u00e9 aper\\u00e7us.\\r\\n        Ces paysans ne sont pas d\\u2019ici : ils avancent discr\\u00e8tement, se regroupent \\u00e0 la tomb\\u00e9e du jour, et pr\\u00eachent un discours de r\\u00e9volte contre les samoura\\u00efs.\\r\\n        Ce sont les avant-gardes des Ikko-ikki, infiltr\\u00e9s depuis le continent par voie maritime.\\r\\n        D\\u00e9couvrir quel est le chef qui les unis pourrait permettre d\\u2019agir avant qu\\u2019il ne soit trop tard.<\\/p>","claim_report":"L\\u2019op\\u00e9ration de Hinako Ichikawa sur C\\u00f4te Ouest d\\u2019Ehime a \\u00e9t\\u00e9 une r\\u00e9ussite totale, malgr\\u00e9 les d\\u00e9gats.<br\\/>"}	2025-06-22 12:42:26.352538
101	15	3	9	8	3	9	10	passive	{}	{"life_report":"Ce.tte trimestre j'ai 3 en investigation et 9\\/10 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p>"}	2025-06-22 13:35:57.582192
94	8	3	10	2	4	11	8	passive	{}	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 11\\/8 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p><p><\\/p><p><\\/p>On a suivi Keita Tani (42) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Sodegarami (\\u8896\\u6426) \\u2013 Garde sp\\u00e9cialis\\u00e9 mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Tetsub\\u014d (\\u9244\\u68d2) \\u2014 Masse de guerre en fer.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement, au moins partiellement.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 7.  <\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p>"}	2025-06-22 13:35:57.582192
106	20	3	7	8	10	6	4	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Prefecture de Kagawa. Ce.tte trimestre j'ai 10 en investigation et 6\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p>J\\u2019ai vu un.e Marin Portugais du nom de Taiga Tani (26) qui enquete dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.Iel travaille avec la faction 4. Ces observations se cumulent avec son utilisation de la discipline Kenjutsu (\\u5263\\u8853) \\u2013 Art du sabre. Iel a de plus une maitrise de la discipline Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire. <\\/p>Je me suis rendu compte que Venturo Attilio (28), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin, surveille dans le coin. En plus, iel est originaire de Portugal. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), ce qui en fait un.e Onmy\\u014dji (\\u9670\\u967d\\u5e2b) \\u2013 Devin et exorciste un peu trop sp\\u00e9cial.e.Ces observations se cumulent avec son utilisation de la discipline Ky\\u016bjutsu (\\u5f13\\u8853) \\u2013 Art du tir \\u00e0 l\\u2019arc. Iel a de plus une maitrise de la discipline Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement.  Iel re\\u00e7oit un soutien financier de la faction 7. Nous l\\u2019avons vu rencontrer en personne Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p>On a suivi Miki Arakawa (19) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e K\\u014dshitsu (\\u9999\\u5e2b) \\u2013 Sp\\u00e9cialiste de l\\u2019art de l\\u2019encens mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Sensu (\\u6247\\u5b50) \\u2013 \\u00c9ventail pliable.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie, au moins partiellement.<\\/p>J\\u2019ai trouv\\u00e9 Marco Venezio (13), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Bugy\\u014d (\\u5949\\u884c) \\u2013 Magistrat ou officier administratif avec un.e Tessen (\\u9244\\u6247) \\u2013 \\u00c9ventail de fer.Je m\\u2019en m\\u00e9fie, iel vient de Portugal. Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari).Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral. En plus, iel maitrise l\\u2019art du Bugaku (\\u821e\\u697d) \\u2013 Danse de cour.  Iel re\\u00e7oit un soutien financier de la faction 7. Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Sarugakushi (\\u733f\\u697d\\u5e2b) \\u2013 Artiste de rue ou com\\u00e9dien du nom de Souta Yamamoto (10) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise J\\u016bjutsu (\\u67d4\\u8853) \\u2013 Techniques de lutte \\u00e0 mains nues. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Juzu (\\u6570\\u73e0) \\u2013 Un bracelet de perles bouddhistes, mais cette information n\\u2019est pas si pertinente.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 6. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Ninjutsu (\\u5fcd\\u8853) \\u2013 Techniques d\\u2019espionnage et de gu\\u00e9rilla. En plus, iel maitrise l\\u2019art du Reiki \\/ Kujikiri (\\u970a\\u6c17 \\/ \\u4e5d\\u5b57\\u5207\\u308a) \\u2013 Pratiques \\u00e9sot\\u00e9riques.  <\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Yashima-ji (\\u5c4b\\u5cf6\\u5bfa) -- Le chemin du Nirvana. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Ancien bastion surplombant les flots, Yashima-ji garde la m\\u00e9moire des batailles et des ermites. \\r\\n    Les brumes de l\\u2019aube y voilent statues et stupas, comme pour dissimuler les myst\\u00e8res du Nirvana. \\r\\n    Certains p\\u00e8lerins affirment y avoir senti l\\u2019oubli du monde descendre sur eux comme une paix.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Maison close de Marugame se trouve dans cette zone. \\u00c0 Marugame, dans une maison close r\\u00e9put\\u00e9e pour son sak\\u00e9 sucr\\u00e9 et ses \\u00e9ventails peints \\u00e0 la main, des courtisanes murmurent entre deux chansons.\\r\\n        L\\u2019une d\\u2019elles pr\\u00e9tend avoir lu une lettre scell\\u00e9e, confi\\u00e9e par un \\u00e9missaire enivr\\u00e9, annon\\u00e7ant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre \\u00e9clair contre les Ch\\u014dsokabe.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Grande route et relais de poste se trouve dans cette zone. Relie Tokushima \\u00e0 K\\u014dchi en serpentant \\u00e0 travers les plaines fertiles du nord.\\r\\n     \\u00c0 chaque relais, les montures peuvent \\u00eatre chang\\u00e9es, et les messagers imp\\u00e9riaux y trouvent toujours une couche et un bol chaud.<\\/p><p>Information int\\u00e9ressante : un.e \\u00c9curies de Kagawa est pr\\u00e9sent.e dans la zone. Les vastes p\\u00e2turages de Kagawa forment l\\u2019\\u00e9crin id\\u00e9al pour l\\u2019\\u00e9levage de chevaux endurants, pris\\u00e9s tant pour la guerre que pour les grandes caravanes.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Post relai du courrier de Kagawa. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Une auberge modeste pr\\u00e8s de la grande route de Kagawa re\\u00e7oit parfois, \\u00e0 l\\u2019aube, des cavaliers fatigu\\u00e9s portant des missives cachet\\u00e9es.\\r\\n        L\\u2019une d\\u2019elles, r\\u00e9cemment intercept\\u00e9e, contenait une promesse de mariage scell\\u00e9e entre Motochika Ch\\u014dsokabe et Tama Hosokawa, fille de Fujitaka.\\r\\n        Si elle venait \\u00e0 se concr\\u00e9tiser, cette alliance unirait deux grandes maisons sur Shikoku et changerait les rapports de pouvoir de toute la r\\u00e9gion.\\r\\n        Pour l\\u2019instant, l\\u2019information est gard\\u00e9e secr\\u00e8te, mais les rumeurs montent.<\\/p>"}	2025-06-22 13:35:57.582192
97	11	3	5	5	8	6	5	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Vall\\u00e9es d\\u2019Iya et d\\u2019Obok\\u00e9 de Tokushima. Ce.tte trimestre j'ai 8 en investigation et 6\\/5 en attaque\\/d\\u00e9fense.","secrets_report":"<p>Dans le territoire Vall\\u00e9es d\\u2019Iya et d\\u2019Obok\\u00e9 de Tokushima :<\\/p><p>Un rapport fragmentaire mentionne un.e Forteresse des Moines Bouddhistes comme \\u00e9tant cach\\u00e9.e dans ce territoire.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Dainichi-ji (\\u5927\\u65e5\\u5bfa) -- Le chemin de l\\u2019\\u00e9veil est bien li\\u00e9.e \\u00e0 cette localisation. Nich\\u00e9 entre les for\\u00eats brumeuses d\\u2019Iya, ce temple vibre encore du souffle ancien des premiers pas du p\\u00e8lerin. \\r\\n    On dit que les pierres du sentier y murmurent des pri\\u00e8res oubli\\u00e9es \\u00e0 ceux qui s\\u2019y attardent. \\r\\n    Le silence y est si pur qu\\u2019on entend le battement de son propre c\\u0153ur.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Information int\\u00e9ressante : un.e Ikeda est pr\\u00e9sent.e dans la zone. Petit village de montagne aux maisons de bois noircies par le temps.\\r\\n     Les voyageurs s\\u2019y arr\\u00eatent pour go\\u00fbter un sak\\u00e9 r\\u00e9put\\u00e9, brass\\u00e9 \\u00e0 l\\u2019eau des gorges profondes qui serpentent en contrebas.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Vall\\u00e9e fertile d\\u2019Obok\\u00e9 est bien li\\u00e9.e \\u00e0 cette localisation. Dans la vall\\u00e9e profonde d\\u2019Obok\\u00e9, o\\u00f9 le bruit de la rivi\\u00e8re est permanent, poussent \\u00e0 flanc de roche de rares th\\u00e9iers.\\r\\n    Leurs feuilles, am\\u00e8res et puissantes, sont cueillies \\u00e0 la main par les familles montagnardes, suspendues au-dessus du grondement des eaux.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p>"}	2025-06-22 13:35:57.582192
114	28	3	7	7	4	9	7	passive	{}	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 9\\/7 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p>"}	2025-06-22 13:35:57.582192
113	27	3	4	7	6	3	4	dead	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 3\\/4 en attaque\\/d\\u00e9fense.<p>Cet agent a disparu sans laisser de traces \\u00e0 partir de la semaine 3.<\\/p>"}	2025-06-22 13:35:57.582192
123	37	3	2	6	4	8	6	attack	[{"attackScope":"worker","attackID":1}]	{"life_report":"Ce.tte trimestre j'ai 4 en investigation et 8\\/6 en attaque\\/d\\u00e9fense.","attack_report":"<p>Malgr\\u00e9 nos efforts, Iwao Jizane s\\u2019est d\\u00e9fendu.e avec succ\\u00e8s et a r\\u00e9ussi \\u00e0 fuir.<\\/p>"}	2025-06-22 13:35:57.582192
52	4	2	9	1	11	6	5	passive	{}	{"life_report":"Ce.tte trimestre j'ai 11 en investigation et 6\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas du nom de Ayaka Noguchi (15) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Chigiriki (\\u5951\\u6728) \\u2013 Masse \\u00e0 cha\\u00eene, mais cette information n\\u2019est pas si pertinente.En plus, sa famille a des liens avec la faction 8. En plus, iel maitrise l\\u2019art du Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau. En plus, iel maitrise l\\u2019art du Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Murai Wako (\\u548c\\u5149). <\\/p>J\\u2019ai trouv\\u00e9 Kanon Takada (21), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Ishitsukai (\\u533b\\u4f7f) \\u2013 M\\u00e9decin de cour avec un.e Sensu (\\u6247\\u5b50) \\u2013 \\u00c9ventail pliable.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.Iel travaille avec la faction 8. Ces observations se cumulent avec son utilisation de la discipline Shigin (\\u8a69\\u541f) \\u2013 Chant po\\u00e9tique. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Murai Wako (\\u548c\\u5149). <\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Crique de Funakoshi. Voici ce que nous avons appris : Cette crique isol\\u00e9e, souvent balay\\u00e9e par les vents, est connue des contrebandiers comme des p\\u00eacheurs.\\r\\n        Depuis quelques jours, un bruit court : un important \\u00e9missaire imp\\u00e9rial aurait \\u00e9t\\u00e9 intercept\\u00e9 par les pirates Wako et d\\u00e9tenu dans une grotte voisine, en attendant ran\\u00e7on ou silence.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e D\\u00e9troit d\\u2019Okayama. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : \\u00c9troit et venteux, ce d\\u00e9troit aux eaux tra\\u00eetresses s\\u00e9pare Shikoku de Honsh\\u016b.\\r\\n     Difficile de tenter cette travers\\u00e9e sans \\u00eatre \\u00e9pi\\u00e9 par les habitants de l\\u2019\\u00eele de Sh\\u014ddoshima.\\r\\n     Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d\\u2019\\u00eatre intercept\\u00e9 par les Kaizokush\\u016b.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Ge\\u00f4les des Kaizokush\\u016b est bien li\\u00e9.e \\u00e0 cette localisation. Creus\\u00e9es dans la falaise m\\u00eame, ces cavernes humides servent de prison aux captifs des Wako. \\r\\n        Des cha\\u00eenes rouill\\u00e9es pendent aux murs, et l\\u2019eau sal\\u00e9e suinte sans cesse, rongeant la volont\\u00e9 des enferm\\u00e9s. \\r\\n        Le silence n\\u2019y est troubl\\u00e9 que par les pas des ge\\u00f4liers \\u2014 ou les rires des pirates. Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Information int\\u00e9ressante : un.e Camp de deserteurs est pr\\u00e9sent.e dans la zone. Dans une gorge dissimul\\u00e9e parmi les pins tordus de Sh\\u014ddoshima, quelques hommes efflanqu\\u00e9s vivent en silence, fuyant le regard des p\\u00eacheurs et des samoura\\u00efs.\\r\\n            Ce sont des survivants de la d\\u00e9route d\\u2019Ishizuchi, dont ils racontent une version bien diff\\u00e9rente de celle propag\\u00e9e \\u00e0 la cour : l\\u2019avant-garde des Ch\\u014dsokabe, command\\u00e9e par Fujitaka Hosokawa, se serait retrouv\\u00e9e face aux fanatiques Ikko-ikki, qui auraient \\u00e9cras\\u00e9 ses lignes avant m\\u00eame que l\\u2019ordre de retraite ne puisse \\u00eatre donn\\u00e9.\\r\\n            Fujitaka, s\\u00e9par\\u00e9 de la force principale, aurait fui pr\\u00e9cipitamment vers Kyoto, mais aurait \\u00e9t\\u00e9 aper\\u00e7u captur\\u00e9 par un g\\u00e9n\\u00e9ral des forces du shogun Ashikaga. Ces aveux, \\u00e9touff\\u00e9s sous le fracas des r\\u00e9cits officiels, pourraient bien r\\u00e9habiliter l\\u2019honneur du daimy\\u014d d\\u00e9chu \\u2014 ou bouleverser les \\u00e9quilibres fragiles entre les clans.<\\/p>"}	2025-06-22 12:42:26.352538
54	6	2	10	2	8	7	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 7\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Mimiiri (\\u8033\\u5165) \\u2013 Informateur discret du nom de Rina Ichinose (20) qui enquete dans notre r\\u00e9gion. En plus, iel est originaire de Honshu - Osaka. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Zudabukuro (\\u982d\\u9640\\u888b) \\u2013 Une besace de p\\u00e8lerin, mais cette information n\\u2019est pas si pertinente.En plus, sa famille a des liens avec la faction 8. En plus, iel maitrise l\\u2019art du Shigin (\\u8a69\\u541f) \\u2013 Chant po\\u00e9tique. <\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\". Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Suzaku Mon est bien ici. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Il semblerait qu\\u2019un.e Cour imp\\u00e9riale se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>"}	2025-06-22 12:42:26.352538
57	9	2	10	2	12	3	4	passive	{}	{"life_report":"Ce.tte trimestre j'ai 12 en investigation et 3\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Mimiiri (\\u8033\\u5165) \\u2013 Informateur discret du nom de Rina Ichinose (20) qui enquete dans notre r\\u00e9gion. Je m\\u2019en m\\u00e9fie, iel vient de Honshu - Osaka. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Zudabukuro (\\u982d\\u9640\\u888b) \\u2013 Une besace de p\\u00e8lerin, mais cette information n\\u2019est pas si pertinente.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 8. En plus, iel maitrise l\\u2019art du Shigin (\\u8a69\\u541f) \\u2013 Chant po\\u00e9tique.  Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 Murai Wako (\\u548c\\u5149). <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e H\\u014dshi (\\u6cd5\\u5e2b) \\u2013 Moine bouddhiste itin\\u00e9rant du nom de Rina Sakai (34) qui enquete dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Waka (\\u548c\\u6b4c) \\u2013 Po\\u00e9sie classique. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Koma (\\u99d2) \\u2013 Pi\\u00e8ces de sh\\u014dgi, mais cette information n\\u2019est pas si pertinente.Iel fait partie de la faction 4.   <\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" se trouve dans cette zone. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Suzaku Mon se trouve dans cette zone. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Cour imp\\u00e9riale. Voici ce que nous avons appris : Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p>"}	2025-06-22 12:42:26.352538
60	13	2	7	7	5	5	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 5\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p><p><\\/p>On a suivi Taiga Tani (26) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Marin Portugais mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court.<\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Nous avons identifi\\u00e9 une information int\\u00e9ressante : un.e Maison close de Marugame serait pr\\u00e9sent.e dans la zone.<\\/p>"}	2025-06-22 12:42:26.352538
63	19	2	6	7	7	2	4	passive	{}	{"life_report":"Ce.tte trimestre j'ai 7 en investigation et 2\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p>J\\u2019ai trouv\\u00e9 Arisa Komatsu (29), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas avec un.e Shamisen (\\u4e09\\u5473\\u7dda) \\u2013 Instrument \\u00e0 cordes.Je m\\u2019en m\\u00e9fie, iel vient de Honshu - Okayama. Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.En plus, sa famille a des liens avec la faction 6. Ce qui veut dire que c\\u2019est un serviteur de Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>J\\u2019ai vu un.e Kusushi (\\u85ac\\u5e2b) \\u2013 M\\u00e9decin itin\\u00e9rant du nom de Haruki Inoue (25) qui enquete dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Kusarigama (\\u9396\\u938c) \\u2014 Arme compos\\u00e9e d\\u2019une faucille attach\\u00e9e \\u00e0 une cha\\u00eene lest\\u00e9e mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire.<\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p><p>Information int\\u00e9ressante : un.e Port de Tokushima est pr\\u00e9sent.e dans la zone. Carrefour maritime entre Honsh\\u016b et Shikoku, le port de Tokushima bruisse de dialectes et de voiles \\u00e9trang\\u00e8res.\\r\\n     Dans les ruelles proches du march\\u00e9, on parle parfois espagnol, ou latin, \\u00e0 voix basse.<\\/p>"}	2025-06-22 12:42:26.352538
118	32	3	4	5	6	9	8	claim	{"claim_controller_id":"5"}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Est de Tokushima. J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Grande Baie de Kochi. Ce.tte trimestre j'ai 6 en investigation et 9\\/8 en attaque\\/d\\u00e9fense.","claim_report":"Nous avons su imposer votre autorit\\u00e9 sur Grande Baie de Kochi. La r\\u00e9gion vous ob\\u00e9it d\\u00e9sormais.J\\u2019ai vu Nanami Koga tenter de prendre le contr\\u00f4le du territoire Grande Baie de Kochi, mais la d\\u00e9fense l\\u2019a repouss\\u00e9.e brutalement.<br\\/>"}	2025-06-22 13:35:57.582192
117	31	3	4	4	8	6	7	claim	{"claim_controller_id":"7"}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 6\\/7 en attaque\\/d\\u00e9fense.<p>Iel a tent\\u00e9 de me r\\u00e9duire au silence, mais apr\\u00e8s avoir surv\\u00e9cu \\u00e0 l\\u2019attaque de Keita Arakawa(45), j\\u2019ai r\\u00e9pondu par une riposte fatale.<\\/p>","claim_report":"J\\u2019ai vu Hinako Ichikawa renverser l\\u2019autorit\\u00e9 sur Grande Baie de Kochi. La zone a chang\\u00e9 de mains.<br\\/>Notre tentative de prise de contr\\u00f4le de Grande Baie de Kochi a \\u00e9chou\\u00e9. La d\\u00e9fense \\u00e9tait trop solide."}	2025-06-22 13:35:57.582192
91	5	3	2	6	11	5	3	claim	{"claim_controller_id":"null"}	{"life_report":"Ce.tte trimestre j'ai 11 en investigation et 5\\/3 en attaque\\/d\\u00e9fense.","claim_report":"Nous avons pris le contr\\u00f4le du territoire Montagnes d\\u2019Ehime avec succ\\u00e8s. F\\u00e9licitations vous en \\u00eates d\\u00e9sormais le maitre."}	2025-06-22 13:35:57.582192
116	30	3	8	5	6	9	7	claim	{"claim_controller_id":"5"}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 9\\/7 en attaque\\/d\\u00e9fense.","claim_report":"Notre offensive sur la zone Ile d\\u2019Awaji a port\\u00e9 ses fruits. Elle est maintenant \\u00e0 vous."}	2025-06-22 13:35:57.582192
104	18	3	3	5	7	13	6	claim	{"claim_controller_id":"5"}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Cap sud de Kochi. Ce.tte trimestre j'ai 7 en investigation et 13\\/6 en attaque\\/d\\u00e9fense.","claim_report":"Notre offensive sur la zone Cap sud de Kochi a port\\u00e9 ses fruits. Elle est maintenant \\u00e0 vous."}	2025-06-22 13:35:57.582192
120	34	3	10	4	11	5	4	claim	{"claim_controller_id":"5"}	{"life_report":"Ce.tte trimestre j'ai 11 en investigation et 5\\/4 en attaque\\/d\\u00e9fense.","claim_report":"Cit\\u00e9 Imp\\u00e9riale de Kyoto est tomb\\u00e9.e sous votre coupe."}	2025-06-22 13:35:57.582192
58	10	2	7	6	8	6	5	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 6\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p>J\\u2019ai trouv\\u00e9 Taiga Tani (26), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Marin Portugais avec un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Kenjutsu (\\u5263\\u8853) \\u2013 Art du sabre.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 4. Ces observations se cumulent avec son utilisation de la discipline Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire.  Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>On a suivi Marco Venezio (13) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Bugy\\u014d (\\u5949\\u884c) \\u2013 Magistrat ou officier administratif mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Tessen (\\u9244\\u6247) \\u2013 \\u00c9ventail de fer.En plus, iel est originaire de Portugal. Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Bugaku (\\u821e\\u697d) \\u2013 Danse de cour, au moins partiellement.Iel travaille avec la faction 7. Nous l\\u2019avons vu rencontrer en personne Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p>Je me suis rendu compte que Venturo Attilio (28), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin, revendique le quartier au nom de Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd) dans le coin. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de Portugal. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), ce qui en fait un.e Onmy\\u014dji (\\u9670\\u967d\\u5e2b) \\u2013 Devin et exorciste un peu trop sp\\u00e9cial.e.Iel travaille avec la faction 7. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Yashima-ji (\\u5c4b\\u5cf6\\u5bfa) -- Le chemin du Nirvana. Voici ce que nous avons appris : Ancien bastion surplombant les flots, Yashima-ji garde la m\\u00e9moire des batailles et des ermites. \\r\\n    Les brumes de l\\u2019aube y voilent statues et stupas, comme pour dissimuler les myst\\u00e8res du Nirvana. \\r\\n    Certains p\\u00e8lerins affirment y avoir senti l\\u2019oubli du monde descendre sur eux comme une paix.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Les donn\\u00e9es concordent : un.e Maison close de Marugame est bien associ\\u00e9.e \\u00e0 cet endroit. \\u00c0 Marugame, dans une maison close r\\u00e9put\\u00e9e pour son sak\\u00e9 sucr\\u00e9 et ses \\u00e9ventails peints \\u00e0 la main, des courtisanes murmurent entre deux chansons.\\r\\n        L\\u2019une d\\u2019elles pr\\u00e9tend avoir lu une lettre scell\\u00e9e, confi\\u00e9e par un \\u00e9missaire enivr\\u00e9, annon\\u00e7ant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre \\u00e9clair contre les Ch\\u014dsokabe.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Grande route et relais de poste est bien li\\u00e9.e \\u00e0 cette localisation. Relie Tokushima \\u00e0 K\\u014dchi en serpentant \\u00e0 travers les plaines fertiles du nord.\\r\\n     \\u00c0 chaque relais, les montures peuvent \\u00eatre chang\\u00e9es, et les messagers imp\\u00e9riaux y trouvent toujours une couche et un bol chaud.<\\/p><p>Les donn\\u00e9es concordent : un.e \\u00c9curies de Kagawa est bien associ\\u00e9.e \\u00e0 cet endroit. Les vastes p\\u00e2turages de Kagawa forment l\\u2019\\u00e9crin id\\u00e9al pour l\\u2019\\u00e9levage de chevaux endurants, pris\\u00e9s tant pour la guerre que pour les grandes caravanes.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p><p>Un rapport fragmentaire mentionne un.e Post relai du courrier de Kagawa comme \\u00e9tant cach\\u00e9.e dans ce territoire.<\\/p>","claim_report":"Je pense que Venturo Attilio pensait avoir une chance au Prefecture de Kagawa. C\\u2019\\u00e9tait mal calcul\\u00e9, iel a \\u00e9chou\\u00e9.<br\\/>"}	2025-06-22 12:42:26.352538
83	35	2	3	6	7	5	1	passive	{}	{"life_report":"Ce.tte trimestre j'ai 7 en investigation et 5\\/1 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cap sud de Kochi.<\\/p>Je me suis rendu compte que Hiuchi Kagaribi (2), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Tepp\\u014d (\\u9244\\u7832) \\u2013 Un mousquet, surveille dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral, ce qui en fait un.e K\\u014dsaku (\\u5de5\\u4f5c) \\u2013 Saboteur un peu trop sp\\u00e9cial.e.<\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p><p>Nous avons identifi\\u00e9 une information int\\u00e9ressante : un.e Mine de fer de Kubokawa serait pr\\u00e9sent.e dans la zone.<\\/p>"}	2025-06-22 12:52:27.225997
95	9	3	10	2	12	3	4	passive	{}	{"life_report":"Ce.tte trimestre j'ai 12 en investigation et 3\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Biwa H\\u014dshi (\\u7435\\u7436\\u6cd5\\u5e2b) \\u2013 Conteur aveugle itin\\u00e9rant du nom de Nao Nobunaga (38) qui enquete dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e K\\u014dro (\\u9999\\u7089) \\u2013 Petit encensoir de voyage, mais cette information n\\u2019est pas si pertinente.Iel fait partie de la faction 7. Iel a de plus une maitrise de la discipline Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral.   <\\/p>J\\u2019ai trouv\\u00e9 Rina Sakai (34), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e H\\u014dshi (\\u6cd5\\u5e2b) \\u2013 Moine bouddhiste itin\\u00e9rant avec un.e Koma (\\u99d2) \\u2013 Pi\\u00e8ces de sh\\u014dgi.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral.<\\/p>J\\u2019ai trouv\\u00e9 Keita Tani (42), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Sodegarami (\\u8896\\u6426) \\u2013 Garde sp\\u00e9cialis\\u00e9 avec un.e Tetsub\\u014d (\\u9244\\u68d2) \\u2014 Masse de guerre en fer.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 7.  Nous l\\u2019avons vu rencontrer en personne Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" est bien li\\u00e9.e \\u00e0 cette localisation. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Suzaku Mon. Voici ce que nous avons appris : Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Cour imp\\u00e9riale est bien li\\u00e9.e \\u00e0 cette localisation. Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p>"}	2025-06-22 13:35:57.582192
86	38	2	1	7	7	4	2	passive	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Ouest d\\u2019Ehime. Ce.tte trimestre j'ai 7 en investigation et 4\\/2 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Ouest d\\u2019Ehime.<\\/p><p><\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Kuro-hatamoto (\\u9ed2\\u65d7\\u672c) \\u2013 Garde d\\u2019\\u00e9lite du nom de Hinako Ichikawa (32) qui revendique le quartier au nom de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d) dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise H\\u014djutsu (\\u7832\\u8853) \\u2013 Art des armes \\u00e0 feu (tepp\\u014d). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin, mais cette information n\\u2019est pas si pertinente.En plus, sa famille a des liens avec la faction 5. Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>Je me suis rendu compte que Riko Hoshino (17), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Ukiyo-e (\\u6d6e\\u4e16\\u7d75) \\u2013 Estampes artistiques, enquete dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), ce qui en fait un.e Tsukai (\\u4f7f\\u3044) \\u2013 Messager rapide un peu trop sp\\u00e9cial.e.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 6. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Ky\\u016bjutsu (\\u5f13\\u8853) \\u2013 Art du tir \\u00e0 l\\u2019arc. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.  <\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Ouest d\\u2019Ehime :<\\/p><p>Il semblerait qu\\u2019un.e Ry\\u016bk\\u014d-ji (\\u7adc\\u5149\\u5bfa) -- Le chemin de l\\u2019illumination se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p><p>Les donn\\u00e9es concordent : un.e Port d\\u2019Uwajima est bien associ\\u00e9.e \\u00e0 cet endroit. Un port anim\\u00e9 aux quais denses et bruyants, o\\u00f9 s\\u2019\\u00e9changent riz, bois, et rumeurs en provenance de Ky\\u016bsh\\u016b comme de Cor\\u00e9e.\\r\\n     Les marins disent que la brume y reste plus longtemps qu\\u2019ailleurs.<\\/p><p>Nos informateurs.rices \\u00e9voquent la d\\u00e9couverte potentielle d\\u2019un.e Port marchand d\\u2019Uwajima dans cette zone.<\\/p>","claim_report":"L\\u2019op\\u00e9ration de Hinako Ichikawa sur C\\u00f4te Ouest d\\u2019Ehime a \\u00e9t\\u00e9 une r\\u00e9ussite totale, malgr\\u00e9 les d\\u00e9gats.<br\\/>"}	2025-06-22 13:21:42.590582
70	26	2	7	4	5	8	7	passive	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 8\\/7 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p><p><\\/p>Je me suis rendu compte que Marco Venezio (13), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Tessen (\\u9244\\u6247) \\u2013 \\u00c9ventail de fer, surveille dans le coin. Je m\\u2019en m\\u00e9fie, iel vient de Portugal. <\\/p>J\\u2019ai vu un.e Onmy\\u014dji (\\u9670\\u967d\\u5e2b) \\u2013 Devin et exorciste du nom de Venturo Attilio (28) qui revendique le quartier au nom de Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd) dans ma zone d\\u2019action. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de Portugal. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari).<\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Nos informateurs.rices \\u00e9voquent la d\\u00e9couverte potentielle d\\u2019un.e Maison close de Marugame dans cette zone.<\\/p>","claim_report":"Venturo Attilio a voulu s\\u2019imposer au Prefecture de Kagawa, sans succ\\u00e8s. Iel a \\u00e9t\\u00e9 forc\\u00e9.e de battre en retraite.<br\\/>J\\u2019ai vu Venturo Attilio tenter de prendre le contr\\u00f4le du territoire Prefecture de Kagawa, mais la d\\u00e9fense l\\u2019a repouss\\u00e9.e brutalement.<br\\/>"}	2025-06-22 12:42:26.352538
69	25	2	6	4	6	6	4	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 6\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p>Je me suis rendu compte que Arisa Komatsu (29), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Shamisen (\\u4e09\\u5473\\u7dda) \\u2013 Instrument \\u00e0 cordes, enquete dans le coin. Je m\\u2019en m\\u00e9fie, iel vient de Honshu - Okayama. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie, ce qui en fait un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas un peu trop sp\\u00e9cial.e.En plus, sa famille a des liens avec la faction 6. <\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p><p>Certains indices laissent penser qu\\u2019un.e Port de Tokushima pourrait se cacher ici.<\\/p>"}	2025-06-22 12:42:26.352538
84	36	2	6	8	8	3	3	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Grande Baie de Kochi. J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Est de Tokushima. Ce.tte trimestre j'ai 8 en investigation et 3\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p>J\\u2019ai trouv\\u00e9 Arisa Komatsu (29), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas avec un.e Shamisen (\\u4e09\\u5473\\u7dda) \\u2013 Instrument \\u00e0 cordes.Je m\\u2019en m\\u00e9fie, iel vient de Honshu - Okayama. Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 6.  Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>J\\u2019ai trouv\\u00e9 Miki Arakawa (19), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e K\\u014dshitsu (\\u9999\\u5e2b) \\u2013 Sp\\u00e9cialiste de l\\u2019art de l\\u2019encens avec un.e Sensu (\\u6247\\u5b50) \\u2013 \\u00c9ventail pliable.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Chad\\u014d (\\u8336\\u9053) \\u2013 Voie du th\\u00e9.<\\/p>J\\u2019ai trouv\\u00e9 Haruki Inoue (25), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Kusushi (\\u85ac\\u5e2b) \\u2013 M\\u00e9decin itin\\u00e9rant avec un.e Kusarigama (\\u9396\\u938c) \\u2014 Arme compos\\u00e9e d\\u2019une faucille attach\\u00e9e \\u00e0 une cha\\u00eene lest\\u00e9e.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 4. En plus, iel maitrise l\\u2019art du Waka (\\u548c\\u6b4c) \\u2013 Po\\u00e9sie classique.  <\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p><p>Nos informateurs.rices \\u00e9voquent la d\\u00e9couverte potentielle d\\u2019un.e Comptoir de Kashiwa dans cette zone.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Port de Tokushima se trouve dans cette zone. Carrefour maritime entre Honsh\\u016b et Shikoku, le port de Tokushima bruisse de dialectes et de voiles \\u00e9trang\\u00e8res.\\r\\n     Dans les ruelles proches du march\\u00e9, on parle parfois espagnol, ou latin, \\u00e0 voix basse.<\\/p><p>Il semblerait qu\\u2019un.e Port de Tokushima se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>"}	2025-06-22 12:52:42.680571
64	20	2	10	8	6	6	3	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Cit\\u00e9 Imp\\u00e9riale de Kyoto. Ce.tte trimestre j'ai 6 en investigation et 6\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p><p><\\/p>Nous avons rep\\u00e9r\\u00e9 le possesseur d\\u2019un.e Cheval Kagawa du nom de Renry\\u016b(\\u84ee\\u7adc) Takeda(\\u6b66\\u7530) (8) qui surveille dans notre r\\u00e9gion. En plus, iel est originaire de Honshu - Kyoto. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie. Iel poss\\u00e8de aussi un.e Katana (\\u5200) \\u2013 L\\u2019arme embl\\u00e9matique du samoura\\u00ef, mais cette information n\\u2019est pas pertinente. En plus, sa famille a des liens avec la faction 2. En plus, iel maitrise l\\u2019art du Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire. En plus, iel maitrise l\\u2019art du Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement. <\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" est bien li\\u00e9.e \\u00e0 cette localisation. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p><p>Il semblerait qu\\u2019un.e Suzaku Mon se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>"}	2025-06-22 12:42:26.352538
62	17	2	1	6	5	8	3	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Ouest d\\u2019Ehime. Ce.tte trimestre j'ai 5 en investigation et 8\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Ouest d\\u2019Ehime.<\\/p><p><\\/p><p><\\/p>J\\u2019ai trouv\\u00e9 Hinako Ichikawa (32), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Kuro-hatamoto (\\u9ed2\\u65d7\\u672c) \\u2013 Garde d\\u2019\\u00e9lite avec un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline H\\u014djutsu (\\u7832\\u8853) \\u2013 Art des armes \\u00e0 feu (tepp\\u014d).En plus, sa famille a des liens avec la faction 5. <\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Ouest d\\u2019Ehime :<\\/p>","claim_report":"L\\u2019op\\u00e9ration de Hinako Ichikawa sur C\\u00f4te Ouest d\\u2019Ehime a \\u00e9t\\u00e9 une r\\u00e9ussite totale, malgr\\u00e9 les d\\u00e9gats.<br\\/>"}	2025-06-22 12:42:26.352538
66	22	2	4	5	12	4	3	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Grande Baie de Kochi. Ce.tte trimestre j'ai 12 en investigation et 4\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p>Je me suis rendu compte que Claire Richard (12), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Hy\\u014dshigi (\\u62cd\\u5b50\\u6728) \\u2013 Claves en bois, surveille dans le coin. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de France. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), ce qui en fait un.e N\\u014dgakushi (\\u80fd\\u697d\\u5e2b) \\u2013 Acteur de th\\u00e9\\u00e2tre N\\u014d un peu trop sp\\u00e9cial.e.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 8. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau. En plus, iel maitrise l\\u2019art du Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer.  Ce qui veut dire que c\\u2019est un serviteur de Murai Wako (\\u548c\\u5149). <\\/p>J\\u2019ai trouv\\u00e9 Shiori Kiriyama (27), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Shikibu (\\u5f0f\\u90e8) \\u2013 Ma\\u00eetre de c\\u00e9r\\u00e9monie avec un.e Juzu (\\u6570\\u73e0) \\u2013 Un bracelet de perles bouddhistes.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral.Iel travaille avec la faction 7. A partir de l\\u00e0 nous avons pu remonter jusqu\\u2019\\u00e0 Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p>On a suivi Emi Nagano (24) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Miko (\\u5deb\\u5973) \\u2013 Servante shint\\u014d mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e N\\u014dky\\u014dch\\u014d (\\u7d0d\\u7d4c\\u5e33) \\u2013 Un carnet de p\\u00e8lerinage.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Reiki \\/ Kujikiri (\\u970a\\u6c17 \\/ \\u4e5d\\u5b57\\u5207\\u308a) \\u2013 Pratiques \\u00e9sot\\u00e9riques, au moins partiellement.En plus, sa famille a des liens avec la faction 6. Ce qui veut dire que c\\u2019est un serviteur de Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>Je me suis rendu compte que Nanami Koga (31), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Jitte (\\u5341\\u624b) \\u2013 Arme de police, revendique le quartier au nom de Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd) dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire, ce qui en fait un.e Kas\\u014d (\\u82b1\\u5320) \\u2013 Artiste florale un peu trop sp\\u00e9cial.e. Iel re\\u00e7oit un soutien financier de la faction 4. Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p><p>Les donn\\u00e9es concordent : un.e Forteresse des Samoura\\u00ef Ch\\u014dsokabe est bien associ\\u00e9.e \\u00e0 cet endroit. \\r\\n        Nous avons trouv\\u00e9 la forteresse de La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8) des Samoura\\u00ef Ch\\u014dsokabe. Les serviteurs de confiance leur manquent encore pour avoir des d\\u00e9fenses solides.\\r\\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\\r\\n        L\\u2019attaque causerait certainement quelques questions \\u00e0 la cour du Shogun, mais un joueur affaibli sur l\\u2019\\u00e9chiquier politique est toujours b\\u00e9n\\u00e9fique.\\r\\n        Nous ne devons pas tarder \\u00e0 prendre notre d\\u00e9cision, ses d\\u00e9fenses se renforcent chaque trimestre.\\r\\n     Il est possible d\\u2019organiser une mission pour faire dispara\\u00eetre ce probl\\u00e8me.Ce lieu contient : <ul><li><strong>Motochika (\\u5143\\u89aa) Ch\\u014dsokabe(\\u9577\\u5b97\\u6211\\u90e8) daimy\\u00f4 en devenir<\\/strong>: Fils de Kunichika, encore trop jeune pour gouverner, il est la clef d\\u2019un fragile h\\u00e9ritage.<\\/li><\\/ul><\\/p><p>Les donn\\u00e9es concordent : un.e Chikurin-ji (\\u7af9\\u6797\\u5bfa) -- Le chemin de l\\u2019asc\\u00e8se est bien associ\\u00e9.e \\u00e0 cet endroit. Perch\\u00e9 au sommet d\\u2019une colline surplombant la baie, le temple veille parmi les bambous. \\r\\n    Les moines y pratiquent une asc\\u00e8se rigoureuse, veillant jour et nuit face \\u00e0 l\\u2019oc\\u00e9an sans fin. \\r\\n    Le vent porte leurs chants jusqu\\u2019aux barques des p\\u00eacheurs, comme des pri\\u00e8res sal\\u00e9es.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Sanctuaire bris\\u00e9 de Hiwasa. Voici ce que nous avons appris : Surplombant la mer, les ruines du sanctuaire de Hiwasa sont battues par les embruns.\\r\\n        On dit que des pr\\u00eatres \\u00e9trangers y ont \\u00e9t\\u00e9 vus de nuit, en compagnie d\\u2019\\u00e9missaires du clan Ch\\u014dsokabe.\\r\\n        La rumeur parle d\\u2019un pacte impie : en \\u00e9change d\\u2019armes \\u00e0 feu venues de Nagasaki, le clan accepterait d\\u2019abriter des convertis clandestins.<\\/p><p>Information int\\u00e9ressante : un.e Port de Kochi est pr\\u00e9sent.e dans la zone. Prot\\u00e9g\\u00e9 par une anse naturelle, ce port militaire et marchand voit passer jonques, bateaux de guerre et pirates repenti.\\r\\n      Son arsenal est surveill\\u00e9 nuit et jour par des ashigaru en armure sombre.\\r\\n      On dit que le clan Ch\\u014dsokabe y cache des objects illegaux import\\u00e9 d\\u2019ailleurs.<\\/p>","claim_report":"L\\u2019assaut de Nanami Koga sur le territoire Grande Baie de Kochi a \\u00e9chou\\u00e9 ; c\\u2019\\u00e9tait un vrai carnage.<br\\/>"}	2025-06-22 12:42:26.352538
105	19	3	7	7	9	3	6	passive	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers C\\u00f4te Ouest d\\u2019Ehime. J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Prefecture de Kagawa. Ce.tte trimestre j'ai 9 en investigation et 3\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p>On a suivi Taiga Tani (26) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de enqueter, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Marin Portugais mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie, au moins partiellement.<\\/p><p><\\/p>J\\u2019ai vu un.e Sarugakushi (\\u733f\\u697d\\u5e2b) \\u2013 Artiste de rue ou com\\u00e9dien du nom de Souta Yamamoto (10) qui surveille dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Juzu (\\u6570\\u73e0) \\u2013 Un bracelet de perles bouddhistes mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de J\\u016bjutsu (\\u67d4\\u8853) \\u2013 Techniques de lutte \\u00e0 mains nues.<\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Les donn\\u00e9es concordent : un.e Yashima-ji (\\u5c4b\\u5cf6\\u5bfa) -- Le chemin du Nirvana est bien associ\\u00e9.e \\u00e0 cet endroit. Ancien bastion surplombant les flots, Yashima-ji garde la m\\u00e9moire des batailles et des ermites. \\r\\n    Les brumes de l\\u2019aube y voilent statues et stupas, comme pour dissimuler les myst\\u00e8res du Nirvana. \\r\\n    Certains p\\u00e8lerins affirment y avoir senti l\\u2019oubli du monde descendre sur eux comme une paix.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Maison close de Marugame. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : \\u00c0 Marugame, dans une maison close r\\u00e9put\\u00e9e pour son sak\\u00e9 sucr\\u00e9 et ses \\u00e9ventails peints \\u00e0 la main, des courtisanes murmurent entre deux chansons.\\r\\n        L\\u2019une d\\u2019elles pr\\u00e9tend avoir lu une lettre scell\\u00e9e, confi\\u00e9e par un \\u00e9missaire enivr\\u00e9, annon\\u00e7ant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre \\u00e9clair contre les Ch\\u014dsokabe.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Grande route et relais de poste est bien li\\u00e9.e \\u00e0 cette localisation. Relie Tokushima \\u00e0 K\\u014dchi en serpentant \\u00e0 travers les plaines fertiles du nord.\\r\\n     \\u00c0 chaque relais, les montures peuvent \\u00eatre chang\\u00e9es, et les messagers imp\\u00e9riaux y trouvent toujours une couche et un bol chaud.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e \\u00c9curies de Kagawa est bien li\\u00e9.e \\u00e0 cette localisation. Les vastes p\\u00e2turages de Kagawa forment l\\u2019\\u00e9crin id\\u00e9al pour l\\u2019\\u00e9levage de chevaux endurants, pris\\u00e9s tant pour la guerre que pour les grandes caravanes.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Post relai du courrier de Kagawa se trouve dans cette zone. Une auberge modeste pr\\u00e8s de la grande route de Kagawa re\\u00e7oit parfois, \\u00e0 l\\u2019aube, des cavaliers fatigu\\u00e9s portant des missives cachet\\u00e9es.\\r\\n        L\\u2019une d\\u2019elles, r\\u00e9cemment intercept\\u00e9e, contenait une promesse de mariage scell\\u00e9e entre Motochika Ch\\u014dsokabe et Tama Hosokawa, fille de Fujitaka.\\r\\n        Si elle venait \\u00e0 se concr\\u00e9tiser, cette alliance unirait deux grandes maisons sur Shikoku et changerait les rapports de pouvoir de toute la r\\u00e9gion.\\r\\n        Pour l\\u2019instant, l\\u2019information est gard\\u00e9e secr\\u00e8te, mais les rumeurs montent.<\\/p>"}	2025-06-22 13:35:57.582192
68	24	2	4	6	6	3	5	passive	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 3\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p><p><\\/p>On a suivi Claire Richard (12) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e N\\u014dgakushi (\\u80fd\\u697d\\u5e2b) \\u2013 Acteur de th\\u00e9\\u00e2tre N\\u014d mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Hy\\u014dshigi (\\u62cd\\u5b50\\u6728) \\u2013 Claves en bois.Je m\\u2019en m\\u00e9fie, iel vient de France. Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), au moins partiellement.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Shikibu (\\u5f0f\\u90e8) \\u2013 Ma\\u00eetre de c\\u00e9r\\u00e9monie du nom de Shiori Kiriyama (27) qui surveille dans notre r\\u00e9gion. <\\/p>J\\u2019ai vu un.e Kas\\u014d (\\u82b1\\u5320) \\u2013 Artiste florale du nom de Nanami Koga (31) qui revendique le quartier au nom de Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd) dans ma zone d\\u2019action. <\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p><p>Un rapport fragmentaire mentionne un.e Port de Kochi comme \\u00e9tant cach\\u00e9.e dans ce territoire.<\\/p>","claim_report":"Nanami Koga a voulu s\\u2019imposer au Grande Baie de Kochi, sans succ\\u00e8s. Iel a \\u00e9t\\u00e9 forc\\u00e9.e de battre en retraite.<br\\/>"}	2025-06-22 12:42:26.352538
72	27	2	4	7	6	3	4	passive	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 3\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p><p><\\/p>Je me suis rendu compte que Claire Richard (12), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Hy\\u014dshigi (\\u62cd\\u5b50\\u6728) \\u2013 Claves en bois, surveille dans le coin. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de France. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), ce qui en fait un.e N\\u014dgakushi (\\u80fd\\u697d\\u5e2b) \\u2013 Acteur de th\\u00e9\\u00e2tre N\\u014d un peu trop sp\\u00e9cial.e.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Miko (\\u5deb\\u5973) \\u2013 Servante shint\\u014d du nom de Emi Nagano (24) qui surveille dans notre r\\u00e9gion. <\\/p>J\\u2019ai vu un.e Kas\\u014d (\\u82b1\\u5320) \\u2013 Artiste florale du nom de Nanami Koga (31) qui revendique le quartier au nom de Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd) dans ma zone d\\u2019action. <\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p><p>Nous avons identifi\\u00e9 une information int\\u00e9ressante : un.e Port de Kochi serait pr\\u00e9sent.e dans la zone.<\\/p>","claim_report":"Je pense que Nanami Koga pensait avoir une chance au Grande Baie de Kochi. C\\u2019\\u00e9tait mal calcul\\u00e9, iel a \\u00e9chou\\u00e9.<br\\/>"}	2025-06-22 12:42:26.352538
102	16	3	3	4	0	0	0	dead	[{"attackScope":"worker","attackID":2}]	{}	2025-06-22 13:35:57.582192
103	17	3	1	6	6	8	3	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 8\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Ouest d\\u2019Ehime.<\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Ouest d\\u2019Ehime :<\\/p><p>Un rapport fragmentaire mentionne un.e Port d\\u2019Uwajima comme \\u00e9tant cach\\u00e9.e dans ce territoire.<\\/p>"}	2025-06-22 13:35:57.582192
88	2	3	3	1	8	9	9	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 9\\/9 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cap sud de Kochi.<\\/p>On a suivi Ryota Yoshikawa (18) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de revendiquer le quartier au nom de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d), ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Kagemusha (\\u5f71\\u6b66\\u8005) \\u2013 Sosie du seigneur mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Wakizashi (\\u8107\\u5dee) \\u2013 Sabre court.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie, au moins partiellement.<\\/p>J\\u2019ai trouv\\u00e9 Mai Ichikawa (35), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Naishi (\\u5185\\u4f8d) \\u2013 Dame de compagnie avec un.e Sensu (\\u6247\\u5b50) \\u2013 \\u00c9ventail pliable.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari).<\\/p>J\\u2019ai vu un.e Monogashira (\\u7269\\u982d) \\u2013 Officier en chef du nom de Yuna Shimizu (39) qui surveille dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Zudabukuro (\\u982d\\u9640\\u888b) \\u2013 Une besace de p\\u00e8lerin mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer.En plus, sa famille a des liens avec la faction 8. Nous l\\u2019avons vu rencontrer en personne Murai Wako (\\u548c\\u5149). <\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Mine de fer de Kubokawa est bien ici. Dans les profondeurs du cap sud de K\\u014dchi, des veines de fer noir sont extraites \\u00e0 la force des bras puis forg\\u00e9es en cuirasses robustes dans les forges voisines.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p>","claim_report":"Cap sud de Kochi appartient d\\u00e9sormais au maitre de Ryota Yoshikawa. Iel a balay\\u00e9 toute r\\u00e9sistance.<br\\/>"}	2025-06-22 13:35:57.582192
90	4	3	4	1	10	6	5	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Grande Baie de Kochi. Ce.tte trimestre j'ai 10 en investigation et 6\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p>J\\u2019ai trouv\\u00e9 Nanami Koga (31), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Kas\\u014d (\\u82b1\\u5320) \\u2013 Artiste florale avec un.e Jitte (\\u5341\\u624b) \\u2013 Arme de police.Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral.En plus, sa famille a des liens avec la faction 4. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire. <\\/p>On a suivi Hinako Ichikawa (32) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de revendiquer le quartier au nom de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d), ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Kuro-hatamoto (\\u9ed2\\u65d7\\u672c) \\u2013 Garde d\\u2019\\u00e9lite mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser H\\u014djutsu (\\u7832\\u8853) \\u2013 Art des armes \\u00e0 feu (tepp\\u014d), au moins partiellement et nous observons qu\\u2019iel poss\\u00e8de un.e Encens Cor\\u00e9en.Iel fait partie de la faction 5. En plus, iel maitrise l\\u2019art du Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire.   Ce qui veut dire que c\\u2019est un serviteur de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e N\\u014dgakushi (\\u80fd\\u697d\\u5e2b) \\u2013 Acteur de th\\u00e9\\u00e2tre N\\u014d du nom de Claire Richard (12) qui surveille dans notre r\\u00e9gion. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de France. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Hy\\u014dshigi (\\u62cd\\u5b50\\u6728) \\u2013 Claves en bois, mais cette information n\\u2019est pas si pertinente.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 8. Ces observations se cumulent avec son utilisation de la discipline Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer.  Nous l\\u2019avons vu rencontrer en personne Murai Wako (\\u548c\\u5149). <\\/p>Je me suis rendu compte que Emi Nagano (24), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e N\\u014dky\\u014dch\\u014d (\\u7d0d\\u7d4c\\u5e33) \\u2013 Un carnet de p\\u00e8lerinage, surveille dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de Reiki \\/ Kujikiri (\\u970a\\u6c17 \\/ \\u4e5d\\u5b57\\u5207\\u308a) \\u2013 Pratiques \\u00e9sot\\u00e9riques, ce qui en fait un.e Miko (\\u5deb\\u5973) \\u2013 Servante shint\\u014d un peu trop sp\\u00e9cial.e.Iel fait partie de la faction 6.   Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>Je me suis rendu compte que Koji Nagano (40), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Kakute (\\u89d2\\u624b) \\u2013 Anneau \\u00e0 pointes, enquete dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), ce qui en fait un.e Sodegarami (\\u8896\\u6426) \\u2013 Garde sp\\u00e9cialis\\u00e9 un peu trop sp\\u00e9cial.e.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 4.  Nous l\\u2019avons vu rencontrer en personne La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Chikurin-ji (\\u7af9\\u6797\\u5bfa) -- Le chemin de l\\u2019asc\\u00e8se est bien li\\u00e9.e \\u00e0 cette localisation. Perch\\u00e9 au sommet d\\u2019une colline surplombant la baie, le temple veille parmi les bambous. \\r\\n    Les moines y pratiquent une asc\\u00e8se rigoureuse, veillant jour et nuit face \\u00e0 l\\u2019oc\\u00e9an sans fin. \\r\\n    Le vent porte leurs chants jusqu\\u2019aux barques des p\\u00eacheurs, comme des pri\\u00e8res sal\\u00e9es.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Sanctuaire bris\\u00e9 de Hiwasa se trouve dans cette zone. Surplombant la mer, les ruines du sanctuaire de Hiwasa sont battues par les embruns.\\r\\n        On dit que des pr\\u00eatres \\u00e9trangers y ont \\u00e9t\\u00e9 vus de nuit, en compagnie d\\u2019\\u00e9missaires du clan Ch\\u014dsokabe.\\r\\n        La rumeur parle d\\u2019un pacte impie : en \\u00e9change d\\u2019armes \\u00e0 feu venues de Nagasaki, le clan accepterait d\\u2019abriter des convertis clandestins.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Port de Kochi se trouve dans cette zone. Prot\\u00e9g\\u00e9 par une anse naturelle, ce port militaire et marchand voit passer jonques, bateaux de guerre et pirates repenti.\\r\\n      Son arsenal est surveill\\u00e9 nuit et jour par des ashigaru en armure sombre.\\r\\n      On dit que le clan Ch\\u014dsokabe y cache des objects illegaux import\\u00e9 d\\u2019ailleurs.<\\/p>","claim_report":"L\\u2019op\\u00e9ration de Hinako Ichikawa sur Grande Baie de Kochi a \\u00e9t\\u00e9 une r\\u00e9ussite totale, malgr\\u00e9 les d\\u00e9gats.<br\\/>L\\u2019assaut de Nanami Koga sur le territoire Grande Baie de Kochi a \\u00e9chou\\u00e9 ; c\\u2019\\u00e9tait un vrai carnage.<br\\/>"}	2025-06-22 13:35:57.582192
111	25	3	6	4	10	7	4	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 10 en investigation et 7\\/4 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p>J\\u2019ai vu un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas du nom de Arisa Komatsu (29) qui enquete dans ma zone d\\u2019action. Je m\\u2019en m\\u00e9fie, iel vient de Honshu - Okayama. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Shamisen (\\u4e09\\u5473\\u7dda) \\u2013 Instrument \\u00e0 cordes mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie.Iel travaille avec la faction 6. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Ninjutsu (\\u5fcd\\u8853) \\u2013 Techniques d\\u2019espionnage et de gu\\u00e9rilla. <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Marin Portugais du nom de Mei Yamamoto (44) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Tessen (\\u9244\\u6247) \\u2013 \\u00c9ventail de fer, mais cette information n\\u2019est pas si pertinente. Iel re\\u00e7oit un soutien financier de la faction 5. Ce qui veut dire que c\\u2019est un serviteur de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Forteresse des Samoura\\u00ef Miyoshi. Voici ce que nous avons appris : \\r\\n        Nous avons trouv\\u00e9 la forteresse de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d) des Samoura\\u00ef Miyoshi. Les serviteurs de confiance leur manquent encore pour avoir des d\\u00e9fenses solides.\\r\\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\\r\\n        L\\u2019attaque causerait certainement quelques questions \\u00e0 la cour du Shogun, mais un joueur affaibli sur l\\u2019\\u00e9chiquier politique est toujours b\\u00e9n\\u00e9fique.\\r\\n        Nous ne devons pas tarder \\u00e0 prendre notre d\\u00e9cision, ses d\\u00e9fenses se renforcent chaque trimestre.\\r\\n    \\r\\n       Il nous apparait en fouillant le lieu que ce quelqu\\u2019un s\\u2019est donn\\u00e9 beaucoup de mal pour que cette forteresse donne l\\u2019impression d\\u2019\\u00eatre li\\u00e9e aux Samoura\\u00ef Miyoshi, mais en r\\u00e9alit\\u00e9 son propri\\u00e9taire est des Chr\\u00e9tiens.\\r\\n     Il est possible d\\u2019organiser une mission pour faire dispara\\u00eetre ce probl\\u00e8me.<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Comptoir de Kashiwa est bien ici. Ce modeste comptoir marchand, adoss\\u00e9 \\u00e0 une crique discr\\u00e8te, conna\\u00eet une activit\\u00e9 \\u00e9trange depuis quelques semaines.\\r\\n        Des jonques aux voiles noires y accostent en silence, et les capitaines refusent de dire d\\u2019o\\u00f9 ils viennent.\\r\\n        Certains affirment que les Wako y auraient re\\u00e7u des fonds d\\u2019un clan du nord \\u2014 peut-\\u00eatre les Hosokawa \\u2014 pour saboter les entrep\\u00f4ts du port de Tokushima.\\r\\n        D\\u2019autres y voient simplement un commerce de sel et de fer\\u2026 mais pourquoi alors tant de discr\\u00e9tion, et autant de lames pr\\u00eates \\u00e0 jaillir \\u00e0 la moindre question ?<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Port de Tokushima est bien ici. Carrefour maritime entre Honsh\\u016b et Shikoku, le port de Tokushima bruisse de dialectes et de voiles \\u00e9trang\\u00e8res.\\r\\n     Dans les ruelles proches du march\\u00e9, on parle parfois espagnol, ou latin, \\u00e0 voix basse.<\\/p><p>Information int\\u00e9ressante : un.e Port de Tokushima est pr\\u00e9sent.e dans la zone. Dans les ruelles du port de Tokushima, \\u00e0 l\\u2019\\u00e9cart des march\\u00e9s, une maison basse aux volets clos abrite un h\\u00f4te peu commun : Lu\\u00eds Fr\\u00f3is, pr\\u00eatre j\\u00e9suite portugais, \\u00e9rudit des m\\u0153urs japonaises.\\r\\n        Il y aurait \\u00e9tabli un sanctuaire clandestin, enseignant les paroles du Christ \\u00e0 quelques convertis du clan Miyoshi.\\r\\n        Ce lieu sert \\u00e9galement de relais discret pour faire entrer armes, livres et messagers depuis Nagasaki.\\r\\n        Sa pr\\u00e9sence confirme l\\u2019implantation secr\\u00e8te du christianisme \\u00e0 Tokushima, et menace de faire basculer les \\u00e9quilibres religieux et politiques de Shikoku.<\\/p>"}	2025-06-22 13:35:57.582192
124	38	3	10	7	10	4	3	investigate	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Cit\\u00e9 Imp\\u00e9riale de Kyoto. Ce.tte trimestre j'ai 10 en investigation et 4\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p>J\\u2019ai trouv\\u00e9 Lady Ibara(\\u8328\\u306e\\u7d05) (7), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas avec un.e Shamisen (\\u4e09\\u5473\\u7dda) \\u2013 Instrument \\u00e0 cordes.J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de Honshu - Kyoto. Iel d\\u00e9montre une l\\u00e9g\\u00e8re maitrise de la discipline Ky\\u016bjutsu (\\u5f13\\u8853) \\u2013 Art du tir \\u00e0 l\\u2019arc, de plus iel laisse penser qu\\u2019iel a un.e Th\\u00e9 d\\u2019Obok\\u00e9 et d\\u2019Iya.<\\/p>Nous avons rep\\u00e9r\\u00e9 le possesseur d\\u2019un.e Armure en fer de Kochi du nom de Lord Asakura(\\u671d\\u5009) Mitsunao(\\u5149\\u76f4) (6) qui surveille dans notre r\\u00e9gion. Je m\\u2019en m\\u00e9fie, iel vient de Honshu - Kyoto. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Shod\\u014d (\\u66f8\\u9053) \\u2013 Calligraphie. Iel poss\\u00e8de aussi un.e Go-ban (\\u7881\\u76e4) \\u2013 Plateau de Go, mais cette information n\\u2019est pas pertinente. En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 2. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Heih\\u014d (\\u5175\\u6cd5) \\u2013 Strat\\u00e9gie militaire. Ces observations se cumulent avec son utilisation de la discipline Bugaku (\\u821e\\u697d) \\u2013 Danse de cour.  <\\/p><p><\\/p><p><\\/p>Je me suis rendu compte que quelqu\\u2019un poss\\u00e9dant un.e Cheval Kagawa surveille dans le coin. On l\\u2019a entendu.e se faire appeler Renry\\u016b(\\u84ee\\u7adc) Takeda(\\u6b66\\u7530) (8). J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de Honshu - Kyoto. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a des capacit\\u00e9s de surveille, ce qui en fait un Kuro-hatamoto (\\u9ed2\\u65d7\\u672c) \\u2013 Garde d\\u2019\\u00e9lite un peu trop sp\\u00e9cial.En plus, sa famille a des liens avec la faction 2. Iel a de plus une maitrise de la discipline Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement. Ce qui veut dire que c\\u2019est un serviteur de Yoshiteru (\\u7fa9\\u8f1d) Ashikaga (\\u8db3\\u5229). <\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" se trouve dans cette zone. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p><p>Les donn\\u00e9es concordent : un.e Suzaku Mon est bien associ\\u00e9.e \\u00e0 cet endroit. Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Des rumeurs persistantes \\u00e9voquent la pr\\u00e9sence d\\u2019un.e Ge\\u00f4les imp\\u00e9riales dans les environs.<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Cour imp\\u00e9riale est bien ici. Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p>"}	2025-06-22 13:35:57.582192
112	26	3	7	4	8	9	7	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 9\\/7 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p><p><\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Onmy\\u014dji (\\u9670\\u967d\\u5e2b) \\u2013 Devin et exorciste du nom de Venturo Attilio (28) qui surveille dans notre r\\u00e9gion. En plus, iel est originaire de Portugal. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin, mais cette information n\\u2019est pas si pertinente.Iel fait partie de la faction 7. Ces observations se cumulent avec son utilisation de la discipline Ky\\u016bjutsu (\\u5f13\\u8853) \\u2013 Art du tir \\u00e0 l\\u2019arc. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement.   Nous l\\u2019avons vu rencontrer en personne Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p><p><\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Bugy\\u014d (\\u5949\\u884c) \\u2013 Magistrat ou officier administratif du nom de Marco Venezio (13) qui surveille dans notre r\\u00e9gion. En plus, iel est originaire de Portugal. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Tessen (\\u9244\\u6247) \\u2013 \\u00c9ventail de fer, mais cette information n\\u2019est pas si pertinente.En plus, iel maitrise l\\u2019art du Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral. Nous avons \\u00e9galement remarqu\\u00e9 sa pratique de l\\u2019art Bugaku (\\u821e\\u697d) \\u2013 Danse de cour.  Iel re\\u00e7oit un soutien financier de la faction 7. <\\/p>J\\u2019ai vu un.e Sarugakushi (\\u733f\\u697d\\u5e2b) \\u2013 Artiste de rue ou com\\u00e9dien du nom de Souta Yamamoto (10) qui surveille dans ma zone d\\u2019action. <\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Yashima-ji (\\u5c4b\\u5cf6\\u5bfa) -- Le chemin du Nirvana. Voici ce que nous avons appris : Ancien bastion surplombant les flots, Yashima-ji garde la m\\u00e9moire des batailles et des ermites. \\r\\n    Les brumes de l\\u2019aube y voilent statues et stupas, comme pour dissimuler les myst\\u00e8res du Nirvana. \\r\\n    Certains p\\u00e8lerins affirment y avoir senti l\\u2019oubli du monde descendre sur eux comme une paix.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Nous pouvons retourner cette information contre son ma\\u00eetre et nous y attaquer.<\\/p><p>Les donn\\u00e9es concordent : un.e Maison close de Marugame est bien associ\\u00e9.e \\u00e0 cet endroit. \\u00c0 Marugame, dans une maison close r\\u00e9put\\u00e9e pour son sak\\u00e9 sucr\\u00e9 et ses \\u00e9ventails peints \\u00e0 la main, des courtisanes murmurent entre deux chansons.\\r\\n        L\\u2019une d\\u2019elles pr\\u00e9tend avoir lu une lettre scell\\u00e9e, confi\\u00e9e par un \\u00e9missaire enivr\\u00e9, annon\\u00e7ant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre \\u00e9clair contre les Ch\\u014dsokabe.<\\/p><p>Information int\\u00e9ressante : un.e Grande route et relais de poste est pr\\u00e9sent.e dans la zone. Relie Tokushima \\u00e0 K\\u014dchi en serpentant \\u00e0 travers les plaines fertiles du nord.\\r\\n     \\u00c0 chaque relais, les montures peuvent \\u00eatre chang\\u00e9es, et les messagers imp\\u00e9riaux y trouvent toujours une couche et un bol chaud.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e \\u00c9curies de Kagawa est bien li\\u00e9.e \\u00e0 cette localisation. Les vastes p\\u00e2turages de Kagawa forment l\\u2019\\u00e9crin id\\u00e9al pour l\\u2019\\u00e9levage de chevaux endurants, pris\\u00e9s tant pour la guerre que pour les grandes caravanes.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p><p>Des rumeurs persistantes \\u00e9voquent la pr\\u00e9sence d\\u2019un.e Post relai du courrier de Kagawa dans les environs.<\\/p>"}	2025-06-22 13:35:57.582192
125	39	3	3	8	3	7	5	passive	{}	{"life_report":"J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Ile de Sh\\u014ddoshima. J'ai d\\u00e9m\\u00e9nag\\u00e9 vers Cap sud de Kochi. Ce.tte trimestre j'ai 3 en investigation et 7\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cap sud de Kochi.<\\/p><p><\\/p><p><\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p>","claim_report":"Cap sud de Kochi appartient d\\u00e9sormais au maitre de Ryota Yoshikawa. Iel a balay\\u00e9 toute r\\u00e9sistance.<br\\/>"}	2025-06-22 13:41:58.56158
87	1	3	2	1	8	7	8	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 7\\/8 en attaque\\/d\\u00e9fense.<p>L\\u2019attaque de Ami Tajima(37) du r\\u00e9sseau 6 des agents de Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982) a \\u00e9chou\\u00e9, je suis sauf.ve et hors de danger.<\\/p>","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p><p><\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Shihan (\\u5e2b\\u7bc4) \\u2013 Ma\\u00eetre instructeur du nom de Miki Yamaguchi (33) qui enquete dans notre r\\u00e9gion. <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e H\\u014din (\\u6cd5\\u5370) \\u2013 Pr\\u00eatresse ou religieuse conseill\\u00e8re du nom de Ami Tajima (37) qui attaque une personne  dans notre r\\u00e9gion. J\\u2019ai des raisons de penser qu\\u2019iel est natif.ve de Kyushu - \\u00d6ita. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise J\\u016bjutsu (\\u67d4\\u8853) \\u2013 Techniques de lutte \\u00e0 mains nues. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Kunai (\\u82e6\\u7121) \\u2013 Dague multi-usage, mais cette information n\\u2019est pas si pertinente.Iel travaille avec la faction 6. Ce r\\u00e9seau d\\u2019informateurs r\\u00e9pond \\u00e0 Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p><p>Information int\\u00e9ressante : un.e Forteresse des Kaizokush\\u016b est pr\\u00e9sent.e dans la zone. \\r\\n        Nous avons trouv\\u00e9 la forteresse de Murai Wako (\\u548c\\u5149) des Kaizokush\\u016b. Les serviteurs de confiance leur manquent encore pour avoir des d\\u00e9fenses solides.\\r\\n        En attaquant ce lieu nous pourrions lui porter un coup fatal.\\r\\n        L\\u2019attaque causerait certainement quelques questions \\u00e0 la cour du Shogun, mais un joueur affaibli sur l\\u2019\\u00e9chiquier politique est toujours b\\u00e9n\\u00e9fique.\\r\\n        Nous ne devons pas tarder \\u00e0 prendre notre d\\u00e9cision, ses d\\u00e9fenses se renforcent chaque trimestre.\\r\\n     Il est possible d\\u2019organiser une mission pour faire dispara\\u00eetre ce probl\\u00e8me.<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Sanctuaire des Pins Br\\u00fbl\\u00e9s. Voici ce que nous avons appris : Dans un ancien sanctuaire shint\\u014d dont les piliers carbonis\\u00e9s r\\u00e9sistent au temps, des p\\u00e8lerins affirment avoir vu un artefact \\u00e9trange cach\\u00e9 sous l\\u2019autel \\u2014 une croix d\\u2019argent sertie d\\u2019inscriptions latines.\\r\\n        Les paysans parlent d\\u2019un pr\\u00eatre chr\\u00e9tien, et de l\\u2019Inquisition j\\u00e9suite elle-m\\u00eame. Mais les recherches men\\u00e9es par les yamabushi locaux n\\u2019ont rien r\\u00e9v\\u00e9l\\u00e9 de probant.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Mt Ishizuchi se trouve dans cette zone. Plus haut sommet de l\\u2019\\u00eele, le mont Ishizuchi domine les vall\\u00e9es alentour comme un sabre dress\\u00e9 vers le ciel.\\r\\n     On dit qu\\u2019un p\\u00e8lerinage ancien y conduit \\u00e0 une dalle sacr\\u00e9e o\\u00f9 les esprits s\\u2019expriment lorsque les vents tournent.<\\/p><p>Il semblerait qu\\u2019un.e Port de Matsuyama se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>","claim_report":"L\\u2019op\\u00e9ration de Ren-j\\u014d fils de Rennyo (\\u84ee\\u5982) sur Montagnes d\\u2019Ehime a \\u00e9t\\u00e9 une r\\u00e9ussite totale, malgr\\u00e9 les d\\u00e9gats.<br\\/>"}	2025-06-22 13:35:57.582192
93	7	3	10	2	9	7	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 9 en investigation et 7\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto.<\\/p><p><\\/p><p><\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Sodegarami (\\u8896\\u6426) \\u2013 Garde sp\\u00e9cialis\\u00e9 du nom de Keita Tani (42) qui surveille dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement. Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Tetsub\\u014d (\\u9244\\u68d2) \\u2014 Masse de guerre en fer, mais cette information n\\u2019est pas si pertinente.Iel travaille avec la faction 7. Nous l\\u2019avons vu rencontrer en personne Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p>","secrets_report":"<p>Dans le territoire Cit\\u00e9 Imp\\u00e9riale de Kyoto :<\\/p><p>Les donn\\u00e9es concordent : un.e Maison de th\\u00e9 \\"Lune d\\u2019Or\\" est bien associ\\u00e9.e \\u00e0 cet endroit. Situ\\u00e9e \\u00e0 l\\u2019\\u00e9cart de Suzaku Mon, la \\"Lune d\\u2019Or\\" attire les lettr\\u00e9s, les po\\u00e8tes\\u2026 et les oreilles curieuses.\\r\\n        On dit qu\\u2019un marchand de soie y viendrait chaque soir, parlant peu mais observant tout.\\r\\n        Selon une geisha, il serait en r\\u00e9alit\\u00e9 un espion du clan Takeda, infiltr\\u00e9 pour sonder la loyaut\\u00e9 des daimy\\u014ds de l\\u2019est.\\r\\n        Il aurait m\\u00eame \\u00e9t\\u00e9 vue avec un membre de la famille Ch\\u014dsokabe.\\r\\n        Pourtant, nul ne peut confirmer son cette histoire, et certains pr\\u00e9tendent qu\\u2019il n\\u2019est en r\\u00e9alit\\u00e9 qu\\u2019un veuf m\\u00e9lancolique, \\u00e9gar\\u00e9 dans ses souvenirs.\\r\\n        Mais \\u00e0 Ky\\u014dto, les apparences mentent plus souvent qu\\u2019elles ne r\\u00e9v\\u00e8lent.<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Suzaku Mon. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Grande art\\u00e8re pav\\u00e9e de la capitale imp\\u00e9riale, menant tout droit au palais. Sous ses tuiles rouges, l\\u2019ombre des complots se m\\u00eale aux parfums de th\\u00e9, et les banni\\u00e8res flottent dans un silence c\\u00e9r\\u00e9moniel.<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Cour imp\\u00e9riale est bien ici. Au sein des couloirs feutr\\u00e9s de la cour imp\\u00e9riale, on ne parle plus qu\\u2019\\u00e0 demi-mot des r\\u00e9cents affrontements.\\r\\n        Le nom des Ch\\u014dsokabe y est devenu tabou, souffl\\u00e9 avec m\\u00e9pris : leur arm\\u00e9e, jadis fi\\u00e8re, aurait fui sans gloire devant l\\u2019avant-garde Takeda.\\r\\n        Le Shogun Ashikaga, humili\\u00e9 par leur d\\u00e9b\\u00e2cle, aurait jur\\u00e9 de ne plus leur accorder confiance ni territoire.\\r\\n        Ce ressentiment pourrait \\u00eatre exploit\\u00e9 \\u2014 ou au contraire, d\\u00e9samorc\\u00e9 \\u2014 selon les preuves et r\\u00e9cits qu\\u2019on parvient \\u00e0 faire \\u00e9merger de l\\u2019ombre.<\\/p>"}	2025-06-22 13:35:57.582192
99	13	3	7	7	6	6	8	passive	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 6\\/8 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p><p><\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Maison close de Marugame est bien li\\u00e9.e \\u00e0 cette localisation. \\u00c0 Marugame, dans une maison close r\\u00e9put\\u00e9e pour son sak\\u00e9 sucr\\u00e9 et ses \\u00e9ventails peints \\u00e0 la main, des courtisanes murmurent entre deux chansons.\\r\\n        L\\u2019une d\\u2019elles pr\\u00e9tend avoir lu une lettre scell\\u00e9e, confi\\u00e9e par un \\u00e9missaire enivr\\u00e9, annon\\u00e7ant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre \\u00e9clair contre les Ch\\u014dsokabe.<\\/p><p>Un rapport fragmentaire mentionne un.e Grande route et relais de poste comme \\u00e9tant cach\\u00e9.e dans ce territoire.<\\/p>"}	2025-06-22 13:35:57.582192
100	14	3	1	4	7	6	6	passive	{}	{"life_report":"Ce.tte trimestre j'ai 7 en investigation et 6\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Ouest d\\u2019Ehime.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Tsukai (\\u4f7f\\u3044) \\u2013 Messager rapide du nom de Riko Hoshino (17) qui enquete dans notre r\\u00e9gion. En poussant nos recherches il s\\u2019av\\u00e8re qu\\u2019iel maitrise S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari). Iel a aussi \\u00e9t\\u00e9 vu.e avec un.e Ukiyo-e (\\u6d6e\\u4e16\\u7d75) \\u2013 Estampes artistiques, mais cette information n\\u2019est pas si pertinente.<\\/p><p><\\/p><p><\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Ouest d\\u2019Ehime :<\\/p><p>Nous avons identifi\\u00e9 une information int\\u00e9ressante : un.e Ry\\u016bk\\u014d-ji (\\u7adc\\u5149\\u5bfa) -- Le chemin de l\\u2019illumination serait pr\\u00e9sent.e dans la zone.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Port d\\u2019Uwajima se trouve dans cette zone. Un port anim\\u00e9 aux quais denses et bruyants, o\\u00f9 s\\u2019\\u00e9changent riz, bois, et rumeurs en provenance de Ky\\u016bsh\\u016b comme de Cor\\u00e9e.\\r\\n     Les marins disent que la brume y reste plus longtemps qu\\u2019ailleurs.<\\/p><p>Il semblerait qu\\u2019un.e Port marchand d\\u2019Uwajima se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>"}	2025-06-22 13:35:57.582192
127	41	3	9	4	7	4	3	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 7 en investigation et 4\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Ile de Sh\\u014ddoshima.<\\/p><p><\\/p><p><\\/p>On a suivi Ayaka Noguchi (15) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Ninja-kah\\u014d (\\u5fcd\\u8005\\u5bb6\\u6cd5) \\u2013 Membre d\\u2019une lign\\u00e9e de ninjas mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Chigiriki (\\u5951\\u6728) \\u2013 Masse \\u00e0 cha\\u00eene.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), au moins partiellement.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 8. Ces observations se cumulent avec son utilisation de la discipline Tant\\u014djutsu (\\u77ed\\u5200\\u8853) \\u2013 Combat au couteau. Iel a de plus une maitrise de la discipline Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer.  Nous l\\u2019avons vu rencontrer en personne Murai Wako (\\u548c\\u5149). <\\/p>","secrets_report":"<p>Dans le territoire Ile de Sh\\u014ddoshima :<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Crique de Funakoshi. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Cette crique isol\\u00e9e, souvent balay\\u00e9e par les vents, est connue des contrebandiers comme des p\\u00eacheurs.\\r\\n        Depuis quelques jours, un bruit court : un important \\u00e9missaire imp\\u00e9rial aurait \\u00e9t\\u00e9 intercept\\u00e9 par les pirates Wako et d\\u00e9tenu dans une grotte voisine, en attendant ran\\u00e7on ou silence.<\\/p><p>Information int\\u00e9ressante : un.e D\\u00e9troit d\\u2019Okayama est pr\\u00e9sent.e dans la zone. \\u00c9troit et venteux, ce d\\u00e9troit aux eaux tra\\u00eetresses s\\u00e9pare Shikoku de Honsh\\u016b.\\r\\n     Difficile de tenter cette travers\\u00e9e sans \\u00eatre \\u00e9pi\\u00e9 par les habitants de l\\u2019\\u00eele de Sh\\u014ddoshima.\\r\\n     Certains racontent avoir vu un noble personnage tenter de rentrer en secret avant d\\u2019\\u00eatre intercept\\u00e9 par les Kaizokush\\u016b.<\\/p>"}	2025-06-22 13:59:18.290288
96	10	3	7	6	8	6	5	passive	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 6\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Prefecture de Kagawa.<\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Marin Portugais du nom de Taiga Tani (26) qui enquete dans notre r\\u00e9gion. <\\/p><p><\\/p>On a suivi Venturo Attilio (28) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Onmy\\u014dji (\\u9670\\u967d\\u5e2b) \\u2013 Devin et exorciste mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin.En plus, iel est originaire de Portugal. Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), au moins partiellement.Iel fait partie de la faction 7. Ces observations se cumulent avec son utilisation de la discipline Ky\\u016bjutsu (\\u5f13\\u8853) \\u2013 Art du tir \\u00e0 l\\u2019arc. Iel a de plus une maitrise de la discipline Iaijutsu (\\u5c45\\u5408\\u8853) \\u2013 Art de d\\u00e9gainer et frapper en un mouvement.   Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Da\\u00efmyo Tadaoki (\\u5fe0\\u8208) Hosokawa (\\u7d30\\u5ddd). <\\/p><p><\\/p>J\\u2019ai vu un.e Bugy\\u014d (\\u5949\\u884c) \\u2013 Magistrat ou officier administratif du nom de Marco Venezio (13) qui surveille dans ma zone d\\u2019action. En plus, iel est originaire de Portugal. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Tessen (\\u9244\\u6247) \\u2013 \\u00c9ventail de fer mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari).En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 7. Ces observations se cumulent avec son utilisation de la discipline Kad\\u014d \\/ Ikebana (\\u83ef\\u9053 \\/ \\u751f\\u3051\\u82b1) \\u2013 Art floral. Ces observations se cumulent avec son utilisation de la discipline Bugaku (\\u821e\\u697d) \\u2013 Danse de cour.  <\\/p>","secrets_report":"<p>Dans le territoire Prefecture de Kagawa :<\\/p><p>Nous avons confirm\\u00e9 la pr\\u00e9sence d\\u2019un.e Yashima-ji (\\u5c4b\\u5cf6\\u5bfa) -- Le chemin du Nirvana. Nous avons enqu\\u00eat\\u00e9 davantage et d\\u00e9couvert que : Ancien bastion surplombant les flots, Yashima-ji garde la m\\u00e9moire des batailles et des ermites. \\r\\n    Les brumes de l\\u2019aube y voilent statues et stupas, comme pour dissimuler les myst\\u00e8res du Nirvana. \\r\\n    Certains p\\u00e8lerins affirment y avoir senti l\\u2019oubli du monde descendre sur eux comme une paix.\\r\\n    (Pour explorer davantage ce lieu, allez voir un orga !) Il est possible d\\u2019organiser une mission pour faire dispara\\u00eetre ce probl\\u00e8me.<\\/p><p>Nous avons v\\u00e9rifi\\u00e9 les rumeurs : un.e Maison close de Marugame est bien ici. \\u00c0 Marugame, dans une maison close r\\u00e9put\\u00e9e pour son sak\\u00e9 sucr\\u00e9 et ses \\u00e9ventails peints \\u00e0 la main, des courtisanes murmurent entre deux chansons.\\r\\n        L\\u2019une d\\u2019elles pr\\u00e9tend avoir lu une lettre scell\\u00e9e, confi\\u00e9e par un \\u00e9missaire enivr\\u00e9, annon\\u00e7ant un pacte secret entre le clan Miyoshi et la famille Hosokawa : mariage, trahison, et guerre \\u00e9clair contre les Ch\\u014dsokabe.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Grande route et relais de poste se trouve dans cette zone. Relie Tokushima \\u00e0 K\\u014dchi en serpentant \\u00e0 travers les plaines fertiles du nord.\\r\\n     \\u00c0 chaque relais, les montures peuvent \\u00eatre chang\\u00e9es, et les messagers imp\\u00e9riaux y trouvent toujours une couche et un bol chaud.<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e \\u00c9curies de Kagawa se trouve dans cette zone. Les vastes p\\u00e2turages de Kagawa forment l\\u2019\\u00e9crin id\\u00e9al pour l\\u2019\\u00e9levage de chevaux endurants, pris\\u00e9s tant pour la guerre que pour les grandes caravanes.\\r\\n    Contr\\u00f4ler ce territoire nous permettrait d\\u2019avoir acc\\u00e8s \\u00e0 cette ressource rare.<\\/p><p>Il semblerait qu\\u2019un.e Post relai du courrier de Kagawa se trouve dans cette r\\u00e9gion, il faudra s\\u2019en assurer.<\\/p>"}	2025-06-22 13:35:57.582192
115	29	3	6	6	8	6	3	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 6\\/3 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire C\\u00f4te Est de Tokushima.<\\/p><p><\\/p>On a suivi Mei Yamamoto (44) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Marin Portugais mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Tessen (\\u9244\\u6247) \\u2013 \\u00c9ventail de fer.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Bajutsu (\\u99ac\\u8853) \\u2013 Art de l\\u2019\\u00e9quitation militaire, au moins partiellement.En plus, sa famille a des liens avec la faction 5. Ce qui veut dire que c\\u2019est un serviteur de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d). <\\/p>","secrets_report":"<p>Dans le territoire C\\u00f4te Est de Tokushima :<\\/p><p>Des rumeurs persistantes \\u00e9voquent la pr\\u00e9sence d\\u2019un.e Comptoir de Kashiwa dans les environs.<\\/p><p>Notre exploration confirme la pr\\u00e9sence d\\u2019un.e Port de Tokushima. Voici ce que nous avons appris : Carrefour maritime entre Honsh\\u016b et Shikoku, le port de Tokushima bruisse de dialectes et de voiles \\u00e9trang\\u00e8res.\\r\\n     Dans les ruelles proches du march\\u00e9, on parle parfois espagnol, ou latin, \\u00e0 voix basse.<\\/p><p>Un rapport fragmentaire mentionne un.e Port de Tokushima comme \\u00e9tant cach\\u00e9.e dans ce territoire.<\\/p>"}	2025-06-22 13:35:57.582192
119	33	3	2	8	8	5	6	investigate	{}	{"life_report":"Ce.tte trimestre j'ai 8 en investigation et 5\\/6 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Montagnes d\\u2019Ehime.<\\/p>On a suivi Iwao Jizane (1) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Kannushi (\\u795e\\u4e3b) \\u2013 Pr\\u00eatre shint\\u014d mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Chigiriki (\\u5951\\u6728) \\u2013 Masse \\u00e0 cha\\u00eene.<\\/p><p><\\/p>J\\u2019ai vu un.e H\\u014din (\\u6cd5\\u5370) \\u2013 Pr\\u00eatresse ou religieuse conseill\\u00e8re du nom de Ami Tajima (37) qui attaque une personne  dans ma zone d\\u2019action. Je m\\u2019en m\\u00e9fie, iel vient de Kyushu - \\u00d6ita. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Kunai (\\u82e6\\u7121) \\u2013 Dague multi-usage mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de J\\u016bjutsu (\\u67d4\\u8853) \\u2013 Techniques de lutte \\u00e0 mains nues.En creusant, iel est rattach\\u00e9.e \\u00e0 la faction 6.  Nous l\\u2019avons vu rencontrer en personne Shinsh\\u014d-in (\\u4fe1\\u8a3c\\u9662) Rennyo (\\u84ee\\u5982). <\\/p>","secrets_report":"<p>Dans le territoire Montagnes d\\u2019Ehime :<\\/p><p>Le myst\\u00e8re est lev\\u00e9 : un.e Sanctuaire des Pins Br\\u00fbl\\u00e9s se trouve dans cette zone. Dans un ancien sanctuaire shint\\u014d dont les piliers carbonis\\u00e9s r\\u00e9sistent au temps, des p\\u00e8lerins affirment avoir vu un artefact \\u00e9trange cach\\u00e9 sous l\\u2019autel \\u2014 une croix d\\u2019argent sertie d\\u2019inscriptions latines.\\r\\n        Les paysans parlent d\\u2019un pr\\u00eatre chr\\u00e9tien, et de l\\u2019Inquisition j\\u00e9suite elle-m\\u00eame. Mais les recherches men\\u00e9es par les yamabushi locaux n\\u2019ont rien r\\u00e9v\\u00e9l\\u00e9 de probant.<\\/p><p>Apr\\u00e8s enqu\\u00eate, il s\\u2019av\\u00e8re qu\\u2019un.e Mt Ishizuchi est bien li\\u00e9.e \\u00e0 cette localisation. Plus haut sommet de l\\u2019\\u00eele, le mont Ishizuchi domine les vall\\u00e9es alentour comme un sabre dress\\u00e9 vers le ciel.\\r\\n     On dit qu\\u2019un p\\u00e8lerinage ancien y conduit \\u00e0 une dalle sacr\\u00e9e o\\u00f9 les esprits s\\u2019expriment lorsque les vents tournent.<\\/p>","claim_report":"L\\u2019op\\u00e9ration de Ren-j\\u014d fils de Rennyo (\\u84ee\\u5982) sur Montagnes d\\u2019Ehime a \\u00e9t\\u00e9 une r\\u00e9ussite totale, malgr\\u00e9 les d\\u00e9gats.<br\\/>"}	2025-06-22 13:35:57.582192
121	35	3	3	6	7	6	2	passive	{}	{"life_report":"Ce.tte trimestre j'ai 7 en investigation et 6\\/2 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Cap sud de Kochi.<\\/p><p><\\/p>J\\u2019ai vu un.e Kagemusha (\\u5f71\\u6b66\\u8005) \\u2013 Sosie du seigneur du nom de Kazusa Noayame (3) qui enquete dans ma zone d\\u2019action. <\\/p>Nous avons rep\\u00e9r\\u00e9 un.e Kagemusha (\\u5f71\\u6b66\\u8005) \\u2013 Sosie du seigneur du nom de Ryota Yoshikawa (18) qui revendique le quartier au nom de Da\\u00efmyo Nagayoshi (\\u9577\\u6176) Miyoshi (\\u4e09\\u597d) dans notre r\\u00e9gion. <\\/p>On a suivi Koki Himura (46) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Kusushi (\\u85ac\\u5e2b) \\u2013 M\\u00e9decin itin\\u00e9rant mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Sh\\u014dyaku fukuro (\\u751f\\u85ac\\u888b) \\u2013 Sachets de plantes.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser H\\u014djutsu (\\u7832\\u8853) \\u2013 Art des armes \\u00e0 feu (tepp\\u014d), au moins partiellement. Iel re\\u00e7oit un soutien financier de la faction 1. Ce qui veut dire que c\\u2019est un serviteur de \\u5996\\u602a de Shikoku (\\u56db\\u56fd). <\\/p>On a suivi Yuna Shimizu (39) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e Monogashira (\\u7269\\u982d) \\u2013 Officier en chef mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Zudabukuro (\\u982d\\u9640\\u888b) \\u2013 Une besace de p\\u00e8lerin.Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser Tessenjutsu (\\u9244\\u6247\\u8853) \\u2013 L\\u2019art du combat \\u00e0 l\\u2019\\u00e9ventail de fer, au moins partiellement.Iel travaille avec la faction 8. Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour Murai Wako (\\u548c\\u5149). <\\/p>","secrets_report":"<p>Dans le territoire Cap sud de Kochi :<\\/p><p>Nous avons identifi\\u00e9 une information int\\u00e9ressante : un.e Mine de fer de Kubokawa serait pr\\u00e9sent.e dans la zone.<\\/p>","claim_report":"Ryota Yoshikawa a pris Cap sud de Kochi par la force. Iel n\\u2019a laiss\\u00e9 aucune chance aux d\\u00e9fenseurs.<br\\/>"}	2025-06-22 13:35:57.582192
98	12	3	4	8	5	7	7	passive	{}	{"life_report":"Ce.tte trimestre j'ai 5 en investigation et 7\\/7 en attaque\\/d\\u00e9fense.<p>Iel a tent\\u00e9 de me r\\u00e9duire au silence, mais apr\\u00e8s avoir surv\\u00e9cu \\u00e0 l\\u2019attaque de Keita Arakawa(45), j\\u2019ai r\\u00e9pondu par une riposte fatale.<\\/p>","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p><p><\\/p><p><\\/p><p><\\/p><p><\\/p><p><\\/p>J\\u2019ai vu un.e Sodegarami (\\u8896\\u6426) \\u2013 Garde sp\\u00e9cialis\\u00e9 du nom de Koji Nagano (40) qui enquete dans ma zone d\\u2019action. J\\u2019ai remarqu\\u00e9 qu\\u2019iel avait un.e Kakute (\\u89d2\\u624b) \\u2013 Anneau \\u00e0 pointes mais je suis s\\u00fbr qu\\u2019iel poss\\u00e8de aussi l\\u2019art de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari).Iel fait partie de la faction 4.   Cela signifie qu\\u2019iel travaille forc\\u00e9ment pour La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p>","claim_report":"L\\u2019op\\u00e9ration de Hinako Ichikawa sur Grande Baie de Kochi a \\u00e9t\\u00e9 une r\\u00e9ussite totale, malgr\\u00e9 les d\\u00e9gats.<br\\/>J\\u2019ai vu Nanami Koga tenter de prendre le contr\\u00f4le du territoire Grande Baie de Kochi, mais la d\\u00e9fense l\\u2019a repouss\\u00e9.e brutalement.<br\\/>"}	2025-06-22 13:35:57.582192
110	24	3	4	6	6	3	5	passive	{}	{"life_report":"Ce.tte trimestre j'ai 6 en investigation et 3\\/5 en attaque\\/d\\u00e9fense.","investigate_report":"<p> Nous avons men\\u00e9 l\\u2019enqu\\u00eate dans le territoire Grande Baie de Kochi.<\\/p><p><\\/p><p><\\/p>J\\u2019ai trouv\\u00e9 Hinako Ichikawa (32), qui n\\u2019est clairement pas un agent \\u00e0 nous, c\\u2019est un.e Kuro-hatamoto (\\u9ed2\\u65d7\\u672c) \\u2013 Garde d\\u2019\\u00e9lite avec un.e Kong\\u014dzue (\\u91d1\\u525b\\u6756) \\u2013 Un b\\u00e2ton de p\\u00e8lerin.<\\/p>On a suivi Claire Richard (12) parce qu\\u2019on l\\u2019a rep\\u00e9r\\u00e9.e en train de surveiller, ce qui nous a mis la puce \\u00e0 l\\u2019oreille. C\\u2019est normalement un.e N\\u014dgakushi (\\u80fd\\u697d\\u5e2b) \\u2013 Acteur de th\\u00e9\\u00e2tre N\\u014d mais on a d\\u00e9couvert qu\\u2019il poss\\u00e9dait aussi un.e Hy\\u014dshigi (\\u62cd\\u5b50\\u6728) \\u2013 Claves en bois.Je m\\u2019en m\\u00e9fie, iel vient de France. Cela dit, le vrai probl\\u00e8me, c\\u2019est qu\\u2019il semble ma\\u00eetriser S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), au moins partiellement.<\\/p><p><\\/p>Je me suis rendu compte que Koji Nagano (40), que j\\u2019ai rep\\u00e9r\\u00e9 avec un.e Kakute (\\u89d2\\u624b) \\u2013 Anneau \\u00e0 pointes, enquete dans le coin. C\\u2019\\u00e9tait \\u00e9trange, alors j\\u2019ai enqu\\u00eat\\u00e9 et trouv\\u00e9 qu\\u2019iel a en r\\u00e9alit\\u00e9 des capacit\\u00e9s de S\\u014djutsu (\\u69cd\\u8853) \\u2013 Art de la lance (Yari), ce qui en fait un.e Sodegarami (\\u8896\\u6426) \\u2013 Garde sp\\u00e9cialis\\u00e9 un peu trop sp\\u00e9cial.e.En plus, sa famille a des liens avec la faction 4. Nous l\\u2019avons vu rencontrer en personne La R\\u00e9gence Ch\\u014dsokabe (\\u9577\\u5b97\\u6211\\u90e8). <\\/p>","secrets_report":"<p>Dans le territoire Grande Baie de Kochi :<\\/p><p>Un rapport fragmentaire mentionne un.e Port de Kochi comme \\u00e9tant cach\\u00e9.e dans ce territoire.<\\/p>","claim_report":"J\\u2019ai vu Hinako Ichikawa renverser l\\u2019autorit\\u00e9 sur Grande Baie de Kochi. La zone a chang\\u00e9 de mains.<br\\/>L\\u2019assaut de Nanami Koga sur le territoire Grande Baie de Kochi a \\u00e9chou\\u00e9 ; c\\u2019\\u00e9tait un vrai carnage.<br\\/>"}	2025-06-22 13:35:57.582192
133	1	4	2	1	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
134	2	4	3	1	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
135	3	4	3	1	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
136	4	4	4	1	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
137	5	4	2	6	0	0	0	claim	{"claim_controller_id":"null"}	{}	2025-06-22 14:46:43.768425
138	6	4	10	2	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
139	7	4	10	2	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
140	8	4	10	2	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
141	9	4	10	2	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
142	10	4	7	6	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
143	11	4	5	5	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
144	12	4	4	8	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
145	13	4	7	7	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
146	14	4	1	4	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
147	15	4	9	8	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
148	16	4	3	4	0	0	0	dead	[{"attackScope":"worker","attackID":2}]	{}	2025-06-22 14:46:43.768425
149	17	4	1	6	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
150	18	4	3	5	0	0	0	claim	{"claim_controller_id":"5"}	{}	2025-06-22 14:46:43.768425
151	19	4	7	7	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
152	20	4	7	8	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
153	21	4	1	8	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
154	22	4	9	5	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
155	23	4	1	5	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
156	24	4	4	6	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
157	25	4	6	4	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
158	26	4	7	4	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
159	27	4	4	7	0	0	0	dead	{}	{}	2025-06-22 14:46:43.768425
160	28	4	7	7	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
161	29	4	6	6	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
162	30	4	8	5	0	0	0	claim	{"claim_controller_id":"5"}	{}	2025-06-22 14:46:43.768425
163	31	4	4	4	0	0	0	claim	{"claim_controller_id":"7"}	{}	2025-06-22 14:46:43.768425
164	32	4	4	5	0	0	0	claim	{"claim_controller_id":"5"}	{}	2025-06-22 14:46:43.768425
165	33	4	2	8	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
166	34	4	10	4	0	0	0	claim	{"claim_controller_id":"5"}	{}	2025-06-22 14:46:43.768425
167	35	4	3	6	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
168	36	4	9	8	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
169	37	4	2	6	0	0	0	attack	[{"attackScope":"worker","attackID":1}]	{}	2025-06-22 14:46:43.768425
170	38	4	10	7	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
171	39	4	3	8	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
172	40	4	4	4	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
173	41	4	9	4	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
174	42	4	10	7	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
175	43	4	4	1	0	0	0	investigate	{}	{}	2025-06-22 14:46:43.768425
176	44	4	6	5	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
177	45	4	4	6	0	0	0	dead	[{"attackScope":"worker","attackID":27},{"attackScope":"worker","attackID":31},{"attackScope":"worker","attackID":12}]	{}	2025-06-22 14:46:43.768425
178	46	4	3	1	0	0	0	passive	{}	{}	2025-06-22 14:46:43.768425
\.


--
-- Data for Name: worker_names; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.worker_names (id, firstname, lastname, origin_id) FROM stdin;
1	Haruki	Takahashi	1
2	Yui	Nakamura	1
3	Ren	Sato	1
4	Kaito	Yamamoto	1
5	Aiko	Fujimoto	1
6	Souta	Ishikawa	1
7	Hina	Kobayashi	1
8	Daichi	Tanaka	1
9	Mei	Arai	1
10	Takumi	Inoue	1
11	Yuto	Matsuda	2
12	Sakura	Hoshino	2
13	Kenta	Murakami	2
14	Riko	Shimizu	2
15	Tsubasa	Ueda	2
16	Miyu	Sakamoto	2
17	Hinata	Endo	2
18	Riku	Fukuda	2
19	Yuna	Hirano	2
20	Sho	Nakagawa	2
21	Ayaka	Terasaki	3
22	Tomo	Sugimoto	3
23	Rio	Kaneko	3
24	Shun	Okada	3
25	Airi	Noguchi	3
26	Mao	Hirano	3
27	Sena	Kawaguchi	3
28	Yuma	Tachibana	3
29	Rina	Yoshikawa	4
30	Takashi	Kuroda	4
31	Saki	Amano	4
32	Itsuki	Hayashi	4
33	Mai	Onishi	4
34	Ryota	Mizuno	4
35	Shiori	Ichikawa	4
36	Hinako	Sakai	4
37	Yuto	Kiriyama	4
38	Keita	Nagano	5
39	Emi	Morita	5
40	Kazuki	Yamaguchi	5
41	Sayaka	Hosokawa	5
42	Miki	Kubota	5
43	Taiga	Koga	5
44	Haruna	Tani	5
45	Koji	Arakawa	5
46	Nanami	Iguchi	5
47	Nao	Miyamoto	6
48	Koki	Maruyama	6
49	Ren	Takada	6
50	Naoki	Sano	6
51	Riko	Himura	6
52	Kanon	Matsumoto	6
53	Rina	Nobunaga	6
54	Kaori	Uchiha	6
55	Natsuki	Nanami	6
56	Soutaro	Kawaï	6
57	Masaru	Yoshida	7
58	Aya	Fukuda	7
59	Shiro	Morimoto	7
60	Jiro	Tominaga	7
61	Hiroki	Noma	7
62	Misaki	Kamiyama	7
63	Kenji	Narita	7
64	Ayumi	Tateishi	7
65	Shinji	Tsukamoto	8
66	Mariko	Ogawa	8
67	Yusuke	Okamoto	8
68	Nozomi	Ichinose	8
69	Rei	Furukawa	8
70	Tomoya	Suda	8
71	Takuto	Saeki	8
72	Minami	Kurata	8
73	Sosuke	Muraoka	8
74	Atsushi	Higashiyama	9
75	Mami	Seto	9
76	Yuto	Ono	9
77	Kaho	Mochizuki	9
78	Naoya	Kurihara	9
79	Arisa	Komatsu	9
80	Soma	Morikawa	9
81	Yuri	Inaba	9
82	Ryunosuke	Takemoto	9
83	Shunpei	Hamamoto	10
84	Sayuri	Kawano	10
85	Takao	Oshiro	10
86	Keisuke	Asano	10
87	Chihiro	Nomura	10
88	Haruto	Iwamoto	10
89	Mizuki	Kudo	10
90	Tetsuya	Ogino	10
91	Hikari	Yokoyama	10
92	Masaki	Tajima	11
93	Naomi	Ebina	11
94	Rena	Furuya	11
95	Takahiro	Nishimoto	11
96	Kana	Tachikawa	11
97	Yuto	Baba	11
98	Misao	Tokuda	11
99	Hikaru	Shimoda	11
100	Ami	Naruse	11
101	Amerigo	Attilio	13
102	Marco	Martino	13
103	Luciana	Marsala	13
104	Michelangelo	Belluchi	13
105	Umberto	Venezio	13
106	Venturo	Vesuvio	13
107	Gino	Giancarlo	13
108	Hortensio	Honorius	13
109	Bianca	Abriana	13
110	Paolo	Pisano	13
111	Jean	Martin	12
112	Marie	Bernard	12
113	Pierre	Dubois	12
114	Jacques	Thomas	12
115	Michel	Robert	12
116	Claude	Richard	12
117	Nicolas	Petit	12
118	Thomas	Durand	12
119	Sophie	Leroy	12
120	Claire	Moreau	12
121	Masaru	Yoshida	7
122	Aya	Fukuda	7
123	Shiro	Morimoto	7
124	Kanon	Matsumoto	7
125	Jiro	Tominaga	7
126	Riko	Shimura	7
127	Hiroki	Noma	7
128	Misaki	Kamiyama	7
129	Kenji	Narita	7
130	Ayumi	Tateishi	7
131	Shinji	Tsukamoto	8
132	Mariko	Ogawa	8
133	Yusuke	Okamoto	8
134	Nozomi	Ichinose	8
135	Rei	Furukawa	8
136	Tomoya	Suda	8
137	Rina	Tokunaga	8
138	Takuto	Saeki	8
139	Minami	Kurata	8
140	Sosuke	Muraoka	8
141	Atsushi	Higashiyama	9
142	Mami	Seto	9
143	Yuto	Ono	9
144	Kaho	Mochizuki	9
145	Naoya	Kurihara	9
146	Arisa	Komatsu	9
147	Soma	Morikawa	9
148	Yuri	Inaba	9
149	Ryunosuke	Takemoto	9
150	Kaori	Uchida	9
151	Shunpei	Hamamoto	10
152	Sayuri	Kawano	10
153	Takao	Oshiro	10
154	Natsuki	Minami	10
155	Keisuke	Asano	10
156	Chihiro	Nomura	10
157	Haruto	Iwamoto	10
158	Mizuki	Kudo	10
159	Tetsuya	Ogino	10
160	Hikari	Yokoyama	10
161	Masaki	Tajima	11
162	Naomi	Ebina	11
163	Soutaro	Kawai	11
164	Rena	Furuya	11
165	Takahiro	Nishimoto	11
166	Kana	Tachikawa	11
167	Yuto	Baba	11
168	Misao	Tokuda	11
169	Hikaru	Shimoda	11
170	Ami	Naruse	11
\.


--
-- Data for Name: worker_origins; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.worker_origins (id, name) FROM stdin;
1	Shikoku - Ehime
2	Shikoku - Kochi
3	Shikoku - Tokushima
4	Shikoku - Kagawa
5	Shikoku - Awaji
6	Shikoku - Shōdoshima
7	Honshu - Kyoto
8	Honshu - Osaka
9	Honshu - Okayama
10	Honshu - Hiroshima
11	Kyushu - Öita
12	France
13	Portugal
\.


--
-- Data for Name: worker_powers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.worker_powers (id, worker_id, link_power_type_id, created_at) FROM stdin;
1	4	8	2025-06-22 11:19:06.71051
2	3	8	2025-06-22 11:19:06.71051
3	2	8	2025-06-22 11:19:06.71051
4	1	8	2025-06-22 11:19:06.71051
5	4	27	2025-06-22 11:19:06.71051
6	3	27	2025-06-22 11:19:06.71051
7	2	27	2025-06-22 11:19:06.71051
8	1	27	2025-06-22 11:19:06.71051
9	4	28	2025-06-22 11:19:06.71051
10	3	28	2025-06-22 11:19:06.71051
11	2	28	2025-06-22 11:19:06.71051
12	1	28	2025-06-22 11:19:06.71051
13	4	29	2025-06-22 11:19:06.71051
14	3	29	2025-06-22 11:19:06.71051
15	2	29	2025-06-22 11:19:06.71051
16	1	29	2025-06-22 11:19:06.71051
17	4	47	2025-06-22 11:19:06.71051
18	1	55	2025-06-22 11:19:06.71051
19	3	56	2025-06-22 11:19:06.71051
20	2	65	2025-06-22 11:19:06.71051
21	1	89	2025-06-22 11:19:06.71051
22	4	100	2025-06-22 11:19:06.71051
23	2	102	2025-06-22 11:19:06.71051
24	3	115	2025-06-22 11:19:06.71051
25	5	27	2025-06-22 11:19:06.71051
26	5	47	2025-06-22 11:19:06.71051
27	5	92	2025-06-22 11:19:06.71051
28	8	1	2025-06-22 11:19:06.71051
29	6	2	2025-06-22 11:19:06.71051
30	7	3	2025-06-22 11:19:06.71051
31	9	4	2025-06-22 11:19:06.71051
32	7	6	2025-06-22 11:19:06.71051
33	8	7	2025-06-22 11:19:06.71051
34	7	7	2025-06-22 11:19:06.71051
35	6	7	2025-06-22 11:19:06.71051
36	9	8	2025-06-22 11:19:06.71051
37	6	10	2025-06-22 11:19:06.71051
38	8	13	2025-06-22 11:19:06.71051
39	8	15	2025-06-22 11:19:06.71051
40	9	16	2025-06-22 11:19:06.71051
41	7	16	2025-06-22 11:19:06.71051
42	6	16	2025-06-22 11:19:06.71051
43	9	17	2025-06-22 11:19:06.71051
44	7	17	2025-06-22 11:19:06.71051
45	7	38	2025-06-22 11:19:06.71051
46	6	41	2025-06-22 11:19:06.71051
47	9	50	2025-06-22 11:19:06.71051
48	8	66	2025-06-22 11:19:06.71051
49	6	95	2025-06-22 11:19:06.71051
50	9	100	2025-06-22 11:19:06.71051
51	8	103	2025-06-22 11:19:06.71051
52	7	105	2025-06-22 11:19:06.71051
53	10	30	2025-06-22 11:44:52.95525
54	10	118	2025-06-22 11:44:52.960432
55	10	18	2025-06-22 11:44:52.964151
56	11	43	2025-06-22 11:45:36.060791
57	11	118	2025-06-22 11:45:36.06743
58	11	13	2025-06-22 11:45:36.072825
59	12	37	2025-06-22 11:45:40.78256
60	12	94	2025-06-22 11:45:40.787479
61	12	26	2025-06-22 11:45:40.791173
62	13	56	2025-06-22 11:46:10.059605
63	13	80	2025-06-22 11:46:10.064567
64	13	16	2025-06-22 11:46:10.068327
65	14	36	2025-06-22 11:46:24.699197
66	14	103	2025-06-22 11:46:24.704212
67	14	10	2025-06-22 11:46:24.708199
68	15	55	2025-06-22 11:52:21.365274
69	15	105	2025-06-22 11:52:21.370928
70	15	26	2025-06-22 11:52:21.375075
71	16	54	2025-06-22 11:56:49.790205
72	16	108	2025-06-22 11:56:49.794185
73	16	9	2025-06-22 11:56:49.798131
74	17	45	2025-06-22 11:56:55.050976
75	17	114	2025-06-22 11:56:55.05445
76	17	6	2025-06-22 11:56:55.057876
77	18	58	2025-06-22 11:57:55.107705
78	18	115	2025-06-22 11:57:55.112373
79	18	12	2025-06-22 11:57:55.115961
80	19	36	2025-06-22 12:01:26.807179
81	19	82	2025-06-22 12:01:26.811311
82	19	17	2025-06-22 12:01:26.814865
83	16	8	2025-06-22 12:10:33.348793
84	20	75	2025-06-22 12:10:56.511843
85	20	88	2025-06-22 12:10:56.516511
86	20	25	2025-06-22 12:10:56.520342
87	21	36	2025-06-22 12:11:33.995477
88	21	120	2025-06-22 12:11:34.000828
89	21	25	2025-06-22 12:11:34.004756
90	22	37	2025-06-22 12:12:03.397789
91	22	99	2025-06-22 12:12:03.400981
92	22	14	2025-06-22 12:12:03.404036
93	10	19	2025-06-22 12:12:23.026451
94	23	31	2025-06-22 12:13:32.575116
95	23	85	2025-06-22 12:13:32.578422
96	23	13	2025-06-22 12:13:32.581257
97	14	8	2025-06-22 12:13:55.162686
98	24	32	2025-06-22 12:14:07.701886
99	24	93	2025-06-22 12:14:07.706642
100	24	20	2025-06-22 12:14:07.710451
101	25	63	2025-06-22 12:23:15.898165
102	25	119	2025-06-22 12:23:15.900318
103	25	11	2025-06-22 12:23:15.902
104	26	58	2025-06-22 12:24:37.111817
105	26	122	2025-06-22 12:24:37.116276
106	26	10	2025-06-22 12:24:37.122723
107	27	30	2025-06-22 12:25:19.347977
108	27	79	2025-06-22 12:25:19.35283
109	27	8	2025-06-22 12:25:19.356469
110	18	13	2025-06-22 12:26:09.930706
111	11	8	2025-06-22 12:28:35.645743
112	12	5	2025-06-22 12:29:07.053315
113	15	24	2025-06-22 12:29:33.80515
115	28	51	2025-06-22 12:31:04.144172
116	28	90	2025-06-22 12:31:04.149454
117	28	5	2025-06-22 12:31:04.153002
118	29	38	2025-06-22 12:34:34.063817
119	29	105	2025-06-22 12:34:34.068998
120	29	7	2025-06-22 12:34:34.072959
121	20	24	2025-06-22 12:43:46.314613
122	12	24	2025-06-22 12:46:18.879042
123	18	7	2025-06-22 12:46:30.999639
124	25	10	2025-06-22 12:46:40.017245
125	15	5	2025-06-22 12:47:03.725481
126	26	9	2025-06-22 12:47:58.723242
127	30	40	2025-06-22 12:49:04.888569
128	30	102	2025-06-22 12:49:04.894338
129	30	13	2025-06-22 12:49:04.898605
130	14	7	2025-06-22 12:49:20.945714
131	31	64	2025-06-22 12:50:06.933843
132	31	83	2025-06-22 12:50:06.939207
133	31	10	2025-06-22 12:50:06.942968
134	32	51	2025-06-22 12:50:29.062146
135	32	103	2025-06-22 12:50:29.067289
136	32	12	2025-06-22 12:50:29.071236
137	21	7	2025-06-22 12:50:53.205468
138	33	39	2025-06-22 12:51:54.867608
139	33	101	2025-06-22 12:51:54.872874
140	33	26	2025-06-22 12:51:54.87729
141	34	42	2025-06-22 12:52:05.010441
142	34	91	2025-06-22 12:52:05.015642
143	34	11	2025-06-22 12:52:05.019485
144	35	36	2025-06-22 12:52:27.232429
145	35	112	2025-06-22 12:52:27.236319
146	35	19	2025-06-22 12:52:27.239657
147	36	45	2025-06-22 12:52:42.687455
148	36	88	2025-06-22 12:52:42.692268
149	36	25	2025-06-22 12:52:42.696154
150	37	60	2025-06-22 12:53:16.237851
151	37	85	2025-06-22 12:53:16.239796
152	37	18	2025-06-22 12:53:16.24131
153	22	13	2025-06-22 12:57:32.400662
154	23	14	2025-06-22 12:59:19.562303
155	38	34	2025-06-22 13:21:42.597545
156	38	100	2025-06-22 13:21:42.602951
157	38	7	2025-06-22 13:21:42.606814
158	11	7	2025-06-22 13:25:01.95089
159	10	20	2025-06-22 13:27:43.037056
160	5	5	2025-06-22 13:33:52.814835
161	17	5	2025-06-22 13:34:40.744257
163	17	7	2025-06-22 13:35:26.408299
164	20	8	2025-06-22 13:36:57.842122
165	21	26	2025-06-22 13:39:02.874948
166	13	8	2025-06-22 13:39:44.867804
167	13	5	2025-06-22 13:40:06.354233
168	33	25	2025-06-22 13:40:10.970488
170	19	7	2025-06-22 13:41:49.749024
171	19	8	2025-06-22 13:41:55.83506
172	39	75	2025-06-22 13:41:58.565522
173	39	110	2025-06-22 13:41:58.568113
174	39	26	2025-06-22 13:41:58.570509
175	25	7	2025-06-22 13:42:35.111152
176	26	7	2025-06-22 13:44:09.160688
177	28	6	2025-06-22 13:46:40.513745
178	31	8	2025-06-22 13:46:48.825152
179	28	15	2025-06-22 13:46:56.451618
180	34	8	2025-06-22 13:47:21.218516
181	38	8	2025-06-22 13:47:58.129391
182	23	7	2025-06-22 13:51:18.637547
183	30	12	2025-06-22 13:51:43.629726
184	32	13	2025-06-22 13:53:38.081434
185	32	4	2025-06-22 13:53:45.068274
186	40	61	2025-06-22 13:57:37.705527
187	40	106	2025-06-22 13:57:37.710584
188	40	5	2025-06-22 13:57:37.71453
189	41	48	2025-06-22 13:59:18.296919
190	41	121	2025-06-22 13:59:18.302015
191	41	11	2025-06-22 13:59:18.308648
192	42	70	2025-06-22 14:04:20.892997
193	42	106	2025-06-22 14:04:20.898575
194	42	15	2025-06-22 14:04:20.904005
195	29	19	2025-06-22 14:04:33.884412
196	35	5	2025-06-22 14:06:27.87413
197	43	49	2025-06-22 14:15:56.698873
198	43	90	2025-06-22 14:15:56.705062
199	43	28	2025-06-22 14:15:56.709073
200	44	56	2025-06-22 14:16:57.308717
201	44	122	2025-06-22 14:16:57.314012
202	44	13	2025-06-22 14:16:57.318357
203	45	47	2025-06-22 14:23:47.653024
204	45	121	2025-06-22 14:23:47.658049
205	45	6	2025-06-22 14:23:47.665054
206	36	8	2025-06-22 14:26:45.812013
207	46	74	2025-06-22 14:29:02.019886
208	46	119	2025-06-22 14:29:02.025005
209	46	12	2025-06-22 14:29:02.028787
210	22	7	2025-06-22 14:40:05.774343
211	23	4	2025-06-22 14:42:40.709841
\.


--
-- Data for Name: workers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.workers (id, firstname, lastname, origin_id, zone_id, is_alive, is_active, created_at) FROM stdin;
1	Iwao	Jizane	1	2	t	t	2025-06-22 11:19:06.71051
2	Hiuchi	Kagaribi	2	3	t	t	2025-06-22 11:19:06.71051
5	Ren-jō	fils de Rennyo (蓮如)	7	2	t	t	2025-06-22 11:19:06.71051
6	Lord Asakura(朝倉)	Mitsunao(光直)	7	10	t	t	2025-06-22 11:19:06.71051
7	Lady	Ibara(茨の紅)	7	10	t	t	2025-06-22 11:19:06.71051
8	Renryū(蓮竜)	Takeda(武田)	7	10	t	t	2025-06-22 11:19:06.71051
9		Sōen(僧円)	7	10	t	t	2025-06-22 11:19:06.71051
10	Souta	Yamamoto	1	7	t	t	2025-06-22 11:44:52.942816
13	Marco	Venezio	13	7	t	t	2025-06-22 11:46:10.048163
15	Ayaka	Noguchi	3	9	t	t	2025-06-22 11:52:21.353101
24	Emi	Nagano	5	4	t	t	2025-06-22 12:14:07.690781
25	Haruki	Inoue	1	6	t	t	2025-06-22 12:23:15.89137
26	Taiga	Tani	5	7	t	t	2025-06-22 12:24:37.099717
28	Venturo	Attilio	13	7	t	t	2025-06-22 12:31:04.131954
14	Nanami	Morita	5	1	t	t	2025-06-22 11:46:24.687693
29	Arisa	Komatsu	9	6	t	t	2025-06-22 12:34:34.051541
16	Kenji	Morimoto	7	3	f	f	2025-06-22 11:56:49.781576
17	Riko	Hoshino	2	1	t	t	2025-06-22 11:56:55.042047
30	Marco	Giancarlo	13	8	t	t	2025-06-22 12:49:04.876435
31	Nanami	Koga	5	4	t	t	2025-06-22 12:50:06.923275
33	Miki	Yamaguchi	5	2	t	t	2025-06-22 12:51:54.854435
34	Rina	Sakai	4	10	t	t	2025-06-22 12:52:04.998971
35	Mai	Ichikawa	4	3	t	t	2025-06-22 12:52:27.22131
37	Ami	Tajima	11	2	t	t	2025-06-22 12:53:16.231522
12	Claire	Richard	12	4	t	t	2025-06-22 11:45:40.771004
19	Miki	Arakawa	5	7	t	t	2025-06-22 12:01:26.79538
4	Kosagi	Kotatsu	6	4	t	t	2025-06-22 11:19:06.71051
3	Kazusa	Noayame	5	3	t	t	2025-06-22 11:19:06.71051
40	Koji	Nagano	5	4	t	t	2025-06-22 13:57:37.694313
41	Bianca	Venezio	13	9	t	t	2025-06-22 13:59:18.285651
42	Keita	Tani	5	10	t	t	2025-06-22 14:04:20.882295
38	Nao	Nobunaga	6	10	t	t	2025-06-22 13:21:42.586355
43	Takumi	Yamamoto	1	4	t	t	2025-06-22 14:15:56.685522
44	Mei	Yamamoto	1	6	t	t	2025-06-22 14:16:57.297143
20	Rina	Ichinose	8	7	t	t	2025-06-22 12:10:56.502146
21	Kanon	Takada	6	1	t	t	2025-06-22 12:11:33.983849
39	Yuna	Shimizu	2	3	t	t	2025-06-22 13:41:58.558034
36	Tomo	Okada	3	9	t	t	2025-06-22 12:52:42.676664
46	Koki	Himura	6	3	t	t	2025-06-22 14:29:02.00996
11	Miyu	Hirano	2	5	t	t	2025-06-22 11:45:36.049994
18	Ryota	Yoshikawa	4	3	t	t	2025-06-22 11:57:55.097125
22	Natsuki	Nobunaga	6	9	t	t	2025-06-22 12:12:03.388366
23	Hikari	Kawano	10	1	t	t	2025-06-22 12:13:32.565499
32	Hinako	Ichikawa	4	4	t	t	2025-06-22 12:50:29.051299
27	Shiori	Kiriyama	4	4	f	f	2025-06-22 12:25:19.336918
45	Keita	Arakawa	5	4	f	f	2025-06-22 14:23:47.641348
\.


--
-- Data for Name: zones; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.zones (id, name, description, defence_val, calculated_defence_val, claimer_controller_id, holder_controller_id) FROM stdin;
2	Montagnes d’Ehime	Entourant le redouté mont Ishizuchi, plus haut sommet de Shikoku, ces montagnes sacrées sont le domaine des ascètes, des yamabushi et des esprits anciens. Les chemins escarpés sont peuplés de temples isolés, de cascades énigmatiques, et d’histoires transmises à demi-mot. Nul ne traverse ces hauteurs sans y laisser un peu de son âme.	6	6	\N	6
3	Cap sud de Kochi	Battue par les vents de l’océan Pacifique, cette pointe rocheuse est riche en minerai de fer, extrait dans la sueur et le sel. Le paysage austère dissuade les faibles, mais attire les clans ambitieux. Les tempêtes y sont violentes, et même les dragons du ciel semblent redouter ses falaises noires.	6	6	5	5
8	Ile d’Awaji	Pont vivant entre Shikoku et Honshū, Awaji est stratégiquement vitale et toujours convoitée. Les vents y sont brutaux, les détroits traîtres, et les seigneurs prudents. Ses collines cachent des fortins, ses criques des repaires, et ses chemins sont surveillés par des yeux invisibles.	6	6	5	5
10	Cité Impériale de Kyoto	Capitale impériale, centre des arts, des lettres et des poisons subtils. Les palais y cachent les plus anciennes lignées, les ruelles les complots les plus jeunes. Kyōto ne brandit pas l’épée, mais ceux qui y règnent peuvent faire plier des provinces entières par un sourire ou un silence.	6	6	5	4
6	Côte Est de Tokushima	Sur cette façade tournée vers le large, le clan Miyoshi établit son pouvoir entre les ports et les postes fortifiés. Bien que prospère, la région est sous tension : les vassaux y sont fiers, les ambitions grandes, et les flottes ennemies jamais loin. La mer y apporte autant de trésors que de périls.	6	8	5	5
7	Prefecture de Kagawa	Plaine fertile dominée par les haras impériaux et les sanctuaires oubliés, Kagawa est renommée pour ses chevaux rapides et robustes. Les émissaires s’y rendent pour négocier montures de guerre, messagers ou montures sacrées. C’est aussi une terre de festivals éclatants et de compétitions féroces.	6	10	7	7
1	Côte Ouest d’Ehime	La porte vers l’île de Kyūshū, cette bande littorale est animée par les flux incessants de navires marchands, pêcheurs et patrouilleurs. Les criques cachent parfois des comptoirs discrets ou des avant-postes de contrebandiers. Les brumes marines y sont fréquentes, rendant les approches aussi incertaines que les intentions de ses habitants.	6	8	5	5
5	Vallées d’Iya et d’Oboké de Tokushima	Ces vallées profondes, creusées par les torrents et le temps, abritent des plantations de thé précieuses et des villages suspendus au flanc des falaises. Peu accessibles, elles sont le refuge de ceux qui fuient la guerre, la loi ou le destin. Le thé qui y pousse a le goût amer des secrets oubliés.	6	8	\N	5
9	Ile de Shōdoshima	Ile montagneuse et sauvage, jadis sanctuaire, aujourd’hui repaire des pirates Wako. Ses ports semblent paisibles, mais ses criques abritent des embarcations rapides prêtes à fondre sur les convois marchands. Les autorités ferment souvent les yeux, car même le vice paie tribut.	6	9	\N	8
4	Grande Baie de Kochi	Centre de pouvoir du clan Chōsokabe, cette baie est à la fois un havre de paix et un verrou stratégique. Bordée de rizières fertiles et de ports animés, elle est défendue par des flottes aguerries et des forteresses discrètes. On dit que ses eaux reflètent les ambitions de ceux qui la contrôlent.	6	9	5	5
\.


--
-- Name: artefacts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.artefacts_id_seq', 5, true);


--
-- Name: config_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.config_id_seq', 121, true);


--
-- Name: controller_known_locations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.controller_known_locations_id_seq', 94, true);


--
-- Name: controller_worker_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.controller_worker_id_seq', 50, true);


--
-- Name: controllers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.controllers_id_seq', 8, true);


--
-- Name: controllers_known_enemies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.controllers_known_enemies_id_seq', 111, true);


--
-- Name: faction_powers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.faction_powers_id_seq', 27, true);


--
-- Name: factions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.factions_id_seq', 9, true);


--
-- Name: link_power_type_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.link_power_type_id_seq', 122, true);


--
-- Name: location_attack_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.location_attack_logs_id_seq', 3, true);


--
-- Name: locations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.locations_id_seq', 43, true);


--
-- Name: mechanics_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.mechanics_id_seq', 1, true);


--
-- Name: players_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.players_id_seq', 9, true);


--
-- Name: power_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.power_types_id_seq', 1, false);


--
-- Name: powers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.powers_id_seq', 122, true);


--
-- Name: worker_actions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.worker_actions_id_seq', 178, true);


--
-- Name: worker_names_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.worker_names_id_seq', 170, true);


--
-- Name: worker_origins_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.worker_origins_id_seq', 13, true);


--
-- Name: worker_powers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.worker_powers_id_seq', 211, true);


--
-- Name: workers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.workers_id_seq', 46, true);


--
-- Name: zones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.zones_id_seq', 10, true);


--
-- Name: artefacts artefacts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.artefacts
    ADD CONSTRAINT artefacts_pkey PRIMARY KEY (id);


--
-- Name: config config_name_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.config
    ADD CONSTRAINT config_name_key UNIQUE (name);


--
-- Name: config config_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.config
    ADD CONSTRAINT config_pkey PRIMARY KEY (id);


--
-- Name: controller_known_locations controller_known_locations_controller_id_location_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_known_locations
    ADD CONSTRAINT controller_known_locations_controller_id_location_id_key UNIQUE (controller_id, location_id);


--
-- Name: controller_known_locations controller_known_locations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_known_locations
    ADD CONSTRAINT controller_known_locations_pkey PRIMARY KEY (id);


--
-- Name: controller_worker controller_worker_controller_id_worker_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_worker
    ADD CONSTRAINT controller_worker_controller_id_worker_id_key UNIQUE (controller_id, worker_id);


--
-- Name: controller_worker controller_worker_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_worker
    ADD CONSTRAINT controller_worker_pkey PRIMARY KEY (id);


--
-- Name: controller_worker controller_worker_worker_id_is_primary_controller_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_worker
    ADD CONSTRAINT controller_worker_worker_id_is_primary_controller_key UNIQUE (worker_id, is_primary_controller);


--
-- Name: controllers_known_enemies controllers_known_enemies_controller_id_discovered_worker_i_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers_known_enemies
    ADD CONSTRAINT controllers_known_enemies_controller_id_discovered_worker_i_key UNIQUE (controller_id, discovered_worker_id);


--
-- Name: controllers_known_enemies controllers_known_enemies_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers_known_enemies
    ADD CONSTRAINT controllers_known_enemies_pkey PRIMARY KEY (id);


--
-- Name: controllers controllers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers
    ADD CONSTRAINT controllers_pkey PRIMARY KEY (id);


--
-- Name: faction_powers faction_powers_faction_id_link_power_type_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.faction_powers
    ADD CONSTRAINT faction_powers_faction_id_link_power_type_id_key UNIQUE (faction_id, link_power_type_id);


--
-- Name: faction_powers faction_powers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.faction_powers
    ADD CONSTRAINT faction_powers_pkey PRIMARY KEY (id);


--
-- Name: factions factions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factions
    ADD CONSTRAINT factions_pkey PRIMARY KEY (id);


--
-- Name: link_power_type link_power_type_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.link_power_type
    ADD CONSTRAINT link_power_type_pkey PRIMARY KEY (id);


--
-- Name: link_power_type link_power_type_power_type_id_power_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.link_power_type
    ADD CONSTRAINT link_power_type_power_type_id_power_id_key UNIQUE (power_type_id, power_id);


--
-- Name: location_attack_logs location_attack_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.location_attack_logs
    ADD CONSTRAINT location_attack_logs_pkey PRIMARY KEY (id);


--
-- Name: locations locations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_pkey PRIMARY KEY (id);


--
-- Name: mechanics mechanics_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.mechanics
    ADD CONSTRAINT mechanics_pkey PRIMARY KEY (id);


--
-- Name: player_controller player_controller_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.player_controller
    ADD CONSTRAINT player_controller_pkey PRIMARY KEY (controller_id, player_id);


--
-- Name: players players_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.players
    ADD CONSTRAINT players_pkey PRIMARY KEY (id);


--
-- Name: players players_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.players
    ADD CONSTRAINT players_username_key UNIQUE (username);


--
-- Name: power_types power_types_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.power_types
    ADD CONSTRAINT power_types_pkey PRIMARY KEY (id);


--
-- Name: powers powers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.powers
    ADD CONSTRAINT powers_pkey PRIMARY KEY (id);


--
-- Name: worker_actions worker_actions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_actions
    ADD CONSTRAINT worker_actions_pkey PRIMARY KEY (id);


--
-- Name: worker_actions worker_actions_worker_id_turn_number_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_actions
    ADD CONSTRAINT worker_actions_worker_id_turn_number_key UNIQUE (worker_id, turn_number);


--
-- Name: worker_names worker_names_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_names
    ADD CONSTRAINT worker_names_pkey PRIMARY KEY (id);


--
-- Name: worker_origins worker_origins_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_origins
    ADD CONSTRAINT worker_origins_pkey PRIMARY KEY (id);


--
-- Name: worker_powers worker_powers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_powers
    ADD CONSTRAINT worker_powers_pkey PRIMARY KEY (id);


--
-- Name: worker_powers worker_powers_worker_id_link_power_type_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_powers
    ADD CONSTRAINT worker_powers_worker_id_link_power_type_id_key UNIQUE (worker_id, link_power_type_id);


--
-- Name: workers workers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workers
    ADD CONSTRAINT workers_pkey PRIMARY KEY (id);


--
-- Name: zones zones_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.zones
    ADD CONSTRAINT zones_pkey PRIMARY KEY (id);


--
-- Name: artefacts artefacts_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.artefacts
    ADD CONSTRAINT artefacts_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: controller_known_locations controller_known_locations_controller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_known_locations
    ADD CONSTRAINT controller_known_locations_controller_id_fkey FOREIGN KEY (controller_id) REFERENCES public.controllers(id);


--
-- Name: controller_known_locations controller_known_locations_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_known_locations
    ADD CONSTRAINT controller_known_locations_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.locations(id);


--
-- Name: controller_worker controller_worker_controller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_worker
    ADD CONSTRAINT controller_worker_controller_id_fkey FOREIGN KEY (controller_id) REFERENCES public.controllers(id);


--
-- Name: controller_worker controller_worker_worker_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controller_worker
    ADD CONSTRAINT controller_worker_worker_id_fkey FOREIGN KEY (worker_id) REFERENCES public.workers(id);


--
-- Name: controllers controllers_faction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers
    ADD CONSTRAINT controllers_faction_id_fkey FOREIGN KEY (faction_id) REFERENCES public.factions(id);


--
-- Name: controllers controllers_fake_faction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers
    ADD CONSTRAINT controllers_fake_faction_id_fkey FOREIGN KEY (fake_faction_id) REFERENCES public.factions(id);


--
-- Name: controllers_known_enemies controllers_known_enemies_controller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers_known_enemies
    ADD CONSTRAINT controllers_known_enemies_controller_id_fkey FOREIGN KEY (controller_id) REFERENCES public.controllers(id);


--
-- Name: controllers_known_enemies controllers_known_enemies_discovered_controller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers_known_enemies
    ADD CONSTRAINT controllers_known_enemies_discovered_controller_id_fkey FOREIGN KEY (discovered_controller_id) REFERENCES public.controllers(id);


--
-- Name: controllers_known_enemies controllers_known_enemies_discovered_worker_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers_known_enemies
    ADD CONSTRAINT controllers_known_enemies_discovered_worker_id_fkey FOREIGN KEY (discovered_worker_id) REFERENCES public.workers(id);


--
-- Name: controllers_known_enemies controllers_known_enemies_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.controllers_known_enemies
    ADD CONSTRAINT controllers_known_enemies_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.zones(id);


--
-- Name: faction_powers faction_powers_faction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.faction_powers
    ADD CONSTRAINT faction_powers_faction_id_fkey FOREIGN KEY (faction_id) REFERENCES public.factions(id);


--
-- Name: faction_powers faction_powers_link_power_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.faction_powers
    ADD CONSTRAINT faction_powers_link_power_type_id_fkey FOREIGN KEY (link_power_type_id) REFERENCES public.link_power_type(id);


--
-- Name: link_power_type link_power_type_power_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.link_power_type
    ADD CONSTRAINT link_power_type_power_id_fkey FOREIGN KEY (power_id) REFERENCES public.powers(id);


--
-- Name: link_power_type link_power_type_power_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.link_power_type
    ADD CONSTRAINT link_power_type_power_type_id_fkey FOREIGN KEY (power_type_id) REFERENCES public.power_types(id);


--
-- Name: location_attack_logs location_attack_logs_attacker_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.location_attack_logs
    ADD CONSTRAINT location_attack_logs_attacker_id_fkey FOREIGN KEY (attacker_id) REFERENCES public.controllers(id);


--
-- Name: location_attack_logs location_attack_logs_attacker_id_fkey1; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.location_attack_logs
    ADD CONSTRAINT location_attack_logs_attacker_id_fkey1 FOREIGN KEY (attacker_id) REFERENCES public.controllers(id);


--
-- Name: location_attack_logs location_attack_logs_target_controller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.location_attack_logs
    ADD CONSTRAINT location_attack_logs_target_controller_id_fkey FOREIGN KEY (target_controller_id) REFERENCES public.controllers(id);


--
-- Name: location_attack_logs location_attack_logs_target_controller_id_fkey1; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.location_attack_logs
    ADD CONSTRAINT location_attack_logs_target_controller_id_fkey1 FOREIGN KEY (target_controller_id) REFERENCES public.controllers(id);


--
-- Name: locations locations_controller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_controller_id_fkey FOREIGN KEY (controller_id) REFERENCES public.controllers(id);


--
-- Name: locations locations_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.zones(id);


--
-- Name: player_controller player_controller_controller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.player_controller
    ADD CONSTRAINT player_controller_controller_id_fkey FOREIGN KEY (controller_id) REFERENCES public.controllers(id);


--
-- Name: player_controller player_controller_player_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.player_controller
    ADD CONSTRAINT player_controller_player_id_fkey FOREIGN KEY (player_id) REFERENCES public.players(id);


--
-- Name: worker_actions worker_actions_controller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_actions
    ADD CONSTRAINT worker_actions_controller_id_fkey FOREIGN KEY (controller_id) REFERENCES public.controllers(id);


--
-- Name: worker_actions worker_actions_worker_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_actions
    ADD CONSTRAINT worker_actions_worker_id_fkey FOREIGN KEY (worker_id) REFERENCES public.workers(id);


--
-- Name: worker_actions worker_actions_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_actions
    ADD CONSTRAINT worker_actions_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.zones(id);


--
-- Name: worker_names worker_names_origin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_names
    ADD CONSTRAINT worker_names_origin_id_fkey FOREIGN KEY (origin_id) REFERENCES public.worker_origins(id);


--
-- Name: worker_powers worker_powers_link_power_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_powers
    ADD CONSTRAINT worker_powers_link_power_type_id_fkey FOREIGN KEY (link_power_type_id) REFERENCES public.link_power_type(id);


--
-- Name: worker_powers worker_powers_worker_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.worker_powers
    ADD CONSTRAINT worker_powers_worker_id_fkey FOREIGN KEY (worker_id) REFERENCES public.workers(id);


--
-- Name: workers workers_origin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workers
    ADD CONSTRAINT workers_origin_id_fkey FOREIGN KEY (origin_id) REFERENCES public.worker_origins(id);


--
-- Name: workers workers_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workers
    ADD CONSTRAINT workers_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.zones(id);


--
-- Name: zones zones_claimer_controller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.zones
    ADD CONSTRAINT zones_claimer_controller_id_fkey FOREIGN KEY (claimer_controller_id) REFERENCES public.controllers(id);


--
-- Name: zones zones_holder_controller_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.zones
    ADD CONSTRAINT zones_holder_controller_id_fkey FOREIGN KEY (holder_controller_id) REFERENCES public.controllers(id);


--
-- PostgreSQL database dump complete
--

