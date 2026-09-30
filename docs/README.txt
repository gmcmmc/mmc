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
