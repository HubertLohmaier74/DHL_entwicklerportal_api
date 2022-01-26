<?php


// ***************************************************************************************
//  Wrapper-Class für Anbindung an DHL Geschäftskundenportal via DHL Entwicklerportal API
// ***************************************************************************************
//
//  Originally derived from:
//  (c) VON https://opensource-blog.de/dhl-label-pdf-mit-php-erstellen/
//	Thank you very much!
//  
//  Updated to majorRelease 3.1:
//  (c) Hubert Lohmaier, 28.03.2021
//
//  Updated to use WSDL file from local directory
//  (c) Hubert Lohmaier, 13.04.2021
//
//	- 23.04.2021: Receiver notification only possible if email address not empty
//	- 26.04.2021: DHL-API Update from 08.04.2021 => Street-No. got optional field, can be given over together with: street_name
//  - 06.05.2021: 'ShipperReference' added to request (Company-Reference)
//  - 08.07.2021: Small bug eliminated that showed out every time when GKS-pass was outdated
//  - 26.01.2022: New product V66WPI added (DHL Warenpost International)
// ***************************************************************************************
class DHLBusinessShipment {

	private $credentials;
	private $company;
	private $shipmentList;
	private $client;
	private $errors;
	private $sandbox;
//	public $WSDL;		26.04.
	private $WSDL;

	// --------------------------------------------------------
	// Constructor for Shipment SDK
	// --------------------------------------------------------
	//
	//
	// @param type $api_credentials
	// @param type $customer_company
	// @param boolean $sandbox use sandbox or production environment
	// 
	// $api_file 		= pure filename to WSDL (without path or URL)
	// $api_url 		= total URL (incl. https + path + filename)
	// --------------------------------------------------------
	function __construct( $api_credentials, $company, $api_file, $api_url, $sandbox = TRUE ) {
		$this->credentials 		= $api_credentials;
		$this->company      	= $company;
		$this->sandbox 			= $sandbox;
		$this->errors 			= array();
		$this->shipmentList		= array();
		$this->WSDL				= array( "local" => FALSE, "directory" => "", "API_FILE" => "/" . trim($api_file, "/"), "API_URL"=> $api_url );
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	// php error logging
	// --------------------------------------------------------
	private function log( $message ) {
		if ( isset( $this->credentials['log'] ) ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( print_r( $message, true ) );

			} else {
				error_log( $message );
			}
		}
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	// check if a local WSDL file does exist
	// --------------------------------------------------------
	private function findLocalWSDL() {
		if ( file_exists($this->WSDL['directory'] . $this->WSDL['API_FILE']) )
			return TRUE;
		else	
			return FALSE;
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	// local WSDL is older than 24h ?
	// 
	// !!! WARNING:
	// findLocalWSDL() has to be checked before or function will crash if file does not exist
	// --------------------------------------------------------
	private function outdatedWSDL() {
		$filename = $this->WSDL['directory'] . $this->WSDL['API_FILE'];
		$hours = ( time() - filemtime($filename) ) / 60 / 60;
		if ($hours > 24)
			return TRUE;
		else	
			return FALSE;
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	// unlink without warning display if file does not exist
	// or cannot be deleted
	// --------------------------------------------------------
	private function do_unlink($filename) {
		$ret = TRUE;
		if ( file_exists($filename) ) {
			$ret = @unlink($filename);
		}
		
		return $ret;
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	// retrieves WSDL from DHL server and stores it to local file
	//
	// returns TRUE if new file could be retrieved
	// --------------------------------------------------------
	private function retrieveNewWSDL() {
		// ......................................
		$WSDL_local 	= $this->WSDL['directory'] . $this->WSDL['API_FILE'];
		$BCS_local 		= str_replace(".wsdl", "-schema-bcs_base.xsd", $WSDL_local);
		$CIS_local 		= str_replace(".wsdl", "-schema-cis_base.xsd", $WSDL_local);
		// ......................................
		$WSDL_server	= $this->WSDL['API_URL'];
		$BCS_server 	= str_replace(".wsdl", "-schema-bcs_base.xsd", $WSDL_server);
		$CIS_server 	= str_replace(".wsdl", "-schema-cis_base.xsd", $WSDL_server);
		// ......................................
		$WSDL_contents	= file_get_contents($WSDL_server);
		$BCS_contents 	= file_get_contents($BCS_server);
		$CIS_contents 	= file_get_contents($CIS_server);
		// ......................................
		if ( $this->do_unlink($WSDL_local) && $this->do_unlink($BCS_local) && $this->do_unlink($CIS_local) ) {
			$bWSDL	= file_put_contents($WSDL_local, $WSDL_contents);
			$bBCS 	= file_put_contents($BCS_local, $BCS_contents);
			$bCIS 	= file_put_contents($CIS_local, $CIS_contents);
			if ($bWSDL && $bBCS && bCIS)
				return TRUE;
		}
		return FALSE;
	}



	// --------------------------------------------------------
	// use WSDL from local file
	// (tries to uses local file but switches back if conditions fail)
	//
	// $local = TRUE / FALSE
	// $directory: Default = __DIR__
	// --------------------------------------------------------
	public function setLocalWSDL( $local , $directory = "") {

		if ($directory == "") 
			$directory = __DIR__;
		$this->WSDL['directory'] = $directory;
		
		// user likes to use local file ?
		if ( $local ) {
			// local WSDL does exist?
			if ( $this->findLocalWSDL () ) {
				// yes, but it is outdated ?
				if ( $this->outdatedWSDL() ) {
					// yes: try to retrieve a new WSDL (sets 'local' to TRUE only in case of success)
					$this->WSDL['local'] = $this->retrieveNewWSDL();
				} else {
					// no, still an actual local WSDL file there
					$this->WSDL['local'] = TRUE;
				}
			} else {
				// no local WSDL file there: try to retrieve it for first time
				$this->WSDL['local'] = $this->retrieveNewWSDL();
			}
		} else // no, user does not like to use local WSDL
			$this->WSDL['local'] = FALSE;
	}



	// --------------------------------------------------------
	// add this error if not registered yet
	// --------------------------------------------------------
	private function addError( $errorMsg ) {
		$bFound = FALSE;
		foreach ($this->errors AS $error) {
			if ($error == $errorMsg) {
				$bFound = TRUE;
				break;
		  }
		}
		
		if (!$bFound)
			$this->errors[] = $errorMsg;
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	// get all registered errors (array)
	// --------------------------------------------------------
	public function getErrors() {
		return $this->errors;
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	// build Soap Client
	// --------------------------------------------------------
	private function buildSoapClient() {
		$header = $this->buildAuthHeader();

		if ($this->sandbox) {
			$location = _DHL_SANDBOX_URL_;
		} else {
			$location = _DHL_PRODUCTION_URL_;
		}

		$auth_params = array(
			'encoding' => 'utf-8',
			'login'    => $this->credentials['api_user'],
			'password' => $this->credentials['api_password'],
			'location' => $location,
			'trace'    => 1
		);

		$this->log( $auth_params );
//		$this->client = new SoapClient( API_URL, $auth_params );
		if ($this->WSDL['local']) {
			$this->client = new SoapClient( $this->WSDL['directory'] . $this->WSDL['API_FILE'], $auth_params );
		} else {
			$this->client = new SoapClient( $this->WSDL['API_URL'], $auth_params );
		}

		$this->client->__setSoapHeaders( $header );
		$this->log( $this->client );
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	// create a cancel request for one or more labels with corresponding shipment no.
	//
	// https://entwickler.dhl.de/group/ep/deleteshipmentorder
	// --------------------------------------------------------
	public function createShipmentDeleteRequest( $myShipmentNumberList ) {
		
		// MAIN array
		$delete = array();

		// Version
		$delete['Version'] = array( 'majorRelease' => '3', 'minorRelease' => '1' );

		// List of numbers to delete
		foreach ($myShipmentNumberList AS $shipmentNumber) {
			$delete['shipmentNumber'][] = $shipmentNumber;
		}
		
		return $delete;
	}


	// --------------------------------------------------------
	// Get an address block for a certain request depending on 
	// which product and delivery target is chosen
	// --------------------------------------------------------
	public function getReceiverAddressBlock( $product, $dhl ) {
		
	}


	// --------------------------------------------------------
	// Create a shipment request for a certain shipment
	//
	// https://entwickler.dhl.de/group/ep/createshipmentorder
	// also see export fields description: https://entwickler.dhl.de/group/ep/foren/-/message_boards/message/193218
	// --------------------------------------------------------
	public function createShipmentRequest( $myShipment, $myCustomer, $myExport) {
		
		// MAIN array
		$shipment = array();

		// Version
		$shipment['Version'] = array( 'majorRelease' => '3', 'minorRelease' => '1' );

		// Order
		$shipment['ShipmentOrder'] = array();

		// Sequence Number
		$shipment['ShipmentOrder']['sequenceNumber'] = time();

		// .........
		// Shipment Details
		// .........
		$sd                 	= array();
		$sd['product']  		= $myShipment[product];
		$sd['accountNumber']	= $this->credentials['ekp'] . $myShipment['verfahren'] . $this->credentials['teilnahme'];
		$sd['customerReference']= $myCustomer['customerReference'];
		$sd['shipmentDate'] 	= $myShipment['shipmentDate'];
		$sd['costCentre'] 		= $myShipment['costCentre'];

		$sd['ShipmentItem']               = array();
		$sd['ShipmentItem']['weightInKG'] = $myShipment['weightInKG'];
		$sd['ShipmentItem']['lengthInCM'] = $myShipment['lengthInCM'];
		$sd['ShipmentItem']['widthInCM']  = $myShipment['widthInCM'];
		$sd['ShipmentItem']['heightInCM'] = $myShipment['heightInCM'];

		switch ( $myShipment['product'] ) {
			case "V62WP"  : // WARENPOST: no premium service for this product 
			case "V01PAK" : // NATIONAL: this element does not exist for NATIONAL shipments
							$sd['Service'] = "";
							break;
			case "V54EPAK" : // EU: always TRUE
							$sd['Service']['Premium active'] = TRUE; 		// true / false: Premium shipment
							break;

			case "V53WPAK" : // INTERNATIONAL: TRUE or FALSE as you have chosen in your shipment setup
							$sd['Service']['Premium active'] = $myShipment['Premium active']; 			// true / false: Premium shipment
							if ( $myShipment['Endorsement active'] != "" ) 								// how to handle parcel if receiver not met
								$sd['Service']['Endorsement'] = array("active"=>TRUE, "type" => $myShipment['Endorsement active']);
							break;
							
			case "V66WPI" : // DHL WARENPOST INTERNATIONAL
							$sd['Service']['Premium active'] = $myShipment['Premium active']; 			// true / false: Premium shipment
							if ( $myShipment['Endorsement active'] != "" ) 								// how to handle parcel if receiver not met
								$sd['Service']['Endorsement'] = array("active"=>TRUE, "type" => $myShipment['Endorsement active']);
							break;
		}

		if ( $myCustomer['notification'] && $myCustomer[email] != "") // 23.04.2021
			$sd['Notification']['recipientEmailAddress'] = $myCustomer['email'];
		else
			$sd['Notification']['recipientEmailAddress'] = "";
		

		// .........
		$shipment['ShipmentOrder']['Shipment']['ShipmentDetails'] = $sd;
		// .........


		// .........
		// Shipper (Company)
		// .........
		
		// Company's name
		$shipper						= array();
		$shipper['Name']				= array();
		$shipper['Name']['name1'] = $this->company['name1'];
		$shipper['Name']['name2'] = $this->company['name2'];
		$shipper['Name']['name3'] = $this->company['name3'];
		
		// Company's reference
		$shipper['ShipperReference'] = $this->company['reference'];

		// Company's address
		$shipper['Address']						= array();
		$shipper['Address']['streetName']		= $this->company['street_name'];
		$shipper['Address']['streetNumber']   	= $this->company['street_number'];
		$shipper['Address']['zip']				= $this->company['zip'];
		$shipper['Address']['city']             = $this->company['city'];

		// Origin country
		$shipper['Address']['Origin'] 						= array();
		$shipper['Address']['Origin']['country']			= $this->company['country'];
		$shipper['Address']['Origin']['countryISOCode']		= $this->company['countryISOCode'];

		// Company's contact
		$shipper['Communication']                  	= array();
//		$shipper['Communication']['!--Optional:--']	= "";
		$shipper['Communication']['phone']         	= $this->company['phone'];
		$shipper['Communication']['email']         	= $this->company['email'];
//		$shipper['Communication']['!--Optional:--']	= "";
		$shipper['Communication']['contactPerson'] 	= $this->company['contactPerson'];

		// .........
		$shipment['ShipmentOrder']['Shipment']['Shipper'] = $shipper;
		// .........

		// .........
		// Receiver of this parcel
		// .........
		$receiver = array();
		$receiver['name1']							= $myCustomer['name1'];

		

		// Receiver's address
		switch ( $myShipment['product'] ) {
		
			case "V62WP" :
				// V62WP darf nur im Innland verwendet werden
				if ($myCustomer['countryISOCode'] != "DE") {
					$this->addError("WARENPOST V62WP not valid for internatonal shipment!");
					return;
				}
				// !!! HIER KEIN BREAK => Fortsetzung bei V01PAK
				
			case "V01PAK" :
				switch ( $myCustomer['dhl_receiver'] ) {
				
					case ""	:
								$receiver['Address'] = array();
								$receiver['Address']['name2']			= $myCustomer['name2'];
								$receiver['Address']['name3']			= $myCustomer['name3'];
								$receiver['Address']['streetName']		= $myCustomer['street_name'];
								// DHL API Update 08.04.2021: street_name & street_number can be delivered together in 1 field (street_name)
								// In this case the field street_number may not be given over to the API
								if ($myCustomer['street_number'] != "###ELIMINATE @ THIS###")
								  $receiver['Address']['streetNumber']	= $myCustomer['street_number'];
								else 
								  $receiver['Address']['streetNumber']	= " ";
								$receiver['Address']['zip']				= $myCustomer['zip'];
								$receiver['Address']['city']			= $myCustomer['city'];
								$receiver['Address']['Origin'] 			= array();
								$receiver['Address']['Origin']['country']			= $myCustomer['country'];
								$receiver['Address']['Origin']['countryISOCode']	= $myCustomer['countryISOCode'];
								if ($myShipment['product'] == "V62WP")
								  $receiver['Address']['Origin']['state']				= $myCustomer['state'];
								break;
								
					case "PACKSTATION" :						
								$dhl_receiver						= array();
								$dhl_receiver['postNumber']			= $myCustomer['name2'];
								$dhl_receiver['packstationNumber']	= $myCustomer['name3'];
								$dhl_receiver['zip']				= $myCustomer['zip'];
								$dhl_receiver['city']				= $myCustomer['city'];
								$dhl_receiver['Origin'] 			= array();
								$dhl_receiver['Origin']['country']			= $myCustomer['country'];
								$dhl_receiver['Origin']['countryISOCode']	= $myCustomer['countryISOCode'];
								$receiver['Packstation'] = $dhl_receiver;
								break;
								
					case "POSTFILIALE" :
								$dhl_receiver						= array();
								$dhl_receiver['postfilialNumber']	= $myCustomer['name3'];
								$dhl_receiver['postNumber']			= $myCustomer['name2'];
								$dhl_receiver['zip']				= $myCustomer['zip'];
								$dhl_receiver['city']				= $myCustomer['city'];
								$dhl_receiver['Origin'] 			= array();
								$dhl_receiver['Origin']['country']			= $myCustomer['country'];
								$dhl_receiver['Origin']['countryISOCode']	= $myCustomer['countryISOCode'];
								$receiver['Postfiliale'] = $dhl_receiver;
								break;
								
					case "PARCELSHOP" :
								$dhl_receiver						= array();
								$dhl_receiver['parcelShopNumber']	= $myCustomer['name2'];
								$dhl_receiver['streetName']			= $myCustomer['street_name'];
								// DHL API Update 08.04.2021: street_name & street_number can be delivered together in 1 field (street_name)
								// In this case the field street_number may not be given over to the API
								if ($myCustomer['street_number'] != "###ELIMINATE @ THIS###")
								  $dhl_receiver['Address']['streetNumber']	= $myCustomer['street_number'];
								else 
								  $dhl_receiver['Address']['streetNumber']	= " ";
								$dhl_receiver['zip']				= $myCustomer['zip'];
								$dhl_receiver['city']				= $myCustomer['city'];
								$dhl_receiver['Origin'] 			= array();
								$dhl_receiver['Origin']['country']			= $myCustomer['country'];
								$dhl_receiver['Origin']['countryISOCode']	= $myCustomer['countryISOCode'];
								$receiver['ParcelShop'] = $dhl_receiver;
								break;
				} // switch (dhl_receiver)
			break; // end V01PAK
				
			case "V66WPI"	:
			case "V53WPAK"	:
			case "V54EPAK"	:
						$receiver['Address'] = array();
						$receiver['Address']['name2']						= $myCustomer['name2'];
						$receiver['Address']['name3']						= $myCustomer['name3'];
						$receiver['Address']['streetName']					= $myCustomer['street_name'];
						// DHL API Update 08.04.2021: street_name & street_number can be delivered together in 1 field (street_name)
						// In this case the field street_number may not be given over to the API
						if ($myCustomer['street_number'] != "###ELIMINATE @ THIS###")
						  $receiver['Address']['streetNumber']	= $myCustomer['street_number'];
						else 
						  $receiver['Address']['streetNumber']	= " ";
						$receiver['Address']['addressAddition']				= $myCustomer['addressAddition'];
						$receiver['Address']['dispatchingInformation']		= $myCustomer['dispatchingInformation'];
						$receiver['Address']['zip']							= $myCustomer['zip'];
						$receiver['Address']['city']						= $myCustomer['city'];
						$receiver['Address']['province']					= $myCustomer['province'];
						$receiver['Address']['Origin'] 						= array();
						$receiver['Address']['Origin']['country']			= $myCustomer['country'];
						$receiver['Address']['Origin']['countryISOCode']	= $myCustomer['countryISOCode'];
						$receiver['Address']['Origin']['state']				= $myCustomer['state'];
			break; // end V53WPAK / V54EPAK
		} // switch (product)
		
		$receiver['Communication']						= array();
		$receiver['Communication']['phone']         	= $myCustomer['phone'];
		$receiver['Communication']['email']         	= $myCustomer['email'];
		$receiver['Communication']['contactPerson'] 	= $myCustomer['name1'];

		// .........
		$shipment['ShipmentOrder']['Shipment']['Receiver'] = $receiver;
		// .........

		// export documents for international parcels
		if ( $myShipment['product'] == "V53WPAK" || $myShipment['product'] == "V66WPI") {
			$export = array();
			$export['invoiceNumber'] 			= $myExport['invoiceNumber'];			// customers invoice number
			$export['exportType']				= $myExport['exportType'];				// see dhl's CN23 form
			$export['exportTypeDescription']	= $myExport['exportTypeDescription'];	// very short description of contents
			$export['termsOfTrade']				= $myExport['termsOfTrade'];			// not required at postal customs clearing (BPI)		
			$export['placeOfCommital']			= $myExport['placeOfCommital'];			// zip + city where parcel is handed over to some dhl shop
			$export['additionalFee'] 			= $myExport['additionalFee'];			// shipping cost
			$export['permitNumber']				= $myExport['permitNumber'];			// customs permission number (articles > 1000 € : you have to declare the shipment to customs before shipping)
			$export['attestationNumber']		= $myExport['attestationNumber'];		// customs certificate number (articles > 1000 € : you have to declare the shipment to customs before shipping)
			$export['WithElectronicExportNtfctn active'] = $myExport['WithElectronicExportNtfctn active'];	// true / false: if shipment/export is communicated electronically to customs authorities

			$exportDoc = array();									// Listing of all articles exported follows
			$cnt = 0;
			if (count($myExport['exportArticles']) > 0) {
				foreach ($myExport['exportArticles'] AS $exportArticle) {					// iterate through all articles		
					$exportDoc['ExportDocPosition'][$cnt]['description']			= $exportArticle['description'];			// detailed short description (e.g. author/title of book)
					$exportDoc['ExportDocPosition'][$cnt]['countryCodeOrigin']		= $exportArticle['countryCodeOrigin']; 		// where article has been produced: empty if not known for shure
					$exportDoc['ExportDocPosition'][$cnt]['customsTariffNumber'] 	= $exportArticle['customsTariffNumber'];	// customs article no =Zolltarifnummer (https://www.zolltarifnummern.de/)
					$exportDoc['ExportDocPosition'][$cnt]['amount'] 				= $exportArticle['amount'];					// how many items of this article
					$exportDoc['ExportDocPosition'][$cnt]['netWeightInKG'] 			= $exportArticle['weightInKG'];				// weight of 1 item
					$exportDoc['ExportDocPosition'][$cnt]['customsValue']			= $exportArticle['customsValue'];			// price of 1 item
					$cnt++;
				}

				// ......... If you have a V66WPI to Europe, just do not add export articles in article setup => in this case no customs section will be added here
				//           (For V53WPAK leaving export articles section empty will cause an DHL error message)
				$shipment['ShipmentOrder']['Shipment']['ExportDocument'] = array_merge ( $export , $exportDoc );
				// .........
			}
		}

		// Leitcodierung
		$shipment['ShipmentOrder']['PrintOnlyIfCodeable active'] = $this->company['PrintOnlyIfCodeable active'];	// false (true = only if using Leitcodierung: https://www.dhl.de/de/geschaeftskunden/paket/information/geschaeftskunden/abrechnung/leitcodierung.html)

		return $shipment;
		
	// END createShipmentRequest()
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	// (OPTIONAL) You may use it before using function "createDHLLabel()"
	// --------------------------------------------------------
	// Create label for this shipment request.
	// A shipment request has to be created before via createShipmentRequest
	// 
	// Return: 	array
	//				[0] 'ERR' => error string / 'MSG' => warning string / 'OK' => success
	//				[1] 'TYOE' => ERROR / WARNING / (empty)
	// --------------------------------------------------------
	public function validateDHLLabel($shipmentRequest) {
		$this->buildSoapClient();
		$response = $this->client->ValidateShipment ( $shipmentRequest );
		
		if ( is_soap_fault( $response ) || $response->status->StatusCode != 0 ) {
			if ( is_soap_fault( $response ) ) {
				$this->addError($response->faultstring); 				// faultstring = soap intern
				return array("ERR" => "VALIDATION ERROR", "TYPE" => "ERROR");
			} else {
				$this->addError($response->status->StatusMessage);		// StatusMessage = from DHL response
				return array("ERR" => "VALIDATION WARNING", "TYPE" => "WARNING");
			} 
		} else
			return array("ERR" => "OK", "TYPE" => "");
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	// Delete all labels included in this deletion request
	// --------------------------------------------------------
	public function deleteDHLLabel($shipmentRequest) {
		$this->buildSoapClient();

		try {
			$response = $this->client->deleteShipmentOrder($shipmentRequest);

		} catch (Exception $e) {
			$this->addError($e->faultstring); 					// faultstring = soap intern
			return array("ERR" => "SOAP ERROR", "MSG" => $e);
		}
		
		if ( $response->Status->statusCode != 0 )
			$this->addError($response->Status->statusText);		// StatusMessage = from DHL response
		
		return array("ERR" => "");
	}



	// --------------------------------------------------------
	// Create label for this shipment request.
	// A shipment request has to be created before via createShipmentRequest
	// 
	// Return: 	array
	//				[0]'ERR' => error string / or empty in case if success
	//				[1]'shipmentNumber (in case if success)
	//				[2]'labelUrl (in case if success)
	// --------------------------------------------------------
	public function createDHLLabel($shipmentRequest) {
		$label = array();			// array of labels and their err-status
		$this->buildSoapClient();

		// 1. VALIDATE REQUEST
		// ........................................................
		try {
			$response = $this->client->ValidateShipment ( $shipmentRequest );
//			if ($this->responseFile != "") 
			if (property_exists($this, "responseFile") && $this->responseFile != "")
				$stored = $this->storeResponseFile($response, "VALIDATE");

		} catch (Exception $e) {
			$this->addError($e->faultstring); 				// faultstring = soap intern
			return array("ERR" => "SOAP ERROR");
		}

		if ( $response->Status->statusCode != 0 ) {
			$this->addError($response->Status->statusText);
			if ( is_array($response->ValidationState->Status->statusMessage) ) { // 08.07.2021: check if array does exist
				foreach ($response->ValidationState->Status->statusMessage AS $message)
					$this->addError($message);
			}
			return array("ERR" => "VALIDATION ERROR");

		} else { // ... AFTER SUCCESSFUL VALIDATION... 
		
			// 2. CREATE LABEL
			// ........................................................
			$response = $this->client->createShipmentOrder( $shipmentRequest );
//			if ($this->responseFile != "") 
			if (property_exists($this, "responseFile") && $this->responseFile != "")
				$stored = $this->storeResponseFile($response, "CREATE");

			if ( is_soap_fault( $response ) || $response->Status->statusCode != 0 ) {

				if ( is_soap_fault( $response ) ) {
					$this->addError($response->faultstring);			// faultstring = soap intern
				} else {
					$this->addError($response->Status->statusText);
				}

				return array("ERR" => "CREATION ERROR");
							
			} else { // 3. VALIDATION + CREATION successfully done: collect request values
					// ........................................................

				$r                    	= array();
				$r['ERR']				= "";
				$r['shipmentNumber'] 	= (String) $response->CreationState->shipmentNumber;
				$r['labelUrl']       	= (String) $response->CreationState->LabelData->labelUrl;
				if ( isset($response->CreationState->LabelData->exportLabelUrl) ) // ExportDocument available?
					$r['exportLabelUrl'] = (String) $response->CreationState->LabelData->exportLabelUrl;

				return $r;
				
			}
		}
	}
	// --------------------------------------------------------



	// --------------------------------------------------------
	/*
	  function getVersion() {
		$this->buildSoapClient();
		$this->log("Response: \n");
		$response = $this->client->getVersion(array('majorRelease' => '3', 'minorRelease' => '1'));
		$this->log($response);
	  }
	  */
	// --------------------------------------------------------



	// --------------------------------------------------------
	private function buildAuthHeader() {
		$head = $this->credentials;

		$auth_params = array(
			'user'      => $this->credentials['user'],
			'signature' => $this->credentials['signature'],
			'type'      => 0
		);

		return new SoapHeader( 'http://dhl.de/webservice/cisbase', 'Authentification', $auth_params );
	}
	// --------------------------------------------------------



// .............................
// END class DHLBusinessShipment
}

?>