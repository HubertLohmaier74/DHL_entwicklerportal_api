<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
</head>

<body>

<?php 
	// ..........................................................................................
	// .. SETUP EXAMPLE VERSION             1.2                                                ..
	// .. 20.04.2021                                                                           ..
	// .. 21.04. Upgrade to WSDL Version 3.1.2                                                 ..
	// ..                                                                                      ..
	// .. 26.04. DHL-API Update from 08.04.2021                                                ..
	// ..        'street_number' got optional field, can be set together with 'street_name'    ..
	// ..                                                                                      ..
	// .. 26.04. Requests can be saved to server                                               ..
	// ..        For this reason setWorkingMode got a 3. parameter (TRUE/FALSE)                ..
	// ..                                                                                      ..
	// .. 26.04. Request files: Naming files with (e.g. user ID) prefix                        ..
	// ..        For this reason setWorkingMode got a 4. parameter (string)             	   ..
	// ..                                                                                      ..
	// .. 06.05. New: A reference to your company may be added as last parm to addCompany()    ..
	// ..                                                                                      ..
	// .. 17.05. Some syntax / content cleaning for easier use                                 ..
	// ..                                                                                      ..
	// .. 26.01.2022                                                                           ..
	// .. 26.01. DHL WARENPOST INTERNATIONAL added (V66WPI)	                                   ..
	// .. (EU/NOT-EU: DHL does not have separate products for WARENPOST INTERNATIONAL as they  ..
	// ..  have with Parcel EU / Parcel International. So if you send to EU just do not add    ..
	// ..  export articles to the customs section. Anyway leaving this section empty for a     ..
	// ..  shipment outside EU results in a DHL error message.=                                ..
	// ..                                                                                      ..
	// ..........................................................................................
	


	// -----------------------------------------------------------------------------------------------------------------------------------------------------
	// Weiterführende Links und Infos
	// -----------------------------------------------------------------------------------------------------------------------------------------------------
	// https://entwickler.dhl.de/group/ep/foren/-/message_boards/message/2090913
	// https://entwickler.dhl.de/group/ep/foren/-/message_boards/message/2310170
	// https://cig.dhl.de/cig-wsdls/com/dpdhl/wsdl/geschaeftskundenversand-api/3.0/geschaeftskundenversand-api-3.0-testsuite.xml  //XML-RESPONSE testsuite.xml
	// https://shop.minhorst.com/know-how/datenbankentwickler/webservice-testen-am-beispiel-von-dhl   
	
	// Tabelle: DHL Produkte, Verfahren, Services
	// https://handbuch.mauve.de/DHL-Service#Unterst.C3.BCtzte_DHL-Services
	
	// -----------------------------------------------------------------------------------------------------------------------------------------------------
	
	// !!! NOTE: IF USING GERMAN CHARACTERS LIKE Ü,Ö,Ä, etc. make shure you are storing this file in UTF-8 format !!!
	// For this reason use an editor with encoding feature (e.g. Notepad++ freeware)
	
	// !!! NOTE: In the following the word "CUSTOMER" is used for "RECEIVER"
	
	// !!! NOTE: You cannot send more than 1 parcel to 1 customer. This depends on the SingleColli procedure of DHL.
	// If you need to send more than 1 parcel to 1 customer you need to create this customer a second, third, ... time
	
	// !!! NOTE: This example setup supports the following parcel types at the moment:
	//				- PAKET NATIONAL
	//				- PAKET NATIONAL to POSTFILIALE
	//				- PAKET NATIONAL to PACKSTATION
	//				- PAKET INTERNATIONAL (for outside EU destinations with customs doc creation)
	//				- PAKET INTERNATIONAL (for within EU descriptions without customs doc creation)
	//				- WARENPOST NATIONAL
	//				- EUROPAKET (= B2B Shipment)
	// (PAKET NATIONAL to PARCELSHOP is prepared but for some reasons it fails. I didn't have time yet to investigate this issue.)
	//              - DHL WARENPOST INTERNATIONAL (for inside & outside EU) 
	//                 => !! ATTENTION: This is not the German Post "Warenpost International" tariff. They also offer this product on small labels !!
	
	
	
	
	// -----------------------------------------------------------------------------------------------------------------------------------------------------
	// Organizer class for customers, shipments, exports, ...
	// -----------------------------------------------------------------------------------------------------------------------------------------------------
	require_once("dhl_gks_shipmentconfigurator.php");

	// -----------------------------------------------------------------------------------------------------------------------------------------------------
	// DEFINES 1 (these are to change for your personal use)
	// -----------------------------------------------------------------------------------------------------------------------------------------------------
	define( '_WORKING_MODE_', 'SANDBOX');							// Change this from 'SANDBOX' to 'LIVE' for production mode
	
	define( '_DHL_Entwickler_ID_', 'Your SANDBOX user-ID: look for it in your entwickler.dhl.de account');		// SANDBOX-USER
	define( '_DHL_APP_ID_', 'Your APP ID');							// LIVE-USER

	define( '_DHL_WebSitePass_', 'Your SANDBOX pass = pass for entwickler.dhl.de account');		// SANDBOX-PASS
	define( '_DHL_TOKEN_', 'Your Token');							// LIVE-PASS

	// Read this block about sufficient access rights for saving files to your server directory:
	define('_SAVE_REQUEST_TO_FILE_', TRUE);
		// All DHL requests can be saved to a subdirectory called "api_request".
		// This helps you checking the contents of a request e.g. if it has failed (etc.)
		// (Remember that the requests contain personal customer data and should be cleaned after a certain time)
		// This will not work if you are not allowed to save files to your server directory. 
		// In this case set it to "FALSE".
	define( '_USE_LOCAL_WSDL_', TRUE); 		
		// DHL asks their users not to load the WSDL file every time creating or validating a label because of traffic reasons.
		// Instead they ask for saving the WSDL to a local directory and refreshing it only if needed.
		// Setting the value to TRUE means that the WSDL file is loaded only once every day.
		// This will not work if you are not allowed to save files to your server directory. 
		// In this case choose "FALSE" for loading WSDL from DHL's server.


	// DHL CREDENTIALS SETUP
	if (_WORKING_MODE_ == "SANDBOX") {
		$user 			= '2222222222_01';					// SANDBOX-USER for GKS 
		$signature 		= 'pass';							// SANDBOX-PASS for GKS
		$ekp 			= '2222222222';						// DHL Kunden- bzw. Abrechnungsnummer
	} else {
		$user 			= 'Your LIVE-USER';					// LIVE-USER for GKS 
		$signature 		= 'Your LIVE-PASS';					// LIVE-PASS for GKS
		$ekp 			= 'Your EKP-NUMBER';				// DHL Kunden- bzw. Abrechnungsnummer
	}
	$log 			= TRUE;		// Set it to FALSE after you are shure that production mode is working fine
	$teilnahme 		= "01";		// Teilnahme-Nummer: I.d.R. "01"
								//
								// Info zu Teilnahme-Nummer / Verfahren / Kundennr.
								// --------------------------------------------------
								// Teilnahme-Nummer: Wird vom DHL Vertrieb bei der Anmeldung im Geschäftskundenportal automatisch 
								// vergeben. Zu finden in den letzten beiden Stellen der Abrechnungsnummer im GK-Portal unter Menü
								// Vertragsdaten / Vertragspositionen => Abrechnungsnummer/Produkt.
								// Hier ist z.B ein Paket National eingerichtet und ein Paket International, beide mit gleicher Abrechnungsnummer. 
								//
								// INFO: 	Die beiden Stellen davor bezeichnen das Verfahren. 
								// 			Noch weiter davor steht die DHL Kundennr.


	// -----------------------------------------------------------------------------------------------------------------------------------------------------
	// DEFINES 2 (don't change)
	// -----------------------------------------------------------------------------------------------------------------------------------------------------
	define( '_DHL_SANDBOX_URL_', 'https://cig.dhl.de/services/sandbox/soap' );				// SANDBOX ENDPOINT 
	define( '_DHL_PRODUCTION_URL_', 'https://cig.dhl.de/services/production/soap' );		// LIVE ENDPOINT 
	
	define( '_DHL_WSDL_VERSION_', '3.1.2' );
	define( '_DHL_API_FILE_', 'geschaeftskundenversand-api-'._DHL_WSDL_VERSION_.'.wsdl');
	define( '_DHL_API_DIR_', 'cig-wsdls/com/dpdhl/wsdl/geschaeftskundenversand-api/'._DHL_WSDL_VERSION_.'/');
	define( '_DHL_API_URL_', 'https://cig.dhl.de/' . _DHL_API_DIR_ . _DHL_API_FILE_ );

	if (_WORKING_MODE_ == "SANDBOX") {
		$api_user  		= _DHL_Entwickler_ID_; 		// SANDBOX/LIVE-Switch  [_DHL_Entwickler_ID_ / _DHL_APP_ID_]
		$api_password  	= _DHL_WebSitePass_;		// SANDBOX/LIVE-Switch  [_DHL_WebSitePass_ / _DHL_TOKEN_]
	} else {
		$api_user  		= _DHL_APP_ID_;
		$api_password  	= _DHL_TOKEN_;
	}

	// -----------------------------------------------------------------------------------------------------------------------------------------------------
	// Start data setup
	// -----------------------------------------------------------------------------------------------------------------------------------------------------		

if (count($_POST) == 0) {

	// ...........................................................................................................................
	// COMPANY SETUP
	$my_company_name1   = "My company name";
	$my_company_name2   = "My additional company name";
	$my_company_name3   = "My very additional company name ;-)";
	$my_street_name     = "Name of my street";
	$my_street_number   = "1";
	$my_zip             = "94577";
	$my_city            = "Winzer";
	$my_country         = "GERMANY";
	$my_countryISOCode	= "DE";						// country converted into ISO code. Also see e.g. https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
	$my_email           = "my.email@provider.de";
	$my_phone           = "012349876543";
	$my_internet        = "www.my-website.de";
	$my_contact_person  = "Responsible person's name";
	$my_use_of_leitcodierung = FALSE; 					// DHL contractor makes use of DHL Leitcodierung
	$my_costCentre		= "";							// your department, e.g. "Sales"
	$my_reference		= ""; 							// your reference

	// ...........................................................................................................................
	// 1. EXAMPLE CUSTOMER SETUP (NATIONAL)
	$n_reference	   	= "11111"; 		// unified identification number (e.g. order number), used for: 1) binding customer and shipment / 2) shows up on DHL label
	$n_product			= "V01PAK";		// DHL product name
	$n_verfahren		= "01";			// DHL Verfahren (procedure)
	$n_premium			= FALSE;		// DHL premium services (NATIONAL = always FALSE): this value is ignored for NATIONAL shipments
	$n_exportType		= "";			// not needed for national shipments
	$n_name1 			= "Max Mustermann";
	$n_name2          	= "Eingang Hinterhaus";
	$n_name3			= "";
	$n_street_name   	= "Buchenstr.";
	$n_street_number 	= "10";
	$n_zip           	= "94491";
	$n_city          	= "Hengersberg";
	$n_province			= ""; 			// not used for NATIONAL
	$n_country       	= "GERMANY";
	$n_countryISOCode 	= "DE";							// country converted into ISO code. Also see e.g. https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
	$n_state			= "";			// not used for NATIONAL
	$n_email			= "Max.Mustermann@g_mail.com";
	$n_phone			= "0990311111";
	$n_notifyCustomer	= TRUE;							// TRUE/FALSE: allow DHL to send tracking information to your customer


	// ...........................................................................................................................
	// 2. EXAMPLE CUSTOMER SETUP (EUROPAKET B2B - INSIDE EU)
	$e_reference	   	= "22222"; 		// unified identification number (e.g. order number), used for: 1) binding customer and shipment / 2) shows up on DHL label
	$e_product			= "V54EPAK";	// DHL product name
	$e_verfahren		= "54";			// Verfahren (procedure)
	$e_premium			= TRUE;			// DHL premium services (EU = always premium): this value is ignored for EU shipments
	$e_name1 			= "Hans Mustermann";
	$e_name2          	= "";
	$e_name3			= "";
	$e_street_name   	= "KAPLANGASSE";
	$e_street_number 	= "1/1";
	$e_zip           	= "2630";
	$e_city          	= "POTTSCHACH";
	$e_province			= ""; 			// not used for EU
	$e_country       	= "AUSTRIA";
	$e_countryISOCode 	= "AT";							// country converted into ISO code. Also see e.g. https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
	$e_state			= "";			// not used for EU
	$e_email			= "Hans.Mustermann@g_mx.de";
	$e_phone			= "00431234522222";
	$e_notifyCustomer	= TRUE;							// TRUE/FALSE: allow DHL to send tracking information to your customer
	// Service "endorsement" is used to specify handling if recipient is not met. The following types are allowed: 
	// For Germany (optional parameter): SOZU (Return immediately), ZWZU (2nd attempt of delivery); 
	// for International (mandatory parameter): IMMEDIATE (Sending back immediately to sender), ABANDONMENT (Abandonment of parcel at the hands of sender [free of charge])
	$e_endorsement		= "ABANDONMENT";


	// ...........................................................................................................................
	// 3. EXAMPLE CUSTOMER SETUP (INTERNATIONAL - OUTSIDE EU)
	$i_reference	   	= "33333"; 		// unified identification number (e.g. order number), used for: 1) binding customer and shipment / 2) shows up on DHL label
	$i_product			= "V53WPAK";	// DHL product name
	$i_verfahren 		= "53";			// DHL Verfahren (procedure)
	$i_premium			= FALSE;		// DHL premium services (INTERNATIONAL = premium or not: premium is more expensive of course)
	$i_name1 			= "Janine Mustermann";
	$i_name2          	= "c/o Klösterli";
	$i_name3			= "";
	$i_street_name   	= "Klösterlistutz";
	$i_street_number 	= "16";
	$i_zip           	= "3013";
	$i_city          	= "Bern";
	$i_province			= "Kanton Bern";
	$i_country       	= "SCHWEIZ";
	$i_countryISOCode 	= "CH";				// country converted into ISO code. Also see e.g. https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
	$i_state			= "";				
	$i_email			= "Janine.Mustermann@g_mx.de";
	$i_phone			= "00411234533333";
	$i_notifyCustomer	= TRUE;								// TRUE/FALSE: allow DHL to send tracking information to your customer
	$i_endorsement		= "IMMEDIATE";
	// ... and for this case also some export data needed:
	$i_exportType 				= "DOCUMENT";						// "OTHER" / "PRESENT" / "RETURN_OF_GOODS" / "COMMERCIAL_GOODS" / "DOCUMENT"
	$i_invoiceNumber 			= "1234567890";						// customers invoice number
	$i_exportTypeDescription 	= "BOOK";							// very short article type description
	$i_termsOfTrade				= "";								// only required if using postal customs clearing (BPI)
	$i_placeOfCommital			= "94491 Hengersberg, Germany";		// zip + city + country: where parcel is handed over to some DHL shop (shop address)
	$i_additionalFee 			= "0";								// additional customs fee
	$i_permitNumber				= "";								// customs permission number (articles > 1000 € : you have to declare the shipment to customs before shipping)
	$i_attestationNumber		= "";								// customs certificate number (articles > 1000 € : you have to declare the shipment to customs before shipping)
	$i_WithElectronicExportNtfctn = FALSE;							// true / false: if shipment/export is communicated electronically to customs authorities

	// ...........................................................................................................................
	// 4. EXAMPLE CUSTOMER SETUP (WARENPOST NATIONAL)
	$wn_reference	   	= "44444"; 		// unified identification number (e.g. order number), used for: 1) binding customer and shipment / 2) shows up on DHL label
	$wn_product			= "V62WP";		// DHL product name
	$wn_verfahren		= "62";			// DHL Verfahren (procedure)
	$wn_premium			= FALSE;		// DHL premium services (WARENPOST = always FALSE): this value is ignored for WARENPOST shipments
	$wn_exportType		= "";			// not needed for national shipments
	$wn_name1 			= "Maria Mustermann";
	$wn_name2          	= "";
	$wn_name3			= "";
	$wn_street_name   	= "Ruselstr.";
	$wn_street_number 	= "1";
	$wn_zip           	= "94577";
	$wn_city          	= "Winzer";
	$wn_province			= ""; 		// not used for NATIONAL
	$wn_country       	= "GERMANY";
	$wn_countryISOCode 	= "DE";							// country converted into ISO code. Also see e.g. https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
	$wn_state			= "";			// not used for NATIONAL
	$wn_email			= "Maria.Mustermann@g_mail.com";
	$wn_phone			= "0990144444";
	$wn_notifyCustomer	= TRUE;							// TRUE/FALSE: allow DHL to send tracking information to your customer

	// ...........................................................................................................................
	// 5. EXAMPLE CUSTOMER SETUP (PACKSTATION)
	$ps_reference	   	= "55555"; 		// unified identification number (e.g. order number), used for: 1) binding customer and shipment / 2) shows up on DHL label
	$ps_product			= "V01PAK";		// DHL product name
	$ps_verfahren		= "01";			// DHL Verfahren (procedure)
	$ps_premium			= FALSE;		// DHL premium services 
	$ps_exportType		= "";			// not needed for national shipments
	$ps_name1 			= "Maria Mustermann";
	$ps_name2          	= "12345678";	// customers postnumber
	$ps_name3			= "102";		// No. of DHL shop
	$ps_zip           	= "28195";
	$ps_street_name   	= "";
	$ps_street_number 	= "";
	$ps_city          	= "Bremen";
	$ps_province			= ""; 		// not used for NATIONAL
	$ps_country       	= "GERMANY";
	$ps_countryISOCode 	= "DE";							// country converted into ISO code. Also see e.g. https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
	$ps_state			= "";			// not used for NATIONAL
	$ps_email			= "Maria.Mustermann@gmail.net";
	$ps_phone			= "0990155555";
	$ps_notifyCustomer	= TRUE;							// TRUE/FALSE: allow DHL to send tracking information to your customer

	// ...........................................................................................................................
	// 6. EXAMPLE CUSTOMER SETUP (POSTFILIALE)
	$pf_reference	   	= "66666"; 		// unified identification number (e.g. order number), used for: 1) binding customer and shipment / 2) shows up on DHL label
	$pf_product			= "V01PAK";		// DHL product name
	$pf_verfahren		= "01";			// DHL Verfahren (procedure)
	$pf_premium			= FALSE;		// DHL premium services 
	$pf_exportType		= "";			// not needed for national shipments
	$pf_name1 			= "Maria Mustermann";
	$pf_name2          	= "12345678";	// customers postnumber
	$pf_name3			= "623";		// No. of DHL shop
	$pf_zip           	= "94557";
	$pf_street_name   	= "";
	$pf_street_number 	= "";
	$pf_city          	= "Niederalteich";
	$pf_province			= ""; 			// not used for NATIONAL
	$pf_country       	= "GERMANY";
	$pf_countryISOCode 	= "DE";							// country converted into ISO code. Also see e.g. https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
	$pf_state			= "";				// not used for NATIONAL
	$pf_email			= "Maria.Mustermann@gmail.net";
	$pf_phone			= "0990166666";
	$pf_notifyCustomer	= TRUE;							// TRUE/FALSE: allow DHL to send tracking information to your customer

	// ...........................................................................................................................
/*
	// 7. EXAMPLE CUSTOMER SETUP (PARCELSHOP)
	$pa_reference	   	= "77777"; 		// unified identification number (e.g. order number), used for: 1) binding customer and shipment / 2) shows up on DHL label
	$pa_product			= "V01PAK";		// DHL product name
	$pa_verfahren		= "01";			// DHL Verfahren (procedure)
	$pa_premium			= TRUE;			// DHL premium services 
	$pa_exportType		= "";			// not needed for national shipments
	$pa_name1 			= "Maria Mustermann";
	$pa_name2          	= "472";		// PARCELSHOP No.
	$pa_name3			= "";			// not used for PARCELSHOP: will be ignored
	$pa_street_name   	= "Admiralstr.";
	$pa_street_number 	= "123";
	$pa_zip           	= "28215";
	$pa_city          	= "Bremen";
	$pa_province		= ""; 			// not used for NATIONAL
	$pa_country       	= "GERMANY";
	$pa_countryISOCode 	= "DE";							// country converted into ISO code. Also see e.g. https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
	$pa_state			= "";			// not used for NATIONAL
	$pa_email			= "Maria.Mustermann@g_mail.com";
	$pa_phone			= "0990177777";
	$pa_notifyCustomer	= TRUE;							// TRUE/FALSE: allow DHL to send tracking information to your customer
*/

	// ...........................................................................................................................
	// 8. EXAMPLE CUSTOMER SETUP (WARENPOST INTERNATIONAL for outside EU)
	$wi_reference	   	= "88888"; 		// unified identification number (e.g. order number), used for: 1) binding customer and shipment / 2) shows up on DHL label
	$wi_product			= "V66WPI";		// DHL product name
	$wi_verfahren		= "66";			// DHL Verfahren (procedure)
	$wi_premium			= TRUE;			// DHL premium services 
	$wi_exportType		= "";			// not needed for national shipments
	$wi_name1 			= "Rudi Beisl";
	$wi_name2          	= "";
	$wi_name3			= "";
	$wi_zip           	= "8045";
	$wi_street_name   	= "Uetlibergstrasse";
	$wi_street_number 	= "132";
	$wi_city          	= "Zürich";
	$wi_province		= "";
	$wi_country       	= "Schweiz";
	$wi_countryISOCode 	= "CH";							// country converted into ISO code. Also see e.g. https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
	$wi_state			= "";				// not used for NATIONAL
	$wi_email			= "Rudi.Beisl@myemail.ch";
	$wi_phone			= "00341543210";
	$wi_notifyCustomer	= TRUE;							// TRUE/FALSE: allow DHL to send tracking information to your customer
	$wi_endorsement		= "ABANDONMENT";

	// 9. EXAMPLE CUSTOMER SETUP (WARENPOST INTERNATIONAL for inside EU)
	$wo_reference	   	= "99999"; 		// unified identification number (e.g. order number), used for: 1) binding customer and shipment / 2) shows up on DHL label
	$wo_product			= "V66WPI";		// DHL product name
	$wo_verfahren		= "66";			// DHL Verfahren (procedure)
	$wo_premium			= FALSE;		// DHL premium services 
	$wo_exportType		= "";			// not needed for national shipments
	$wo_name1 			= "Rudi Beisl";
	$wo_name2          	= "";
	$wo_name3			= "";
	$wo_zip           	= "1050";
	$wo_street_name   	= "Wiedner Hauptstraße";
	$wo_street_number 	= "88";
	$wo_city          	= "Wien";
	$wo_province		= "";
	$wo_country       	= "Österreich";
	$wo_countryISOCode 	= "AT";							// country converted into ISO code. Also see e.g. https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
	$wo_state			= "";				// not used for NATIONAL
	$wo_email			= "Rudi.Beisl@myemail.ch";
	$wo_phone			= "00341543210";
	$wo_notifyCustomer	= TRUE;							// TRUE/FALSE: allow DHL to send tracking information to your customer


	// ...........................................................................................................................
	// ARTICLE SETUP FOR INTERNATIONAL SHIPMENT (EXPORT)
	// ...........................................................................................................................
	// 
	// for german customs tariff numbers see: https://www.zolltarifnummern.de/
	// ...........................................................................................................................
	// only for example purpose: you have to submit these values for each shipment separately
	$article_1_name 	= "A.Barlow: Looking back";		// article's identification name (a short description in detail)
	$article_1_weight 	= "0.2"; 						// article's NETTO weight in KG without packaging
	$article_1_price 	= "12.80";						// article's price (of 1 item)
	$article_1_tariff 	= "49019900";					// customs tariff number (points will be removed later)
	$article_1_amount 	= "1";							// 1 oder more articles of the same name, price and type
	$article_1_origin 	= "DE";							// producing country (ISO-3 Code)

	$article_2_name 	= "Julius Baer, Theodor Steeger: das Ringen der Griechen um ihre Freiheit";		// article's identification name (a short description in detail)
	$article_2_weight 	= "0.16"; 						// article's NETTO weight in KG without packaging
	$article_2_price 	= "22.80";						// article's price (of 1 item)
	$article_2_tariff 	= "49019900";					// customs tariff number (points will be removed later)
	$article_2_amount 	= "2";							// 1 oder more articles of the same name, price and type
	$article_2_origin 	= "DE";							// producing country (ISO-3 Code)

	$article_3_name 	= "Something";		// article's identification name (a short description in detail)
	$article_3_weight 	= "0.8"; 						// article's NETTO weight in KG without packaging
	$article_3_price 	= "39.99";						// article's price (of 1 item)
	$article_3_tariff 	= "49019900";					// customs tariff number (points will be removed later)
	$article_3_amount 	= "1";							// 1 oder more articles of the same name, price and type
	$article_3_origin 	= "DE";							// producing country (ISO-3 Code)

	// ...........................................................................................................................
	// Only for example purpose: you have to submit these values for each shipment separately.
	// Below i will use these over and over again...
	$weightInKG	= "1";								// parcel's weight incl. packaging (!! You get an error if this weight is less than the weight of all exported articles !!)
	$lengthInCM	= "";								// optional: needed if exceeds default values
	$widthInCM 	= "";								// optional: needed if exceeds default values
	$heightInCM	= ""; 								// optional: needed if exceeds default values

	// ...........................................................................................................................
	// CREATE DHL PARCEL CLASS
	// ...........................................................................................................................
	$parcel = new DHLParcel();
	
		//call these methods only once for this object
		$parcel->setWorkingMode(_WORKING_MODE_, _USE_LOCAL_WSDL_, _SAVE_REQUEST_TO_FILE_, ""); // 1.SANDBOX/LIVE, 2. TRUE/FALSE: use an own WSDL-File from your server / 3. TRUE/FALSE: store requests to server / 4. (Optional) prefix for request filename
		$parcel->setApiLocation(_DHL_API_FILE_, _DHL_API_URL_);
		$parcel->addCredentials($user, $signature, $ekp, $api_user, $api_password, $teilnahme);
		$parcel->addCompany($my_use_of_leitcodierung, $my_company_name1, $my_company_name2, $my_company_name3, $my_street_name, $my_street_number, $my_zip, $my_city, $my_country, $my_countryISOCode, $my_email, $my_phone, $my_internet, $my_contact_person, $my_reference);
		
		// call these methods for each customer / shipment
		// 1. setup for a national receiver
		// .....................................
		$bindNo = $parcel->addCustomer($n_reference, $n_name1, $n_name2, $n_name3, $n_street_name, $n_street_number, $n_zip, $n_city, $n_province, $n_country, $n_countryISOCode, $n_state, $n_notifyCustomer, $n_email, $n_phone);
		$parcel->addShipment($n_reference, $n_premium, $n_product, $n_verfahren, $my_costCentre, $weightInKG, $lengthInCM, $widthInCM, $heightInCM, $bindNo);
		// .....................................
		
		// 2. setup example for a EU receiver
		// .....................................
		$bindNo = $parcel->addCustomer($e_reference, $e_name1, $e_name2, $e_name3, $e_street_name, $e_street_number, $e_zip, $e_city, $e_province, $e_country, $e_countryISOCode, $e_state, $e_notifyCustomer, $e_email, $e_phone);
		$parcel->addShipment($e_reference, $e_premium, $e_product, $e_verfahren, $my_costCentre, $weightInKG, $lengthInCM, $widthInCM, $heightInCM, $bindNo);
		// .....................................
		
		// 3. setup example for an international receiver
		// .....................................
		$bindNo = $parcel->addCustomer($i_reference, $i_name1, $i_name2, $i_name3, $i_street_name, $i_street_number, $i_zip, $i_city, $i_province, $i_country, $i_countryISOCode, $i_state, $i_notifyCustomer, $i_email, $i_phone);
		$parcel->addShipment($i_reference, $i_premium, $i_product, $i_verfahren, $my_costCentre, $weightInKG, $lengthInCM, $widthInCM, $heightInCM, $bindNo, $i_endorsement); // !! additional parm $i_endorsement for handling parcels that cannot be delivered !!
		// Making a list of exportArticles
		$exportArticles = array();
		// foreach of your articles shipped to this customer: {
			$exportArticles[] = $parcel->getFormattedExportArticle($article_1_name, $article_1_weight, $article_1_price, $article_1_tariff, $article_1_amount, $article_1_origin);
			$exportArticles[] = $parcel->getFormattedExportArticle($article_2_name, $article_2_weight, $article_2_price, $article_2_tariff, $article_2_amount, $article_2_origin);
		// }
		// bind export articles to this customer
		$parcel->addExport($i_invoiceNumber, $i_exportType, $i_exportTypeDescription, $i_termsOfTrade, $i_placeOfCommital, $i_additionalFee, $i_permitNumber, $i_attestationNumber, $i_WithElectronicExportNtfctn, $exportArticles, $bindNo);
		// .....................................

		// 4. setup example for WARENPOST NATIONAL receiver
		$bindNo = $parcel->addCustomer($wn_reference, $wn_name1, $wn_name2, $wn_name3, $wn_street_name, $wn_street_number, $wn_zip, $wn_city, $wn_province, $wn_country, $wn_countryISOCode, $wn_state, $wn_notifyCustomer, $wn_email, $wn_phone);
		$parcel->addShipment($wn_reference, $wn_premium, $wn_product, $wn_verfahren, $my_costCentre, $weightInKG, $lengthInCM, $widthInCM, $heightInCM, $bindNo);

		// 5. setup example for PACKSTATION (see additional parameter in addCustomer)
		$bindNo = $parcel->addCustomer($ps_reference, $ps_name1, $ps_name2, $ps_name3, $ps_street_name, $ps_street_number, $ps_zip, $ps_city, $ps_province, $ps_country, $ps_countryISOCode, $ps_state, $ps_notifyCustomer, $ps_email, $ps_phone, "PACKSTATION");
		$parcel->addShipment($ps_reference, $ps_premium, $ps_product, $ps_verfahren, $my_costCentre, $weightInKG, $lengthInCM, $widthInCM, $heightInCM, $bindNo);

		// 6. setup example for POSTFILIALE (see additional parameter in addCustomer)
		$bindNo = $parcel->addCustomer($pf_reference, $pf_name1, $pf_name2, $pf_name3, $pf_street_name, $pf_street_number, $pf_zip, $pf_city, $pf_province, $pf_country, $pf_countryISOCode, $pf_state, $pf_notifyCustomer, $pf_email, $pf_phone, "POSTFILIALE");
		$parcel->addShipment($pf_reference, $pf_premium, $pf_product, $pf_verfahren, $my_costCentre, $weightInKG, $lengthInCM, $widthInCM, $heightInCM, $bindNo);
/*
		// 7. setup example for PARCELSHOP (see additional parameter in addCustomer)
		$bindNo = $parcel->addCustomer($pa_reference, $pa_name1, $pa_name2, $pa_name3, $pa_street_name, $pa_street_number, $pa_zip, $pa_city, $pa_province, $pa_country, $pa_countryISOCode, $pa_state, $pa_notifyCustomer, $pa_email, $pa_phone, "PARCELSHOP");
		$parcel->addShipment($pa_reference, $pa_premium, $pa_product, $pa_verfahren, $my_costCentre, $weightInKG, $lengthInCM, $widthInCM, $heightInCM, $bindNo);
*/
		// 8. setup example for WARENPOST INTERNATIONAL to outside EU
		$bindNo = $parcel->addCustomer($wi_reference, $wi_name1, $wi_name2, $wi_name3, $wi_street_name, $wi_street_number, $wi_zip, $wi_city, $wi_province, $wi_country, $wi_countryISOCode, $wi_state, $wi_notifyCustomer, $wi_email, $wi_phone);
		$parcel->addShipment($wi_reference, $wi_premium, $wi_product, $wi_verfahren, $my_costCentre, $weightInKG, $lengthInCM, $widthInCM, $heightInCM, $bindNo, $wi_endorsement); // !! additional parm $i_endorsement for handling parcels that cannot be delivered !!
		// Making a list of exportArticles
		unset($exportArticles);
		$exportArticles = array();
		// foreach of your articles shipped to this customer: {
			$exportArticles[] = $parcel->getFormattedExportArticle($article_3_name, $article_3_weight, $article_3_price, $article_3_tariff, $article_3_amount, $article_3_origin);
		// }
		$parcel->addExport($i_invoiceNumber, $i_exportType, $i_exportTypeDescription, $i_termsOfTrade, $i_placeOfCommital, $i_additionalFee, $i_permitNumber, $i_attestationNumber, $i_WithElectronicExportNtfctn, $exportArticles, $bindNo);

		// 9. setup example for WARENPOST INTERNATIONAL to outside EU (no customs)
		$bindNo = $parcel->addCustomer($wo_reference, $wo_name1, $wo_name2, $wo_name3, $wo_street_name, $wo_street_number, $wo_zip, $wo_city, $wo_province, $wo_country, $wo_countryISOCode, $wo_state, $wo_notifyCustomer, $wo_email, $wo_phone);
		$parcel->addShipment($wo_reference, $wo_premium, $wo_product, $wo_verfahren, $my_costCentre, $weightInKG, $lengthInCM, $widthInCM, $heightInCM, $bindNo);


		// CREATE ALL LABELS !!
		$response = $parcel->createLabels();

	// ...........................................................................................................................
	// ...........................................................................................................................
	

// -------------------------
// NOW EVALUATE API RESPONSE
// -------------------------


	$labelCnt = 1;
	$bAtLeastOneSuccess=FALSE;
	echo "<h3>YOUR LABELS:</h3>"; 
	echo "<br>Mode / Credentials: " . _WORKING_MODE_ . " / " . $api_user. " / " . $api_password;
	echo "<pre>";
	echo "<form action='dhl_gks_setup.php' method='post'>";
	foreach ($response AS $r) {
		echo "<div style='clear: both; margin-top: 30px; padding: 5px; max-width: 300px; font-weight: bold;'>";
		echo "LABEL No. $labelCnt / Your reference: " . $r["REF"];
		echo "</div>";
		if ($r["TYPE"] != "SUCCESS") {
			echo "<hr>RESPONSE:<br>"; var_dump($r);
		} else {
			$bAtLeastOneSuccess = TRUE;
			echo "<div style='clear: both; border-style: solid; padding: 10px; max-width: 300px; font-weight: bold;'>";
			echo "<br>SHIPMENT NO: " . $r["MORE"]["shipmentNumber"];
			echo "<br>";
			echo "<br><a target='_blank' href='".$r["MORE"]["labelUrl"]."'>LABEL DOWNLOAD LINK</a>";
			if ( isset($r["MORE"]['exportLabelUrl']) ) {
				echo "<br>";
				echo "<br>Export documents";
				echo "<br><a target='_blank' href='".$r["MORE"]["exportLabelUrl"]."'>EXPORT DOWNLOAD LINK</a>";
			}
			echo "<br>";
			echo "<br>";
			echo "Dieses Label stornieren: <input type='checkbox' id='".$r["REF"]."' name='".$r["REF"]."' value='".$r["MORE"]["shipmentNumber"]."'>";
			echo "</div>";
		}
		$labelCnt++;
	}
	echo "</pre>";
	if ($bAtLeastOneSuccess)
		echo "<button type='submit'>Label-Auswahl stornieren</button>";
	echo "</form>";
	echo "<br><br>";
	echo "<hr>";
	unset($parcel);

} else { 	// Label cancelling of previously created labels ?
			// ------------------------------------------------
			// !!! When deleting SANDBOX labels you get success reply but they remain still available (for at least some time as DHL support says)
	echo "<pre>";
	foreach ($_POST AS $label) {
		echo "<br>";
		echo "CANCEL LABEL: ";
		var_dump($label);
	}

	echo "<br>";
	echo "<br>";
	$parcelDelete = new DHLParcel();
	$parcelDelete->setWorkingMode("SANDBOX", _USE_LOCAL_WSDL_); // 1.SANDBOX/LIVE, 2.TRUE/FALSE
	$parcelDelete->setApiLocation(_DHL_API_FILE_, _DHL_API_URL_);
	$parcelDelete->addCredentials($user, $signature, $ekp, $api_user, $api_password, $teilnahme);
	$parcelDelete->addCompany($my_use_of_leitcodierung, $my_company_name1, $my_company_name2, $my_company_name3, $my_street_name, $my_street_number, $my_zip, $my_city, $my_country, $my_countryISOCode, $my_email, $my_phone, $my_internet, $my_contact_person);
	$response = $parcelDelete->deleteLabels($_POST);
	
	var_dump($response);
	echo "</pre>";
	unset($parcelDelete);
}

	echo "<hr><b><a href='dhl_gks_setup.php'> TRY AGAIN </a></b>";

?>
</body>
</html>
