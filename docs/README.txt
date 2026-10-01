MMC ERP Control Center v1.9.1 — popravak mobilnog konfiguratora i sigurnosno čišćenje
=====================================================================================
- Mobitel: panel "Vaša konfiguracija" se sada zatvara dugmetom ×, dodirom na tamnu pozadinu, tipkom Esc, a zatvara se i
  sam kad se pojavi obrazac s podacima. Prije toga je ostajao otvoren bez načina za zatvaranje.
- Klik na sidra unutar stranice (Paketi, Moduli, Saznaj više...) više ne dodaje stavke u historiju preglednika, pa
  dugme "Nazad" vraća na prethodnu stranicu.
- Iz paketa su uklonjene slike mmc-erp-login-bg.png i mmc-erp-v104-hero.png (imale su upisan email administratora),
  podaci klijenta (JIB/PDV/adresa) iz install.php i prečice "MIKRO" te .php-ini (putanja servera) iz izvornog koda.
  NA SERVERU OBRIŠI: public/mmc-erp-login-bg.png i public/mmc-erp-v104-hero.png (raspakiranje ZIP-a ih ne briše).
- Web pristup je zabranjen za docs/, mmc-update.json, *.sql, *.log, *.bak, *.zip; isključeno listanje direktorija;
  dodana zaglavlja X-Content-Type-Options i Permissions-Policy.

MMC ERP Control Center v1.9.0 — javni cjenik, konfigurator i upiti kupaca
=========================================================================
NOVO: javna stranica  https://erp.mmc.ba/cjenik  (bez prijave; ?page=cjenik radi uvijek kao rezervni link)
- Paketi (START/BUSINESS/PRO) s cijenama mjesečno / 6 mj. / 12 mj., implementacijom i uključenom podrškom.
- Svi moduli detaljno objašnjeni: čemu služi, šta dobijate, za koga je, primjer iz prakse, od čega ovisi
  i u kojem je paketu uključen ili koliko košta kao dodatni modul. Tekstove uređuješ u app/public_catalog.php.
- Konfigurator: djelatnost → paket → period plaćanja → dodatni moduli → vertikalni moduli (Servis, Moto DMS, Optika:
  "cijena po dogovoru") → usluge po satu (procjena) → podaci o poslovanju → podaci firme i pitanja.
  Cijena se prikazuje uživo (bez PDV-a, PDV, ukupno, prosjek mjesečno, ušteda). Modul koji traži viši paket
  ne može se označiti. Na mobitelu je ukupna cijena u donjoj traci s detaljima.
- Dio za knjigovođe: opis modula Centar knjigovođe, partnerski uslovi (provizija i bonus iz Cjenika), kalkulator zarade
  i poseban upit.
- CIJENE SE NE UPISUJU U KOD: čitaju se iz Cjenika u Control Centru (paketi, dodatni moduli, usluge). Promjena cijene u
  Cjeniku odmah vrijedi na javnoj stranici. Iznos upita server uvijek iznova izračunava iz baze (iz preglednika se
  ne prima nikakav iznos).

UPITI U CONTROL CENTRU (novi izbornik "Upiti", broj novih upita uz izbornik i obavijest na Nadzornoj ploči):
- Lista s filterom po statusu (Novi / U obradi / Ponuda poslana / Dobijeno / Izgubljeno / Spam) i pretragom.
- Detalj: podaci o firmi i kontaktu (email i telefon su linkovi), pitanja kupca, podaci o poslovanju, cijela
  konfiguracija s iznosima, interna bilješka, status, "Odgovori emailom".
- "Kreiraj ponudu iz upita": pravi nacrt u Ponudama (stavke iz konfiguracije, podaci kupca, vrijedi 30 dana).
- Obavijest emailom: svaki upit šalje email svim aktivnim administratorima (PHP mail()). Ako mail() na serveru ne radi,
  upit se ipak spremi i vidi u Control Centru (u detalju piše "email obavijest nije poslana").
  Opcije u config.local.php (nije dio paketa, dodaj ručno):  'notify_email' => 'a@mmc.ba,b@mmc.ba',
  'app_url' => 'https://erp.mmc.ba'.
- Tabela inquiries se sama kreira pri prvom otvaranju (baza se ne mijenja ručno).

ZAŠTITA JAVNOG OBRASCA: potpisan token (mora proći 4 s do 12 h), skriveno polje za botove, najviše 5 upita po IP adresi
na sat (storage/inquiry-throttle.json), validacija svih polja, izlaz se escape-a, saglasnost za obradu podataka.
Stvarna IP adresa se čita i iza nginx proxyja (X-Forwarded-For) — vrijedi i za blokadu prijave iz 1.8.0.

INSTALACIJA: raspakirati preko roota erp.mmc.ba (novo: app/public_site.php, app/public_page.php, app/public_catalog.php,
public/cjenik.css, public/cjenik.js; mijenjaju se index.php, app/core.php, app/maintenance.php, public/style.css,
.htaccess, config.php, mmc-update.json, docs/README.txt). config.local.php i storage/ se ne diraju.
Adresa /cjenik radi preko .htaccess (mod_rewrite). Ako u Plesku nginx sam poslužuje stranicu, u
Apache & nginx Settings › Additional nginx directives dodaj:
    location = /cjenik { try_files $uri /index.php?page=cjenik; }
Ako .htaccess na serveru nije bio od Control Centra, dodaj u njega:  RewriteEngine On  /  RewriteRule ^cjenik/?$ index.php?page=cjenik [L,QSA,NC]

MMC ERP Control Center v1.8.0 — profesionalni shell, prijava i odjava
=====================================================================
PRIJAVA:
- Nova stranica za prijavu: čisti raspored (slika lijevo, forma desno; na mobitelu samo forma).
  Uklonjena je pozadinska slika s "ugrađenom" lažnom formom preko koje se stvarna forma loše poravnavala,
  kao i gumbi koji ništa nisu radili ("Zapamti me", "Prijava s Microsoftom", "SSO").
- Prikaži/sakrij lozinku, upozorenje za Caps Lock, poruke nakon odjave i isteka sesije.
- Sigurnost: CSRF token na prijavi, blokada 15 min nakon 5 neuspjelih pokušaja (po IP adresi i po emailu,
  storage/login-throttle.json), audit LOGIN / LOGIN_FAILED / LOGOUT, automatski rehash lozinke.

ODJAVA I SESIJA:
- Odjava je uvijek vidljiva: u dnu sidebara (uz ime, email i ulogu) i u korisničkom izborniku gore desno.
  Prije je link bio izvan ekrana (sidebar nije imao scroll), a na mobitelu potpuno skriven.
- Odjava radi samo POST-om sa sigurnosnim tokenom (GET ?page=logout više ne odjavljuje).
- Sesija istječe nakon 60 min neaktivnosti i najkasnije 12 h nakon prijave.

POVEZANOST S FIRMAMA I CORE NADOGRADNJAMA:
- Svaka kartica/redak firme prikazuje stanje nadogradnje iz Update Centra: "Dostupna X" (STABILNA, kompatibilna
  s profilom/slugom/core_from firme), "Pilot X" ili "Ažurno". Paketi Control Centra se nikad ne nude firmama.
- KPI "CORE" prikazuje najvišu instaliranu Core verziju i broj firmi koje čekaju nadogradnju
  (prije: zadnji upisani red iz core_versions, što je mogao biti i paket Control Centra).
- "Nadogradnje" s kartice firme otvara Update Center filtriran na pakete kompatibilne s tom firmom.
- Uklonjen je fiksni gumb "+ MIKRO Retail"; nove firme se dodaju kroz "+ Nova firma" s profilom djelatnosti.
- Slug firme se provjerava (mala slova, brojke, crtica) i mora biti jedinstven.
- Statusi na bosanskom (Aktivna / Pauzirana / Održavanje), boja kartice prati status, datumi d.m.Y. H:i.
- Popravljen izobličen (okrugli) badge statusa na karticama firmi.

MOBITEL: sidebar postaje ladica (gumb ☰), odjava dostupna i u ladici i u gornjem izborniku.

INSTALACIJA: raspakirati preko roota erp.mmc.ba (mijenjaju se index.php, app/core.php, public/style.css,
config.php, mmc-update.json, docs/README.txt). config.local.php i storage/ se ne diraju. Baza se ne mijenja.

MMC ERP Control Center v1.7.1 — čišćenje roota, čišćenje paketa i sigurnosne popravke
=====================================================================================
ZAŠTO: u root erp.mmc.ba su greškom raspakirani ERP paketi. Prepisani su config.php, public/style.css
i mmc-update.json Control Centra (sučelje bez stila), a na domeni su se našle ERP skripte
(public/index.php, public/mmc-agent.php, app/v2.php ...).

NOVO:
- Samoizlječenje roota: kad administrator prvi put otvori Nadzornu ploču ili Sistem, CC briše
  TOČNO poznate ERP datoteke (52 datoteke; popis u app/maintenance.php) i prazne mape public/icons
  i public/assets. storage/, config.local.php i datoteke Control Centra se nikad ne diraju.
  Rezultat se prikazuje kao obavijest i bilježi u Auditu (CC_ROOT_CLEANUP).
- Upozorenje: ako se ERP datoteke ponovno pojave u rootu (ili je config.php prepisan), svaka stranica
  prikazuje upozorenje s gumbom "Ukloni ERP datoteke iz roota".
  ERP paketi se UČITAVAJU u Update Center, nikad se ne raspakiravaju u erp.mmc.ba.
- Update Center › "Očisti stare pakete": prvo pregled (dry-run) s veličinama, pa potvrda.
  Zadržava se: najnovija verzija po cilju/opsegu, paket u rolloutu koji nije završen i STABILNI paketi
  koje firme trenutno vide. Paketi korišteni u deploymentu/rolloutu ostaju kao arhivirani zapis
  (briše se samo ZIP). ZIP-ovi bez zapisa u bazi se brišu po izboru. Zapis u Auditu (PACKAGE_CLEANUP).
- AutoDMS/AQMC (moto) svugdje: jedan popis profila, sinonimi u manifestu (autodms, aqmc -> moto),
  predlošci modula u formi firme isti kao u Module Centru.

POPRAVCI:
- Verzija se čita samo iz config.php (prije ručno upisano v1.4.0/v1.5.0/v1.6.0 na raznim mjestima).
- Prijava: nova sesija nakon prijave, kolačić HttpOnly/SameSite, greška baze se ne prikazuje posjetitelju.
- Greške spremanja (npr. firma) sada se prikazuju na svakoj stranici, ne samo u Update Centru.
- Tajni ključ agenta se više ne ispisuje u formi (prazno = ostaje isti, "-" = briše).
- URL instance i agenta moraju biti http(s); status i fiskalni driver se provjeravaju.
- Rollout povijest prikazuje i rollout čiji je paket obrisan.
- install.php se ne može ponovno pokrenuti (storage/installed.lock), lozinka min. 12 znakova i na serveru.
- .htaccess: zabranjen web pristup config*.php, app/ i storage/ (Apache).

INSTALACIJA: ovaj ZIP se raspakira preko roota erp.mmc.ba (on vraća config.php, public/style.css i
mmc-update.json Control Centra). Zatim otvoriti Nadzornu ploču — čišćenje se izvrši samo.
Baza se ne mijenja. Preporuka za Plesk (nginx poslužuje statiku mimo .htaccess):
Apache & nginx Settings › Additional nginx directives:  location ^~ /storage/ { deny all; }

MMC ERP Control Center v1.7.0 — nadogradnja koju pokreće firma
==============================================================
NOVO:
- app/update_api.php: ERP firme (5.12.1+) pita Control Center postoji li nova verzija (samo paketi
  označeni kao STABILNA i kompatibilni s firmom) i preuzima je sam: Sistem › Nadogradnja.
- Firma se prijavljuje ključem svog MMC Agenta (isti tajni ključ kao za deployment), potpisano HMAC-om.
- Verzija firme se bilježi pri svakoj provjeri (Firme › CORE, Deployments).
- Postojeći rollout (push) iz Update Centra radi kao i do sada.

INSTALACIJA: raspakovati preko postojećeg roota erp.mmc.ba (mijenjaju se index.php, app/update_api.php,
mmc-update.json, docs/README.txt). Baza se ne mijenja.

MMC ERP Control Center v1.6.0 — Multi-tenant Update Manager

INSTALACIJA:
- ZIP je ROOT-relative za Control Center (erp.mmc.ba).
- Napraviti backup postojećeg roota/baze, zatim raspakirati preko postojećih datoteka.
- Nema ručne SQL migracije: agent_schema() automatski dodaje release_channel te rollout tablice.

NOVO:
- jedan update paket -> checkbox odabir više kompatibilnih firmi -> rollout;
- postojeća package_target_ok pravila ostaju obavezna za svaku firmu;
- backup/deploy/health se izvršavaju zasebno po firmi kroz postojeći agent;
- rezultat se bilježi po firmi; greška jedne firme ne prekida ostale;
- rollout povijest: ukupno / uspješno / greška / korisnik / vrijeme;
- release status paketa: Nacrt / Pilot / Stabilna;
- postojeći pojedinačni deployment i rollback ostaju sačuvani.

PREPORUČENI TOK:
1. Upload update ZIP-a jednom u Update Center.
2. Oznaka PILOT.
3. Označiti MIKRO i primijeniti.
4. Testirati.
5. Oznaka STABLE.
6. Označiti ostale kompatibilne firme i pokrenuti rollout.
7. Ako pojedina firma ima problem, koristiti postojeći Rollback uz njen deployment.
