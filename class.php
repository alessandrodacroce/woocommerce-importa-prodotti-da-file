<?php
require_once ( $_SERVER['DOCUMENT_ROOT'] . "wp-load.php" );

if ( ! class_exists( 'WC_Import_Product' ) ) {
	class WC_Import_Product {
		
		/*
		 *  path del file csv presente sul serve dove è eseguito questo programma
		 */
		private $_file_csv_locale;
        /*
         *  path del file di origine
         */
        private $_file_csv_remote = ''; // link al file csv contenente gli aggiornamenti	
		/*
		 *  la mail a cui far giungere le notifiche
		 */
		private $_admin_mail='tua_mail@tuo_dominio.com'; 	
		/*
		 *  header della mail
		 */
		private $_from_mail = 'Nome Cognome <tua_mail@tuo_dominio.com>';
		
		/*
		 * array che contiene il log generato dall'esecuzione del programma
		 * più semplice poi gestirlo in fase di creazione del report 
		 */
		private $_log;		
		
		/*
		 * array che contiene i vari puntamenti assoluti e uri della cartella wp_contents di wp
		 */		
		// private $_upload_dir;
		
		private $_tbl_importa_db = '' ;
        private $_tbl_conversione = '' ;
		
		
		public $_class_enable = false;
		
		/*
		 * Funzione chiamata quando si inizializza la classe, a cascata viene attivato il check sulla classe
         * inizializzata la directory ed il percorso del file csv locale.
		 */
		public function __construct(){
			
            global $wpdb;
            
			$this->_class_enable = true;
            $this->_tbl_importa_db = $wpdb->prefix . "importa_db";
            $this->_tbl_conversione = $wpdb->prefix . "conversione";
			$this->init_direcotry_file();		
		
		}
	
		public function initialize_database(){
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			global $wpdb;
			
			$charset_collate = $wpdb->get_charset_collate();
			
			$sql = "CREATE TABLE IF NOT EXISTS ".$this->_tbl_importa_db." (
					  ArtDsp int(11) NOT NULL,
					  CodArt varchar(50) NOT NULL,
					  NomArt varchar(255) NOT NULL,
					  NomArtExt text NOT NULL,
					  Cat varchar(30) NOT NULL,
					  SttCat varchar(60) NOT NULL,
					  Tgl varchar(50) NOT NULL,
					  Col varchar(50) NOT NULL,
					  DttArt text NOT NULL,
					  Mat varchar(100) NOT NULL,
					  Mrc varchar(45) NOT NULL,
					  PrzIng varchar(150) NOT NULL,
					  Sct varchar(150) NOT NULL,
					  PrzCns varchar(150) NOT NULL,
					  PrzCnsSct varchar(150) NOT NULL,
					  FotoMin text NOT NULL,
					  FotoMed text NOT NULL,
					  FotoMax text NOT NULL
					) $charset_collate;";
            dbDelta( $sql );
			
			$sql = "CREATE TABLE IF NOT EXISTS ".$this->_tbl_conversione." (
					  ID bigint(20) NOT NULL AUTO_INCREMENT,
					  ArtDsp int(11) NOT NULL,
					  CodArt varchar(50) NOT NULL,
					  CodArtIA varchar(50) NOT NULL,
					  NomArt varchar(255) NOT NULL,
					  NomArtExt text NOT NULL,
					  Cat varchar(30) NOT NULL,
					  SttCat varchar(60) NOT NULL,
					  Tgl varchar(50) NOT NULL,
					  Col varchar(50) NOT NULL,
					  DttArt text NOT NULL,
					  Mat varchar(100) NOT NULL,
					  Mrc varchar(45) NOT NULL,
					  PrzIng varchar(150) NOT NULL,
					  Sct varchar(150) NOT NULL,
					  PrzCns varchar(150) NOT NULL,
					  PrzCnsSct varchar(150) NOT NULL,
					  FotoMin text NOT NULL,
					  FotoMed text NOT NULL,
					  FotoMax text NOT NULL,
					  Attributo varchar(50) NOT NULL,
					  Variazione varchar(250) NOT NULL,
					  NomArtVar varchar(250) NOT NULL,
					  Stato varchar(15) NOT NULL,
					  PRIMARY KEY (ID)
				) $charset_collate;";
			dbDelta( $sql );
            
		}
		
		public function delete_database(){
			
			global $wpdb;
			$sql = "DROP TABLE ".$this->_tbl_importa_db.", ".$this->_tbl_conversione.";";
			$wpdb->query( $sql );
			
		}
	
		public function init_direcotry_file(){
			$upload_dir = wp_upload_dir();
			
			$this->_file_csv_locale_dir = $upload_dir["basedir"] . "/woo_import_prodotti_da_file";
			
			if ( ! is_dir( $this->_file_csv_locale_dir ) ) wp_mkdir_p( $this->_file_csv_locale_dir );
			
			$this->_file_csv_locale = $this->_file_csv_locale_dir . '/file_csv.csv'; 
			
		}
		
	
		/*
		 * Funzione dedicata alla creazione della mail e dell'invio sfruttando la funzione send mail
		 * di wordpress
		 * 
		 * @input:		$msg - testo della mail - stringa
		 * @output:		NULL
		 */
		private function crea_report_invia( $msg )  {
			
			$message =  'Inviato in data: ' . date("Y-m-d H:i:s") . "\n\r";
			$message .= 'Messaggio: ' . "\n\r" . $msg ;
			$message .= "\n\r";
		
			$headers[] = 'From: ' . $this->from_mail;

			wp_mail( $this->_admin_mail, 'Messaggio da importatore prodotti', $message, $headers );
		
		}
	
		/*
		 * Funzione che crea un array contenente i vari passaggi eseguiti dall'applicazione
		 * 
		 * @input:		$id - identificativo del tipo di log - stringa
		 * 				$msg - messaggio del log generato - stringa
		 * 				$stato - identifica lo stato del log - int 0 o 1
		 */
		private function crea_log( $id, $msg, $stato ) {
			switch ( $id ) {
				case 'inserisci_articolo':
				case 'aggiorna_articolo':
					$this->_log[$id][] = array(
						'data' 	   => date("Y-m-d H:i:s"),
						'log'	   => $msg,
						'stato'	   => $stato
					);
				break;
				default:
					$this->_log[$id] = array(
						'data' 	   => date("Y-m-d H:i:s"),
						'log'      => $msg,
						'stato'	   => $stato
					);
				break;
			}
		}

		/*
		 * Funzione che in base al prodotto passato identifica la tipologia del prodotto
		 * semplice, variabile o attributo
		 * 
		 * @input:		$prodotto - array che contiene le informazioni del prodotto - array
		 * @output: 	$tipo - identificativo del prodotto - stringa
		 */
		private function recupera_prodotto_tipo ( $prodotto ) {
			if ( 
				( $prodotto["CodArtIA"] != 0 ) && 
				( $prodotto["NomArtVar"] != "" ) &&
				( $prodotto["Attributo"] != "" )
			){
				// variabile 
				return "variabile";
			} else if ( $prodotto["CodArtIA"] == 0 ) {
				// semplice
				return "semplice";
			} else {	
				// escludo i soli attributi
				return "attributo";
			}
		}
	
		/*
		 * Recupera l'ID del prodotto presente in WooCommerce tramite il suo SKU
		 * 
		 * @input:		$sku - codice prodotto - stringa
		 * @output:		$id - ID del prodotto - int
		 */	
		private function woocommerce_recupera_pro_id_da_sku ( $sku ) {
			global $wpdb;
		  	return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta AS A JOIN $wpdb->posts AS B WHERE A.post_id = B.ID and meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );
		}
	
		/*
		 * Funzione che tramite lo sku ( deve essere univoco ) fa ad aggiornare lo stato del prodotto in WooCommerce, mettendolo in stato outofstock
		 * 
		 * @input:		$sku - codice prodotto - stringa
		 * 				$disponibilità - 0 prodotto non disponibile, 1 prodotto disponibile - int
		 * @output:		NULL
		 */
		private function woocommerce_aggiorna_disponibilita_articolo ( $sku, $disponibile){
			$pro_id = $this->woocommerce_recupera_pro_id_da_sku( $sku );
		
			if ( $disponibile == 0 )
				$stato = 'outofstock';
			else $stato = 'instock';
		
			return update_post_meta( $pro_id, '_stock_status', $stato );
		
		}
	
		/*
		 * Funzione che aggiorna i prezzi legati al prodotto presente in WooCommerce
		 * 
		 * @input:		$sku - codice prodotto - stringa
		 * 				$prezzi - i vari prezzi del prodotto - array
		 * @output:		NULL
		 */
		private function woocommerce_aggiorna_prezzi_articolo( $sku, $prezzi ) {
			$pro_id = $this->woocommerce_recupera_pro_id_da_sku( $sku );
			update_post_meta( $pro_id, '_price', $prezzi["PrezzoScontato"] );
			update_post_meta( $pro_id, '_regular_price', $prezzi["PrezzoScontato"] );
			update_post_meta( $pro_id, '_sale_price', "" );		
		}
	
		/*
		 * Verifica se il prodotto è già presente in WooCommerce, fa il check tramite slug
		 * 
		 * @input:		$NomArt - Nome del prodotto - stringa
		 * @output:		$id - id del prodotto - int
		 */
		private function woocommerce_prodotto_presente ( $NomArt ) {
			global $wpdb;
			$sql = "SELECT ID FROM ". $wpdb->posts . " WHERE post_title = '".$NomArt."' and post_name = '". sanitize_title( $NomArt ) ."' and post_status <> 'trash' ;";
		 
			return $wpdb->get_var( $sql );
		}
	
		/*
		 * Verifica se nella tabella di confine il prodotto è già presente oppure no
		 * 
		 * @input:		$CodArt - codice prodotto - stringa
		 * @output:		$id	- id prodotto - int
		 */
		private function prodotto_presente ( $CodArt ) {
			global $wpdb;
			$sql = 'SELECT ID FROM '.$this->_tbl_conversione.' WHERE CodArt = "'. $CodArt .'";';
		
			return $wpdb->get_var ( $sql );
		
		}
	
		/*
		 * Legge la tabella aggiornata prefix_conversione e per ogni riga 
		 * - verifica se il prodotto è semplice o variabile
		 * - aggiorna la disponibilità e il prezzo
		 * - crea il nuovo prodotto sia questo semplice o variabile
		 * - al termine dell'esecuzione invia una mail riepilogativa
		 * 
		 * @input:		NULL
		 * @output:		NULL
		 */ 
		public function aggiorna_prodotti() {
			global $wpdb;
			$i = 0;
			$sql = "SELECT * FROM ".$this->_tbl_conversione." WHERE Stato = 'update' ";
			$prodotti = $wpdb->get_results($sql, ARRAY_A );
		
			foreach ( $prodotti as $prodotto ) {
				
				$tipo = $this->recupera_prodotto_tipo( $prodotto );
			
				if ( $tipo == "variabile" ) {
					// variabile 
					$pro_var = $this->recupera_prodotto_variabile ( $prodotto ) ;
					$this->crea_prodotto_woocommerce( $pro_var, 'variabile' );
				} else if ( $tipo == "semplice" ) {
					// semplice
					$pro_sem = $this->recupera_prodotto_semplice( $prodotto );
					$this->crea_prodotto_woocommerce( $pro_sem, 'semplice' );
				} else {	
					// escludo i soli attributi
					// stato == aggiungi aggiungo attributo
					$this->crea_log( 'inserisci_articolo', 'Prodotto saltato, attributo puro ID: #' . $prodotto["CodArt"], 0 );
				}
			}

			$this->crea_log( 'fine_aggiornamento', 'Terminato l\'aggiornamento di tutti i vari prodotti', 0 );
		
			$sql = "SELECT * FROM ".$this->_tbl_conversione." WHERE NomArt NOT IN ( SELECT NomArt FROM " . $this->_tbl_importa_db . " )";
			$prodotti_da_verificare = $wpdb->get_results( $sql, ARRAY_A );
			$num_prod_cancellati = count($prodotti_da_verificare);
			foreach ( $prodotti_da_verificare as $pdv ) {
				$lista_nome_prod_ver .= $pdv["CodArt"] . " - " . $pdv["CodArtIA"] . " - " . $pdv["NomArt"] . "\n\r";
			}
		
		
		
			$msg .= $this->_log['file_csv']['log'] . "\n\r";
			$msg .= $this->_log['database_update']['log'] . "\n\r";
			$msg .= $this->_log['database_filtro']['log'] . "\n\r";
			$msg .= $this->_log['database_filtro_risultato']['log'] . "\n\r";
			$msg .= $this->_log['aggiorna_tbl_conversione']['log'] . "\n\r";
			$msg .= $this->_log['fine_aggiornamento']['log'] . "\n\r";
			$msg .= "\n\r";
			$msg .= "Prodotti da verificare: " . $num_prod_cancellati . "\n\r";
			$msg .= "Lista prodotti da verificare: \n\r";
			$msg .= $lista_nome_prod_ver;
		
			$this->crea_report_invia( $msg );
		
		}

		/*
		 * Crea il prodotto in WooCommerce prendendo i dati dalla tabella di confine o se già presente 
		 * aggiorna il prezzo e la disponibiltà
		 * 
		 * @input:		$prodotto - riga con le informazioni del prodotto - array
		 * 				$tipo - semplice o variabile - stringa
		 * @output:		NULL
		 */
		private function crea_prodotto_woocommerce ( $prodotto, $tipo = "semplice" ) {
			
				
			//print $this->_i . " - " . $prodotto["NomArt"] . " <br /> ";
			//$this->_i++;
		
			if ( $this->woocommerce_prodotto_presente( $prodotto['NomArt'] ) ) {
			  
				if ( $tipo == 'semplice' ) {
					// aggiorno la disponibilità
					$this->woocommerce_aggiorna_disponibilita_articolo( $prodotto["CodArt"], $prodotto["ArtDsp"] );		
					$this->crea_log( 'aggiorna_articolo', 'Aggiornato prodotto semplice: ' . $prodotto["NomArt"], 0 );
				
				
					/* AGGIORNA PREZZO */
					$prezzo_tmp = array(
						'Prezzo'				=> str_replace(",", ".", $prodotto["PrzCns"]),
						'PrezzoScontato' 	=> str_replace(",", ".", $prodotto["PrzCnsSct"])
					);
						   
					$this->woocommerce_aggiorna_prezzi_articolo ( $prodotto["CodArt"], $prezzo_tmp );
				
				} else {
					// aggiorno la disponibilità e prezzo
					foreach ( $prodotto["Variazioni"] as $variazione ) {
						$this->woocommerce_aggiorna_disponibilita_articolo( $variazione["Sku"], $variazione["ArtDsp"] ) ;
					
						$prezzo_tmp_variazione = array(
							'Prezzo'				=> str_replace(",", ".", $variazione["Prezzo"]),
							'PrezzoScontato' 	    => str_replace(",", ".", $variazione["PrezzoScontato"])
						);
					
						$this->woocommerce_aggiorna_prezzi_articolo ( $variazione["Sku"], $prezzo_tmp_variazione );
					
					}
					$this->crea_log( 'aggiorna_articolo', 'Aggiornata disponibilità variazioni prodotto: ' . $prodotto["NomArt"], 0 );

				}
		
			} else {
			
				$pro_id = $this->woocommerce_crea_prodotto_principale ( $prodotto, $tipo ) ;
				$this->crea_log( 'inserisci_articolo', 'Creato prodotto ' . $tipo . ' con ID: #' . $pro_id, 0 );
		
				if ( $tipo == 'variabile' ) {
					$variabile_term_id = get_term_by( 'slug', 'variable', 'product_type' );
					wp_set_object_terms( $pro_id, array($variabile_term_id->term_id), 'product_type' );
			
					$_product_attributes = array(
						'pa_' . sanitize_title( $prodotto["Attributo"] ) => array ( 
							'name'	=> 'pa_' . sanitize_title($prodotto["Attributo"]),
							'value'  => '',
							'position' => '0',
							'is_visible' => 1,
							'is_variation' => 1,
							'is_taxonomy' => 1
						)
					);
					update_post_meta( $pro_id, '_product_attributes', $_product_attributes );
			
					foreach ( $prodotto["Variazioni"] as $variazione ) {
						$var_id = $this->woocommerce_crea_prodotto_variazione( $pro_id, $prodotto["Attributo"], $prodotto["NomArt"], $variazione );
						$this->crea_log( 'inserisci_articolo', 'Creata variazione' . $pro_id . ' con ID: #' . $var_id, 0 );
						$variazione_term = get_term_by( 'slug', sanitize_title( $variazione["Nome"] ), 'pa_' . sanitize_title($prodotto["Attributo"] ));
						if ( $variazione_term == false ){
							$variazione_term = wp_insert_term( $variazione["Nome"], "pa_" . sanitize_title($prodotto["Attributo"]) );
							$variazione_term_id = $variazione_term->term_id;
						} else {
							$variazione_term_id = $variazione_term->term_id;
						}
				
						$variazione_term_id_array[] = $variazione_term_id;
				
						$gallery_id[] = $variazione["FotoMax"];
				
					}
			
					wp_set_object_terms( $pro_id, $variazione_term_id_array, "pa_" . sanitize_title($prodotto["Attributo"]) );
					$gallery_id_uniche = array_unique( $gallery_id );
					foreach ( $gallery_id_uniche as $gallery )
						$tmp[] = $this->carica_immagine_da_url( $gallery );
			
					update_post_meta( $pro_id, '_product_image_gallery', implode(",", $tmp)); 	
			
				}
			}
		}
	
		/*
		 * Crea in WooCommerce il prodotto di tipo variabile
		 * 
		 * @input:		$pro_id - id del prodotto principale - int
		 * 				$attributo - stringa
		 * 				$post_title - titolo del prodotto - stringa
		 * 				$variazione - stringa
		 * @output:		NULL
		 */
		private function woocommerce_crea_prodotto_variazione( $pro_id, $attributo, $post_title, $variazione ) {
			global $wpdb; 

			$defaults = array(
			  'post_title'    		  => 'TEMP',
			  'post_content'  		  => '',
			  'post_excerpt'			  => '',
			  'post_status'           => 'publish', 
			  'post_type'             => 'product_variation',
			  'post_name'			  	  => 'product-' . $pro_id . '-variation',
			  'post_author'           => 1,
			  'ping_status'           => get_option('default_ping_status'), 
			  'post_parent'           => $pro_id,
			  'menu_order'            => 0,
			  'to_ping'               => '',
			  'pinged'                => '',
			  'post_password'         => '',
			  'guid'                  => '', 
			  'post_content_filtered' => '',
			  'post_excerpt'          => '',
			  'import_id'             => 0
			);
	
			$media_id = $this->carica_immagine_da_url( $variazione["FotoMax"] );
	
			$metas = array (
				'attribute_pa_' . sanitize_title($attributo)  => sanitize_title( $variazione['Nome'] ),
				'_downloadable_files' 				=> '',	
				'_download_expiry'					=> '',	
				'_download_limit' 					=> '',
				'_tax_class' 							=> '',
				'_price' 								=> str_replace(",", ".", $variazione['PrezzoScontato']),    // da impostare
				'_sale_price_dates_to' 				=> '',	
				'_sale_price_dates_from' 	 		=> '',
				'_sale_price' 							=> str_replace(",", ".", $variazione['PrezzoScontato']),	 //da impostare
				'_regular_price' 						=> '', //str_replace(",", ".", $variazione['Prezzo']),
				'_manage_stock' 						=>	'no',
				'_height' 								=> '',
				'_width'									=> '',
				'_length' 								=> '',
				'_weight' 								=> '',
				'_downloadable' 						=> 'no',
				'_virtual' 								=> 'no',
				'_thumbnail_id'						=> $media_id,
				'_sku'									=> $variazione['Sku']
			);
	
			$post_id = wp_insert_post ( $defaults );
	
			$wpdb->update(
				$wpdb->posts,
				array (
					'post_title'	=> 'Variazione #'. $post_id .' di ' . sanitize_title($post_title)
				),
				array(
					'ID'	=> $post_id
				)	
			);
	
	
			foreach( $metas as $k => $v ) {
				update_post_meta( $post_id, $k , $v );
			}
	
			return $post_id;
		
		}
	
		/*
		 * Che sia semplice o variabile, tramite questa funzione si crea il prodotto principale
		 * 
		 * @input:		$prodotto - riga con le informazioni del prodotto - array
		 * @output:		NULL
		 */
		private function woocommerce_crea_prodotto_principale ( $prodotto ) {
			$content = "Produttore: " . $prodotto["Mrc"] . "<br/>";
			$content .= "Materiale: " . $prodotto["Mat"];
		
			if ( $prodotto["DttArt"] ) {
				$DttArtArr = explode ( "|", $prodotto["DttArt"] );
				$content .= "<ul>";
				foreach ( $DttArtArr as $DttArt ) {
					$content .= "<li>" . $DttArt . "</li>";
				}				
				$content .= "</ul>";
			}
	
			$media_id = $this->carica_immagine_da_url( $prodotto["FotoMax"] );
			$defaults = array(
			  'post_title'    		  	=> trim($prodotto["NomArt"]),
			  'post_name'					=> sanitize_title( $prodotto["NomArt"] ),
			  'post_content'  			=> $content,
			  'post_excerpt'			   => $prodotto['NomArtExt'],
			  'post_status'          	=> ( $tipo == 'semplice' ) ? 'publish' : 'draft', 
			  'post_type'             	=> 'product',
			  'post_author'           	=> 6,
			  'ping_status'           	=> get_option('default_ping_status'), 
			  'post_parent'           	=> 0,
			  'menu_order'            	=> 0,
			  'to_ping'               	=> '',
			  'pinged'                	=> '',
			  'post_password'         	=> '',
			  'guid'                  	=> '',
			  'post_content_filtered' 	=> ''
			);
		
			$metas = array (
				'_edit_last'					=> 1,	
				'_edit_lock'					=> $prodotto['_edit_lock'],
				'_visibility'					=> 'visible',
				'_stock_status'				=> 'instock',
				'total_sales'					=> 0,
				'_downloadable'				=> 'no',
				'_virtual'						=> 'no',
				'_product_image_gallery'	=> 0,
				'_regular_price'				=> str_replace(",", ".", $prodotto['PrzCnsSct']),
				'_sale_price'					=> '', //str_replace(",", ".", $prodotto['PrzCnsSct']),
				'_purchase_note'				=> '',
				'_featured'						=> 'no',
				'_weight'						=> '',
				'_length'						=> '',
				'_width'							=> '',
				'_height'						=> '',
				'_sku'							=> $prodotto['CodArt'],
				'_product_attributes'		=> '',
				'_sale_price_dates_from'	=> '',
				'_sale_price_dates_to'		=> '',
				'_price'							=> str_replace(",", ".", $prodotto['PrzCnsSct']),
				'_sold_individually'			=> 0,
				'_stock'						   => 0,
				'_backorders'					=> 0,
				'_manage_stock'				=> 0,
				'slide_template'				=> 'default',
				'_thumbnail_id'				=> $media_id,
				'_yoast_wpseo_title'			=> $prodotto['NomArt'],
				'_yoast_wpseo_metadesc'		=> substr($prodotto['NomArtExt'], 0, 145) . "...",
				'_yoast_wpseo_focuskw'		=> 'sex toys, vibratori, bdsm, bondage, erotismo, masturbazione, piacere, desiderio, dildo, plug-anali, strap-on, lingeri, biancheria sexy',
				'_tax_status'					=> 'taxable',
				'default_attributes'			=> array()
			);
			$post_id = wp_insert_post ( $defaults );
		
			// CATEGORY & SottoCategoria
		
			if ( $prodotto["Cat"] ) {
				$cat = get_term_by( 'slug', sanitize_title( $prodotto["Cat"] ), 'product_cat' );

				if ( $cat == false ) {
					$cat = wp_insert_term( $prodotto["Cat"], "product_cat" );
					$cat_id = $cat->term_id;
				} else {
					$cat_id = $cat->term_id;
				}
			}
		
			if ( $prodotto["SttCat"] ) {
				$SttCat = get_term_by( 'slug', sanitize_title( $prodotto["SttCat"] ), 'product_cat' );

				if ( $SttCat == false ) {
					$SttCat = wp_insert_term( $prodotto["SttCat"], "product_cat" );
					$sttcat_id = $SttCat->term_id;
				} else {
					$sttcat_id = $SttCat->term_id;
				}
			}
		
			wp_set_object_terms($post_id, array($cat_id, $sttcat_id), "product_cat" );
		
			// TAGS
			$prodotto['tag'] = $prodotto["Mrc"] . ", " . $prodotto["Cat"] . ", " . $prodotto["SttCat"];
			if ( $prodotto['tag'] ) {
				$tags = explode(', ', $prodotto['tag']);
				foreach ($tags as $solotag) {
					$solotag = trim($solotag);
					$check = is_term($solotag, 'product_tag');
					if (is_null($check)) {
						$tag = wp_insert_term($solotag, 'product_tag');
						if(!is_wp_error($tag)) {
							$tagid = $tag['term_id'];
						} else {
							$tagid = $check['term_id'];
						}
					}
				}
			
				wp_set_object_terms($post_id, $tags , 'product_tag');
			}
		
		
			foreach( $metas as $k => $v ) {
				update_post_meta( $post_id, $k , $v );
		
			}
		
			return $post_id;
		
		}
	
		/*
		 * Tramite l'url fornito dalla tabella di confine, questa funzione le carica sia fisicamente sul server che 
		 * nel db rimanendo fedele allo standard Wordpress
		 * 
		 * @input:		$url - link dell'immagine http://link_immagine - stringa
		 * @output:		$id - id dell'immagine caricata sul db - int
		 */
		private function carica_immagine_da_url ( $url ) {
			global $wpdb;
		
		
			$url_arr = explode ('/', $url );
			$ct = count($url_arr);
			$image_name = $url_arr[$ct-1];
			$filename = preg_replace( '/\.[^.]+$/', '', basename( $image_name ) );

		
			$sql = "SELECT ID FROM ". $wpdb->posts ." WHERE post_title = '" . basename($filename) . "' AND post_status = 'inherit'";
			$attach_id = $wpdb->get_var( $sql );
		
			if ( $attach_id > 0 ) {
				return $attach_id;
			} else {
				$uploaddir = wp_upload_dir();
				$uploadfile = $uploaddir['path'] . '/' . $image_name;

				$contents= file_get_contents($url);
				$savefile = fopen($uploadfile, 'w');
				fwrite($savefile, $contents);
				fclose($savefile);
		
				$wp_filetype = wp_check_filetype(basename($image_name), null );

				$attachment = array(
					'guid'           	=> $uploaddir['url'] . '/' . basename( $image_name ), 
					'post_mime_type' 	=> $wp_filetype['type'],
					'post_title'     	=> $filename,
					'post_content' 	=> '',
					'post_status' 		=> 'inherit'
				);
			
				// print_a ( $attachment );
			
				$attach_id = wp_insert_attachment( $attachment, $uploadfile );
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				$attach_data = wp_generate_attachment_metadata( $attach_id, $image_name );
				wp_update_attachment_metadata( $attach_id, $attach_data );
		
				return $attach_id;
			}
			return 0;		
		}
	
		/*
		 * Funzione che standardizza i dati recuperati dalla tabella di passaggio
		 * 
		 * @input:		$prodotto - riga con le informazioni del prodotto - array
		 * @output:		$tmp - prodotto standardizzato semplice - array
		 */
		private function recupera_prodotto_semplice ( $prodotto ) {
			$tmp['ArtDsp'] = $prodotto["ArtDsp"];
			$tmp['CodArt'] = $prodotto["CodArt"];
			$tmp['NomArt'] = $prodotto["NomArt"];
			$tmp['NomArtExt'] = $prodotto["NomArtExt"];
			$tmp['Cat'] = $prodotto["Cat"];
			$tmp['SttCat'] = $prodotto["SttCat"];
			$tmp['Tgl'] = $prodotto["Tgl"];
			$tmp['Col'] = $prodotto["Col"];
			$tmp['DttArt'] = $prodotto["DttArt"];
			$tmp['Mat'] = $prodotto["Mat"];
			$tmp['Mrc'] = $prodotto["Mrc"];
			$tmp['PrzIng'] = $prodotto["PrzIng"];
			$tmp['Sct'] = $prodotto["Sct"];
			$tmp['PrzCns'] = $prodotto["PrzCns"];
			$tmp['PrzCnsSct'] = $prodotto["PrzCnsSct"];
			$tmp['FotoMin'] = $prodotto["FotoMin"];
			$tmp['FotoMed'] = $prodotto["FotoMed"];
			$tmp['FotoMax'] = $prodotto["FotoMax"];
			return $tmp;
		}
	
		/*
		 * Funzione che recupera tutte le informzioni di un prodotto di tipo variabile dalla tabella di confine
		 * e standardizza l'output per i successivi usi in questo programma
		 * 
		 * @input:		$prodotto - riga con le informazioni del prodotto - array
		 * @output:		$tmp - prodotto standardizzato variabile - array
		 */
		private function recupera_prodotto_variabile( $prodotto ) {
			global $wpdb;
			$CodArtIA = $prodotto["CodArtIA"];
		
			$sql = "SELECT * FROM ".$this->_tbl_conversione." WHERE CodArtIA = $CodArtIA";
			$prodotti = $wpdb->get_results( $sql, ARRAY_A );
		
			foreach ( $prodotti as $prodotto ) {

				if ( $prodotto["NomArtVar"] != "" ) {
			
					$tmp['CodArt'] = 'IA0000' . $prodotto['CodArtIA'];
					$tmp['NomArt'] = $prodotto["NomArtVar"];
					$tmp['NomArtExt'] = $prodotto["NomArtExt"];
					$tmp['Cat'] = $prodotto["Cat"];
					$tmp['SttCat'] = $prodotto["SttCat"];
					$tmp['Tgl'] = $prodotto["Tgl"];
					$tmp['Col'] = $prodotto["Col"];
					$tmp['DttArt'] = $prodotto["DttArt"];
					$tmp['Mat'] = $prodotto["Mat"];
					$tmp['Mrc'] = $prodotto["Mrc"];
					$tmp['PrzIng'] = $prodotto["PrzIng"];
					$tmp['Sct'] = $prodotto["Sct"];
					$tmp['PrzCns'] = $prodotto["PrzCns"];
					$tmp['PrzCnsSct'] = $prodotto["PrzCnsSct"];
					$tmp['FotoMin'] = $prodotto["FotoMin"];
					$tmp['FotoMed'] = $prodotto["FotoMed"];
					$tmp['FotoMax'] = $prodotto["FotoMax"];
					$tmp['Attributo'] = $prodotto["Attributo"];
					$tmp['Variazioni'][] = array(
						'ArtDsp' 			=> $prodotto['ArtDsp'],
						'Sku'				=> $prodotto['CodArt'],
						'Nome'				=> $prodotto['Variazione'],
						'Prezzo'			=> $prodotto['PrzCns'],
						'PrezzoScontato'	=> $prodotto['PrzCnsSct'],
						'FotoMax'			=> $prodotto['FotoMax']
					);
				} else {
					$tmp['Variazioni'][] = array(
						'ArtDsp' 			=> $prodotto['ArtDsp'],
						'Sku'				=> $prodotto['CodArt'],
						'Nome'				=> $prodotto['Variazione'],
						'Prezzo'			=> $prodotto['PrzCns'],
						'PrezzoScontato'	=> $prodotto['PrzCnsSct'],
						'FotoMax'			=> $prodotto['FotoMax']
					);
				}
			}
		
			return $tmp;
		
		}
	
	
		/*
		 * Funzione che per ogni riga presente nella tabella prefix_importa_db va ad aggiornare il singolo prodotto o variazione 
		 * se già presente, altrimenti inserisce il prodotto come nuovo nella tabella di confine.
		 * 
		 * @input:		NULL
		 * @output:		NULL
		 */
		public function aggiorna_tbl_conversione () {
			global $wpdb;
			if ( ( $this->_log['file_csv']['stato'] == 0 ) &&
				  ( $this->_log['database_update']['stato'] == 0 )  &&
				  ( $this->_log['database_filtro']['stato'] == 0 ) ) {
			
				$sql = 'SELECT * FROM ' . $this->_tbl_importa_db;
				$prodotti = $wpdb->get_results( $sql, ARRAY_A );
			
				$count_prod_upd = 0;
				$count_prod_new = 0;
			
				foreach ( $prodotti as $prodotto ) {
					if ( $this->prodotto_presente( $prodotto['CodArt'] ) ) {
						// presente 
						$this->aggiorna_prodotto_tbl_variazione ( $prodotto );
						$count_prod_upd ++;
					} else {
						// non presente
						$this->inserisci_nuovo_prodotto ( $prodotto );
						$count_prod_new ++;
					}
				}
			
				$this->crea_log( 'aggiorna_tbl_conversione', 'TBL conversione aggiornata. Count UPD: ' . $count_prod_upd . ', Count NEW: ' . $count_prod_new , 0 );
					  
			} else {
				$msg = 'Aggiornamento db $this->_tbl_conversione NON riuscito'; 
				$this->crea_report_invia ( $msg );
				$this->crea_log( 'aggiorna_tbl_conversione', 'tbl conversione NON aggiornata', 1 );
			}		
		}

		/*
		 * Funzione che aggiorna i dati dei prodotti già presenti nella tabella di confine
		 * 
		 * @input:		$prodotto - informazioni del prodotto - array
		 * @output:		NULL
		 */
		private function aggiorna_prodotto_tbl_variazione( $prodotto ) {
			global $wpdb;
			
			$sql = 'UPDATE '.$this->_tbl_conversione.'
				       SET	ArtDsp 		= "' . $prodotto['ArtDsp'] . '",
				       		PrzIng		= "' . $prodotto['PrzIng'] . '",
						  	Sct 		= "' . $prodotto['Sct'] . '",
						 	PrzCns 		= "' . $prodotto['PrzCns'] . '",
						 	PrzCnsSct	= "' . $prodotto['PrzCnsSct'] . '" ,
						WHERE CodArt 	= "' . $prodotto['CodArt'] . '" ';			  
						  
			$wpdb->query ( $sql );
		}
	
		/*
		 * Funzione che inserisce nella tabella di confine un nuovo prodotto, impostando lo stato su new
		 * 
		 * @input:		$prodotto - informazioni del prodotto - array
		 * @output:		NULL
		 */
		private function inserisci_nuovo_prodotto( $prodotto ) {
			global $wpdb;
		
			$wpdb->insert(
				$this->_tbl_conversione,
				array(
					 'ArtDsp'		=> $prodotto['ArtDsp'],
					 'CodArt'		=> $prodotto['CodArt'], 	 	
					 'NomArt' 		=> $prodotto['NomArt'],	
					 'NomArtExt'	=> $prodotto['NomArtExt'], 	
					 'Cat' 			=> $prodotto['Cat'],	
					 'SttCat' 		=> $prodotto['SttCat'],	
					 'Tgl' 			=> $prodotto['Tgl'],	
					 'Col' 			=> $prodotto['Col'],	
				 	 'DttArt' 		=> $prodotto['DttArt'],	
					 'Mat' 			=> $prodotto['Mat'],	
					 'Mrc' 	      	=> $prodotto['Mrc'],	
					 'PrzIng'  	   	=> $prodotto['PrzIng'], 
					 'Sct'	      	=> $prodotto['Sct'],	
					 'PrzCns' 		=> $prodotto['PrzCns'],	
					 'PrzCnsSct' 	=> $prodotto['PrzCnsSct'],	
					 'FotoMin' 		=> $prodotto['FotoMin'],	
					 'FotoMed' 		=> $prodotto['FotoMed'],	
					 'FotoMax'		=> $prodotto['FotoMax'],
					 'Stato'		=> 'new'
				)
			);
		
		}
	
		/*
	 	 * Legge il file csv remoto e lo crea sul server così che poi lo si possa importare tramite 
		 * sql nel db
		 * 
		 * @input:		NULL
		 * @output: 	NULL
	  	 */
		public function carica_file_csv_da_url() {
		    
			$file_headers = @get_headers( $this->_file_csv_remote );
			if($file_headers[0] == 'HTTP/1.1 404 Not Found') {
				$msg = 'Il file dei prodotti non esiste, controllare il sorgente csv';
				$this->crea_report_invia ( $msg );
			} else {		
				$contents= file_get_contents( $this->_file_csv_remote );
				$savefile = fopen($this->_file_csv_locale, 'w');
				$res = fwrite($savefile, $contents);
				fclose($savefile);
				if ( $res ) {
					$this->crea_log( 'file_csv', 'file aggiornato', 0 );
				} else {
					$msg = 'Il file tmp non è stato creato sul server, verifica il log';
					$this->crea_report_invia ( $msg );
					$this->crea_log( 'file_csv', 'file non aggiornato', 1 );
				}
			}
		}
	
		/*
	 	 * Svuoto la tabella che andrà poi a contenere i nuovi dati importati dal csv
		 * 
		 * @input:		NULL
		 * @output: 	NULL
	  	 */
		private function inizializza_tabella () {
			global $wpdb;
			$sql = 'TRUNCATE TABLE ' . $this->_tbl_importa_db;
			return $wpdb->query( $sql );
		}
	
		/*
	 	 * Funzione che si occupa di portare i dati contenuti nel CSV nella tabella del DB
		 * 
		 * @input:		NULL
		 * @output: 	NULL
	  	 */
		private function inserisci_file_in_db() {
			global $wpdb;
			$sql = 'LOAD DATA LOCAL INFILE "' . $this->_file_csv_locale . '" INTO TABLE '.$this->_tbl_importa_db.' FIELDS TERMINATED BY "\t" ESCAPED BY "\\\" LINES TERMINATED BY "\n"';
			return $wpdb->query($sql);
		}
	
		/*
	 	 * Funzione che si occupa di inizializzare la tabella del database e di riempirla con i dati nuovi letti dal CSV
		 * 
		 * @input:		NULL
		 * @output: 	NULL
	  	 */
		public function aggiorna_db() {
			if ( $this->_log['file_csv']['stato'] == 0 )  {
				$res = $this->inizializza_tabella ();
				if ( ! $res ) {
					$msg = 'Database non inizializzato';
					$this->crea_report_invia ( $msg );
					$this->crea_log( 'database_init', 'database non inizializzato', 1 );
					return;
				}
			
				$res = $this->inserisci_file_in_db();
				if ( $res != 0 ) {
					$msg = 'Database non riempito con il file csv aggiornato';
					$this->crea_report_invia ( $msg );
					$this->crea_log( 'database_update', 'database non aggiornato', 1 );
					return;
				} else {
					$this->crea_log( 'database_update', 'database aggiornato', 0 );
				}
		
			}
		
		}
	
		public function filtra_db() {
			global $wpdb;
		
			if ( $this->_log['database_update']['stato'] == 0 ) {
				// filtro per Marca
				$sql = 'DELETE FROM ' . $this->_tbl_importa_db . ' 
							WHERE Mrc NOT IN ( 
								
							)';
				//$res_mrc = $wpdb->query( $sql );

		        // filtro per Categorie
				$sql = 'DELETE FROM ' . $this->_tbl_importa_db . '
							WHERE Cat NOT IN ( 
				               
							)';
				$res_cat = $wpdb->query( $sql );

				// filtro per SottoCategoria
				$sql = 'DELETE FROM ' . $this->_tbl_importa_db . '
							WHERE SttCat NOT IN (
				                "lubrificanti-anali",
                                
							)';
			
				$res_sttcat = $wpdb->query( $sql );
			
				if (( $res_mrc >= 0 ) && ( $res_cat >= 0 ) && ( $res_sttcat >= 0 )) {
					// $msg = 'Database filtrato. Risultato Marche: ' . $res_mrc . ' Risultato Categorie: ' . $res_cat . ' Risultato Sotto Cat: ' . $res_sttcat; 
					// $this->crea_report_invia ( $msg );
					$msg = 'Filtri applicati: Marche: ' . $res_mrc . ' Categorie: ' . $res_cat . ' SottoCat: ' . $res_sttcat;
					$this->crea_log( 'database_filtro', 'database filtrato', 0 );
					$this->crea_log( 'database_filtro_risultato', $msg, 0 );
				} else {
					$msg = 'Database non filtrato. Risultato Marche: ' . $res_mrc . ' Risultato Categorie: ' . $res_cat . ' Risultato Sotto Cat: ' . $res_sttcat; 
					$this->crea_report_invia ( $msg );
					$this->crea_log( 'database_filtro', 'database non filtrato', 1 );
					return;
				}		
			}
		}
	}
}

?>
