<?php
/*
 * Plugin Name: WooCommerce Importa Prodotti da File
 * Plugin URI: http://www.alessandrodacroce.it/progetto/plugin-woocommerce-importa-prodotti-da-file-csv/
 * Description: Plugin per woocommerce che permette di importare in modo automatico i prodotti o aggiornare quelli presenti da un file csv presente sul server o raggiungibile tramite un indirizzo internet pubblico. Il plugin può essere anche impostato tramite schedulazione dei lavori affinchè giornalmente esegua l'importazione e l'aggiornamento dei prodotti. 
 * Author: Alessandro Dacroce <adacroce [AT] gmail [DOT] com>
 * Version: 0.0.2
 * Author URI: http://alessandrodacroce.it/
 * License: MIT
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

final class woo_importa_prodotti_plugin {
	
	// identifica l'oggetto della classe di gestione dell'importazione
	public $_woo_ip_obj;
	private $_settings;
	
	function __construct() {
		// self::$instance =& $this;
		
		
		
		register_activation_hook	( __FILE__, array( &$this, 'woo_import_enable_plugin'     	   	) );
		register_deactivation_hook	( __FILE__, array( &$this, 'woo_import_deactivate_plugin'       ) );
		
		// add_action					( 'plugins_loaded', array( &$this, 'myplugin_update_db_check' 	) );
		add_action					( 'init', array( &$this, 'woo_import_init' 				) );
	}
	
	function myplugin_update_db_check() {
		/* global $jal_db_version;
		if ( get_site_option( 'jal_db_version' ) != $jal_db_version ) {
		    jal_install();
		}
		*/
	}
	
	function woo_import_init() {
		
		$this->woo_import_includes();
		
		$this->_settings = new WC_Woo_Import_Settings();
		
		$woo_import_product = ( isset($_GET["woo_import_product"]) ) ? $_GET["woo_import_product"] : '0';
		
		if ( $woo_import_product == '1' ) {
		
			$this->_wooip_obj = new WC_Woo_Import_Product();	
			$this->_wooip_obj->carica_file_csv_da_url ();
        	$this->_wooip_obj->aggiorna_db();
        	$this->_wooip_obj->filtra_db();
        	$this->_wooip_obj->aggiorna_tbl_conversione ();
        	$this->_wooip_obj->aggiorna_prodotti();	
		}
	}
	
	function woo_import_includes() {
		include_once ( $_SERVER['DOCUMENT_ROOT'] . "/wp-load.php" );
		include_once ( 'wooip_class.php' );
		include_once ( 'wooip_admin.php' );
	}
	
	function woo_import_enable_plugin() {
		if ( ! $this->_wooip_obj->_class_eneable ) {
			$this->woo_import_includes();
			$this->_wooip_obj = new WC_Woo_Import_Product(); 
		}
		$this->_wooip_obj->initialize_database();
	}
	
	function woo_import_deactivate_plugin() {
		if ( ! $this->_wooip_obj->_class_eneable ) {
			$this->woo_import_includes();	
			$this->_wooip_obj = new WC_Woo_Import_Product(); 
		}
		$this->_wooip_obj->delete_database();
	}
			
}

new woo_importa_prodotti_plugin();

?>
