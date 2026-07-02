# Taranto-Viva.com — Funzionalità Principali

## Mappe Interattive Multi-Livello

Il cuore della piattaforma è un sistema di mappe interattive che integra tecnologie **Leaflet** e **MapLibre GL JS**. Le mappe supportano:

- Visualizzazione simultanea di molteplici livelli tematici (luoghi, immagini, eventi, percorsi, segnalazioni)
- Clustering intelligente dei marker per una lettura chiara anche ad alta densità di contenuti
- Controllo della visibilità per livello di zoom, che rivela i dettagli progressivamente man mano che l'utente si avvicina al territorio
- Mappe di sfondo multiple selezionabili, incluso il supporto a tile vettoriali in formato **PMTiles**
- Popup georiferiti con link diretti a Google Maps e Street View
- Mappe coropletiche per la visualizzazione di dati statistici aggregati per zona

---

## Geo Places — Luoghi Georeferenziati

Il catalogo dei **Geo Places** raccoglie punti di interesse, monumenti, infrastrutture, quartieri e spazi pubblici di Taranto e del suo territorio. Ogni luogo è:

- Posizionato con precisione geografica sulla mappa
- Classificato per **tipo** (monumento, chiesa, museo, ospedale, biblioteca, castello, fontana, ponte, ecc.) e **categoria** (luoghi pubblici, religiosi, militari, infrastrutture, percorsi ciclabili, zone e quartieri, ecc.)
- Arricchito con descrizioni, immagini, link a Google Maps e Street View
- Collegato ad altri contenuti correlati attraverso relazioni semantiche

---

## Geo Images — Archivio Fotografico Georeferenziato

Le **Geo Images** sono fotografie automaticamente posizionate sulla mappa grazie alle coordinate GPS incorporate nei metadati EXIF del file immagine. Il sistema:

- Estrae latitudine, longitudine e data di scatto direttamente dai file fotografici
- Genera automaticamente un contenuto georeferenziato per ogni immagine importata
- Organizza le fotografie in un archivio navigabile per posizione, data e progetto
- Mostra le immagini come marker sulla mappa con popup fotografici

---

## Segnalazioni Territoriali

Il modulo di **Segnalazione Territoriale** è uno strumento di partecipazione civica che consente di documentare condizioni, criticità e punti di interesse sul territorio. Ogni segnalazione può esprimere una valutazione qualitativa — positiva, negativa o molto negativa — e viene georeferenziata e resa visibile sulla mappa pubblica, contribuendo a costruire una memoria condivisa dello stato del territorio.

---

## Eventi

Il sistema degli **Eventi** permette di mappare appuntamenti, iniziative e attività culturali o civiche agganciandoli alla loro specifica localizzazione geografica, rendendo esplicito il legame tra ciò che accade e il luogo in cui accade.

---

## Percorsi Ciclabili — Ciclovia degli Acquedotti

La piattaforma ospita la mappatura dettagliata dei tracciati della **Ciclovia degli Acquedotti**, uno dei percorsi cicloturistici più significativi del Sud Italia. I tracciati sono importati da fonti ufficiali, visualizzati come polilinee interattive sulla mappa e navigabili per tratta.

---

## Progetti e Namespace

I contenuti sono organizzati in **Progetti** (namespace), spazi tematici o comunitari che raccolgono luoghi, immagini ed eventi attorno a un tema o a un'iniziativa specifica. Tra i progetti attivi:

- **Taranto Viva** — il catalogo generale della città
- **Vaganti** — documentazione di camminate ed esplorazioni urbane e periurbane
- **Copenhagen Discovery** — confronto tra il territorio tarantino e best practice internazionali
- **Ciclovia Acquedotti AQP** — percorsi cicloturistici nella provincia

---

## Ricerca Istantanea

La piattaforma integra un motore di **ricerca full-text istantanea** basato su **Typesense**, con un'interfaccia React che restituisce risultati in tempo reale mentre l'utente digita, con filtri per tipo di contenuto, categoria e progetto.

---

## Assistente AI — RAG su Base Territoriale

Un modulo sperimentale di **Intelligenza Artificiale** consente di interrogare la base di conoscenza della piattaforma in linguaggio naturale. Basato su architettura **RAG (Retrieval-Augmented Generation)**, l'assistente utilizza i contenuti georeferenziati della piattaforma come contesto per rispondere a domande sul territorio, sui luoghi e sulla storia di Taranto.

---

## Multilingua e Traduzione

La piattaforma supporta la gestione dei contenuti in più lingue. Le traduzioni sono gestite attraverso un flusso integrato che sfrutta servizi di traduzione automatica, con revisione umana, per rendere i contenuti accessibili a un pubblico internazionale.

---

## Tecnologia Open Source

L'intera infrastruttura è costruita su componenti Open Source:

| Componente | Tecnologia |
|---|---|
| CMS | Drupal |
| Mappe vettoriali interattive | MapLibre GL JS |
| Mappe Leaflet | Leaflet + plugin ecosystem |
| Ricerca | Typesense |
| Frontend dinamico | React + Vite |
| Dati geografici | GeoJSON, GeoField, GeoPHP |
| Tile vettoriali | PMTiles |
| Geocoding | Geocoder (multi-provider) |