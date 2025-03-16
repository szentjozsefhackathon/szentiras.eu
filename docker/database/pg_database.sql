--
-- PostgreSQL database dump
--

-- Dumped from database version 14.15 (Ubuntu 14.15-1.pgdg22.04+1)
-- Dumped by pg_dump version 14.15 (Ubuntu 14.15-0ubuntu0.22.04.1)

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
-- Name: kar_articles; Type: TABLE; Schema: public; Owner: homestead
--

CREATE EXTENSION IF NOT EXISTS vector SCHEMA public;

CREATE TABLE public.kar_articles (
    id integer NOT NULL,
    created_at timestamp without time zone DEFAULT '2001-01-01 00:00:00'::timestamp without time zone NOT NULL,
    updated_at timestamp without time zone DEFAULT '2001-01-01 00:00:00'::timestamp without time zone NOT NULL,
    frontpage boolean NOT NULL,
    text text NOT NULL COLLATE pg_catalog."hu-x-icu",
    title character varying(100) NOT NULL COLLATE pg_catalog."hu-x-icu",
    publish_date timestamp without time zone DEFAULT '2001-01-01 00:00:00'::timestamp without time zone NOT NULL
);


ALTER TABLE public.kar_articles OWNER TO homestead;

--
-- Name: kar_articles_id_seq; Type: SEQUENCE; Schema: public; Owner: homestead
--

CREATE SEQUENCE public.kar_articles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.kar_articles_id_seq OWNER TO homestead;

--
-- Name: kar_articles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: homestead
--

ALTER SEQUENCE public.kar_articles_id_seq OWNED BY public.kar_articles.id;


--
-- Name: kar_book_abbrevs; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_book_abbrevs (
    id integer NOT NULL,
    abbrev character varying(255) DEFAULT NULL::character varying COLLATE pg_catalog."hu-x-icu",
    books_id integer NOT NULL,
    translation_id integer
);


ALTER TABLE public.kar_book_abbrevs OWNER TO homestead;

--
-- Name: kar_book_abbrevs_id_seq; Type: SEQUENCE; Schema: public; Owner: homestead
--

CREATE SEQUENCE public.kar_book_abbrevs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.kar_book_abbrevs_id_seq OWNER TO homestead;

--
-- Name: kar_book_abbrevs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: homestead
--

ALTER SEQUENCE public.kar_book_abbrevs_id_seq OWNED BY public.kar_book_abbrevs.id;


--
-- Name: kar_books; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_books (
    number integer NOT NULL,
    created_at timestamp without time zone DEFAULT '2001-01-01 00:00:00'::timestamp without time zone NOT NULL,
    updated_at timestamp without time zone DEFAULT '2001-01-01 00:00:00'::timestamp without time zone NOT NULL,
    translation_id integer NOT NULL,
    name character varying(100) NOT NULL COLLATE pg_catalog."hu-x-icu",
    abbrev character varying(10) NOT NULL COLLATE pg_catalog."hu-x-icu",
    link character varying(10) NOT NULL COLLATE pg_catalog."hu-x-icu",
    old_testament integer NOT NULL,
    id integer NOT NULL
);


ALTER TABLE public.kar_books OWNER TO homestead;

--
-- Name: kar_books_id_seq; Type: SEQUENCE; Schema: public; Owner: homestead
--

CREATE SEQUENCE public.kar_books_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.kar_books_id_seq OWNER TO homestead;

--
-- Name: kar_books_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: homestead
--

ALTER SEQUENCE public.kar_books_id_seq OWNED BY public.kar_books.id;


--
-- Name: kar_migrations; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_migrations (
    migration character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu",
    batch integer NOT NULL
);


ALTER TABLE public.kar_migrations OWNER TO homestead;

--
-- Name: kar_password_resets; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_password_resets (
    email character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu",
    token character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu",
    created_at timestamp without time zone NOT NULL
);


ALTER TABLE public.kar_password_resets OWNER TO homestead;

--
-- Name: kar_reading_plan_days; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_reading_plan_days (
    id integer NOT NULL,
    plan_id integer NOT NULL,
    day_number integer NOT NULL,
    description character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu",
    verses character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu"
);


ALTER TABLE public.kar_reading_plan_days OWNER TO homestead;

--
-- Name: kar_reading_plan_days_id_seq; Type: SEQUENCE; Schema: public; Owner: homestead
--

CREATE SEQUENCE public.kar_reading_plan_days_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.kar_reading_plan_days_id_seq OWNER TO homestead;

--
-- Name: kar_reading_plan_days_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: homestead
--

ALTER SEQUENCE public.kar_reading_plan_days_id_seq OWNED BY public.kar_reading_plan_days.id;


--
-- Name: kar_reading_plans; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_reading_plans (
    id integer NOT NULL,
    name character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu",
    description character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu"
);


ALTER TABLE public.kar_reading_plans OWNER TO homestead;

--
-- Name: kar_reading_plans_id_seq; Type: SEQUENCE; Schema: public; Owner: homestead
--

CREATE SEQUENCE public.kar_reading_plans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.kar_reading_plans_id_seq OWNER TO homestead;

--
-- Name: kar_reading_plans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: homestead
--

ALTER SEQUENCE public.kar_reading_plans_id_seq OWNED BY public.kar_reading_plans.id;


--
-- Name: kar_synonyms; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_synonyms (
    id integer NOT NULL,
    created_at timestamp without time zone DEFAULT '2001-01-01 00:00:00'::timestamp without time zone NOT NULL,
    updated_at timestamp without time zone DEFAULT '2001-01-01 00:00:00'::timestamp without time zone NOT NULL,
    word character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu",
    "group" integer NOT NULL
);


ALTER TABLE public.kar_synonyms OWNER TO homestead;

--
-- Name: kar_synonyms_id_seq; Type: SEQUENCE; Schema: public; Owner: homestead
--

CREATE SEQUENCE public.kar_synonyms_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.kar_synonyms_id_seq OWNER TO homestead;

--
-- Name: kar_synonyms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: homestead
--

ALTER SEQUENCE public.kar_synonyms_id_seq OWNED BY public.kar_synonyms.id;


--
-- Name: kar_tdbook; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_tdbook (
    id integer DEFAULT 0 NOT NULL,
    trans integer DEFAULT 0 NOT NULL,
    name character varying(100) DEFAULT ''::character varying NOT NULL COLLATE pg_catalog."hu-x-icu",
    abbrev character varying(10) DEFAULT ''::character varying NOT NULL COLLATE pg_catalog."hu-x-icu",
    url character varying(10) DEFAULT ''::character varying NOT NULL COLLATE pg_catalog."hu-x-icu",
    countch integer DEFAULT 0 NOT NULL,
    oldtest integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.kar_tdbook OWNER TO homestead;

--
-- Name: kar_tdtrans; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_tdtrans (
    id integer NOT NULL,
    name character varying(100) DEFAULT ''::character varying NOT NULL COLLATE pg_catalog."hu-x-icu",
    abbrev character varying(10) DEFAULT ''::character varying NOT NULL COLLATE pg_catalog."hu-x-icu",
    denom character varying(20) DEFAULT NULL::character varying COLLATE pg_catalog."hu-x-icu",
    lang character varying(10) DEFAULT NULL::character varying COLLATE pg_catalog."hu-x-icu",
    copyright text COLLATE pg_catalog."hu-x-icu",
    publisher character varying(200) DEFAULT NULL::character varying COLLATE pg_catalog."hu-x-icu",
    publisherurl character varying(200) DEFAULT NULL::character varying COLLATE pg_catalog."hu-x-icu",
    reference character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu"
);


ALTER TABLE public.kar_tdtrans OWNER TO homestead;

--
-- Name: kar_tdtrans_id_seq; Type: SEQUENCE; Schema: public; Owner: homestead
--

CREATE SEQUENCE public.kar_tdtrans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.kar_tdtrans_id_seq OWNER TO homestead;

--
-- Name: kar_tdtrans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: homestead
--

ALTER SEQUENCE public.kar_tdtrans_id_seq OWNED BY public.kar_tdtrans.id;


--
-- Name: kar_tdverse; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_tdverse (
    trans integer NOT NULL,
    gepi bigint NOT NULL,
    book_number integer NOT NULL,
    chapter integer NOT NULL,
    numv character varying(4) NOT NULL COLLATE pg_catalog."hu-x-icu",
    tip integer NOT NULL,
    verse text COLLATE pg_catalog."hu-x-icu",
    verseroot text COLLATE pg_catalog."hu-x-icu",
    ido character varying(50) DEFAULT NULL::character varying COLLATE pg_catalog."hu-x-icu",
    id integer NOT NULL,
    book_id integer NOT NULL
);


ALTER TABLE public.kar_tdverse OWNER TO homestead;

--
-- Name: kar_tdverse_id_seq; Type: SEQUENCE; Schema: public; Owner: homestead
--

CREATE SEQUENCE public.kar_tdverse_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.kar_tdverse_id_seq OWNER TO homestead;

--
-- Name: kar_tdverse_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: homestead
--

ALTER SEQUENCE public.kar_tdverse_id_seq OWNED BY public.kar_tdverse.id;


--
-- Name: kar_translations; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_translations (
    id integer NOT NULL,
    created_at timestamp without time zone DEFAULT '2001-01-01 00:00:00'::timestamp without time zone NOT NULL,
    updated_at timestamp without time zone DEFAULT '2001-01-01 00:00:00'::timestamp without time zone NOT NULL,
    name character varying(100) NOT NULL COLLATE pg_catalog."hu-x-icu",
    abbrev character varying(10) NOT NULL COLLATE pg_catalog."hu-x-icu",
    "order" integer NOT NULL,
    denom character varying(20) NOT NULL COLLATE pg_catalog."hu-x-icu",
    lang character varying(10) NOT NULL COLLATE pg_catalog."hu-x-icu",
    copyright text NOT NULL COLLATE pg_catalog."hu-x-icu",
    publisher character varying(200) NOT NULL COLLATE pg_catalog."hu-x-icu",
    publisher_url character varying(200) NOT NULL COLLATE pg_catalog."hu-x-icu",
    reference character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu"
);


ALTER TABLE public.kar_translations OWNER TO homestead;

--
-- Name: kar_translations_id_seq; Type: SEQUENCE; Schema: public; Owner: homestead
--

CREATE SEQUENCE public.kar_translations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.kar_translations_id_seq OWNER TO homestead;

--
-- Name: kar_translations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: homestead
--

ALTER SEQUENCE public.kar_translations_id_seq OWNED BY public.kar_translations.id;


--
-- Name: kar_users; Type: TABLE; Schema: public; Owner: homestead
--

CREATE TABLE public.kar_users (
    id integer NOT NULL,
    name character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu",
    email character varying(255) NOT NULL COLLATE pg_catalog."hu-x-icu",
    password character varying(60) NOT NULL COLLATE pg_catalog."hu-x-icu",
    remember_token character varying(100) DEFAULT NULL::character varying COLLATE pg_catalog."hu-x-icu",
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


ALTER TABLE public.kar_users OWNER TO homestead;

--
-- Name: kar_users_id_seq; Type: SEQUENCE; Schema: public; Owner: homestead
--

CREATE SEQUENCE public.kar_users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.kar_users_id_seq OWNER TO homestead;

--
-- Name: kar_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: homestead
--

ALTER SEQUENCE public.kar_users_id_seq OWNED BY public.kar_users.id;


--
-- Name: kar_articles id; Type: DEFAULT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_articles ALTER COLUMN id SET DEFAULT nextval('public.kar_articles_id_seq'::regclass);


--
-- Name: kar_book_abbrevs id; Type: DEFAULT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_book_abbrevs ALTER COLUMN id SET DEFAULT nextval('public.kar_book_abbrevs_id_seq'::regclass);


--
-- Name: kar_books id; Type: DEFAULT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_books ALTER COLUMN id SET DEFAULT nextval('public.kar_books_id_seq'::regclass);


--
-- Name: kar_reading_plan_days id; Type: DEFAULT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_reading_plan_days ALTER COLUMN id SET DEFAULT nextval('public.kar_reading_plan_days_id_seq'::regclass);


--
-- Name: kar_reading_plans id; Type: DEFAULT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_reading_plans ALTER COLUMN id SET DEFAULT nextval('public.kar_reading_plans_id_seq'::regclass);


--
-- Name: kar_synonyms id; Type: DEFAULT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_synonyms ALTER COLUMN id SET DEFAULT nextval('public.kar_synonyms_id_seq'::regclass);


--
-- Name: kar_tdtrans id; Type: DEFAULT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_tdtrans ALTER COLUMN id SET DEFAULT nextval('public.kar_tdtrans_id_seq'::regclass);


--
-- Name: kar_tdverse id; Type: DEFAULT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_tdverse ALTER COLUMN id SET DEFAULT nextval('public.kar_tdverse_id_seq'::regclass);


--
-- Name: kar_translations id; Type: DEFAULT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_translations ALTER COLUMN id SET DEFAULT nextval('public.kar_translations_id_seq'::regclass);


--
-- Name: kar_users id; Type: DEFAULT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_users ALTER COLUMN id SET DEFAULT nextval('public.kar_users_id_seq'::regclass);


--
-- Data for Name: kar_articles; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_articles (id, created_at, updated_at, frontpage, text, title, publish_date) FROM stdin;
9	2014-08-01 11:19:30	2014-08-01 11:19:30	f	mindegyik fordításban. Ehhez viszont adatbázis átalakítás is kellett, aminek eredményeként <font color=\\"red\\">speciális keresésfunkciók ideiglenesen nem elérhetőek</font>.	Javított szövegek	2013-11-10 23:00:00
10	2014-08-01 11:19:30	2014-08-01 11:19:30	t	<p>Több kedves felhasználónk szóvá tette, hogy a protestáns fordítások lekerültek honlapunk főoldaláról. Bár paradoxonnak tűnhet, úgy ítéljük meg, hogy a valódi ökumenét tiszteljük ezzel, ill. azoknak a kiadóknak a szándékait is, amelyek a fordítói jogokat birtokolják.</p>\\r\\n<p>A honlap több éves gazdátlanság után Elek László SJ és Dr. Harmai Gábor plébános kezelésébe került, tehát két katolikus személy működik együtt a karbantartásban. A katolikus könyvkiadókkal természetesen közvetlenül partneri a viszonyunk, kijelenthetjük, hogy a két teljes katolikus fordítás digitális szövege nálunk lelhető föl legjobb minőségben. A föltárt sajtóhibákat azonnal javítjuk, és visszajelezzünk a könyvkiadók felé.</p>\\r\\n<p>Protestáns partnereink közül a Magyar Bibliatársulat joggal ragaszkodik ahhoz, hogy a protestáns új fordítás elsődleges digitális forrása a Magyar Bibliatársulat honlapja legyen, nekünk csak az összehasonlítás céljából történő másodlagos publikációt engedélyezték, amit így is hálásan köszönünk.</p>\\r\\n<p>A Károli-fordítás fordítói jogai természetesen lejártak, jogilag tisztázatlan ugyan számunkra, hogy a revízió teremt-e új szerzői jogokat, de az viszont nyilvánvalónak tűnik, hogy két katolikus honlapkarbantartó nem törekedhet többre, mint e szép ősi protestáns Szentírásfordítás összehasonlítás céljából történő megjelentetésére. Tárgyalunk arról, hogy a legújabb revíziójú szöveget megjeleníthetjük-e, hasonlóképpen: összehasonlítás céljából.</p>	Állásfoglalás a protestáns fordításokról	2013-11-11 23:00:00
11	2014-08-01 11:19:30	2014-08-01 11:19:30	f	- találatok súlyozott sorrendben<br/>\\r\\n- idézőjeles keresés támogatása<br/>\\r\\n- szinoníma és hasonló tippek<br/>\\r\\n- keresés másik fordításban<br/>\\r\\n- várható találatok számának előjelzése<br/>\\r\\n- találatok csoportosítása fejezetenként<br/>\\r\\n- találatok tárolása a gyorsabb keresésért<br/>\\r\\nTovábbá sok-sok szöveghibát kijavítottunk.<br/>	Áldott karácsony kívánunk a kövekező újdonságainkkal:	2013-12-24 23:00:00
12	2014-08-01 11:19:30	2014-08-01 11:19:30	f	<p>Bár nincsenek látványos változások és hol gyors, hol pedig rettentesen lassú a honlap, komoly háttér munkálatok folynak. Git-et ismerő lelkes php programozók segítségét szeretettel várjuk.</p>	Munkálatok folynak	2014-02-28 23:00:00
13	2014-08-01 11:19:30	2014-08-01 11:19:30	t	<p>A honlap forráskódja elérhető a <a href=\\"https://github.com/borazslo/szentiras.hu\\">https://github.com/borazslo/szentiras.hu</a> honlapon. Bekapcsolódási lehetőségről bővebben a wiki-ben: <a href=\\"https://github.com/borazslo/szentiras.hu/wiki/Hogyan-seg%C3%ADthetek%3F\\">https://github.com/borazslo/szentiras.hu/wiki/Hogyan-seg%C3%ADthetek%3F</a></p>	Segíthetsz a honlap fejlesztésében!	2014-03-23 23:00:00
\.


--
-- Data for Name: kar_book_abbrevs; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_book_abbrevs (id, abbrev, books_id, translation_id) FROM stdin;
1	1Móz	101	\N
2	1Moz	101	\N
3	1Mozes	101	\N
4	1Mózes	101	\N
5	Teremtes	101	\N
6	Teremtés	101	\N
7	Kiv	102	\N
8	2Móz	102	\N
9	2Moz	102	\N
10	2Mozes	102	\N
11	2Mózes	102	\N
12	Kivonulas	102	\N
13	Kivonulás	102	\N
14	Lev	103	\N
15	3Móz	103	\N
16	3Moz	103	\N
17	3Mozes	103	\N
18	3Mózes	103	\N
19	Leviták	103	\N
20	Szám	104	\N
21	4Móz	104	\N
22	4Moz	104	\N
23	4Mozes	104	\N
24	4Mózes	104	\N
25	Szam	104	\N
26	Szamok	104	\N
27	Számok	104	\N
28	MTörv	105	\N
29	5Móz	105	\N
30	5Moz	105	\N
31	5Mozes	105	\N
32	5Mózes	105	\N
33	Mtorv	105	\N
34	Józs	106	\N
35	Jozs	106	\N
36	Jozsue	106	\N
37	Józsue	106	\N
38	Józsué	106	\N
39	Bír	107	\N
40	Bir	107	\N
41	Birak	107	\N
42	Birák	107	\N
43	Bírák	107	\N
44	Rut	108	\N
45	Ruth	108	\N
46	Rút	108	\N
47	Rúth	108	\N
48	1Sám	109	\N
49	1Sam	109	\N
50	1Samuel	109	\N
51	1Sámuel	109	\N
52	1Sámuél	109	\N
53	Samuel1	109	\N
54	SamuelI	109	\N
55	Sámuel1	109	\N
56	SámuelI	109	\N
57	2Sám	110	\N
58	2Sam	110	\N
59	2Samuel	110	\N
60	2Sámuel	110	\N
61	2Sámuél	110	\N
62	Samuel2	110	\N
63	SamuelII	110	\N
64	Sámuel2	110	\N
65	SámuelII	110	\N
66	1Kir	111	\N
67	2Kir	112	\N
68	1Krón	113	\N
69	2Krón	114	\N
70	Ezd	115	\N
71	Ezsd	115	\N
72	Ezdr	115	\N
73	Ezsdr	115	\N
74	Neh	116	\N
75	Tób	117	\N
76	Jud	118	1
77	Judit	118	\N
78	Esz	119	\N
79	Eszt	119	\N
80	Jób	120	\N
81	Zsolt	121	\N
82	Zs	121	\N
83	Zsoltar	121	\N
84	Zsoltarok	121	\N
85	Zsoltár	121	\N
86	Zsoltárok	121	\N
87	Péld	122	\N
88	Préd	123	\N
89	Én	124	\N
90	Énekek	124	\N
91	ÉnekÉn	124	\N
92	Bölcs	125	\N
93	Sir	126	\N
94	Sír	126	\N
95	Iz	127	\N
96	Ézs	127	\N
97	Ésa	127	\N
98	Jer	128	\N
99	Siral	129	\N
100	Jsir	129	\N
101	Siralm	129	\N
102	Sir	129	4
103	Bár	130	\N
104	Ez	131	\N
105	Ezék	131	\N
106	Ezek	131	\N
107	Ezekias	131	\N
108	Ezekiás	131	\N
109	Ezékiás	131	\N
110	Dán	132	\N
111	Oz	133	\N
112	Hós	133	\N
113	Óz	133	\N
114	Jo	134	\N
115	Jóel	134	\N
116	Ám	135	\N
117	Ámós	135	\N
118	Abd	136	\N
119	Jón	137	\N
120	Mik	138	\N
121	Náh	139	\N
122	Hab	140	\N
123	Szof	141	\N
124	Zof	141	\N
125	Sof	141	\N
126	Ag	142	\N
127	Hag	142	\N
128	Agg	142	\N
129	Zak	143	\N
130	Mal	144	\N
131	Malak	144	\N
132	1Mak	145	\N
133	1Makk	145	\N
134	2Mak	146	\N
135	2Makk	146	\N
136	Mt	201	\N
137	Mát	201	\N
138	Mat	201	\N
139	Mate	201	\N
140	Máté	201	\N
141	Mk	202	\N
142	Márk	202	\N
143	Mar	202	\N
144	Mark	202	\N
145	Már	202	\N
146	Lk	203	\N
147	Luk	203	\N
148	Lukacs	203	\N
149	Lukács	203	\N
150	Jn	204	\N
151	Ján	204	\N
152	Jan	204	\N
153	Janos	204	\N
154	János	204	\N
155	ApCsel	205	\N
156	Csel	205	\N
157	Róm	206	\N
158	1Kor	207	\N
159	2Kor	208	\N
160	Gal	209	\N
161	Ef	210	\N
162	Eféz	210	\N
163	Fil	211	\N
164	Kol	212	\N
165	1Tessz	213	\N
166	1Thessz	213	\N
167	1Thess	213	\N
168	2Tessz	214	\N
169	2Thessz	214	\N
170	2Thess	214	\N
171	1Tim	215	\N
172	2Tim	216	\N
173	Tit	217	\N
174	Filem	218	\N
175	Zsid	219	\N
176	Jak	220	\N
177	1Pt	221	\N
178	1Pét	221	\N
179	2Pt	222	\N
180	2Pét	222	\N
181	1Jn	223	\N
182	1Ján	223	\N
183	1Jan	223	\N
184	1Janos	223	\N
185	1János	223	\N
186	2Jn	224	\N
187	2Ján	224	\N
188	2Jan	224	\N
189	2Janos	224	\N
190	2János	224	\N
191	3Jn	225	\N
192	3Ján	225	\N
193	Júd	226	\N
194	Jud	226	\N
195	Júdás	226	\N
196	Jel	227	\N
197	Ter	101	\N
198	1Tesz	213	\N
199	2Tesz	214	\N
200	Tít	217	\N
201	Csel	205	\N
202	Joel	134	\N
203	Rom	206	\N
204	En	124	\N
\.


--
-- Data for Name: kar_books; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_books (number, created_at, updated_at, translation_id, name, abbrev, link, old_testament, id) FROM stdin;
101	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Teremtés könyve	Ter	Ter	1	1
101	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Mózes első könyve	1Móz	1Moz	1	2
101	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Teremtés könyve	Ter	Ter	1	3
101	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Mózes első könyve a teremtésről	1Móz	1Moz	1	4
102	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Kivonulás könyve	Kiv	Kiv	1	5
102	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Mózes második könyve	2Móz	2Moz	1	6
102	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Kivonulás könyve	Kiv	Kiv	1	7
102	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Mózes második könyve a zsidóknak Égyiptomból kijöveteléről	2Móz	2Moz	1	8
103	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Leviták könyve	Lev	Lev	1	9
103	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Mózes harmadik könyve	3Móz	3Moz	1	10
103	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Leviták könyve	Lev	Lev	1	11
103	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Mózes harmadik könyve a Léviták egyházi szolgálatáról	3Móz	3Moz	1	12
104	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Számok könyve	Szám	Szam	1	13
104	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Mózes negyedik könyve	4Móz	4Moz	1	14
104	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Számok könyve	Szám	Szam	1	15
104	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Mózes negyedik könyve az Izráeliták megszámlálásáról való könyv	4Móz	4Moz	1	16
105	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Második Törvénykönyv	MTörv	MTorv	1	17
105	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Mózes ötödik könyve	5Móz	5Moz	1	18
105	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Második Törvénykönyv	MTörv	MTorv	1	19
105	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Mózes ötödik könyve a törvény summája	5Móz	5Moz	1	20
106	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Józsue könyve	Józs	Jozs	1	21
106	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Józsué könyve	Józs	Jozs	1	22
106	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Józsue könyve	Józs	Jozs	1	23
106	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Józsué könyve	Józs	Jozs	1	24
107	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Bírák könyve	Bír	Bir	1	25
107	2014-08-01 11:19:30	2014-08-01 11:19:30	2	A bírák könyve	Bír	Bir	1	26
107	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Bírák könyve	Bír	Bir	1	27
107	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Birák könyve	Bir	Bir	1	28
108	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Rut könyve	Rut	Rut	1	29
108	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Ruth könyve	Ruth	Ruth	1	30
108	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Rút könyve	Rút	Rut	1	31
108	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Ruth könyve	Ruth	Ruth	1	32
109	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Sámuel I. könyve	1Sám	1Sam	1	33
109	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Sámuel első könyve	1Sám	1Sam	1	34
109	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Sámuel első könyve	1Sám	1Sam	1	35
109	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Sámuel első könyve	1Sám	1Sam	1	36
110	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Sámuel II. könyve	2Sám	2Sam	1	37
110	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Sámuel második könyve	2Sám	2Sam	1	38
110	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Sámuel második könyve	2Sám	2Sam	1	39
110	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Sámuel második könyve	2Sám	2Sam	1	40
111	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Királyok I. könyve	1Kir	1Kir	1	41
111	2014-08-01 11:19:30	2014-08-01 11:19:30	2	A királyok első könyve	1Kir	1Kir	1	42
111	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Királyok első könyve	1Kir	1Kir	1	43
111	2014-08-01 11:19:30	2014-08-01 11:19:30	4	A királyokról írt I. könyv	1Kir	1Kir	1	44
112	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Királyok II. könyve	2Kir	2Kir	1	45
112	2014-08-01 11:19:30	2014-08-01 11:19:30	2	A királyok második könyve	2Kir	2Kir	1	46
112	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Királyok második könyve	2Kir	2Kir	1	47
112	2014-08-01 11:19:30	2014-08-01 11:19:30	4	A királyokról írt II. könyv	2Kir	2Kir	1	48
113	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Krónikák I. könyve	1Krón	1Kron	1	49
113	2014-08-01 11:19:30	2014-08-01 11:19:30	2	A krónikák első könyve	1Krón	1Kron	1	50
113	2014-08-01 11:19:30	2014-08-01 11:19:30	3	A krónikák első könyve	1Krón	1Kron	1	51
113	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Krónika I. könyve	1Krón	1Kron	1	52
114	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Krónikák II. könyve	2Krón	2Kron	1	53
114	2014-08-01 11:19:30	2014-08-01 11:19:30	2	A krónikák második könyve	2Krón	2Kron	1	54
114	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Krónikák második könyve	2Krón	2Kron	1	55
114	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Krónika II. könyve	2Krón	2Kron	1	56
115	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Ezdrás könyve	Ezd	Ezd	1	57
115	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Ezsdrás könyve	Ezsd	Ezsd	1	58
115	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Ezdrás könyve	Ezdr	Ezdr	1	59
115	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Eszdrás könyve	Ezsdr	Ezsdr	1	60
116	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Nehemiás könyve	Neh	Neh	1	61
116	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Nehémiás könyve	Neh	Neh	1	62
116	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Nehemiás könyve	Neh	Neh	1	63
116	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Nehémiás könyve	Neh	Neh	1	64
117	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Tóbiás könyve	Tób	Tob	1	65
117	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Tóbiás könyve	Tób	Tób	1	66
118	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Judit könyve	Jud	Jud	1	67
118	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Judit könyve	Judit	Judit	1	68
119	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Eszter könyve	Esz	Esz	1	69
119	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Eszter könyve	Eszt	Eszt	1	70
119	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Eszter könyve	Eszt	Eszt	1	71
119	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Eszter könyve	Eszt	Eszt	1	72
120	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Jób könyve	Jób	Job	1	73
120	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Jób könyve	Jób	Job	1	74
120	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Jób könyve	Jób	Jób	1	75
120	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Jób könyve	Jób	Job	1	76
121	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Zsoltárok könyve	Zsolt	Zsolt	1	77
121	2014-08-01 11:19:30	2014-08-01 11:19:30	2	A zsoltárok könyve	Zsolt	Zsolt	1	78
121	2014-08-01 11:19:30	2014-08-01 11:19:30	3	A zsoltárok könyve	Zsolt	Zsolt	1	79
121	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Zsoltárok könyve	Zsolt	Zsolt	1	80
122	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Példabeszédek könyve	Péld	Peld	1	81
122	2014-08-01 11:19:30	2014-08-01 11:19:30	2	A példabeszédek könyve	Péld	Peld	1	82
122	2014-08-01 11:19:30	2014-08-01 11:19:30	3	A példabeszédek könyve	Péld	Peld	1	83
122	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Bölcs Salamonnak példabeszédei	Péld	Peld	1	84
123	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Prédikátor könyve	Préd	Pred	1	85
123	2014-08-01 11:19:30	2014-08-01 11:19:30	2	A prédikátor könyve	Préd	Pred	1	86
123	2014-08-01 11:19:30	2014-08-01 11:19:30	3	A Prédikátor könyve	Préd	Pred	1	87
123	2014-08-01 11:19:30	2014-08-01 11:19:30	4	A prédikátor Salamon könyve	Préd	Pred	1	88
124	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Énekek éneke	Én	En	1	89
124	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Énekek éneke	Énekek	Enekek	1	90
124	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Az énekek éneke	Én	En	1	91
124	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Salamon énekek éneke	Ének.Én	EnekEn	1	92
125	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Bölcsesség könyve	Bölcs	Bolcs	1	93
125	2014-08-01 11:19:30	2014-08-01 11:19:30	3	A bölcsesség könyve	Bölcs	Bolcs	1	94
126	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Sirák fia könyve	Sir	Sir	1	95
126	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Jézus, Sirák fiának könyve\t	Sír	Sir	1	96
127	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Izajás könyve	Iz	Iz	1	97
127	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Ézsaiás próféta könyve	Ézs	Ezs	1	98
127	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Izajás könyve	Iz	Iz	1	99
127	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Ésaiás próféta könyve	Ésa	Esa	1	100
128	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Jeremiás könyve	Jer	Jer	1	101
128	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Jeremiás próféta könyve	Jer	Jer	1	102
128	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Jeremiás könyve	Jer	Jer	1	103
128	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Jeremiás próféta könyve	Jer	Jer	1	104
129	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Siralmak könyve	Siral	Siral	1	105
129	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Jeremiás siralmai	Jsir	Jsir	1	106
129	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Jeremiás siralmai	Siralm	Siralm	1	107
129	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Jeremiás siralmai	Sir	Sir	1	108
130	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Báruk könyve	Bár	Bar	1	109
130	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Báruk könyve	Bár	Bar	1	110
131	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Ezekiel könyve	Ez	Ez	1	111
131	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Ezékiel próféta könyve	Ez	Ez	1	112
131	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Ezekiel jövendölése	Ez	Ez	1	113
131	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Ezékiel próféta könyve	Ezék	Ezek	1	114
132	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Dániel könyve	Dán	Dan	1	115
132	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Dániel próféta könyve	Dán	Dan	1	116
132	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Dániel jövendölése	Dán	Dan	1	117
132	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Dániel próféta könyve	Dán	Dan	1	118
133	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Ozeás könyve	Oz	Oz	1	119
133	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Hóseás próféta könyve	Hós	Hos	1	120
133	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Ózeás jövendölése	Óz	Oz	1	121
133	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Hóseás próféta könyve	Hós	Hos	1	122
134	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Joel könyve	Jo	Jo	1	123
134	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Jóel próféta könyve	Jóel	Joel	1	124
134	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Joel jövendölése	Jo	Jo	1	125
134	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Jóel próféta könyve	Jóel	Joel	1	126
135	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Ámosz könyve	Ám	Am	1	127
135	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Ámósz próféta könyve	Ám	Am	1	128
135	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Ámosz jövendölése	Ám	Am	1	129
135	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Ámos próféta könyve	Ámós	Amos	1	130
136	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Abdiás könyve	Abd	Abd	1	131
136	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Abdiás próféta könyve	Abd	Abd	1	132
136	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Abdiás jövendölése	Abd	Abd	1	133
136	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Abdiás próféta könyve	Abd	Abd	1	134
137	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Jónás könyve	Jón	Jon	1	135
137	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Jónás próféta könyve	Jón	Jon	1	136
137	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Jónás jövendölése	Jón	Jon	1	137
137	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Jónás próféta könyve	Jón	Jon	1	138
138	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Mikeás könyve	Mik	Mik	1	139
138	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Mikeás próféta könyve	Mik	Mik	1	140
138	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Mikeás jövendölése	Mik	Mik	1	141
138	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Mikeás próféta könyve	Mik	Mik	1	142
139	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Náhum könyve	Náh	Nah	1	143
139	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Náhum próféta könyve	Náh	Nah	1	144
139	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Náhum jövendölése	Náh	Nah	1	145
139	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Náhum próféta könyve	Náh	Nah	1	146
140	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Habakuk könyve	Hab	Hab	1	147
140	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Habakuk próféta könyve	Hab	Hab	1	148
140	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Habakuk jövendölése	Hab	Hab	1	149
140	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Habakuk próféta könyve	Hab	Hab	1	150
141	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Szofoniás könyve	Szof	Szof	1	151
141	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Zofóniás próféta könyve	Zof	Zof	1	152
141	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Szofoniás jövendölése	Szof	Szof	1	153
141	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Sofóniás próféta könyve	Sof	Sof	1	154
142	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Aggeus könyve	Ag	Ag	1	155
142	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Haggeus próféta könyve	Hag	Hag	1	156
142	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Aggeus jövendölése	Agg	Agg	1	157
142	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Aggeus próféta könyve	Agg	Agg	1	158
143	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Zakariás könyve	Zak	Zak	1	159
143	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Zakariás próféta könyve	Zak	Zak	1	160
143	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Zakariás jövendölése	Zak	Zak	1	161
143	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Zakariás próféta könyve	Zak	Zak	1	162
144	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Malakiás könyve	Mal	Mal	1	163
144	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Malakiás próféta könyve	Mal	Mal	1	164
144	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Malakiás jövendölése	Mal	Mal	1	165
144	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Malakiás próféta könyve	Malak	Malak	1	166
145	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Makkabeusok I. könyve	1Mak	1Mak	1	167
145	2014-08-01 11:19:30	2014-08-01 11:19:30	3	A Makkabeusok első könyve	1Makk	1Makk	1	168
146	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Makkabeusok II. könyve	2Mak	2Mak	1	169
146	2014-08-01 11:19:30	2014-08-01 11:19:30	3	A Makkabeusok második könyve	2Makk	2Makk	1	170
201	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Máté evangéliuma	Mt	Mt	0	171
201	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Máté evangéliuma	Mt	Mt	0	172
201	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Evangélium Máté szerint	Mt	Mt	0	173
201	2014-08-01 11:19:30	2014-08-01 11:19:30	4	A Máté írása szerint való szent evangyéliom	Mát	Mat	0	174
202	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Márk evangéliuma	Mk	Mk	0	175
202	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Márk evangéliuma	Mk	Mk	0	176
202	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Evangélium Márk szerint	Mk	Mk	0	177
202	2014-08-01 11:19:30	2014-08-01 11:19:30	4	A Márk írása szerint való szent evangyéliom	Márk	Mark	0	178
203	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Lukács evangéliuma	Lk	Lk	0	179
203	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Lukács evangéliuma	Lk	Lk	0	180
203	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Evangélium Lukács szerint	Lk	Lk	0	181
203	2014-08-01 11:19:30	2014-08-01 11:19:30	4	A Lukács írása szerint való szent evangyéliom	Luk	Luk	0	182
204	2014-08-01 11:19:30	2014-08-01 11:19:30	1	János evangéliuma	Jn	Jn	0	183
204	2014-08-01 11:19:30	2014-08-01 11:19:30	2	János evangéliuma	Jn	Jn	0	184
204	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Evangélium János szerint	Jn	Jn	0	185
204	2014-08-01 11:19:30	2014-08-01 11:19:30	4	A János írása szerint való szent evangyéliom	Ján	Jan	0	186
205	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Apostolok Cselekedetei	ApCsel	ApCsel	0	187
205	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Az apostolok cselekedetei	ApCsel	ApCsel	0	188
205	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Az apostolok cselekedetei	Csel	Csel	0	189
205	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Az apostolok cselekedetei 	Csel	Csel	0	190
206	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Rómaiaknak írt levél	Róm	Rom	0	191
206	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál levele a rómaiakhoz	Róm	Rom	0	192
206	2014-08-01 11:19:30	2014-08-01 11:19:30	3	A rómaiaknak írt levél	Róm	Rom	0	193
206	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak a rómabeliekhez írt levele	Róm	Rom	0	194
207	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Korintusiaknak írt I. levél	1Kor	1Kor	0	195
207	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál első levele a korinthusiakhoz	1Kor	1Kor	0	196
207	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Első levél a korintusiaknak	1Kor	1Kor	0	197
207	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak a korinthusbeliekhez írt első levele	1Kor	1Kor	0	198
208	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Korintusiaknak írt II. levél	2Kor	2Kor	0	199
208	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál második levele a korinthusiakhoz	2Kor	2Kor	0	200
208	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Második levél a korintusiaknak	2Kor	2Kor	0	201
208	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak a korinthusbeliekhez írt második levele	2Kor	2Kor	0	202
209	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Galatáknak írt levél	Gal	Gal	0	203
209	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál levele a galatákhoz	Gal	Gal	0	204
209	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Levél a galatáknak	Gal	Gal	0	205
209	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak a galátziabeliekhez írt levele	Gal	Gal	0	206
210	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Efezusiaknak írt levél	Ef	Ef	0	207
210	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál levele az efezusiakhoz	Ef	Ef	0	208
210	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Levél az efezusiaknak	Ef	Ef	0	209
210	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak az efézusbeliekhez írt levele	Eféz	Efez	0	210
211	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Filippieknek írt levél	Fil	Fil	0	211
211	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál levele a filippiekhez	Fil	Fil	0	212
211	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Levél a filippieknek	Fil	Fil	0	213
211	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak a filippibeliekhez írt levele	Fil	Fil	0	214
212	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Kolosszeieknek írt levél	Kol	Kol	0	215
212	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál levele a kolosséiakhoz	Kol	Kol	0	216
212	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Levél a kolosszeieknek	Kol	Kol	0	217
212	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak a kolossébeliekhez írt levele	Kol	Kol	0	218
213	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Tesszalonikaiaknak írt I. levél	1Tesz	1Tesz	0	219
213	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál első levele a thesszalonikaiakhoz	1Thessz	1Thessz	0	220
213	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Első levél a tesszalonikieknek	1Tessz	1Tessz	0	221
213	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak a thessalonikabeliekhez írott első levele	1Thess	1Thess	0	222
214	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Tesszalonikaiaknak írt II. levél	2Tesz	2Tesz	0	223
214	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál második levele a thesszalonikaiakhoz	2Thessz	2Thessz	0	224
214	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Második levél a tesszalonikieknek	2Tessz	2Tessz	0	225
214	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak a thessalonikabeliekhez írott második levele	2Thess	2Thess	0	226
215	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Timóteusnak írt I. levél	1Tim	1Tim	0	227
215	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál első levele Timóteushoz	1Tim	1Tim	0	228
215	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Első levél Timóteusnak	1Tim	1Tim	0	229
215	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak Timótheushoz írt első levele	1Tim	1Tim	0	230
216	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Timóteusnak írt II. levél	2Tim	2Tim	0	231
216	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál második levele Timóteushoz	2Tim	2Tim	0	232
216	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Második levél Timóteusnak	2Tim	2Tim	0	233
216	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak Timótheushoz írt második levele	2Tim	2Tim	0	234
217	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Titusznak írt levél	Tit	Tit	0	235
217	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál levele Tituszhoz	Tit	Tit	0	236
217	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Levél Títusznak	Tít	Tit	0	237
217	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak Titushoz írt levele	Tit	Tit	0	238
218	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Filemonnak írt levél	Filem	Filem	0	239
218	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Pál levele Filemonhoz	Filem	Filem	0	240
218	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Levél Filemonnak	Filem	Filem	0	241
218	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Pál apostolnak Filemonhoz írt levele	Filem	Filem	0	242
219	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Zsidóknak írt levél	Zsid	Zsid	0	243
219	2014-08-01 11:19:30	2014-08-01 11:19:30	2	A zsidókhoz írt levél	Zsid	Zsid	0	244
219	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Levél a zsidóknak	Zsid	Zsid	0	245
219	2014-08-01 11:19:30	2014-08-01 11:19:30	4	A zsidókhoz írt levél	Zsid	Zsid	0	246
220	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Jakab levele	Jak	Jak	0	247
220	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Jakab levele	Jak	Jak	0	248
220	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Jakab levele	Jak	Jak	0	249
220	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Jakab apostolnak közönséges levele	Jak	Jak	0	250
221	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Péter I. levele	1Pt	1Pt	0	251
221	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Péter első levele	1Pt	1Pt	0	252
221	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Péter első levele	1Pét	1Pet	0	253
221	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Péter apostolnak közönséges első levele	1Pét	1Pet	0	254
222	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Péter II. levele	2Pt	2Pt	0	255
222	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Péter második levele	2Pt	2Pt	0	256
222	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Péter második levele	2Pét	2Pet	0	257
222	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Péter apostolnak közönséges második levele	2Pét	2Pet	0	258
223	2014-08-01 11:19:30	2014-08-01 11:19:30	1	János I. levele	1Jn	1Jn	0	259
223	2014-08-01 11:19:30	2014-08-01 11:19:30	2	János első levele	1Jn	1Jn	0	260
223	2014-08-01 11:19:30	2014-08-01 11:19:30	3	János első levele	1Ján	1Jan	0	261
223	2014-08-01 11:19:30	2014-08-01 11:19:30	4	János apostolnak közönséges első levele	1Ján	1Jan	0	262
224	2014-08-01 11:19:30	2014-08-01 11:19:30	1	János II. levele	2Jn	2Jn	0	263
224	2014-08-01 11:19:30	2014-08-01 11:19:30	2	János második levele	2Jn	2Jn	0	264
224	2014-08-01 11:19:30	2014-08-01 11:19:30	3	János második levele	2Ján	2Jan	0	265
224	2014-08-01 11:19:30	2014-08-01 11:19:30	4	János apostolnak közönséges második levele	2Ján	2Jan	0	266
225	2014-08-01 11:19:30	2014-08-01 11:19:30	1	János III. levele	3Jn	3Jn	0	267
225	2014-08-01 11:19:30	2014-08-01 11:19:30	2	János harmadik levele	3Jn	3Jn	0	268
225	2014-08-01 11:19:30	2014-08-01 11:19:30	3	János harmadik levele	3Ján	3Jan	0	269
225	2014-08-01 11:19:30	2014-08-01 11:19:30	4	János apostolnak közönséges hamadik levele	3Ján	3Jan	0	270
226	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Júdás levele	Júd	Judas	0	271
226	2014-08-01 11:19:30	2014-08-01 11:19:30	2	Júdás levele	Júd	Jud	0	272
226	2014-08-01 11:19:30	2014-08-01 11:19:30	3	Júdás levele	Júd	Jud	0	273
226	2014-08-01 11:19:30	2014-08-01 11:19:30	4	Júdás apostolnak közönséges levele	Júd	Jud	0	274
227	2014-08-01 11:19:30	2014-08-01 11:19:30	1	Jelenések könyve	Jel	Jel	0	275
227	2014-08-01 11:19:30	2014-08-01 11:19:30	2	A jelenések könyve	Jel	Jel	0	276
227	2014-08-01 11:19:30	2014-08-01 11:19:30	3	János jelenései	Jel	Jel	0	277
227	2014-08-01 11:19:30	2014-08-01 11:19:30	4	János apostolnak mennyei jelenésekről való könyve	Jel	Jel	0	278
201	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Jézus Krisztus evangéliuma Máté szerint	Mt	Mt	0	356
202	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Jézus Krisztus evangéliuma Márk szerint	Mk	Mk	0	357
203	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Jézus Krisztus evangéliuma Lukács szerint	Lk	Lk	0	358
204	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Jézus Krisztus evangéliuma szent János szerint	Jn	Jn	0	359
205	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Az Apostolok Cselekedetei	ApCsel	ApCsel	0	360
206	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol levele a rómaiakhoz	Róm	Rom	0	361
207	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol első levele a korintusiakhoz	1Kor	1Kor	0	362
208	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol második levele a korintusiakhoz	2Kor	2Kor	0	363
209	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol levele a galatákhoz	Gal	Gal	0	364
210	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol levele az efezusiakhoz	Ef	Ef	0	365
211	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol levele a filippiekhez	Fil	Fil	0	366
212	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol levele a kolosszeiekhez	Kol	Kol	0	367
213	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol első levele a tesszalonikaiakhoz	1Tessz	1Tessz	0	368
214	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol második levele a tesszalonikaiakhoz	2Tessz	2Tessz	0	369
215	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol első levele Timóteushoz	1Tim	1Tim	0	370
216	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol második levele Timóteushoz	2Tim	2Tim	0	371
217	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol levele Tituszhoz	Tit	Tit	0	372
218	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol levele Filemonhoz	Filem	Filem	0	373
219	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Pál apostol leveve a zsidókhoz	Zsid	Zsid	0	374
220	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Jakab apostol levele	Jak	Jak	0	375
221	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Péter apostol első levele	1Pét	1Pet	0	376
222	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Péter apostol második levele	2Pét	2Pet	0	377
223	2025-01-29 16:52:16	2025-01-29 16:52:16	5	János apostol első levele	1Ján	1Jan	0	378
224	2025-01-29 16:52:16	2025-01-29 16:52:16	5	János apostol második levele	2Ján	2Jan	0	379
225	2025-01-29 16:52:16	2025-01-29 16:52:16	5	János apostol harmadik levele	3Ján	3Jan	0	380
226	2025-01-29 16:52:16	2025-01-29 16:52:16	5	Júdás apostol levele	Júd	Jud	0	381
227	2025-01-29 16:52:16	2025-01-29 16:52:16	5	János apostol Jelenéseinek Könyve	Jel	Jel	0	382
101	2025-01-29 16:52:16	2025-01-29 16:52:16	6	Mózes első könyve	1Móz	1Moz	1	383
102	2025-01-29 16:52:16	2025-01-29 16:52:16	6	Mózes második könyve	2Móz	2Moz	1	384
103	2025-01-29 16:52:16	2025-01-29 16:52:16	6	Mózes harmadik könyve	3Móz	3Moz	1	385
104	2025-01-29 16:52:16	2025-01-29 16:52:16	6	Mózes negyedik könyve	4Móz	4Moz	1	386
105	2025-01-29 16:52:16	2025-01-29 16:52:16	6	Mózes ötödik könyve	5Móz	5Moz	1	387
106	2025-01-29 16:52:16	2025-01-29 16:52:16	6	Józsué könyve	Józs	Jozs	1	388
107	2025-01-29 16:52:16	2025-01-29 16:52:16	6	A bírák könyve	Bír	Bir	1	389
108	2025-01-29 16:52:16	2025-01-29 16:52:16	6	Ruth könyve	Ruth	Ruth	1	390
109	2025-01-29 16:52:16	2025-01-29 16:52:16	6	Sámuel első könyve	1Sám	1Sam	1	391
110	2025-01-29 16:52:16	2025-01-29 16:52:16	6	Sámuel második könyve	2Sám	2Sam	1	392
111	2025-01-29 16:52:16	2025-01-29 16:52:16	6	A királyok első könyve	1Kir	1Kir	1	393
112	2025-01-29 16:52:16	2025-01-29 16:52:16	6	A királyok második könyve	2Kir	2Kir	1	394
113	2025-01-29 16:52:16	2025-01-29 16:52:16	6	A krónikák első könyve	1Krón	1Kron	1	395
114	2025-01-29 16:52:17	2025-01-29 16:52:17	6	A krónikák második könyve	2Krón	2Kron	1	396
115	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Ezsdrás könyve	Ezsd	Ezsd	1	397
116	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Nehémiás könyve	Neh	Neh	1	398
119	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Eszter könyve	Eszt	Eszt	1	399
120	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Jób könyve	Jób	Job	1	400
121	2025-01-29 16:52:17	2025-01-29 16:52:17	6	A zsoltárok könyve	Zsolt	Zsolt	1	401
122	2025-01-29 16:52:17	2025-01-29 16:52:17	6	A példabeszédek könyve	Péld	Peld	1	402
123	2025-01-29 16:52:17	2025-01-29 16:52:17	6	A prédikátor könyve	Préd	Pred	1	403
124	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Énekek éneke	Énekek	Enekek	1	404
127	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Ézsaiás próféta könyve	Ézs	Ezs	1	405
128	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Jeremiás próféta könyve	Jer	Jer	1	406
129	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Jeremiás siralmai	Jsir	Jsir	1	407
131	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Ezékiel próféta könyve	Ez	Ez	1	408
132	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Dániel próféta könyve	Dán	Dan	1	409
133	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Hóseás próféta könyve	Hós	Hos	1	410
134	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Jóel próféta könyve	Jóel	Joel	1	411
135	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Ámósz próféta könyve	Ám	Am	1	412
136	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Abdiás próféta könyve	Abd	Abd	1	413
137	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Jónás próféta könyve	Jón	Jon	1	414
138	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Mikeás próféta könyve	Mik	Mik	1	415
139	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Náhum próféta könyve	Náh	Nah	1	416
140	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Habakuk próféta könyve	Hab	Hab	1	417
141	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Zofóniás próféta könyve	Zof	Zof	1	418
142	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Haggeus próféta könyve	Hag	Hag	1	419
143	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Zakariás próféta könyve	Zak	Zak	1	420
144	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Malakiás próféta könyve	Mal	Mal	1	421
201	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Máté evangéliuma	Mt	Mt	0	422
202	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Márk evangéliuma	Mk	Mk	0	423
203	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Lukács evangéliuma	Lk	Lk	0	424
204	2025-01-29 16:52:17	2025-01-29 16:52:17	6	János evangéliuma	Jn	Jn	0	425
205	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Az apostolok cselekedetei	ApCsel	ApCsel	0	426
206	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál levele a rómaiakhoz	Róm	Rom	0	427
207	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál első levele a korinthusiakhoz	1Kor	1Kor	0	428
208	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál második levele a korinthusiakhoz	2Kor	2Kor	0	429
209	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál levele a galatákhoz	Gal	Gal	0	430
210	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál levele az efezusiakhoz	Ef	Ef	0	431
211	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál levele a filippiekhez	Fil	Fil	0	432
212	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál levele a kolosséiakhoz	Kol	Kol	0	433
213	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál első levele a thesszalonikaiakhoz	1Thessz	1Thessz	0	434
214	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál második levele a thesszalonikaiakhoz	2Thessz	2Thessz	0	435
215	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál első levele Timóteushoz	1Tim	1Tim	0	436
216	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál második levele Timóteushoz	2Tim	2Tim	0	437
217	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál levele Tituszhoz	Tit	Tit	0	438
218	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Pál levele Filemonhoz	Filem	Filem	0	439
219	2025-01-29 16:52:17	2025-01-29 16:52:17	6	A zsidókhoz írt levél	Zsid	Zsid	0	440
220	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Jakab levele	Jak	Jak	0	441
221	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Péter első levele	1Pt	1Pt	0	442
222	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Péter második levele	2Pt	2Pt	0	443
223	2025-01-29 16:52:17	2025-01-29 16:52:17	6	János első levele	1Jn	1Jn	0	444
224	2025-01-29 16:52:17	2025-01-29 16:52:17	6	János második levele	2Jn	2Jn	0	445
225	2025-01-29 16:52:17	2025-01-29 16:52:17	6	János harmadik levele	3Jn	3Jn	0	446
226	2025-01-29 16:52:17	2025-01-29 16:52:17	6	Júdás levele	Júd	Jud	0	447
227	2025-01-29 16:52:17	2025-01-29 16:52:17	6	A jelenések könyve	Jel	Jel	0	448
201	2025-01-29 16:52:17	2025-01-29 16:52:17	7	Máté evangéliuma	Mt	Mt	0	449
202	2025-01-29 16:52:17	2025-01-29 16:52:17	7	Márk evangéliuma	Mk	Mk	0	450
203	2025-01-29 16:52:17	2025-01-29 16:52:17	7	Lukács evangéliuma	Lk	Lk	0	451
204	2025-01-29 16:52:17	2025-01-29 16:52:17	7	János evangéliuma	Jn	Jn	0	452
205	2025-01-29 16:52:17	2025-01-29 16:52:17	7	Az apostolok cselekedetei	ApCsel	ApCsel	0	453
206	2025-01-29 16:52:17	2025-01-29 16:52:17	7	A rómaiaknak írt levél	Róm	Rom	0	454
207	2025-01-29 16:52:17	2025-01-29 16:52:17	7	A korintusiaknak írt első levél	1Kor	1Kor	0	455
208	2025-01-29 16:52:17	2025-01-29 16:52:17	7	A korintusiaknak írt második levél	2Kor	2Kor	0	456
209	2025-01-29 16:52:17	2025-01-29 16:52:17	7	A galatáknak írt levél	Gal	Gal	0	457
210	2025-01-29 16:52:17	2025-01-29 16:52:17	7	Az efezusiaknak írt levél	Ef	Ef	0	458
211	2025-01-29 16:52:17	2025-01-29 16:52:17	7	A filippieknek írt levél	Fil	Fil	0	459
212	2025-01-29 16:52:18	2025-01-29 16:52:18	7	A kolosszeieknek írt levél	Kol	Kol	0	460
213	2025-01-29 16:52:18	2025-01-29 16:52:18	7	A tesszalonikaiaknak írt első levél	1Tessz	1Tessz	0	461
214	2025-01-29 16:52:18	2025-01-29 16:52:18	7	A tesszalonikaiaknak írt második levél	2Tessz	2Tessz	0	462
215	2025-01-29 16:52:18	2025-01-29 16:52:18	7	A Timóteusnak írt első levél	1Tim	1Tim	0	463
216	2025-01-29 16:52:18	2025-01-29 16:52:18	7	A Timóteusnak írt második levél	2Tim	2Tim	0	464
217	2025-01-29 16:52:18	2025-01-29 16:52:18	7	A Titusznak írt levél	Tit	Tit	0	465
218	2025-01-29 16:52:18	2025-01-29 16:52:18	7	A Filemonnak írt levél	Filem	Filem	0	466
219	2025-01-29 16:52:18	2025-01-29 16:52:18	7	A zsidóknak írt levél	Zsid	Zsid	0	467
220	2025-01-29 16:52:18	2025-01-29 16:52:18	7	Jakab levele	Jak	Jak	0	468
221	2025-01-29 16:52:18	2025-01-29 16:52:18	7	Péter első levele	1Pt	1Pt	0	469
222	2025-01-29 16:52:18	2025-01-29 16:52:18	7	Péter második levele	2Pt	2Pt	0	470
223	2025-01-29 16:52:18	2025-01-29 16:52:18	7	János első levele	1Jn	1Jn	0	471
224	2025-01-29 16:52:18	2025-01-29 16:52:18	7	János második levele	2Jn	2Jn	0	472
225	2025-01-29 16:52:18	2025-01-29 16:52:18	7	János harmadik levele	3Jn	3Jn	0	473
226	2025-01-29 16:52:18	2025-01-29 16:52:18	7	Júdás levele	Júd	Jud	0	474
227	2025-01-29 16:52:18	2025-01-29 16:52:18	7	Jelenések könyve	Jel	Jel	0	475
\.


--
-- Data for Name: kar_migrations; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_migrations (migration, batch) FROM stdin;
2014_04_04_062157_create_book_abbrev_table	1
2014_04_04_093158_rename_book_abbrev_table	1
2014_04_04_131602_create_books_table	1
2014_04_04_152425_book_abbrevs_change_book_id	1
2014_04_04_170317_migrate_tdbook	1
2014_04_05_233136_migrate_tdtrans	1
2014_04_06_221445_testing_create_tdverse	1
2014_04_07_100636_create_articles_table	1
2014_04_11_054121_add_Ter_to_book_abbrevs	1
2014_04_16_122756_add_unique_id_to_tdverse	1
2014_04_21_225318_add_indexes_to_tdverse	1
2014_05_19_083737_create_synonyms_table	1
2014_05_23_081141_remove_composite_key_from_books	1
2014_05_23_083846_use_new_book_id_in_verses	1
2014_05_23_093535_rename_book_column_on_tdverse	1
2014_05_29_211513_clean_tdverse	2
2014_06_18_201841_rename_books	2
2014_08_19_181441_add_optional_translation_to_bookAbbrevs	2
2014_08_20_235453_add_Sir_to_KG	2
2014_08_25_175002_addTeszToAbbrevs	3
2014_09_17_105544_addTitToAbbrevs	4
2014_10_12_000000_create_users_table	4
2014_10_12_100000_create_password_resets_table	4
2014_11_11_213403_add_csel_to_bookAbbrevs	4
2014_11_15_021147_addTranslation_BekesDalos	4
2014_12_16_194238_add_order_to_translations	4
2015_02_18_182319_add_joel_to_bookAbbrevs	4
2015_10_11_003151_KNB_books_capitalization	4
2015_10_11_020034_addTranslation_RUF2014	4
2016_08_01_141909_AddBookAbbrevsRomEn	4
2017_10_11_175301_addTranslation_STL	4
2024_12_29_175659_create_reading_plan_tables	4
\.


--
-- Data for Name: kar_password_resets; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_password_resets (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: kar_reading_plan_days; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_reading_plan_days (id, plan_id, day_number, description, verses) FROM stdin;
1	1	1	Őstörténet	Ter1-2;Zsolt19
2	1	2	Őstörténet	Ter3-4;Zsolt104
3	1	3	Őstörténet	Ter5-6;Zsolt136
4	1	4	Őstörténet	Ter7-9;Zsolt1
5	1	5	Őstörténet	Ter10-11;Zsolt2
6	1	6	Pátriárkák kora	Ter12-13;Jób1-2;Péld1,1-7
7	1	7	Pátriárkák kora	Ter14-15;Jób3-4;Péld1,8-19
8	1	8	Pátriárkák kora	Ter16-17;Jób5-6;Péld1,20-33
9	1	9	Pátriárkák kora	Ter18-19;Jób7-8;Péld2,1-5
10	1	10	Pátriárkák kora	Ter20-21;Jób9-10;Péld2,6-8
11	1	11	Pátriárkák kora	Ter22-23;Jób11-12;Péld2,9-15
12	1	12	Pátriárkák kora	Ter24;Jób13-14;Péld2,16-19
13	1	13	Pátriárkák kora	Ter25-26;Jób15-16;Péld2,20-22
14	1	14	Pátriárkák kora	Ter27-28;Jób17-18;Péld3,1-4
15	1	15	Pátriárkák kora	Ter29-30;Jób19-20;Péld3,5-8
16	1	16	Pátriárkák kora	Ter31-32;Jób21-22;Péld3,9-12
17	1	17	Pátriárkák kora	Ter33-34;Jób23-24;Péld3,13-18
18	1	18	Pátriárkák kora	Ter35-36;Jób25-26;Péld3,19-24
19	1	19	Pátriárkák kora	Ter37;Jób27-28;Péld3,25-27
20	1	20	Pátriárkák kora	Ter38;Jób29-30;Péld3,28-32
21	1	21	Pátriárkák kora	Ter39-40;Jób31-32;Péld3,33-35
22	1	22	Pátriárkák kora	Ter41-42;Jób33-34;Péld4,1-9
23	1	23	Pátriárkák kora	Ter43-44;Jób35-36;Péld4,10-19
24	1	24	Pátriárkák kora	Ter45-46;Jób37-38;Péld4,20-27
25	1	25	Pátriárkák kora	Ter47-48;Jób39-40;Zsolt16
26	1	26	Pátriárkák kora	Ter49-50;Jób41-42;Zsolt17
27	1	27	Egyiptom és kivonulás	Kiv1-2;Lev1;Zsolt44
28	1	28	Egyiptom és kivonulás	Kiv3;Lev2-3;Zsolt45
29	1	29	Egyiptom és kivonulás	Kiv4-5;Lev4;Zsolt46
30	1	30	Egyiptom és kivonulás	Kiv6-7;Lev5;Zsolt47
31	1	31	Egyiptom és kivonulás	Kiv8;Lev6;Zsolt48
32	1	32	Egyiptom és kivonulás	Kiv9;Lev7;Zsolt49
33	1	33	Egyiptom és kivonulás	Kiv10-11;Lev8;Zsolt50
34	1	34	Egyiptom és kivonulás	Kiv12;Lev9;Zsolt114
35	1	35	Egyiptom és kivonulás	Kiv13-14;Lev10;Zsolt53
36	1	36	Egyiptom és kivonulás	Kiv15-16;Lev11;Zsolt71
37	1	37	Egyiptom és kivonulás	Kiv17-18;Lev12;Zsolt73
38	1	38	Egyiptom és kivonulás	Kiv19-20;Lev13;Zsolt74
39	1	39	Egyiptom és kivonulás	Kiv21;Lev14;Zsolt75
40	1	40	Egyiptom és kivonulás	Kiv22;Lev15;Zsolt76
41	1	41	Egyiptom és kivonulás	Kiv23;Lev16;Zsolt77
42	1	42	Egyiptom és kivonulás	Kiv24;Lev17-18;Zsolt78
43	1	43	Egyiptom és kivonulás	Kiv25-26;Lev19;Zsolt79
44	1	44	Egyiptom és kivonulás	Kiv27-28;Lev20;Zsolt119,1-88
45	1	45	Egyiptom és kivonulás	Kiv29;Lev21;Zsolt119,89-176
46	1	46	Egyiptom és kivonulás	Kiv30-31;Lev22;Zsolt115
47	1	47	Egyiptom és kivonulás	Kiv32;Lev23;Zsolt80
48	1	48	Egyiptom és kivonulás	Kiv33-34;Lev24;Zsolt81
49	1	49	Egyiptom és kivonulás	Kiv35-36;Lev25;Zsolt82
50	1	50	Egyiptom és kivonulás	Kiv37-38;Lev26;Zsolt83
51	1	51	Egyiptom és kivonulás	Kiv39-40;Lev27;Zsolt84
52	1	52	Sivatagi vándorlás	Szam1;MTorv1;Zsolt85
53	1	53	Sivatagi vándorlás	Szam2;MTorv2;Zsolt87
54	1	54	Sivatagi vándorlás	Szam3;MTorv3;Zsolt88
55	1	55	Sivatagi vándorlás	Szam4;MTorv4;Zsolt89
56	1	56	Sivatagi vándorlás	Szam5;MTorv5;Zsolt90
57	1	57	Sivatagi vándorlás	Szam6;MTorv6;Zsolt91
58	1	58	Sivatagi vándorlás	Szam7;MTorv7;Zsolt92
59	1	59	Sivatagi vándorlás	Szam8-9;MTorv8;Zsolt93
60	1	60	Sivatagi vándorlás	Szam10;MTorv9;Zsolt10
61	1	61	Sivatagi vándorlás	Szam11;MTorv10;Zsolt33
62	1	62	Sivatagi vándorlás	Szam12-13;MTorv11;Zsolt94
63	1	63	Sivatagi vándorlás	Szam14;MTorv12;Zsolt95
64	1	64	Sivatagi vándorlás	Szam15;MTorv13-14;Zsolt96
65	1	65	Sivatagi vándorlás	Szam16;MTorv15-16;Zsolt97
66	1	66	Sivatagi vándorlás	Szam17;MTorv17-18;Zsolt98
67	1	67	Sivatagi vándorlás	Szam18;MTorv19-20;Zsolt99
68	1	68	Sivatagi vándorlás	Szam19-20;MTorv21;Zsolt100
69	1	69	Sivatagi vándorlás	Szam21;MTorv22;Zsolt102
70	1	70	Sivatagi vándorlás	Szam22;MTorv23;Zsolt105
71	1	71	Sivatagi vándorlás	Szam23;MTorv24-25;Zsolt106
72	1	72	Sivatagi vándorlás	Szam24-25;MTorv26;Zsolt107
73	1	73	Sivatagi vándorlás	Szam26;MTorv27;Zsolt111
74	1	74	Sivatagi vándorlás	Szam27-28;MTorv28;Zsolt112
75	1	75	Sivatagi vándorlás	Szam29-30;MTorv29;Zsolt113
76	1	76	Sivatagi vándorlás	Szam31;MTorv30;Zsolt116
77	1	77	Sivatagi vándorlás	Szam32;MTorv31;Zsolt117
78	1	78	Sivatagi vándorlás	Szam33;MTorv32;Zsolt118
79	1	79	Sivatagi vándorlás	Szam34;MTorv33;Zsolt120
80	1	80	Sivatagi vándorlás	Szam35-36;MTorv34;Zsolt121
81	1	81	Ígéret földjének elfoglalása és bírák kora	Jozs1-4;Zsolt123
82	1	82	Ígéret földjének elfoglalása és bírák kora	Jozs5-7;Zsolt125
83	1	83	Ígéret földjének elfoglalása és bírák kora	Jozs8-9;Zsolt126
84	1	84	Ígéret földjének elfoglalása és bírák kora	Jozs10-11;Zsolt128
85	1	85	Ígéret földjének elfoglalása és bírák kora	Jozs12-14;Zsolt129
86	1	86	Ígéret földjének elfoglalása és bírák kora	Jozs15-18;Zsolt130
87	1	87	Ígéret földjének elfoglalása és bírák kora	Jozs19-21;Zsolt131
88	1	88	Ígéret földjének elfoglalása és bírák kora	Jozs22-24;Zsolt132
89	1	89	Ígéret földjének elfoglalása és bírák kora	Bir1-3;Rut1;Zsolt133
90	1	90	Ígéret földjének elfoglalása és bírák kora	Bir4-5;Rut2;Zsolt134
91	1	91	Ígéret földjének elfoglalása és bírák kora	Bir6-8;Rut3;Zsolt135
92	1	92	Ígéret földjének elfoglalása és bírák kora	Bir9-11;Rut4;Zsolt137
93	1	93	Ígéret földjének elfoglalása és bírák kora	Bir12-15;Zsolt146
94	1	94	Ígéret földjének elfoglalása és bírák kora	Bir16-18;Zsolt147
95	1	95	Ígéret földjének elfoglalása és bírák kora	Bir19-21;Zsolt148
96	1	96	Ígéret földjének elfoglalása és bírák kora	1Sam1-2;Zsolt149
97	1	97	Ígéret földjének elfoglalása és bírák kora	1Sam3-5;Zsolt150
98	1	98	Ígéret földjének elfoglalása és bírák kora	1Sam6-8;Zsolt86
99	1	99	Messiási fordulópont	Jn1-3;Péld5,1-6
100	1	100	Messiási fordulópont	Jn4-6;Péld5,7-14
101	1	101	Messiási fordulópont	Jn7-9;Péld5,15-23
102	1	102	Messiási fordulópont	Jn10-12;Péld6,1-11
103	1	103	Messiási fordulópont	Jn13-15;Péld6,12-19
104	1	104	Messiási fordulópont	Jn16-18;Péld6,20-24
105	1	105	Messiási fordulópont	Jn19-21;Péld6,25-35
106	1	106	Királyság kora	1Sam9-10;Zsolt50
107	1	107	Királyság kora	1Sam11-12;Zsolt55
108	1	108	Királyság kora	1Sam13-14;Zsolt58
109	1	109	Királyság kora	1Sam15-16;Zsolt61
110	1	110	Királyság kora	1Sam17;Zsolt12
111	1	111	Királyság kora	1Sam18-19;Zsolt59
112	1	112	Királyság kora	1Sam20;Zsolt142
113	1	113	Királyság kora	1Sam21-22;Zsolt52
114	1	114	Királyság kora	1Sam23;Zsolt54
115	1	115	Királyság kora	1Sam24;Zsolt57
116	1	116	Királyság kora	1Sam25;Zsolt63
117	1	117	Királyság kora	1Sam26;Zsolt56
118	1	118	Királyság kora	1Sam27-28;Zsolt34
119	1	119	Királyság kora	1Sam29-31;Zsolt18
120	1	120	Királyság kora	2Sam1;1Krón1;Zsolt13
121	1	121	Királyság kora	2Sam2;1Krón2;Zsolt24
122	1	122	Királyság kora	2Sam3;1Krón3-4;Zsolt25
123	1	123	Királyság kora	2Sam4;1Krón5-6;Zsolt26
124	1	124	Királyság kora	2Sam5;1Krón7-8;Zsolt27
125	1	125	Királyság kora	2Sam6-7;1Krón9;Zsolt89
126	1	126	Királyság kora	2Sam8;1Krón10-11;Zsolt60
127	1	127	Királyság kora	2Sam9;1Krón12;Zsolt28
128	1	128	Királyság kora	2Sam10;1Krón13;Zsolt31
129	1	129	Királyság kora	2Sam11;1Krón14-15;Zsolt32
130	1	130	Királyság kora	2Sam12;1Krón16;Zsolt51
131	1	131	Királyság kora	2Sam13;1Krón17;Zsolt35
132	1	132	Királyság kora	2Sam14;1Krón18;Zsolt14
133	1	133	Királyság kora	2Sam15;1Krón19-20;Zsolt3
134	1	134	Királyság kora	2Sam16;1Krón21;Zsolt15
135	1	135	Királyság kora	2Sam17;1Krón22;Zsolt36
136	1	136	Királyság kora	2Sam18;1Krón23;Zsolt37
137	1	137	Királyság kora	2Sam19;1Krón24;Zsolt38
138	1	138	Királyság kora	2Sam20;1Krón25;Zsolt39
139	1	139	Királyság kora	2Sam21;1Krón26;Zsolt40
140	1	140	Királyság kora	2Sam22;1Krón27;Zsolt41
141	1	141	Királyság kora	2Sam23;1Krón28;Zsolt42
142	1	142	Királyság kora	2Sam24;1Krón29;Zsolt30
143	1	143	Királyság kora	1Kir1;2Krón1;Zsolt43
144	1	144	Királyság kora	1Kir2;2Krón2-3;Zsolt62
145	1	145	Királyság kora	1Kir3;2Krón4-5;Zsolt64
146	1	146	Királyság kora	1Kir4;2Krón6;Zsolt65
147	1	147	Királyság kora	1Kir5;2Krón7-8;Zsolt66
148	1	148	Királyság kora	1Kir6;2Krón9;Zsolt4
149	1	149	Királyság kora	1Kir7;Préd1-3;Zsolt5
150	1	150	Királyság kora	1Kir8;Préd4-6;Zsolt6
151	1	151	Királyság kora	1Kir9;Préd5-6;Zsolt7
152	1	152	Királyság kora	1Kir10;Préd7-9;Zsolt8
153	1	153	Királyság kora	1Kir11;Préd10-12;Zsolt9
154	1	154	Messiási fordulópont	Mk1-2;Zsolt11
155	1	155	Messiási fordulópont	Mk3-4;Zsolt20
156	1	156	Messiási fordulópont	Mk5-6;Zsolt21
157	1	157	Messiási fordulópont	Mk7-8;Zsolt23
158	1	158	Messiási fordulópont	Mk9-10;Zsolt29
159	1	159	Messiási fordulópont	Mk11-12;Zsolt67
160	1	160	Messiási fordulópont	Mk13-14;Zsolt68
161	1	161	Messiási fordulópont	Mk15-16;Zsolt22
162	1	162	Megosztott királyság kora	1Kir12;2Krón10-11;Én1
163	1	163	Megosztott királyság kora	1Kir13;2Krón12-13;Én2
164	1	164	Megosztott királyság kora	1Kir14;2Krón14-15;Én3
165	1	165	Megosztott királyság kora	1Kir15-16;2Krón16-17;Én4
166	1	166	Megosztott királyság kora	1Kir17-18;2Krón18-19;Én5
167	1	167	Megosztott királyság kora	1Kir19-20;2Krón20;Én6
168	1	168	Megosztott királyság kora	1Kir21;2Krón21-22;Én7
169	1	169	Megosztott királyság kora	1Kir22;2Krón23;Én8
170	1	170	Megosztott királyság kora	2Kir1;2Krón24;Zsolt69
171	1	171	Megosztott királyság kora	2Kir2;2Krón25;Zsolt70
172	1	172	Megosztott királyság kora	2Kir3;2Krón26-27;Zsolt72
173	1	173	Megosztott királyság kora	2Kir4;2Krón28;Zsolt127
174	1	174	Megosztott királyság kora	2Kir5;Óz1-3;Zsolt101
175	1	175	Megosztott királyság kora	2Kir6-7;Óz4-7;Zsolt103
176	1	176	Megosztott királyság kora	2Kir8;Óz8-10;Zsolt108
177	1	177	Megosztott királyság kora	2Kir9;Óz11-14;Zsolt109
178	1	178	Megosztott királyság kora	2Kir10;Ámós1-3;Zsolt110
179	1	179	Megosztott királyság kora	2Kir11-12;Ámós4-6;Zsolt122
180	1	180	Megosztott királyság kora	2Kir13-14;Ámós7-9;Zsolt124
181	1	181	Megosztott királyság kora	2Kir15;Jón1-4;Zsolt138
182	1	182	Megosztott királyság kora	2Kir16;Mik1-4;Zsolt139
183	1	183	Megosztott királyság kora	2Kir17;Mik5-7;Zsolt140
184	1	184	Fogság	2Kir18;2Krón29;Zsolt141
185	1	185	Fogság	2Kir19;2Krón30;Zsolt143
186	1	186	Fogság	2Kir20;2Krón31;Zsolt144
187	1	187	Fogság	2Kir21;2Krón32;Zsolt145
188	1	188	Fogság	2Kir22;2Krón33;Péld7
189	1	189	Fogság	2Kir23;2Krón34;Péld8,1-21
190	1	190	Fogság	2Kir24;2Krón35;Péld8,22-36
191	1	191	Fogság	2Kir25;2Krón36;Péld9,1-6
192	1	192	Fogság	Iz1-2;Tób1-2;Péld9,7-12
193	1	193	Fogság	Iz3-4;Tób3-4;Péld9,13-18
194	1	194	Fogság	Iz5-6;Tób5-6;Péld10,1-4
195	1	195	Fogság	Iz7-8;Tób7-9;Péld10,5-8
196	1	196	Fogság	Iz9-10;Tób10-12;Péld10,9-12
197	1	197	Fogság	Iz11-13;Tób13-14;Péld10,13-16
198	1	198	Fogság	Iz14-15;Jóel1-2;Péld10,17-20
199	1	199	Fogság	Iz16-17;Jóel3;Péld10,21-24
200	1	200	Fogság	Iz18-20;Náh1-2;Péld10,25-28
201	1	201	Fogság	Iz21-22;Náh3;Péld10,29-32
202	1	202	Fogság	Iz23-24;Hab1-2;Péld11,1-4
203	1	203	Fogság	Iz25-27;Hab3;Péld11,5-8
204	1	204	Fogság	Iz28-29;Szof1-2;Péld11,9-12
205	1	205	Fogság	Iz30-31;Szof3;Péld11,13-16
206	1	206	Fogság	Iz32-33;Bár1-2;Péld11,17-20
207	1	207	Fogság	Iz34-36;Bár3-4;Péld11,21-24
208	1	208	Fogság	Iz37-38;Bár5-6;Péld11,25-28
209	1	209	Fogság	Iz39-40;Ezek1;Péld11,29-31
210	1	210	Fogság	Iz41-42;Ezek2-3;Péld12,1-4
211	1	211	Fogság	Iz43-44;Ezek4-5;Péld12,5-8
212	1	212	Fogság	Iz45-46;Ezek6-7;Péld12,9-12
213	1	213	Fogság	Iz47-48;Ezek8-9;Péld12,13-16
214	1	214	Fogság	Iz49-50;Ezek10-11;Péld12,17-20
215	1	215	Fogság	Iz51-52;Ezek12-13;Péld12,21-24
216	1	216	Fogság	Iz53-54;Ezek14-15;Péld12,25-28
217	1	217	Fogság	Iz55-56;Ezek16;Péld13,1-4
218	1	218	Fogság	Iz57-58;Ezek17-18;Péld13,5-8
219	1	219	Fogság	Iz59-60;Ezek19;Péld13,9-12
220	1	220	Fogság	Iz61-62;Ezek20;Péld13,13-16
221	1	221	Fogság	Iz63-64;Ezek21-22;Péld13,17-20
222	1	222	Fogság	Iz65;Ezek23-24;Péld13,21-25
223	1	223	Fogság	Iz66;Ezek25-26;Péld14,1-4
224	1	224	Fogság	Jer1;Ezek27;Péld14,5-8
225	1	225	Fogság	Jer2;Ezek28;Péld14,9-12
226	1	226	Fogság	Jer3;Ezek29-30;Péld14,13-16
227	1	227	Fogság	Jer4;Ezek31-32;Péld14,17-20
228	1	228	Fogság	Jer5;Ezek33;Péld14,21-24
229	1	229	Fogság	Jer6;Ezek34-35;Péld14,25-28
230	1	230	Fogság	Jer7;Ezek36;Péld14,29-32
231	1	231	Fogság	Jer8;Ezek37-38;Péld14,33-35
232	1	232	Fogság	Jer9;Ezek39;Péld15,1-4
233	1	233	Fogság	Jer10-11;Ezek40;Péld15,5-8
234	1	234	Fogság	Jer12-13;Ezek41-42;Péld15,9-12
235	1	235	Fogság	Jer14-15;Ezek43-44;Péld15,13-16
236	1	236	Fogság	Jer16-17;Ezek45-46;Péld15,17-20
237	1	237	Fogság	Jer18-19;Ezek47-48;Péld15,21-24
238	1	238	Fogság	Jer20-21;Dán1-2;Péld15,25-28
239	1	239	Fogság	Jer22;Dán3;Péld15,29-33
240	1	240	Fogság	Jer23;Dán4-5;Péld16,1-4
241	1	241	Fogság	Jer24-25;Dán6-7;Péld16,5-8
242	1	242	Fogság	Jer26-27;Dán8-9;Péld16,9-12
243	1	243	Fogság	Jer28-29;Dán10-11;Péld16,13-16
244	1	244	Fogság	Jer30;Dán12-13;Péld16,17-20
245	1	245	Fogság	Jer31;Dán14;Péld16,21-24
246	1	246	Fogság	Jer32;Jud1-2;Péld16,25-28
247	1	247	Fogság	Jer33-34;Jud3-5;Péld16,29-33
248	1	248	Fogság	Jer35-36;Jud6-7;Péld17,1-4
249	1	249	Fogság	Jer37-38;Jud8-9;Péld17,5-8
250	1	250	Fogság	Jer39-40;Jud10-11;Péld17,9-12
251	1	251	Fogság	Jer41-42;Jud12-14;Péld17,13-16
252	1	252	Fogság	Jer43-44;Jud15-16;Péld17,17-20
253	1	253	Fogság	Jer45-46;Siral1;Péld17,21-24
254	1	254	Fogság	Jer47-48;Siral2;Péld18,1-4
255	1	255	Fogság	Jer49-50;Siral3;Péld18,5-8
256	1	256	Fogság	Jer51;Siral4-5;Péld18,9-12
257	1	257	Fogság	Jer52;Abd1;Péld18,13-16
258	1	258	Messiási fordulópont	Mt1-4;Péld18,17-20
259	1	259	Messiási fordulópont	Mt5-7;Péld18,21-24
260	1	260	Messiási fordulópont	Mt8-10;Péld19,1-4
261	1	261	Messiási fordulópont	Mt11-13;Péld19,5-8
262	1	262	Messiási fordulópont	Mt14-17;Péld19,9-12
263	1	263	Messiási fordulópont	Mt18-21;Péld19,13-16
264	1	264	Messiási fordulópont	Mt22-24;Péld19,17-20
265	1	265	Messiási fordulópont	Mt25-26;Péld19,21-24
266	1	266	Messiási fordulópont	Mt27-28;Péld19,25-29
267	1	267	Hazatérés	Ezd1-2;Agg1-2;Péld20,1-3
268	1	268	Hazatérés	Ezd3-4;Zak1-3;Péld20,4-7
269	1	269	Hazatérés	Ezd5-6;Zak4-6;Péld20,8-11
270	1	270	Hazatérés	Ezd7-8;Zak7-8;Péld20,12-15
271	1	271	Hazatérés	Ezd9-10;Zak9-11;Péld20,16-19
272	1	272	Hazatérés	Neh1-2;Zak12-13;Péld20,20-22
273	1	273	Hazatérés	Neh3;Zak14;Péld20,23-26
274	1	274	Hazatérés	Neh4-5;Eszt1-2;Péld20,27-30
275	1	275	Hazatérés	Neh6-7;Eszt3;Péld21,1-4
276	1	276	Hazatérés	Neh8;Eszt4;Péld21,5-8
277	1	277	Hazatérés	Neh9;Eszt5-7;Péld21,9-12
278	1	278	Hazatérés	Neh10;Eszt8;Péld21,13-16
279	1	279	Hazatérés	Neh11;Eszt9;Péld21,17-20
280	1	280	Hazatérés	Neh12;Eszt10;Péld21,21-24
281	1	281	Hazatérés	Neh13;Mal1-4;Péld21,25-28
282	1	282	Makkabeus felkelés	1Mak1;Sir1-3;Péld21,29-31
283	1	283	Makkabeus felkelés	1Mak2;Sir4-6;Péld22,1-4
284	1	284	Makkabeus felkelés	1Mak3;Sir7-9;Péld22,5-8
285	1	285	Makkabeus felkelés	1Mak4;Sir10-12;Péld22,9-12
286	1	286	Makkabeus felkelés	1Mak5;Sir13-15;Péld22,13-16
287	1	287	Makkabeus felkelés	1Mak6;Sir16-18;Péld22,17-21
288	1	288	Makkabeus felkelés	1Mak7;Sir19-21;Péld22,22-25
289	1	289	Makkabeus felkelés	1Mak8;Sir22-23;Péld22,26-29
290	1	290	Makkabeus felkelés	1Mak9;Sir24-25;Péld23,1-4
291	1	291	Makkabeus felkelés	1Mak10;Sir26-27;Péld23,5-8
292	1	292	Makkabeus felkelés	1Mak11;Sir28-29;Péld23,9-12
293	1	293	Makkabeus felkelés	1Mak12;Sir30-31;Péld23,13-16
294	1	294	Makkabeus felkelés	1Mak13;Sir32-33;Péld23,17-21
295	1	295	Makkabeus felkelés	1Mak14;Sir34-35;Péld23,22-25
296	1	296	Makkabeus felkelés	1Mak15;Sir36-37;Péld23,26-28
297	1	297	Makkabeus felkelés	1Mak16;Sir38-39;Péld23,29-35
298	1	298	Makkabeus felkelés	2Mak1;Sir40-41;Péld24,1-7
299	1	299	Makkabeus felkelés	2Mak2;Sir42-44;Péld24,8-9
300	1	300	Makkabeus felkelés	2Mak3;Sir45-46;Péld24,10-12
301	1	301	Makkabeus felkelés	2Mak4;Sir47-49;Péld24,13-16
302	1	302	Makkabeus felkelés	2Mak5;Sir50-51;Péld24,17-20
303	1	303	Makkabeus felkelés	2Mak6;Bölcs1-2;Péld24,21-26
304	1	304	Makkabeus felkelés	2Mak7;Bölcs3-4;Péld24,27-29
305	1	305	Makkabeus felkelés	2Mak8;Bölcs5-6;Péld24,30-34
306	1	306	Makkabeus felkelés	2Mak9;Bölcs7-8;Péld25,1-3
307	1	307	Makkabeus felkelés	2Mak10;Bölcs9-10;Péld25,4-7
308	1	308	Makkabeus felkelés	2Mak11;Bölcs11-12;Péld25,8-10
309	1	309	Makkabeus felkelés	2Mak12;Bölcs13-14;Péld25,11-14
310	1	310	Makkabeus felkelés	2Mak13;Bölcs15-16;Péld25,15-17
311	1	311	Makkabeus felkelés	2Mak14;Bölcs17-18;Péld25,18-20
312	1	312	Makkabeus felkelés	2Mak15;Bölcs19;Péld25,21-23
313	1	313	A Messiás beteljesedése	Lk1-2;Péld25,24-26
314	1	314	A Messiás beteljesedése	Lk3-5;Péld25,27-28
315	1	315	A Messiás beteljesedése	Lk6-8;Péld26,1-3
316	1	316	A Messiás beteljesedése	Lk9-10;Péld26,4-6
317	1	317	A Messiás beteljesedése	Lk11-12;Péld26,7-9
318	1	318	A Messiás beteljesedése	Lk13-16;Péld26,10-12
319	1	319	A Messiás beteljesedése	Lk17-19;Péld26,13-16
320	1	320	A Messiás beteljesedése	Lk20-22,38;Péld26,17-19
321	1	321	A Messiás beteljesedése	Lk22,39-24;Péld26,20-23
322	1	322	Az Egyház	ApCsel1;Rom1;Péld26,24-26
323	1	323	Az Egyház	ApCsel2;Rom2-3;Péld26,27-28
324	1	324	Az Egyház	ApCsel3;Rom4-5;Péld27,1-3
325	1	325	Az Egyház	ApCsel4;Rom6-7;Péld27,4-6
326	1	326	Az Egyház	ApCsel5;Rom8;Péld27,7-9
327	1	327	Az Egyház	ApCsel6;Rom9-10;Péld27,10-12
328	1	328	Az Egyház	ApCsel7;Rom11-12;Péld27,13-14
329	1	329	Az Egyház	ApCsel8;Rom13-14;Péld27,15-17
330	1	330	Az Egyház	ApCsel9;Rom15-16;Péld27,18-20
331	1	331	Az Egyház	ApCsel10;1Kor1-2;Péld27,21-22
332	1	332	Az Egyház	ApCsel11;1Kor3-4;Péld27,23-27
333	1	333	Az Egyház	ApCsel12;1Kor5-6;Péld28,1-3
334	1	334	Az Egyház	ApCsel13;1Kor7-8;Péld28,4-6
335	1	335	Az Egyház	ApCsel14;1Kor9-10;Péld28,7-9
336	1	336	Az Egyház	ApCsel15;1Kor11-12;Péld28,10-12
337	1	337	Az Egyház	ApCsel16;1Kor13-14;Péld28,13-15
338	1	338	Az Egyház	ApCsel17;1Kor15;Péld28,16-18
339	1	339	Az Egyház	ApCsel18;1Kor16;Péld28,19-21
340	1	340	Az Egyház	ApCsel19;2Kor1-2;Péld28,22-24
341	1	341	Az Egyház	ApCsel20;2Kor3-5;Péld28,25-28
342	1	342	Az Egyház	ApCsel21;2Kor6-8;Péld29,1-4
343	1	343	Az Egyház	ApCsel22;2Kor9-11;Péld29,5-7
344	1	344	Az Egyház	ApCsel23;2Kor12-13;Péld29,8-11
345	1	345	Az Egyház	ApCsel24;Gal1-3;Péld29,12-14
346	1	346	Az Egyház	ApCsel25;Gal4-6;Péld29,15-17
347	1	347	Az Egyház	ApCsel26;Ef1-3;Péld29,18-21
348	1	348	Az Egyház	ApCsel27;Ef4-6;Péld29,22-24
349	1	349	Az Egyház	ApCsel28;Fil1-2;Péld29,25-27
350	1	350	Az Egyház	Jak1-2;Fil3-4;Péld30,1-6
351	1	351	Az Egyház	Jak3-5;Kol1-2;Péld30,7-9
352	1	352	Az Egyház	1Pt1-2;Kol3-4;Péld30,10-14
353	1	353	Az Egyház	1Pt3-5;1Tessz1-3;Péld30,15-16
354	1	354	Az Egyház	2Pt1-3;1Tessz4-5;Péld30,17-19
355	1	355	Az Egyház	1Jn1-3;2Tessz1-3;Péld30,20-23
356	1	356	Az Egyház	1Jn4-5;1Tim1-3;Péld30,24-28
357	1	357	Az Egyház	2Jn,3Jn;1Tim4-6;Péld30,29-33
358	1	358	Az Egyház	Jud1;2Tim1-2;Péld31,1-7
359	1	359	Az Egyház	Jel1-3;2Tim3-4;Péld31,8-9
360	1	360	Az Egyház	Jel4-7;Tit1-3;Péld31,10-15
361	1	361	Az Egyház	Jel8-11;Filem1;Péld31,16-18
362	1	362	Az Egyház	Jel12-14;Zsid1-4;Péld31,19-22
363	1	363	Az Egyház	Jel15-17;Zsid5-8;Péld31,23-25
364	1	364	Az Egyház	Jel18-20;Zsid9-10;Péld31,26-29
365	1	365	Az Egyház	Jel21-22;Zsid11-13;Péld31,30-31
\.


--
-- Data for Name: kar_reading_plans; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_reading_plans (id, name, description) FROM stdin;
1	AscensionPress 365 napos terv	A Szentírás elolvasása 365 nap alatt.
\.


--
-- Data for Name: kar_synonyms; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_synonyms (id, created_at, updated_at, word, "group") FROM stdin;
1	2014-08-01 11:19:37	2014-08-01 11:19:37	Krisztus	1
2	2014-08-01 11:19:37	2014-08-01 11:19:37	Jézus	1
3	2014-08-01 11:19:37	2014-08-01 11:19:37	lajtorja	3
4	2014-08-01 11:19:37	2014-08-01 11:19:37	létra	3
5	2014-08-01 11:19:37	2014-08-01 11:19:37	mécses	5
6	2014-08-01 11:19:37	2014-08-01 11:19:37	lámpás	5
7	2014-08-01 11:19:37	2014-08-01 11:19:37	száznegyvennégyezer	7
8	2014-08-01 11:19:37	2014-08-01 11:19:37	144000	7
9	2014-08-01 11:19:37	2014-08-01 11:19:37	Melkisédek	9
10	2014-08-01 11:19:37	2014-08-01 11:19:37	Melkizedek	9
11	2014-08-01 11:19:37	2014-08-01 11:19:37	növekedett	11
12	2014-08-01 11:19:37	2014-08-01 11:19:37	gyarapodott	11
13	2014-08-01 11:19:37	2014-08-01 11:19:37	tálentum	13
14	2014-08-01 11:19:37	2014-08-01 11:19:37	talentum	13
15	2014-08-01 11:19:37	2014-08-01 11:19:37	jós	15
16	2014-08-01 11:19:37	2014-08-01 11:19:37	csillagfejtő	15
17	2014-08-01 11:19:37	2014-08-01 11:19:37	lemá szabaktáni	17
18	2014-08-01 11:19:37	2014-08-01 11:19:37	lama sabaktani	17
19	2014-08-01 11:19:37	2014-08-01 11:19:37	lamma szabaktani	17
20	2014-08-01 11:19:37	2014-08-01 11:19:37	lamá sabaktáni	17
21	2014-08-01 11:19:37	2014-08-01 11:19:37	bárány	21
22	2014-08-01 11:19:37	2014-08-01 11:19:37	juh	21
23	2014-08-01 11:19:37	2014-08-01 11:19:37	Juh-kapu	23
24	2014-08-01 11:19:37	2014-08-01 11:19:37	juhkapu	23
25	2014-08-01 11:19:37	2014-08-01 11:19:37	Betesda	25
26	2014-08-01 11:19:37	2014-08-01 11:19:37	Beteszda	25
27	2014-08-01 11:19:37	2014-08-01 11:19:37	Betesdának	25
28	2014-08-01 11:19:37	2014-08-01 11:19:37	Betezdának	25
29	2014-08-01 11:19:37	2014-08-01 11:19:37	Bethesda	25
30	2014-08-01 11:19:37	2014-08-01 11:19:37	Betezda	25
31	2014-08-01 11:19:37	2014-08-01 11:19:37	Nátánáel	31
32	2014-08-01 11:19:37	2014-08-01 11:19:37	nathanael	31
33	2014-08-01 11:19:37	2014-08-01 11:19:37	Káin	33
34	2014-08-01 11:19:37	2014-08-01 11:19:37	Kain	33
\.


--
-- Data for Name: kar_tdbook; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_tdbook (id, trans, name, abbrev, url, countch, oldtest) FROM stdin;
101	1	Teremtés könyve	Ter	Ter	49	1
102	1	Kivonulás könyve	Kiv	Kiv	40	1
103	1	Leviták könyve	Lev	Lev	27	1
104	1	Számok könyve	Szám	Szam	36	1
105	1	Második Törvénykönyv	MTörv	MTorv	34	1
106	1	Józsue könyve	Józs	Jozs	24	1
107	1	Bírák könyve	Bír	Bir	21	1
108	1	Rut könyve	Rut	Rut	4	1
109	1	Sámuel I. könyve	1Sám	1Sam	31	1
110	1	Sámuel II. könyve	2Sám	2Sam	24	1
111	1	Királyok I. könyve	1Kir	1Kir	22	1
112	1	Királyok II. könyve	2Kir	2Kir	25	1
113	1	Krónikák I. könyve	1Krón	1Kron	29	1
114	1	Krónikák II. könyve	2Krón	2Kron	36	1
115	1	Ezdrás könyve	Ezd	Ezd	10	1
116	1	Nehemiás könyve	Neh	Neh	13	1
117	1	Tóbiás könyve	Tób	Tob	14	1
118	1	Judit könyve	Jud	Jud	16	1
119	1	Eszter könyve	Esz	Esz	10	1
145	1	Makkabeusok I. könyve	1Mak	1Mak	16	1
146	1	Makkabeusok II. könyve	2Mak	2Mak	15	1
120	1	Jób könyve	Jób	Job	42	1
121	1	Zsoltárok könyve	Zsolt	Zsolt	150	1
122	1	Példabeszédek könyve	Péld	Peld	31	1
123	1	Prédikátor könyve	Préd	Pred	12	1
124	1	Énekek éneke	Én	En	8	1
125	1	Bölcsesség könyve	Bölcs	Bolcs	19	1
126	1	Sirák fia könyve	Sir	Sir	51	1
127	1	Izajás könyve	Iz	Iz	66	1
128	1	Jeremiás könyve	Jer	Jer	52	1
129	1	Siralmak könyve	Siral	Siral	51	1
130	1	Báruk könyve	Bár	Bar	6	1
131	1	Ezekiel könyve	Ez	Ez	48	1
132	1	Dániel könyve	Dán	Dan	14	1
133	1	Ozeás könyve	Oz	Oz	14	1
134	1	Joel könyve	Jo	Jo	4	1
135	1	Ámosz könyve	Ám	Am	9	1
136	1	Abdiás könyve	Abd	Abd	1	1
137	1	Jónás könyve	Jón	Jon	4	1
138	1	Mikeás könyve	Mik	Mik	7	1
139	1	Náhum könyve	Náh	Nah	3	1
140	1	Habakuk könyve	Hab	Hab	3	1
141	1	Szofoniás könyve	Szof	Szof	3	1
142	1	Aggeus könyve	Ag	Ag	2	1
143	1	Zakariás könyve	Zak	Zak	14	1
144	1	Malakiás könyve	Mal	Mal	3	1
201	1	Máté evangéliuma	Mt	Mt	28	0
202	1	Márk evangéliuma	Mk	Mk	16	0
203	1	Lukács evangéliuma	Lk	Lk	24	0
204	1	János evangéliuma	Jn	Jn	21	0
205	1	Apostolok Cselekedetei	ApCsel	ApCsel	28	0
206	1	Rómaiaknak írt levél	Róm	Rom	16	0
207	1	Korintusiaknak írt I. levél	1Kor	1Kor	16	0
208	1	Korintusiaknak írt II. levél	2Kor	2Kor	13	0
209	1	Galatáknak írt levél	Gal	Gal	6	0
210	1	Efezusiaknak írt levél	Ef	Ef	6	0
211	1	Filippieknek írt levél	Fil	Fil	4	0
212	1	Kolosszeieknek írt levél	Kol	Kol	4	0
213	1	Tesszalonikaiaknak írt I. levél	1Tessz	1Tessz	5	0
214	1	Tesszalonikaiaknak írt II. levél	2Tessz	2Tessz	3	0
215	1	Timóteusnak írt I. levél	1Tim	1Tim	6	0
216	1	Timóteusnak írt II. levél	2Tim	2Tim	4	0
217	1	Titusznak írt levél	Tit	Tit	3	0
218	1	Filemonnak írt levél	Filem	Filem	1	0
219	1	Zsidóknak írt levél	Zsid	Zsid	13	0
220	1	Jakab levele	Jak	Jak	5	0
221	1	Péter I. levele	1Pt	1Pt	5	0
222	1	Péter II. levele	2Pt	2Pt	3	0
223	1	János I. levele	1Jn	1Jn	5	0
224	1	János II. levele	2Jn	2Jn	1	0
225	1	János III. levele	3Jn	3Jn	1	0
226	1	Júdás levele	Júd	Judas	16	0
227	1	Jelenések könyve	Jel	Jel	22	0
101	2	Mózes első könyve	1Móz	1Moz	50	1
102	2	Mózes második könyve	2Móz	2Moz	40	1
103	2	Mózes harmadik könyve	3Móz	3Moz	27	1
104	2	Mózes negyedik könyve	4Móz	4Moz	36	1
105	2	Mózes ötödik könyve	5Móz	5Moz	34	1
106	2	Józsué könyve	Józs	Jozs	24	1
107	2	A bírák könyve	Bír	Bir	21	1
108	2	Ruth könyve	Ruth	Ruth	4	1
109	2	Sámuel első könyve	1Sám	1Sam	31	1
110	2	Sámuel második könyve	2Sám	2Sam	24	1
111	2	A királyok első könyve	1Kir	1Kir	22	1
112	2	A királyok második könyve	2Kir	2Kir	25	1
113	2	A krónikák első könyve	1Krón	1Kron	29	1
114	2	A krónikák második könyve	2Krón	2Kron	36	1
115	2	Ezsdrás könyve	Ezsd	Ezsd	10	1
116	2	Nehémiás könyve	Neh	Neh	13	1
119	2	Eszter könyve	Eszt	Eszt	10	1
120	2	Jób könyve	Jób	Job	42	1
121	2	A zsoltárok könyve	Zsolt	Zsolt	150	1
122	2	A példabeszédek könyve	Péld	Peld	31	1
123	2	A prédikátor könyve	Préd	Pred	12	1
124	2	Énekek éneke	Énekek	Enekek	8	1
127	2	Ézsaiás próféta könyve	Ézs	Ezs	66	1
128	2	Jeremiás próféta könyve	Jer	Jer	52	1
129	2	Jeremiás siralmai	Jsir	Jsir	5	1
131	2	Ezékiel próféta könyve	Ez	Ez	48	1
132	2	Dániel próféta könyve	Dán	Dan	12	1
133	2	Hóseás próféta könyve	Hós	Hos	14	1
134	2	Jóel próféta könyve	Jóel	Joel	4	1
135	2	Ámósz próféta könyve	Ám	Am	9	1
136	2	Abdiás próféta könyve	Abd	Abd	1	1
137	2	Jónás próféta könyve	Jón	Jon	4	1
138	2	Mikeás próféta könyve	Mik	Mik	7	1
139	2	Náhum próféta könyve	Náh	Nah	3	1
140	2	Habakuk próféta könyve	Hab	Hab	3	1
141	2	Zofóniás próféta könyve	Zof	Zof	3	1
142	2	Haggeus próféta könyve	Hag	Hag	2	1
143	2	Zakariás próféta könyve	Zak	Zak	14	1
144	2	Malakiás próféta könyve	Mal	Mal	3	1
201	2	Máté evangéliuma	Mt	Mt	28	0
202	2	Márk evangéliuma	Mk	Mk	16	0
203	2	Lukács evangéliuma	Lk	Lk	24	0
204	2	János evangéliuma	Jn	Jn	21	0
205	2	Az apostolok cselekedetei	ApCsel	ApCsel	28	0
206	2	Pál levele a rómaiakhoz	Róm	Rom	16	0
207	2	Pál első levele a korinthusiakhoz	1Kor	1Kor	16	0
208	2	Pál második levele a korinthusiakhoz	2Kor	2Kor	13	0
209	2	Pál levele a galatákhoz	Gal	Gal	6	0
210	2	Pál levele az efezusiakhoz	Ef	Ef	6	0
211	2	Pál levele a filippiekhez	Fil	Fil	4	0
212	2	Pál levele a kolosséiakhoz	Kol	Kol	4	0
213	2	Pál első levele a thesszalonikaiakhoz	1Thessz	1Thessz	5	0
214	2	Pál második levele a thesszalonikaiakhoz	2Thessz	2Thessz	3	0
215	2	Pál első levele Timóteushoz	1Tim	1Tim	6	0
216	2	Pál második levele Timóteushoz	2Tim	2Tim	4	0
217	2	Pál levele Tituszhoz	Tit	Tit	3	0
218	2	Pál levele Filemonhoz	Filem	Filem	1	0
219	2	A zsidókhoz írt levél	Zsid	Zsid	13	0
220	2	Jakab levele	Jak	Jak	5	0
221	2	Péter első levele	1Pt	1Pt	5	0
222	2	Péter második levele	2Pt	2Pt	3	0
223	2	János első levele	1Jn	1Jn	5	0
224	2	János második levele	2Jn	2Jn	1	0
225	2	János harmadik levele	3Jn	3Jn	1	0
226	2	Júdás levele	Jud	Jud	1	0
227	2	A jelenések könyve	Jel	Jel	22	0
101	3	Teremtés könyve	Ter	Ter	50	1
102	3	Kivonulás könyve	Kiv	Kiv	40	1
103	3	Leviták könyve	Lev	Lev	27	1
104	3	Számok könyve	Szám	Szam	36	1
105	3	Második törvénykönyv	MTörv	MTorv	34	1
106	3	Józsue könyve	Józs	Jozs	24	1
107	3	Bírák könyve	Bír	Bir	21	1
108	3	Rút könyve	Rút	Rut	4	1
109	3	Sámuel első könyve	1Sám	1Sam	31	1
110	3	Sámuel második könyve	2Sám	2Sam	24	1
111	3	Királyok első könyve	1Kir	1Kir	22	1
112	3	Királyok második könyve	2Kir	2Kir	25	1
113	3	A krónikák első könyve	1Krón	1Kron	29	1
114	3	Krónikák második könyve	2Krón	2Kron	36	1
115	3	Ezdrás könyve	Ezdr	Ezdr	10	1
116	3	Nehemiás könyve	Neh	Neh	13	1
117	3	Tóbiás könyve	Tób	Tób	14	1
118	3	Judit könyve	Judit	Judit	16	1
119	3	Eszter könyve	Eszt	Eszt	10	1
120	3	Jób könyve	Jób	Jób	42	1
121	3	A zsoltárok könyve	Zsolt	Zsolt	150	1
122	3	A példabeszédek könyve	Péld	Peld	31	1
123	3	A prédikátor könyve	Préd	Pred	12	1
124	3	Az énekek éneke	Én	En	8	1
125	3	A bölcsesség könyve	Bölcs	Bolcs	19	1
126	3	Jézus, sírák fiának könyve	Sír	Sir	51	1
127	3	Izajás könyve	Iz	Iz	66	1
128	3	Jeremiás könyve	Jer	Jer	52	1
129	3	Jeremiás siralmai	Siralm	Siralm	5	1
130	3	Báruk könyve	Bár	Bar	6	1
131	3	Ezekiel jövendölése	Ez	Ez	48	1
132	3	Dániel jövendölése	Dán	Dan	14	1
133	3	Ózeás jövendölése	Óz	Oz	14	1
134	3	Joel jövendölése	Jo	Jo	4	1
135	3	Ámosz jövendölése	Ám	Am	9	1
136	3	Abdiás jövendölése	Abd	Abd	1	1
137	3	Jónás jövendölése	Jón	Jon	4	1
138	3	Mikeás jövendölése	Mik	Mik	7	1
139	3	Náhum jövendölése	Náh	Nah	3	1
140	3	Habakuk jövendölése	Hab	Hab	3	1
141	3	Szofoniás jövendölése	Szof	Szof	3	1
142	3	Aggeus jövendölése	Agg	Agg	2	1
143	3	Zakariás jövendölése	Zak	Zak	14	1
144	3	Malakiás jövendölése	Mal	Mal	3	1
145	3	A makkabeusok első könyve	1Makk	1Makk	16	1
146	3	A makkabeusok második könyve	2Makk	2Makk	15	1
201	3	Evangélium Máté szerint	Mt	Mt	28	0
202	3	Evangélium Márk szerint	Mk	Mk	16	0
203	3	Evangélium Lukács szerint	Lk	Lk	24	0
204	3	Evangélium János szerint	Jn	Jn	21	0
205	3	Az apostolok cselekedetei	Csel	Csel	28	0
206	3	A rómaiaknak írt levél	Róm	Rom	16	0
207	3	Első levél a korintusiaknak	1Kor	1Kor	16	0
208	3	Második levél a korintusiaknak	2Kor	2Kor	13	0
209	3	Levél a galatáknak	Gal	Gal	6	0
210	3	Levél az efezusiaknak	Ef	Ef	6	0
211	3	Levél a filippieknek	Fil	Fil	4	0
212	3	Levél a kolosszeieknek	Kol	Kol	4	0
213	3	Első levél a tesszalonikieknek	1Tessz	1Tessz	5	0
214	3	Második levél a tesszalonikieknek	2Tessz	2Tessz	3	0
215	3	Első levél timóteusnak	1Tim	1Tim	6	0
216	3	Második levél timóteusnak	2Tim	2Tim	4	0
217	3	Levél títusznak	Tit	Tit	3	0
218	3	Levél filemonnak	Filem	Filem	1	0
219	3	Levél a zsidóknak	Zsid	Zsid	13	0
220	3	Jakab levele	Jak	Jak	5	0
221	3	Péter első levele	1Pét	1Pet	5	0
222	3	Péter második levele	2Pét	2Pet	3	0
223	3	János első levele	1Ján	1Jan	5	0
224	3	János második levele	2Ján	2Jan	1	0
225	3	János harmadik levele	3Ján	3Jan	1	0
226	3	Júdás levele	Júdás	Judas	1	0
227	3	János jelenései	Jel	Jel	22	0
142	4	Aggeus próféta könyve	Agg	Agg	2	1
101	4	Mózes első könyve a teremtésről	1Móz	1Moz	50	1
102	4	Mózes második könyve a zsidóknak Égyiptomból kijöveteléről	2Móz	2Moz	40	1
103	4	Mózes harmadik könyve a Léviták egyházi szolgálatáról	3Móz	3Moz	27	1
104	4	Mózes negyedik könyve az Izráeliták megszámlálásáról való könyv	4Móz	4Moz	36	1
105	4	Mózes ötödik könyve a törvény summája	5Móz	5Moz	34	1
106	4	Józsué könyve	Józs	Jozs	24	1
107	4	Birák könyve	Bir	Bir	21	1
108	4	Ruth könyve	Ruth	Ruth	4	1
109	4	Sámuel első könyve	1Sám	1Sam	31	1
110	4	Sámuel második könyve	2Sám	2Sam	24	1
111	4	A királyokról írt I. könyv	1Kir	1Kir	22	1
112	4	A királyokról írt II. könyv	2Kir	2Kir	25	1
113	4	Krónika I. könyve	1Krón	1Kron	29	1
114	4	Krónika II. könyve	2Krón	2Kron	36	1
115	4	Eszdrás könyve	Ezsdr	Ezsdr	10	1
116	4	Nehémiás könyve	Neh	Neh	13	1
119	4	Eszter könyve	Eszt	Eszt	10	1
120	4	Jób könyve	Jób	Job	42	1
121	4	Zsoltárok könyve	Zsolt	Zsolt	150	1
122	4	Bölcs Salamonnak példabeszédei	Péld	Peld	31	1
123	4	A prédikátor Salamon könyve	Préd	Pred	12	1
124	4	Salamon énekek éneke	ÉnekÉn	EnekEn	8	1
127	4	Ésaiás próféta könyve	Ésa	Esa	66	1
128	4	Jeremiás próféta könyve	Jer	Jer	52	1
129	4	Jeremiás siralmai	Sir	Sir	5	1
131	4	Ezékiel próféta könyve	Ezék	Ezek	48	1
132	4	Dániel próféta könyve	Dán	Dan	12	1
133	4	Hóseás próféta könyve	Hós	Hos	14	1
134	4	Jóel próféta könyve	Jóel	Joel	3	1
135	4	Ámos próféta könyve	Ámós	Amos	9	1
136	4	Abdiás próféta könyve	Abd	Abd	1	1
137	4	Jónás próféta könyve	Jón	Jon	4	1
138	4	Mikeás próféta könyve	Mik	Mik	7	1
139	4	Náhum próféta könyve	Náh	Nah	3	1
140	4	Habakuk próféta könyve	Hab	Hab	3	1
141	4	Sofóniás próféta könyve	Sof	Sof	3	1
143	4	Zakariás próféta könyve	Zak	Zak	14	1
144	4	Malakiás próféta könyve	Malak	Malak	4	1
201	4	A Máté írása szerint való szent evangyéliom	Mát	Mat	28	0
202	4	A Márk írása szerint való szent evangyéliom	Márk	Mark	16	0
203	4	A Lukács írása szerint való szent evangyéliom	Luk	Luk	24	0
204	4	A János írása szerint való szent evangyéliom	Ján	Jan	21	0
205	4	Az apostolok cselekedetei 	Csel	Csel	28	0
206	4	Pál apostolnak a rómabeliekhez írt levele	Róm	Rom	16	0
207	4	Pál apostolnak a korinthusbeliekhez írt első levele	1Kor	1Kor	16	0
208	4	Pál apostolnak a korinthusbeliekhez írt második levele	2Kor	2Kor	13	0
209	4	Pál apostolnak a galátziabeliekhez írt levele	Gal	Gal	6	0
210	4	Pál apostolnak az efézusbeliekhez írt levele	Eféz	Efez	6	0
211	4	Pál apostolnak a filippibeliekhez írt levele	Fil	Fil	4	0
212	4	Pál apostolnak a kolossébeliekhez írt levele	Kol	Kol	4	0
213	4	Pál apostolnak a thessalonikabeliekhez írott első levele	1Thess	1Thess	5	0
214	4	Pál apostolnak a thessalonikabeliekhez írott második levele	2Thess	2Thess	3	0
215	4	Pál apostolnak Timótheushoz írt első levele	1Tim	1Tim	6	0
216	4	Pál apostolnak Timótheushoz írt második levele	2Tim	2Tim	4	0
217	4	Pál apostolnak Titushoz írt levele	Tit	Tit	3	0
218	4	Pál apostolnak Filemonhoz írt levele	Filem	Filem	1	0
219	4	A zsidókhoz írt levél	Zsid	Zsid	13	0
220	4	Jakab apostolnak közönséges levele	Jak	Jak	5	0
221	4	Péter apostolnak közönséges első levele	1Pét	1Pet	5	0
222	4	Péter apostolnak közönséges második levele	2Pét	2Pet	3	0
223	4	János apostolnak közönséges első levele	1Ján	1Jan	5	0
224	4	János apostolnak közönséges második levele	2Ján	2Jan	1	0
225	4	János apostolnak közönséges hamadik levele	3Ján	3Jan	1	0
226	4	Júdás apostolnak közönséges levele	Júd	Jud	1	0
227	4	János apostolnak mennyei jelenésekről való könyve	Jel	Jel	22	0
\.


--
-- Data for Name: kar_tdtrans; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_tdtrans (id, name, abbrev, denom, lang, copyright, publisher, publisherurl, reference) FROM stdin;
1	Szent István Társulati Biblia	SZIT	katolikus	magyar	A <a href=\\"http://szit.katolikus.hu\\">Szent István Társulat</a> Szentírás-Bizottságának fordítása, új bevezetőkkel és magyarázatokkal; sajtó alá rendezte Rózsa Huba. Használatának engedélye megújítva: 2013. április.	Szent István Társulat	http://szit.katolikus.hu	<i>BIBLIA ószövetségi és újszövetségi Szentírás</i>; Ford. Gál Ferenc, Gál József, Gyürki László, Kosztolányi István, Rosta Ferenc, Szénási Sándor, Tarjányi Béla; Szent István Társulat; Budapest; 2009; 3. kiadás.
2	Magyar Bibliatársulat újfordítású Bibliája	UF	protestáns	magyar	A <a href=\\"http://bibliatarsulat.hu/\\">Magyar Bibliatársulat</a> ideiglenes engedélyével. A szöveg revíziója a Bibliatársulatnál jelenleg zajlik, hivatalos változat <a href=\\"http://bibliatarsulat.hu/\\">ott látható</a>. Közeli tervünk a revideált szöveg teljes átvétele, amiről a Bibliatársulattal megegyeztünk.	Magyar Bibliatársulat	http://bibliatarsulat.hu	
3	Káldi-Neovulgáta	KNB	katolikus	magyar	A <a href=\\"http://www.biblia-tarsulat.hu/\\">Katolikus Bibliatársulat</a> engedélyével. Megújítva 2013. február.	Katolikus Bibliatársulat	http://www.biblia-tarsulat.hu	<i>Ó- és Újszövetségi Szentírás a Neovulgáta alapján.</i> A Káldi-féle szentírásfordítás felhasználásával készítette a Szent Jeromos Bibliatársulat Bibliafordító Munkaközössége: Fodor György, Gyürki László, Kocsis Imre, Kránitz Mihály, Mátéffy Balázs, Sza
4	Károli Gáspár revideált fordítása	KG	protestáns	magyar	A szöveg nem jogvédett, a digitalizációért a <a href=\\"http://www.theword.net/\\">The Word szoftver</a>  magyar moduljainak felelőse, <a href=\\"http://baranyilaszlozsolt.blogspot.hu/\\">Baranyi László Zsolt</a> vállalt szerkesztői felelősséget. Az esetleges digitalizációs hibákat kérjük jelezni!	The Word	http://theword.net	
\.


--
-- Data for Name: kar_tdverse; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_tdverse (trans, gepi, book_number, chapter, numv, tip, verse, verseroot, ido, id, book_id) FROM stdin;
\.


--
-- Data for Name: kar_translations; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_translations (id, created_at, updated_at, name, abbrev, "order", denom, lang, copyright, publisher, publisher_url, reference) FROM stdin;
1	2014-08-01 11:19:30	2014-08-01 11:19:30	Szent István Társulati Biblia	SZIT	3	katolikus	magyar	A <a href=\\"http://szit.katolikus.hu\\">Szent István Társulat</a> Szentírás-Bizottságának fordítása, új bevezetőkkel és magyarázatokkal; sajtó alá rendezte Rózsa Huba. Használatának engedélye megújítva: 2013. április.	Szent István Társulat	http://szit.katolikus.hu	<i>BIBLIA ószövetségi és újszövetségi Szentírás</i>; Ford. Gál Ferenc, Gál József, Gyürki László, Kosztolányi István, Rosta Ferenc, Szénási Sándor, Tarjányi Béla; Szent István Társulat; Budapest; 2009; 3. kiadás.
2	2014-08-01 11:19:30	2014-08-01 11:19:30	Magyar Bibliatársulat újfordítású Bibliája (1990)	UF	10	protestáns	magyar	Az 1990-es újfordítású Bibliát a <a href=\\"http://bibliatarsulat.hu/\\">Magyar Bibliatársulat</a> ideiglenes engedélyével publikáljuk.	Magyar Bibliatársulat	http://bibliatarsulat.hu	
3	2014-08-01 11:19:30	2014-08-01 11:19:30	Káldi-Neovulgáta	KNB	1	katolikus	magyar	A <a href=\\"http://www.biblia-tarsulat.hu/\\">Katolikus Bibliatársulat</a> engedélyével. Megújítva 2013. február.	Katolikus Bibliatársulat	http://www.biblia-tarsulat.hu	<i>Ó- és Újszövetségi Szentírás a Neovulgáta alapján.</i> A Káldi-féle szentírásfordítás felhasználásával készítette a Szent Jeromos Bibliatársulat Bibliafordító Munkaközössége: Fodor György, Gyürki László, Kocsis Imre, Kránitz Mihály, Mátéffy Balázs, Sza
4	2014-08-01 11:19:30	2014-08-01 11:19:30	Károli Gáspár revideált fordítása	KG	11	protestáns	magyar	A szöveg nem jogvédett, a digitalizációért a <a href=\\"http://www.theword.net/\\">The Word szoftver</a>  magyar moduljainak felelőse, <a href=\\"http://baranyilaszlozsolt.blogspot.hu/\\">Baranyi László Zsolt</a> vállalt szerkesztői felelősséget. Az esetleges digitalizációs hibákat kérjük jelezni!	The Word	http://theword.net	
5	2025-01-29 16:52:16	2025-01-29 16:52:16	Békés-Dalos Újszövetségi Szentírás	BD	5	katolikus	magyar	A <a href=\\"http://www.benceskiado.hu/\\">Bencés Kiadó</a> engedélyével. 2014. február.	Bencés Kiadó	http://www.benceskiado.hu/	
6	2025-01-29 16:52:16	2025-01-29 16:52:16	Magyar Bibliatársulat újfordítású Bibliája (2014)	RUF	9	protestáns	magyar	A 2014-es revidált Bibliát a <a href=\\"http://bibliatarsulat.hu/\\">Magyar Bibliatársulat</a> ideiglenes engedélyével publikáljuk. A hivatalos változat <a href=\\"http://abibliamindenkie.hu/\\">ott látható</a>.	Katolikus Bibliatársulat	http://bibliatarsulat.hu	
7	2025-01-29 16:52:17	2025-01-29 16:52:17	Simon Tamás László Újszövetség-fordítása	STL	4	katolikus	magyar	A Bencés Kiadó engedélyével (2017)	Bencés Kiadó	http://benceskiado.hu	
\.


--
-- Data for Name: kar_users; Type: TABLE DATA; Schema: public; Owner: homestead
--

COPY public.kar_users (id, name, email, password, remember_token, created_at, updated_at) FROM stdin;
\.


--
-- Name: kar_articles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: homestead
--

SELECT pg_catalog.setval('public.kar_articles_id_seq', 14, false);


--
-- Name: kar_book_abbrevs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: homestead
--

SELECT pg_catalog.setval('public.kar_book_abbrevs_id_seq', 205, false);


--
-- Name: kar_books_id_seq; Type: SEQUENCE SET; Schema: public; Owner: homestead
--

SELECT pg_catalog.setval('public.kar_books_id_seq', 476, false);


--
-- Name: kar_reading_plan_days_id_seq; Type: SEQUENCE SET; Schema: public; Owner: homestead
--

SELECT pg_catalog.setval('public.kar_reading_plan_days_id_seq', 366, false);


--
-- Name: kar_reading_plans_id_seq; Type: SEQUENCE SET; Schema: public; Owner: homestead
--

SELECT pg_catalog.setval('public.kar_reading_plans_id_seq', 2, false);


--
-- Name: kar_synonyms_id_seq; Type: SEQUENCE SET; Schema: public; Owner: homestead
--

SELECT pg_catalog.setval('public.kar_synonyms_id_seq', 35, false);


--
-- Name: kar_tdtrans_id_seq; Type: SEQUENCE SET; Schema: public; Owner: homestead
--

SELECT pg_catalog.setval('public.kar_tdtrans_id_seq', 5, false);


--
-- Name: kar_tdverse_id_seq; Type: SEQUENCE SET; Schema: public; Owner: homestead
--

SELECT pg_catalog.setval('public.kar_tdverse_id_seq', 1, false);


--
-- Name: kar_translations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: homestead
--

SELECT pg_catalog.setval('public.kar_translations_id_seq', 8, false);


--
-- Name: kar_users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: homestead
--

SELECT pg_catalog.setval('public.kar_users_id_seq', 1, false);


--
-- Name: kar_articles kar_articles_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_articles
    ADD CONSTRAINT kar_articles_pkey PRIMARY KEY (id);


--
-- Name: kar_book_abbrevs kar_book_abbrevs_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_book_abbrevs
    ADD CONSTRAINT kar_book_abbrevs_pkey PRIMARY KEY (id);


--
-- Name: kar_books kar_books_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_books
    ADD CONSTRAINT kar_books_pkey PRIMARY KEY (id);


--
-- Name: kar_reading_plan_days kar_reading_plan_days_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_reading_plan_days
    ADD CONSTRAINT kar_reading_plan_days_pkey PRIMARY KEY (id);


--
-- Name: kar_reading_plans kar_reading_plans_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_reading_plans
    ADD CONSTRAINT kar_reading_plans_pkey PRIMARY KEY (id);


--
-- Name: kar_synonyms kar_synonyms_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_synonyms
    ADD CONSTRAINT kar_synonyms_pkey PRIMARY KEY (id);


--
-- Name: kar_tdbook kar_tdbook_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_tdbook
    ADD CONSTRAINT kar_tdbook_pkey PRIMARY KEY (id, trans);


--
-- Name: kar_tdtrans kar_tdtrans_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_tdtrans
    ADD CONSTRAINT kar_tdtrans_pkey PRIMARY KEY (id);


--
-- Name: kar_tdverse kar_tdverse_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_tdverse
    ADD CONSTRAINT kar_tdverse_pkey PRIMARY KEY (id);


--
-- Name: kar_translations kar_translations_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_translations
    ADD CONSTRAINT kar_translations_pkey PRIMARY KEY (id);


--
-- Name: kar_users kar_users_pkey; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_users
    ADD CONSTRAINT kar_users_pkey PRIMARY KEY (id);


--
-- Name: kar_users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: Index 3; Type: INDEX; Schema: public; Owner: homestead
--

CREATE INDEX "Index 3" ON public.kar_tdbook USING btree (url, trans);


--
-- Name: tdverse_book_index; Type: INDEX; Schema: public; Owner: homestead
--

CREATE INDEX tdverse_book_index ON public.kar_tdverse USING btree (book_number);


--
-- Name: tdverse_gepi_index; Type: INDEX; Schema: public; Owner: homestead
--

CREATE INDEX tdverse_gepi_index ON public.kar_tdverse USING btree (gepi);


--
-- Name: kar_tdverse kar_tdverse_book_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: homestead
--

ALTER TABLE ONLY public.kar_tdverse
    ADD CONSTRAINT kar_tdverse_book_id_fkey FOREIGN KEY (book_id) REFERENCES public.kar_books(id);


--
-- PostgreSQL database dump complete
--

