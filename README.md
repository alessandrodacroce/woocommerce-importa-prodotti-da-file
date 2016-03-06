# WooCommerce Plugin: Importazione e Aggiornamento di prodotti da file CSV
Plugin per woocommerce che permette di importare in modo automatico i prodotti o aggiornare quelli presenti da un file csv presente sul server o raggiungibile tramite un indirizzo internet pubblico. Il plugin può essere anche impostato tramite schedulazione dei lavori affinchè giornalmente esegua l'importazione e l'aggiornamento.

# Utilizzo e idea alla base del plugin
Il funzionamento del plugin è il seguente:
  1. Legge il file csv remoto e ne crea una copia in una cartella del server http://vostro_dominio/wp-content/uploads/woo_import_prodotti_da_file,
  2. Importa il file locale in una tabella ( tbl_importa_db ) creata precedentemente dal plugin
  3. Su questa tabella vengono eseguiti dei filtri sulle colonne Mrc / SttCat / Cat
  4. Successivamente viene eseguita una procedura che legge ogni singolo prodotto presente nella tbl_importa_db ed aggiorna una seconda tabella ( tbl_conversione ) andando ad aggiornare alcuni campi se il prodotto è già presente o, in caso contrario lo inserisce ex novo impostando lo stato in new
  5. Terminato il processo ci si deve collegare tramite phpmyadmin o altro strumento al db ed esaminare i nuovi record, cambiando lo stato in update e se necesario creando il prodotto di tipo variazione.
  6. Alla successiva esecuzione del plugin, verrà letta la tabella tbl_conversione ed i record con stato update andranno ad aggiornare i prodotti presenti nello store, se non presenti verranno inseriti, ma in stato di bozza.

## File csv
Il file csv da cui tutto ha inizio deve avere questa struttura
> N.B. La separazione tra una colonna ed un'altra deve essere fatta tramite TAB e non tramite il classico **;**

| **Nome campo** | **Tipo dati** | **Descrizione** |
|----------------|---------------|-----------------|
|ArtDsp|Intero|Articolo disponibile (0=NO 1=SI)|
|CodArt|Varchar (50)|Codice articolo o SKU|
|NomArt|Varchar (255)|Titolo del prodotto|
|NomArtExt|Text|Descrizione estesa dell'articolo|
|Cat|Varchar (30)|Categoria|
|SttCat|Varchar (60)|Sottocategoria|
|Tgl|Varchar (50)|Taglia|
|Col|Varchar (50)|Colore|
|DttArt|Text|Dettagli articolo separati dalla barra verticale |
|Mat|Varchar (100)|Materiale con cui è composto l'articolo|
|Mrc|Varchar (45)|Marca articolo (brand)|
|PrzIng|Double|Prezzo netto ingrosso - (formato 1234,56)|
|Sct|Int|Sconto|
|PrzCns|Double|Prezzo di vendita CONSIGLIATO - IVA compresa - (formato 1234,56)|
|PrzCnsSct|Double|Prezzo di vendita SCONTATO - IVA compresa - (formato 1234,56)|
|FotoMin|Text|URL file immagine (foto piccola thumbnail)|
|FotoMed|Text|URL file immagine (foto media per elenchi)|
|FotoMax|Text|URL file immagine (foto grande per dettaglio)|

Per l'esempio vedi il file **file_csv_esempio** contenuto in questo archivio.

## Configurazione del plugin 
Il plugin per poter funzionare ha bisogno di alcune informazioni, attualmente è possibile modificare queste informazioni solamente agendo direttamente sul file class.php, vediamo quali sono:

- $_file_csv_remote: Questa variabile deve contenere l'url al vostro file csv contenente i dati, ad esempio: http://www.mio_dominio.it/wp-login/mio_file_csv.csv
- $_admin_mail: In questa variabile si deve inserire la mail a cui il plugin invia il report dopo aver eseguito tutto il codice
- $_from_mail: Tramite questa variabile potete impostare il mittente della mail

## Funzionamento
Per poter eseguire il plugin basta collegarsi all'indirizzo http://www.tuo_dominio.com/?woo_import_product=1
La variabile woo_import_product farà scattare il plugin che eseguirà i passi visti sopra.

# FAQ
Per ogni richiesta / domanda o aggiornamento vi prego di contattarmi attraverso il mio sito web alessandrodacroce.it.
Ogni richiesta sarà valutata e cercherò di rispondere a tutte le domande, se si tratta di aggiornamento o aggiunta di specifiche se ritenute utili verranno aggiunte al plugin.

# Info dettagliate funzionamento plugin
A questo link http://www.alessandrodacroce.it/woocommerce-importare-prodotti-da-file-csv/ troverete una premessa sul funzionamento del plugin e di cosa è necessario
A questo link la pagina di riferimento del plugin stesso http://www.alessandrodacroce.it/progetto/plugin-woocommerce-importa-prodotti-da-file-csv/
