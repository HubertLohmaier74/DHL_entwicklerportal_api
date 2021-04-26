<?php
// ***************************************************************************************
//  (c) Hubert Lohmaier, 13.04.2021
//
//	- 26.04.2021: 	- Added function storeRequestFile()
//  				- Function expanded: setWorkingMode: 
//								3.Parameter = storing a request file 
//								4.Parameter = optional user-id in request Filename
// ***************************************************************************************

require_once('dhl_gks_labelcreator.php');
define('_CREATEREQUEST_FILE_', 'CREATE.TXT');	// for storing a request
define('_DELETEREQUEST_FILE_', 'DELETE.TXT');	// for storing a request

class DHLParcel {

	private $validSetup;			// bool: FALSE or TRUE: shows if setup is completed successfully?
	private $credentials;			// your GKS credentials (array - from addCredentials function)
	private $company;				// your company data (array - from addCompany function)
	private $customers;				// address data for all customers added (multidimensional array)
	private $shipments;				// parcel data for all shipments added (multidimensional array)
	private $export;				// export data, needed for parcels outside EU
	private $mode;					// String: "SANDBOX" or "LIVE" mode
	private $localWSDL;				// bool: FALSE or TRUE: make use of local WSDL file or get if from DHL server
	private $api_file;				// pure filename of WSDL-File (without path or URL)
	private $api_url;				// complete URL for WSDL-File (incl. URL + path + filename)
	private $storeRequests;			// Enable/Disable storing requests to file
	private $userID;				// storing requests to filename with user id

	// ----------------------------------------------------------
	// Constructor
	// ----------------------------------------------------------
	function __construct() {
		$this->validSetup 		= array("credentials"=>FALSE, "company"=>FALSE, "customers"=>FALSE, "api"=>FALSE);
		$this->credentials		= array();
		$this->company 			= array();
		$this->customers 		= array();
		$this->shipments 		= array();
		$this->export 			= array();
		$this->setWorkingMode();			// Default = SANDBOX mode
		$this->api_file			= "";
		$this->api_url			= "";
		$this->storeRequests	= FALSE;
		$this->userID			= "";
	}
	// ----------------------------------------------------------



	// ----------------------------------------------------------
	// 1. "SANDBOX" or "LIVE" mode (Default = SANDBOX)
	// 2. make use of a local WSDL file or use it from DHL server (Default = DHL SERVER)
	// 3. Enable/Disable storing requests to file
	// 4. (optional) User-ID for stored requests
	// ----------------------------------------------------------
	public function setWorkingMode($mode = "SANDBOX", $localWSDL = FALSE, $storeRequests = FALSE, $userID = "") {
		$this->mode 			= trim($mode);
		$this->localWSDL 		= (bool)$localWSDL;
		$this->storeRequests 	= (bool)$storeRequests;
		$this->userID			= trim($userID);
	}
	// ----------------------------------------------------------



	// --------------------------------------------------------
	// Store API request to the file given
	// --------------------------------------------------------
	// 1. A subdirectory "api_request" will be created if it does not exist.
	// 2. The subdirectory will created below act. directory of this file
	// 3. For not overwriting a file the filename will be expanded with 
	//    a timestamp before the DOT '.'
	//
	// PARM: 	1. an API request
	//			2. Basic Filename
	//
	// RETURN: TRUE if saved successfully, otherwise FALSE
	// --------------------------------------------------------
	private function storeRequestFile($request, $requestFile) {
		$subdirectory = "/api_request";
		$requestDir = __DIR__ . $subdirectory;
		
		// include USER-ID in Filename?
		if ($this->userID != "") 
			$userID = $this->userID . "_";
		else $userID = "";
				
		// Filename setup
		$requestFile = $requestDir . "/" . $userID . str_replace('.', date("Y-m-d H:i", time()).'.', $requestFile);
			
		if ($this->storeRequests) {

			// Create DIR if it does not exist
			if ( !is_dir($requestDir) ) {
				if ( !mkdir($requestDir) ) {
					$this->addError("Cannot create request directory on server");
					return FALSE;
				} 
			}
			
			ob_start();
			echo "REQUEST FOR USER: " . $this->userID . "\r\n\r\n";
			print_r($request);
			$request = ob_get_contents();
			ob_end_clean();
			
			file_put_contents($requestFile, $request);
		}
	}
	


	// ----------------------------------------------------------
	// $api_file = pure filename to WSDL (without path or URL)
	// $api_url = total URL (incl. https + path + filename)
	// ----------------------------------------------------------
	public function setApiLocation($api_file, $api_url) {
		$this->api_file 		 = trim($api_file);
		$this->api_url 			 = trim($api_url);
		
		if ($this->api_file != "" && $this->api_url != "")
			$this->validSetup["api"] = TRUE;
	}


	
	// ----------------------------------------------------------
	// add your dhl customer and api credentials
	// ----------------------------------------------------------
	public function addCredentials($user, $signature, $ekp, $api_user, $api_password, $teilnahme) {
		$this->credentials = array(
			'user' 			=> trim($user), 
			'signature' 	=> trim($signature), 
			'ekp' 			=> trim($ekp),
			'api_user'  	=> trim($api_user),
			'api_password'  => trim($api_password),
			'teilnahme'		=> trim($teilnahme),
			'log' 			=> TRUE										// switch to FALSE after successful testing         ############################
			);
		$this->validSetup["credentials"] = TRUE;
	}
	// ----------------------------------------------------------
	
	

	// ----------------------------------------------------------
	// add your company info
	// ----------------------------------------------------------
	public function addCompany($useDHLLeitcodierung, $company_name1, $company_name2, $company_name3, $street_name, $street_number, $zip, $city, $country, $countryISOCode, $email, $phone, $internet, $contactPerson) {
		if ($phone[0] == "+")
			$phone = "+" . trim(preg_replace('![^0-9]!', '', $phone));
		else 
			$phone = trim(preg_replace('![^0-9]!', '', $phone));
		// .....................
		$this->company = array(
			'PrintOnlyIfCodeable active'	=> $useDHLLeitcodierung, 	// DHL contractor makes use of DHL Leitcodierung (TRUE/FALSE)
			'name1'    						=> trim($company_name1),
			'name2'    						=> trim($company_name2),
			'name3'    						=> trim($company_name3),
			'street_name'     				=> trim($street_name),
			'street_number'   				=> trim($street_number),
			'zip'             				=> trim($zip),
			'city'            				=> trim($city),
			'country'         				=> trim($country),
			'countryISOCode'				=> trim($countryISOCode),
			'email'           				=> trim($email),
			'phone'           				=> $phone,
			'internet'        				=> trim($internet),
			'contactPerson'  				=> trim($contactPerson)
		);
		$this->validSetup["company"] = TRUE;
	}
	// ----------------------------------------------------------


	
	// ----------------------------------------------------------
	// add a customer
	// ----------------------------------------------------------
	//
	// if not shipped directly to customer you need to specify this PARM:
	// $dhl_receiver = "PACKSTATION", or "POSTFILIALE", or "PARCELSHOP"
	// (Only valid for NATIONAL shipments)
	//
	// This results in the following behaviour in class DHLBusinessShipment:
	//
	// for PACKSTATION the following values are used:
	// name2 => will be used as "postNumber" (up to 10 num. characters allowed)
	// name3 => will be used as "packstationNumber" (3 num. characters allowed)
	// (street_name / street_number are NOT used in this case)
	// 
	// for POSTFILIALE the following values are used:
	// name2 => will be used as "postNumber" (up to 10 num. characters allowed)
	// name3 => will be used as "postfilialNumber" (3 num. characters allowed)
	// (street_name / street_number are NOT used in this case)
	//
	// for PARCELSHOP the following values are used:
	// name2 => will be used as "parcelShopNumber" (3 num. characters allowed)
	// (name3 ist not used in this case)
	// (street_name + street_number are used if given)
	// ----------------------------------------------------------
	public function addCustomer($reference, $name1, $name2, $name3, $street_name, $street_number, $zip, $city, $province, $country, $countryISOCode, $state, $notification, $email, $phone, $dhl_receiver = "") {
		if ($phone[0] == "+")
			$phone = "+" . trim(preg_replace('![^0-9]!', '', $phone));
		else 
			$phone = trim(preg_replace('![^0-9]!', '', $phone));
		// .....................
		$customer_details = array(
			'customerReference' 	=> trim($reference),				// some reference to your customer e.g. customer no.
			'name1'    				=> trim($name1),
			'name2'     			=> trim($name2),
			'name3'           		=> trim($name3),					// (c/o = Receiver to be found at address from another person or company)
			'street_name'   		=> trim($street_name),
			'street_number' 		=> trim($street_number),
			'zip'           		=> trim($zip),
			'city'          		=> trim($city),
			'province'        		=> trim($province),
			'country'       		=> trim($country),
			'countryISOCode'   		=> trim($countryISOCode),
			'state'					=> trim($state),
			'notification'			=> (bool)$notification,				// TRUE/FALSE: allow DHL to send tracking information to your customer
			'email'					=> trim($email),
			'phone'					=> $phone,
			'dhl_receiver'			=> trim(strtoupper($dhl_receiver)),	// if not shipped directly to some customer address
			'bind'					=> (count($this->customers)+1)		// connects customer data to shipment data
		);
		
		// DHL API Update 08.04.2021: street_name & street_number can be delivered together in 1 field (street_name)
		// In this case the field street_number may not be given over to the API
		if ( $customer_details['street_number'] == "") {
			// Test if street_name contains at least more than 1 word
			$test = mb_split(" ", $customer_details['street_name']);    	// For case: "First Street 1"
			if ( count($test) == 1)
				$test = mb_split(".", $customer_details['street_name']);	// For case: "Hallstr.1"
			// Yes, we have more words in this field: 
			// Still not shure that a numeric (or alphanumeric, or numericalpha, or mixed) street_number is included but it is better than nothing
			if ( count($test) > 1) {
				$customer_details['street_number'] = '###ELIMINATE @ THIS###';
			}
		}
		
		$this->customers[] = $customer_details;	// add customer to customers array
		$this->validSetup["customers"] = TRUE;   // TRUE = at least one customer added
		// use return-value to bind customer to parcel
		return count($this->customers);
	}
	// ----------------------------------------------------------



	// ----------------------------------------------------------
	// add a shipment and bind parcel to customer
	// ----------------------------------------------------------
	public function addShipment($reference, $premium_service, $product, $verfahren, $costCentre, $weightInKG, $lengthInCM, $widthInCM, $heightInCM, $bindNo, $endorsement="") {
		
		$shipment = array(
			'customerReference' 	=> trim($reference),			// some reference to your customer e.g. customer no.
			'Premium active'		=> (bool)$premium_service, 		// TRUE/FALSE
			'product'				=> trim($product),				// e.g. V01PAK for DHL national parcel (see dhl_gks_products_list.csv)
			'verfahren'				=> trim($verfahren),			// e.g. 01 for DHL Paket national, etc.
			'costCentre'			=> trim($costCentre),			// e.g. your department
			'weightInKG'			=> trim($weightInKG),			// weight incl. packaging
			'lengthInCM'			=> trim($lengthInCM),			// length, optional
			'widthInCM'				=> trim($widthInCM),			// width, optional
			'heightInCM'			=> trim($heightInCM),			// height, optional
			'bind'					=> (int)$bindNo,				// connects customer data to shipment data
			'Endorsement active'	=> trim($endorsement),			// how to handle parcel if reveiver not met
			'timestamp'				=> time(),						// timestamp
			'shipmentDate'			=> date("Y-m-d")				// today
		);
		// ..................................
		$this->shipments[$bindNo] = $shipment;						// connects customer data to shipment data
	}
	// ----------------------------------------------------------



	// ----------------------------------------------------------
	// add export data and bind to customer
	// ----------------------------------------------------------
	public function addExport($invoiceNumber, $exportType, $exportTypeDescription, $termsOfTrade, $placeOfCommital, $additionalFee, $permitNumber, 
							  $attestationNumber, $WithElectronicExportNtfctn, $exportArticles, $bindNo) {
		$export = array (
			'invoiceNumber' 				=> $invoiceNumber,						// your customer's invoice no.
			'exportType'					=> trim($exportType),					// see dhl's CN23 form
			'exportTypeDescription'			=> $exportTypeDescription,				// very short article type description
			'termsOfTrade'					=> $termsOfTrade,						// not required at postal customs clearing (BPI)
			'placeOfCommital'				=> $placeOfCommital,					// zip + city + country: where parcel is handed over to some DHL shop
			'additionalFee'					=> $additionalFee,						// additional customs fee
			'permitNumber'					=> $permitNumber,						// customs permission number (articles > 1000 € : you have to declare the shipment to customs before shipping)
			'attestationNumber'				=> $attestationNumber,					// customs certificate number (articles > 1000 € : you have to declare the shipment to customs before shipping)
			'WithElectronicExportNtfctn active' 	
											=> (bool)$WithElectronicExportNtfctn,	// true / false: TRUE if shipment/export is communicated electronically to customs authorities
			'exportArticles'				=> $exportArticles,						// array() : list of exportArticles
			'bind'							=> (int)$bindNo							// connects customer data to export data
		);
		// ..................................
		$this->export[$bindNo] = $export;											// connects customer data to export data
	}
	// ----------------------------------------------------------


	
	// ----------------------------------------------------------
	// returns a formatted array. Use it once for each article.
	// ----------------------------------------------------------
	public function getFormattedExportArticle($name, $weight, $price, $tariff, $amount, $origin) {
		return array(
			'description' 			=> $name, 
			'countryCodeOrigin'		=> $origin,
			'customsTariffNumber'	=> $tariff, 
			'amount'				=> $amount, 
			'weightInKG'			=> $weight, 
			'customsValue'			=> $price
		);
	}
	// ----------------------------------------------------------
	
	
	
	// ----------------------------------------------------------
	// check if binding element [IDX] does exist in shipments array
	// ----------------------------------------------------------
	private function binding_exists($bind) {
		if ( isset($this->shipments[$bind]) )
			return TRUE;
		else
			return FALSE;
	}
	// ----------------------------------------------------------

	

	// ----------------------------------------------------------
	// check if this shipment is INTERNATIONAL and binding element [IDX] does exist in export array
	// 
	// (Export data is only needed if shipment destination_type == INTERNATIONAL. 
	// Otherwise always return TRUE.)
	// ----------------------------------------------------------
	private function export_exists($bind) {
		
		if ( strtoupper($this->shipment[$bind]['destination_type']) == "INTERNATIONAL") {
			if ( isset($this->export[$bind]) )
				return TRUE;
			else
				return FALSE;
		} else
			return TRUE;
	}
	// ----------------------------------------------------------

	

	// ----------------------------------------------------------
	// All needed data provided?  
	// 1. Credentials setup done?
	// 2. Company setup done?
	// 3. At least one customer added?
	// 4. To each customer there is a shipment available?
	// 5. To each international shipment there are export data available?
	//
	// return: TRUE / or Error message
	// ----------------------------------------------------------
	private function isValidSetup() {

		// 1..............................................
		if ( $this->validSetup["api"] 	 !== TRUE )
			return "NO API ACCESS SETUP: please provide file and url!";
		// 2..............................................
		if ( $this->validSetup["credentials"] 	 !== TRUE )
			return "NO CREDENTIALS SETUP";
		// 3..............................................
		if ( $this->validSetup["company"] 	 !== TRUE )
			return "NO COMPANY SETUP";
		// 4..............................................
		if ( $this->validSetup["customers"] 	 !== TRUE )
			return "NO CUSTOMER SETUP";
		// 5..............................................
		foreach ($this->customers AS $customer) {
			if ( !$this->binding_exists($customer['bind']) ) {
				return "INVALID BINDING TO CUSTOMER WITH REFERENCE: ".$customer['customerReference'];
			}
		}
		// 6..............................................
		foreach ( $this->shipments AS $shipment) {
			if ( !$this->export_exists($shipment["bind"]) )
				return "NO EXPORT DATA FOR INTERNATIONAL SHIPMENT. YOUR CUSTOMER REFERENCE: ".$shipment['customerReference'];
		}
		
		return TRUE;
	}
	// ----------------------------------------------------------



	// ----------------------------------------------------------
	// get related customer data from a certain binding
	// ----------------------------------------------------------
	private function getCustomerFromBinding($shipment) {
		$ret = FALSE;
		foreach ($this->customers AS $customer) {
			if ($customer['bind'] == $shipment['bind']) {
				$ret = $customer;
				break;
			}
		}
		return $ret;
	}
	// ----------------------------------------------------------


	// ----------------------------------------------------------
	// get related export data from a certain binding
	// ----------------------------------------------------------
	private function getExportFromBinding($shipment) {
		$ret = FALSE;
		foreach ($this->export AS $export) {
			if ($export['bind'] == $shipment['bind']) {
				$ret = $export;
				break;
			}
		}
		return $ret;
	}
	// ----------------------------------------------------------



	public function deleteLabels( $shipmentLabelList ) {

		// Delete labels in Sandbox mode?
		if ($this->mode == "SANDBOX") 
			$mode = TRUE;
		else $mode = FALSE;

		$dhl = new DHLBusinessShipment($this->credentials, $this->company, $this->api_file, $this->api_url, $mode); // Constructs object
		$dhl->setLocalWSDL($this->localWSDL); // use WSDL file from local dir or from DHL server?
		$deleteRequest = $dhl->createShipmentDeleteRequest($shipmentLabelList);
		$this->storeRequestFile($deleteRequest, _DELETEREQUEST_FILE_);
		$response = $dhl->deleteDHLLabel($deleteRequest);
		
		if ($response['ERR'] != "")
			$response = array("TYPE"=>"ERROR", "CODE"=>7, "CLSS"=>"DHLParcel Class", "REF"=>"", "MSG"=>"Could not delete Label(s)", "MORE"=>$response);
		else 
			$response = array("TYPE"=>"SUCCESS", "CODE"=>1, "CLSS"=>"DHLParcel Class", "REF"=>"", "MSG"=>"", "MORE"=>$response);
			
		return $response;
	}

	

	// ----------------------------------------------------------
	// Label creation
	//
	// $response-SETUP:
	//	- String: 	"TYPE"=>	"SUCCESS" / "ERROR"
	//	- Int:		"CODE"=> 	Return-Code: 1 = SUCCESS / other = ERROR (see below)
	//	- String: 	"CLSS"=>	CLASS-NAME which produced this error(s)
	//  - String:	"REF"=>		Your reference number
	//	- String: 	"MSG"=> 	ERROR short description
	//	- ARRAY: 	"MORE"=>	Errors that occured in detailed text
	// ----------------------------------------------------------
	public function createLabels() {
		$shipmentRequest = NULL;
		$responses = array();
		
		// Get labels in Sandbox mode?
		if ($this->mode == "SANDBOX") 
			$mode = TRUE;
		else $mode = FALSE;
		
		// create label for each shipment
		foreach ($this->shipments AS $shipment) {
			$response = array("TYPE"=>"ERROR", "CODE"=>9, "CLSS"=>"DHLParcel Class", "REF"=>$shipment['customerReference'], "MSG"=>"INTERNAL: UNDEFINED ERROR", "MORE"=>array("This error should not have appeared!")); // Default error should be overwritten
			// ...........................
			if ( ($message = $this->isValidSetup()) === TRUE) {
				$dhl = new DHLBusinessShipment($this->credentials, $this->company, $this->api_file, $this->api_url, $mode); // Constructs object
				$dhl->setLocalWSDL($this->localWSDL); // use WSDL file from local dir or from DHL server?
				
				$customer	= $this->getCustomerFromBinding($shipment);
				$export 	= $this->getExportFromBinding($shipment);

				if ($customer == FALSE)
					return array("TYPE"=>"ERROR", "CODE"=>8, "CLSS"=>"DHLParcel Class", "REF"=>$shipment['customerReference'], "MSG"=>"INTERNAL: INVALID CUSTOMER", "MORE"=>array("This error should not have appeared!"));
				
				$shipmentRequest = $dhl->createShipmentRequest($shipment, $customer , $export);
				$this->storeRequestFile($shipmentRequest, _CREATEREQUEST_FILE_);
				$response = $dhl->createDHLLabel($shipmentRequest); // Validation + Creation

				if ($response['ERR'] != "") {
					// ERROR
					$response = array("TYPE"=>$response['ERR'], "CODE"=>2, "CLSS"=>"DHLBusinessShipment Class", "REF"=>$shipment['customerReference'], "MSG"=>"COULD NOT CREATE LABEL", "MORE"=>$dhl->getErrors());
				} 
				else {  // SUCCESS
						// $response['shipmentNumber'] 	= trackingnumber
						// $response['labelUrl']		= download URL for label
						// $response['exportLabelUrl']	= download export documents (if export shipment)
						$response = array("TYPE"=>"SUCCESS", "CODE"=>1, "CLSS"=>"DHLParcel Class", "REF"=>$shipment['customerReference'], "MSG"=>"", "MORE"=>$response);
				}
				
			} else {
				$response = array("TYPE"=>"ERROR", "CODE"=>3, "CLSS"=>"DHLParcel Class", "REF"=>$shipment['customerReference'], "MSG"=>"NO VALID SETUP", "MORE"=>array($message, "You need to add (1) api access data, (2) your credentials, (3) your company data and (4) at least one customer. (5) After that you need to bind a shipment to each customer added. (6) If you ship a parcel to a foreign country outside EU than you also need to add export data."));
			}
			// ...........................
			$responses[] = $response;
		}
		return $responses;
	}
	// ----------------------------------------------------------



// .....................
} // END class DHLParcel

?>
