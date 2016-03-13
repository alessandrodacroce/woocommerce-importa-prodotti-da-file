<?php

if ( ! class_exists( 'WC_Woo_Import_Settings' ) ) {
	
	class WC_Woo_Import_Settings{
	    /**
	     * Holds the values to be used in the fields callbacks
	     */
	    private $_woo_import_products_main;
		private $_woo_import_products_csv;
	
	    /**
	     * Start up
	     */
	    public function __construct()
	    {
	        add_action( 'admin_menu', array( $this, 'woo_import_menu' ) );
	        add_action( 'admin_init', array( $this, 'woo_import_init_settings' ) );
	    }
	
	    /**
	     * Add options page
	     */
	    public function woo_import_menu() {
	  		add_management_page( 
				'Woo Import Impostazioni', 
				'Woo Import Impostazioni', 
				'manage_options', 
				'woo-import-impostazioni', 
				array( &$this, 'woo_import_create_admin_page') 
			);	
	    }
	
	    /**
	     * Options page callback
	     */
	    public function woo_import_create_admin_page() {
	        // Set class property
	        $this->_woo_import_products_main = get_option( 'woo_import_products_main' );
	        ?>
	        <div class="wrap">
	            <h2>Woo Import Products - Plugins</h2>           
	            <form method="post" action="options.php">
	            <?php
	                // This prints out all hidden setting fields
	                settings_fields( 'woo_import_products_option_group' );   
	                do_settings_sections( 'my-setting-admin' );
	                submit_button(); 
	            ?>
	            </form>
	            
	            <h2>Woo Import Products - FAQ</h2>
	            <ul>
	            	<li>
	            		D. Attivazione dell'codice di aggiornamento <br/>
	            		R. Basta lanciare questo url <a href="<?php bloginfo('url') ?>/?woo_import_product=1"><?php bloginfo('url') ?>/?woo_import_product=1</a>
	            	</li>
	            	<li>
	            		D. Formato del file csv da importare <br/>
	            		R. Di seguito l'elenco dei campi necessari per importare il file csv <a href="https://github.com/alessandrodacroce/woocommerce-importa-prodotti-da-file/blob/master/file_csv_esempio">QUI IL FILE DI ESEMPIO</a><br/>
	            		<div class="row">
							<table border="1" class="table table-bordered table-hover table-condensed table-responsive">
							<thead>
							<tr>
							<th nowrap="nowrap">Nome campo</th>
							<th nowrap="nowrap">Tipo dati</th>
							<th>Descrizione</th>
							</tr>
							</thead>
							<tbody>
							<tr>
							<td>ArtDsp</td>
							<td>Intero</td>
							<td>Articolo disponibile (0=NO 1=SI)</td>
							</tr>
							<tr>
							<td>CodArt</td>
							<td>Varchar (50)</td>
							<td>Codice articolo o SKU</td>
							</tr>
							<tr>
							<td>NomArt</td>
							<td>Varchar (255)</td>
							<td>Titolo del prodotto</td>
							</tr>
							<tr>
							<td>NomArtExt</td>
							<td>Text</td>
							<td>Descrizione estesa dell'articolo</td>
							</tr>
							<tr>
							<td>Cat</td>
							<td>Varchar (30)</td>
							<td>Categoria</td>
							</tr>
							<tr>
							<td>SttCat</td>
							<td>Varchar (60)</td>
							<td>Sottocategoria</td>
							</tr>
							<tr>
							<td>Tgl</td>
							<td>Varchar (50)</td>
							<td>Taglia</td>
							</tr>
							<tr>
							<td>Col</td>
							<td>Varchar (50)</td>
							<td>Colore</td>
							</tr>
							<tr>
							<td>DttArt</td>
							<td>Text</td>
							<td>Dettagli articolo separati dalla barra verticale (|) ad esempio:
							Diametro Max: 4,2cm|Peso: 14kg|Altezza: 16cm</td>
							</tr>
							<tr>
							<td>Mat</td>
							<td nowrap="nowrap">Varchar (100)</td>
							<td>Materiale con cui è composto l'articolo</td>
							</tr>
							<tr>
							<td>Mrc</td>
							<td>Varchar (45)</td>
							<td>Marca articolo (brand)</td>
							</tr>
							<tr>
							<td>PrzIng</td>
							<td>Doppio</td>
							<td>Prezzo netto ingrosso - (formato 1234,56)</td>
							</tr>
							<tr>
							<td>Sct</td>
							<td>Int</td>
							<td>Sconto</td>
							</tr>
							<tr>
							<td>PrzCns</td>
							<td>Doppio</td>
							<td>Prezzo di vendita CONSIGLIATO - IVA compresa - (formato 1234,56)</td>
							</tr>
							<tr>
							<td>PrzCnsSct</td>
							<td>Doppio</td>
							<td>Prezzo di vendita SCONTATO - IVA compresa - (formato 1234,56)</td>
							</tr>
							<tr>
							<td>FotoMin</td>
							<td>Text</td>
							<td>Percorso file immagine (foto piccola thumbnail)</td>
							</tr>
							<tr>
							<td>FotoMed</td>
							<td>Text</td>
							<td>Percorso file immagine (foto media per elenchi)</td>
							</tr>
							<tr>
							<td>FotoMax</td>
							<td>Text</td>
							<td>Percorso file immagine (foto grande per dettaglio)</td>
							</tr>
							</tbody>
							</table>
							</div>
	            		
	            	</li>
	            </ul>
	        </div>
	        <?php
	    }
	
	    /**
	     * Register and add settings
	     */
	    public function woo_import_init_settings() {        
	       	register_setting(
	            'woo_import_products_option_group', // Option group
	            'woo_import_products_main', // Option name
	            array( $this, 'sanitize' ) // Sanitize
	        );
	
	        add_settings_section(
	            'setting_section_id', // ID
	            'Woo Import Products - Impostazioni', // Title
	            array( $this, 'print_section_info' ), // Callback
	            'my-setting-admin' // Page
	        );  
	
	        add_settings_field(
	            'admin_mail', // ID
	            'Destinatario', // Title 
	            array( $this, 'admin_mail_callback' ), // Callback
	            'my-setting-admin', // Page
	            'setting_section_id' // Section           
	        );    
			
			add_settings_field(
	            'from_mail', // ID
	            'Mittente', // Title 
	            array( $this, 'from_mail_callback' ), // Callback
	            'my-setting-admin', // Page
	            'setting_section_id' // Section           
	        );      
	
	        add_settings_field(
	            'file_csv_remote', 
	            'Url file CSV', 
	            array( $this, 'file_csv_remote_callback' ), 
	            'my-setting-admin', 
	            'setting_section_id'
	        );      
	    }
	
	    /**
	     * Sanitize each setting field as needed
	     *
	     * @param array $input Contains all settings fields as array keys
	     */
	    public function sanitize( $input ) {
	        $new_input = array();
	        if( isset( $input['admin_mail'] ) )
	            $new_input['admin_mail'] = sanitize_email( $input['admin_mail'] );
			
			if( isset( $input['from_mail'] ) )
	            $new_input['from_mail'] = sanitize_email( $input['from_mail'] );
	
	        if( isset( $input['file_csv_remote'] ) )
	            $new_input['file_csv_remote'] = sanitize_text_field( $input['file_csv_remote'] );
	
	        return $new_input;
	    }
	
	    /** 
	     * Print the Section text
	     */
	    public function print_section_info() {
	        //print 'Enter your settings below:';
	    }
	
	    /** 
	     * Get the settings option array and print one of its values
	     */
	    public function admin_mail_callback() {
	        printf(
	            '<input type="mail" id="admin_mail" name="woo_import_products_main[admin_mail]" value="%s" /> es. tua_mail@tuo_dominio.it',
	            isset( $this->_woo_import_products_main['admin_mail'] ) ? esc_attr( $this->_woo_import_products_main['admin_mail']) : ''
	        );
	    }
		
		public function from_mail_callback () {
			printf(
	            '<input type="mail" id="from_mail" name="woo_import_products_main[from_mail]" value="%s" />  es. Import Plugin <tua_mail@tuo_dominio.it>',
	            isset( $this->_woo_import_products_main['from_mail'] ) ? esc_attr( $this->_woo_import_products_main['from_mail']) : ''
	        );
		}
		
	    /** 
	     * Get the settings option array and print one of its values
	     */
	    public function file_csv_remote_callback() {
	        printf(
	            '<input type="text" id="file_csv_remote" name="woo_import_products_main[file_csv_remote]" value="%s" /> es. http://www_tuo_dominio.it/wp-content/remote_csv.csv',
	            isset( $this->_woo_import_products_main['file_csv_remote'] ) ? esc_attr( $this->_woo_import_products_main['file_csv_remote']) : ''
	        );
	    }
	}
}

?>
