<?php
declare(strict_types=1);
/*
 * MMC ERP Control Center 1.9.0 — opisi modula za javnu stranicu /cjenik.
 * Ključevi odgovaraju module_definitions() u core.php. Tekst uredi ovdje; cijene se NE upisuju ovdje
 * nego se čitaju iz Cjenika u Control Centru.
 *   tagline  – jedna rečenica (vidi se uvijek)
 *   what     – čemu modul služi
 *   features – šta tačno dobijate
 *   for      – kome je namijenjen
 *   example  – primjer iz svakodnevnog rada
 */
function pub_module_texts(): array {
  return [
    'core' => [
      'tagline' => 'Temelj sistema: korisnici, prava pristupa i postavke firme.',
      'what' => 'Core je osnova na kojoj rade svi ostali moduli. U njemu podešavate podatke o firmi, korisnike i njihove uloge, a sistem bilježi ko je i kada šta promijenio. Uključen je u svaki paket i ne može se isključiti.',
      'features' => ['Korisnici i uloge s pravima pristupa po modulima', 'Podaci firme: naziv, JIB, PDV broj, adresa', 'Audit trag: evidencija ko je šta i kada mijenjao', 'Sigurna prijava u sistem', 'Nadogradnje na nove verzije kroz MMC Control Center, uz sigurnosnu kopiju i provjeru rada prije i poslije'],
      'for' => 'Svaka firma koja koristi MMC ERP.',
      'example' => 'Vlasnik vidi sve podatke, a prodavač samo module koje mu vi dodijelite.',
    ],
    'partners' => [
      'tagline' => 'Kupci, dobavljači i kontakti na jednom mjestu.',
      'what' => 'Jedan zajednički imenik poslovnih partnera koji koriste svi ostali moduli. Partnera unesete jednom, a zatim ga birate u ponudama, računima, nabavi i finansijama bez ponovnog unosa.',
      'features' => ['Kupci i dobavljači u jedinstvenom imeniku', 'JIB, PDV broj, adresa i kontakt podaci', 'Kontakt osobe po partneru', 'Isti partner se koristi u prodaji, nabavi i finansijama', 'Pretraga i pregled svih dokumenata vezanih za partnera'],
      'for' => 'Svaka firma koja fakturiše kupcima ili kupuje od dobavljača.',
      'example' => 'Novi kupac se unese jednom, a odmah je dostupan za ponudu, račun i pregled potraživanja.',
    ],
    'inventory' => [
      'tagline' => 'Artikli, zalihe i kretanje robe pod kontrolom.',
      'what' => 'Evidencija artikala i stanja zaliha po skladištima. Svaki ulaz i izlaz robe ostaje zabilježen, pa u svakom trenutku znate šta imate na stanju i kako se stanje mijenjalo.',
      'features' => ['Katalog artikala sa šiframa i barkodovima', 'Jedno ili više skladišta', 'Trenutno stanje zaliha', 'Kartica artikla: pregled svih ulaza i izlaza', 'Automatsko ažuriranje zaliha iz nabave i prodaje'],
      'for' => 'Trgovine, veleprodaje, servisi i sve firme koje drže robu ili rezervne dijelove.',
      'example' => 'Nakon prodaje na blagajni stanje artikla se smanji samo, a u kartici artikla vidite tačno kada i na kojem dokumentu.',
    ],
    'purchasing' => [
      'tagline' => 'Od narudžbe dobavljaču do ulazne kalkulacije.',
      'what' => 'Vodi cijeli tok nabave: narudžba dobavljaču, prijem robe, ulazni dokument i kalkulacija cijene. Nakon prijema zalihe se ažuriraju, a ulazni dokument je spreman za knjigu ulaznih faktura.',
      'features' => ['Narudžbe dobavljačima', 'Prijem robe na skladište', 'Ulazni dokumenti (fakture dobavljača)', 'Kalkulacije: nabavna cijena, marža i prodajna cijena', 'Veza sa zalihama i PDV evidencijom'],
      'for' => 'Firme koje kupuju robu za preprodaju ili materijal za rad.',
      'example' => 'Stigne faktura dobavljača: unesete prijem, kalkulacija izračuna prodajnu cijenu, a stanje skladišta se uvećava.',
    ],
    'sales' => [
      'tagline' => 'Ponude, otpremnice i računi u istom toku.',
      'what' => 'Prodajni dokumenti od ponude do računa. Podaci o kupcu i artiklima se preuzimaju iz partnera i skladišta, a izdani račun automatski smanjuje zalihe i ulazi u evidencije.',
      'features' => ['Ponude kupcima', 'Otpremnice', 'Računi', 'Izbor kupca i artikala iz postojećih šifarnika', 'Povezanost sa zalihama i PDV evidencijom'],
      'for' => 'Sve firme koje izdaju račune, ponude ili otpremnice.',
      'example' => 'Ponuda koju kupac prihvati pretvara se u račun bez ponovnog unosa stavki.',
    ],
    'pos' => [
      'tagline' => 'Brza maloprodaja s fiskalnim računom.',
      'what' => 'Blagajna za maloprodajni objekt: brz unos artikala, naplata i izdavanje fiskalnog računa na fiskalnom uređaju. Promet s blagajne ulazi u zalihe i evidenciju prodaje.',
      'features' => ['Brza prodaja artikala na blagajni', 'Fiskalni računi — podržani uređaji Datecs i Tring', 'Prodaja se odmah skida sa zaliha', 'Promet s blagajne ulazi u knjigu izlaznih faktura', 'Dnevni pregled prometa'],
      'for' => 'Trgovine, radnje, optike i svi koji prodaju krajnjim kupcima.',
      'example' => 'Prodavač skenira artikal, naplati, a fiskalni uređaj izda račun; stanje i dnevni promet se ažuriraju odmah.',
    ],
    'cash' => [
      'tagline' => 'Blagajničko poslovanje: uplate, isplate i dnevni promet.',
      'what' => 'Evidencija gotovinskih uplata i isplata te stanja blagajne. Uz pregled dnevnog prometa uvijek znate koliko novca bi trebalo biti u blagajni.',
      'features' => ['Uplate i isplate gotovine', 'Stanje blagajne', 'Pregled dnevnog prometa', 'Povezanost sa prodajom i finansijama'],
      'for' => 'Firme koje posluju s gotovinom.',
      'example' => 'Na kraju dana uporedite stanje u sistemu sa stvarnim stanjem blagajne.',
    ],
    'finance' => [
      'tagline' => 'Obaveze, potraživanja i kontrola poslovanja.',
      'what' => 'Finansijski pregledi koji pokazuju ko vama duguje i kome vi dugujete. Dokumenti iz prodaje i nabave ulaze automatski, pa ne prepisujete podatke.',
      'features' => ['Pregled potraživanja od kupaca', 'Pregled obaveza prema dobavljačima', 'Finansijski pregledi po periodu', 'Automatske sheme knjiženja', 'Osnova za PDV evidencije i rad s knjigovođom'],
      'for' => 'Vlasnike i finansijske radnike kojima treba jasna slika likvidnosti.',
      'example' => 'Jednim pregledom vidite koji računi kupaca su dospjeli, a koje fakture dobavljača treba platiti.',
    ],
    'vat' => [
      'tagline' => 'Knjige KUF/KIF i PDV evidencije bez ručnog prepisivanja.',
      'what' => 'Knjiga ulaznih (KUF) i izlaznih (KIF) faktura i evidencija PDV-a. Podaci dolaze iz nabave, prodaje i blagajne, pa su evidencije spremne za obračun i razgovor s knjigovođom.',
      'features' => ['Knjiga ulaznih faktura (KUF)', 'Knjiga izlaznih faktura (KIF)', 'Evidencija PDV-a po stopama', 'Automatski prenos iz nabave, prodaje i blagajne', 'Pregled po periodu'],
      'for' => 'Firme u sistemu PDV-a.',
      'example' => 'Na kraju mjeseca KUF i KIF su već popunjeni — ostaje samo provjera.',
    ],
    'uio' => [
      'tagline' => 'Priprema i kontrola elektronskih evidencija za UIO.',
      'what' => 'Priprema podataka za elektronske evidencije (eKUF/eKIF) prema UIO i provjera ispravnosti prije predaje. Greške se uoče u sistemu, a ne tek nakon odbijene prijave.',
      'features' => ['Priprema eKUF i eKIF evidencija', 'Validator: provjera podataka prije predaje', 'Zasnovano na podacima iz KUF/KIF', 'Manje ručnog rada i manje grešaka'],
      'for' => 'Firme koje predaju elektronske evidencije Upravi za indirektno oporezivanje.',
      'example' => 'Validator vas upozori na neispravan podatak prije nego što pošaljete evidenciju.',
    ],
    'mobile' => [
      'tagline' => 'Mobilni rad po ulozi: skeniranje, prijem, izdavanje i inventura.',
      'what' => 'Radna površina prilagođena telefonu i ručnom skeneru. Zaposleni na terenu ili u skladištu dobijaju samo akcije koje im trebaju, bez rada za računarom.',
      'features' => ['Radni prikaz prilagođen ulozi korisnika', 'Skeniranje artikala', 'Prijem i izdavanje robe', 'Mobilna inventura', 'Brze akcije za svakodnevne zadatke'],
      'for' => 'Skladištare, prodavce i radnike koji rade van kancelarije.',
      'example' => 'Skladištar telefonom skenira robu pri prijemu, a zaliha je ažurirana prije nego što vozač ode.',
    ],
    'warehouse' => [
      'tagline' => 'Napredno skladište: barkod/QR, lokacije i kompletiranje narudžbi.',
      'what' => 'Za skladišta gdje je bitno tačno znati gdje se roba nalazi. Modul vodi ulaznu kontrolu, smještaj na police, kompletiranje narudžbi i inventuru s mobilnog uređaja.',
      'features' => ['Barkod i QR kodovi', 'Ulazna kontrola robe (inbound)', 'Smještaj na lokacije (put-away)', 'Kompletiranje narudžbi (picking)', 'Lokacije i police (bin lokacije)', 'Mobilna inventura'],
      'for' => 'Veleprodaje i firme s većim skladištem i više artikala.',
      'example' => 'Radnik dobije listu za kompletiranje, skenira police i artikle, a sistem pokaže greške odmah.',
    ],
    'analytics' => [
      'tagline' => 'Pregled poslovanja za upravu: KPI, trendovi i upozorenja.',
      'what' => 'Management pregled ključnih pokazatelja na jednom mjestu. Umjesto traženja po izvještajima, vidite prodaju, trendove i upozorenja koja zahtijevaju pažnju.',
      'features' => ['KPI dashboardi za upravu', 'Trendovi kroz vrijeme', 'Upozorenja o odstupanjima', 'Management pregled poslovanja'],
      'for' => 'Vlasnike i menadžere koji žele brzu sliku poslovanja.',
      'example' => 'Ujutro otvorite dashboard i vidite kako prodaja stoji u odnosu na prošli period.',
    ],
    'accountant' => [
      'tagline' => 'Portal za saradnju firme i knjigovođe.',
      'what' => 'Odvojeni prostor u kojem knjigovođa dobija podatke firme na pregled: KUF/KIF i PDV priprema, razmjena dokumenata, kontrole, napomene i korekcije. Nema slanja Excel tabela i mailova naprijed-nazad.',
      'features' => ['Pregled KUF/KIF i pripreme PDV-a za knjigovođu', 'Razmjena dokumenata između firme i knjigovođe', 'Kontrole i napomene uz podatke', 'Korekcije koje knjigovođa predlaže', 'Uključuje se neovisno o izabranom paketu'],
      'for' => 'Firme čiji knjigovođa radi na podacima iz MMC ERP-a, i knjigovodstvene agencije.',
      'example' => 'Knjigovođa ostavi napomenu uz pogrešan račun, a firma ga ispravi bez telefonskog poziva.',
    ],
    'service' => [
      'tagline' => 'Servisni nalozi, uređaji i statusi popravke.',
      'what' => 'Vodi servis od prijema uređaja do izdavanja: servisni nalog, dijagnostika, utrošeni dijelovi i status popravke. Kupac i radnik uvijek znaju u kojoj je fazi posao.',
      'features' => ['Servisni nalozi', 'Evidencija uređaja i klijenata', 'Dijagnostika', 'Dijelovi utrošeni na popravci', 'Statusi popravke'],
      'for' => 'Servise vozila, opreme i uređaja.',
      'example' => 'Otvorite nalog pri prijemu, dodate dijelove i dijagnostiku, a na kraju nalog postane račun.',
    ],
    'dms' => [
      'tagline' => 'Prodaja i evidencija vozila (Moto DMS).',
      'what' => 'Specijalizovan modul za prodavce vozila: evidencija vozila na stanju, prodaja i moto specifičnosti. Radi zajedno sa servisom, pa vozilo ima cijelu istoriju na jednom mjestu.',
      'features' => ['Evidencija vozila', 'Prodaja vozila', 'Moto specifični podaci', 'Povezanost sa servisom (Servis je uključen kao zavisnost)'],
      'for' => 'Moto i auto trgovine koje prodaju i servisiraju vozila.',
      'example' => 'Za vozilo vidite kada je prodato i sve servise koji su na njemu rađeni.',
    ],
    'optics' => [
      'tagline' => 'Radni tok optike: recepti, okviri, leće i narudžbe.',
      'what' => 'Modul prilagođen optičarskom radu: prijem recepta, izbor okvira i leća, narudžba i izdavanje naočala, uz redovnu prodaju i fiskalni račun.',
      'features' => ['Recepti klijenata', 'Okviri i leće', 'Narudžbe naočala', 'Prodaja i fiskalni račun na blagajni', 'Povezanost s partnerima i zalihama'],
      'for' => 'Optike i optičarske radnje.',
      'example' => 'Klijent donese recept, birate okvir i leće, kreirate narudžbu i naplatite na istoj blagajni.',
    ],
  ];
}

/* Često postavljana pitanja (javna stranica). */
function pub_faq(): array {
  return [
    ['Da li su cijene sa PDV-om?', 'Sve cijene na ovoj stranici su bez PDV-a. PDV (' . number_format(PUB_VAT, 0) . '%) je u konfiguratoru prikazan posebno.'],
    ['Šta se dešava kad pošaljem upit?', 'Upit stiže direktno našem timu zajedno sa konfiguracijom koju ste sastavili. Javit ćemo vam se sa ponudom ili pitanjima. Upit vas ni na šta ne obavezuje.'],
    ['Šta je implementacija?', 'Jednokratni trošak postavljanja sistema za vašu firmu: priprema instance, podešavanje modula i početna konfiguracija. Pretplata se naplaćuje za izabrani period.'],
    ['Koliko je uključeno podrške?', 'Svaki paket uključuje mjesečni broj minuta podrške (vidi paket). Dodatni rad naplaćuje se po satu prema cjeniku usluga.'],
    ['Kako funkcionišu nadogradnje?', 'Nove verzije isporučuju se centralno kroz MMC Control Center. Prije nadogradnje radi se sigurnosna kopija, nakon nje provjera rada, a po potrebi je moguć povrat na prethodnu verziju.'],
    ['Imam podatke u drugom programu. Može li se preuzeti?', 'Osnovni uvoz iz Excela je dio postavljanja. Za napredniju migraciju i čišćenje podataka izdvojena je usluga koja se naplaćuje po satu — možete je dodati u konfiguraciju.'],
    ['Podržavate li fiskalne uređaje?', 'Da, podržani su fiskalni uređaji Datecs i Tring.'],
  ];
}
