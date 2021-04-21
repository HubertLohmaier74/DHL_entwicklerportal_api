# DHL_entwicklerportal_api
Creating DHL labels using DHL Entwickler Portal API

Der hier vorgestellte Code ist ein PHP Wrapper für die API aus dem DHL Entwicklerportal zum automatisierten Erstellen von DHL Labels und unterstützt die WSDL Version 3.1.2

Vorab: Dieser Wrapper versteht sich nicht das Musterbeispiel für meist-elegantes Programmieren.
       Vielmehr will er auf einfachste Weise in ein Projekt integriert werden können. (Einfach ein Unterverzeichnis anlegen und alle Dateien hineinkopieren)
	   Ausserdem will er (möglichst) einfach verständlich sein.



Der Wrapper wird noch weiterentwickelt. Im Moment kann er folgende Labels erzeugen:

				- PAKET NATIONAL
				- PAKET NATIONAL (POSTFILIALE)
				- PAKET NATIONAL (PACKSTATION)
				- PAKET INTERNATIONAL (for outside EU destinations + customs doc creation)
				- PAKET INTERNATIONAL (for within EU destinations without customs doc creation)
				- WARENPOST NATIONAL
				- EUROPAKET (= B2B Shipment)

Ausserdem können erzeugte Labels storniert werden.

-------------------------------------------------------------------
VORAUSSETZUNGEN:
-------------------------------------------------------------------

1. Der Nutzer muss angemeldeter DHL Kunde beim Geschäftskundenportal sein und auch zusätzlich im DHL Entwicklerportal registriert sein.
a) https://www.dhl-geschaeftskundenportal.de
b) https://entwickler.dhl.de/

2. Im Entwicklerportal:
a) müssen unter https://entwickler.dhl.de/group/ep/mein-konto die Daten vervollständigt und entsprechende "Benachrichtigungs-Einstellungen" angekreuzt sein (z.B. unter Geschäftskundenversand)
b) muss unter https://entwickler.dhl.de/group/ep/myapps eine APP angelegt worden sein und unter "Verwendete Operationen" entsprechende Methoden angekreuzt sein (z.B. unter Geschäftskundenversand)
   Dort kann auch schon das Token angelegt werden, das für den späteren Livebetrieb benötigt wird.

-------------------------------------------------------------------
STARTUP:
-------------------------------------------------------------------

a) Öffne die dhl_gks_setup.php und ergänze die folgenden Daten:

	define( 'DHL_Entwickler_ID', '### Hier deine Entwickler ID eintragen ###');		// SANDBOX-USER
	define( 'DHL_APP_ID', '### Deine Entwickler APP-ID ###');						// LIVE-USER

	define( 'DHL_WebSitePass', '### Login für Entwicklerportal ###');			// SANDBOX-PASS
	define( 'DHL_TOKEN', '### Token ###');										// LIVE-PASS (siehe auch Punkt 2b)
	Das Token muss nach dem Anlegen der APP (im Entwicklerportal) generiert werden. Siehe unter Menü "Freigabe & Betrieb" / "Details" / "Token generieren"

b) In der dhl_gks_setup.php findet man angelegte Beispielkunden (= Empfänger), z.B.:
	Einen NATIONAL - Kunden, z.B. Sendung innerhalb Deutschlands
	Einen EUROPAKET - Kunden, z.B. Sendung von Deutschland nach Österreich (innerhalb EU)
	Einen WELTPAKET - Kunden, z.B. Sendung von D. nach Schweiz (ausserhalb EU)
	Einen EUROPAKET - Kunden (innerhalb EU)
	Anstatt der Beispielkunden können hier eigene Daten hinterlegt werden.
	
c) Zu jedem Kunden/Empfänger ist auch eine Beispiel-Sendung definiert.
   Nur aus Gründen der Übersichtlichkeit (und der Einfachheit halber) wird hier jedem Kunden derselbe "Artikel" zugeschickt.
   Man kann selbstverständlich jedem Kunden separate Artikel zuweisen.
   
d) Liegt das Zielland ausserhalb der EU, müssen Zolldaten zu jedem Artikel zugewiesen werden.
   Ausserdem muss die Sendung für den Zoll konfiguriert werden (z.B. Gesamtgewicht, etc.)
 
e) Das Paketobjekt wird erzeugt mit 

			$parcel = new DHLParcel();
	  
f) Um dhl_gks_setup.php im Sandbox-Mode laufen zu lassen, nutzen Sie den Sandbox-Switch

			$parcel->setWorkingMode("SANDBOX", ...
        
   ... sonst kann das Feld leer gelassen oder mit anderem Inhalt z.B. "LIVE" übergeben werden
		
g) DHL fordert die Nutzer der API auf, die WSDL Datei nicht ständig nachzuladen.
   Dazu gibt es hier die Möglichkeit, die WSDL Datei lokal abzuspeichern
   
			$parcel->setWorkingMode(..., _USE_LOCAL_WSDL_); 
			Werte für _USE_LOCAL_WSDL_ = TRUE/FALSE
        
   Bei TRUE wird die Datei einmal täglich vom DHL Server heruntergeladen und im Unterverzeichnis dieses Tools 
   zwischengespeichert, bzw. dort wo Sie die Dateien abgespeichert haben.
   (WICHTIG: Dazu muss das Tool am Server Schreibrechte auf dieses Verzeichnis haben)
   Ansonsten übergeben Sie FALSE. Dann wird die WSDL Datei jedes Mal direkt vom DHL Server angesprochen.

h) Danach können die Beispiel-Labels erstellt werden durch einfachen Aufruf von dhl_gks_setup.php
   Sind ihre Zugangsdaten korrekt hinterlegt, wird nun für jeden Beispielkunden ein Label erzeugt.
   Danach erhalten Sie Links zum Download, oder können die erzeugten Labels wieder löschen.
   !!! Im Sandbox-Mode sind nach Auskunft vom DHL Support die Download-Links zu den gelöschten Labels weiterhin verfübar, zumindest für geraume Zeit.
   
-------------------------------------------------------------------
Integration in ein eigenes Projekt:
-------------------------------------------------------------------

Dieser Wrapper bietet keine Datenprüfungen an. Dies muss vorab von ihrem Projekt geleistet werden.
Beispielsweise sollte geprüft werden, ob das Gesamtgewicht des Pakets >= des Gewichts der Einzelartikel ist, oder ob überhaupt ein Verpackungsgewicht übergeben wurde.
Ausserdem müssen Kommawerte bei Gewicht und Preisen mit '.' der DHL API anstatt eines Kommas zur Verfügung gestellt werden.

Sind Werte falsch oder fehlen notwendige Werte, erhalten Sie von der DHL API entsprechende Rückmeldungen.
Ihr Projekt muss diese auswerten.
In dem Fall wird auch kein Label erzeugt.


Wenn Sie den Wrapper über ein Unterverzeichnis ansprechen, müssen Sie bei einer Fehlermeldung zur Datei "dhl_gks_shipmentconfigurator.php" anstatt

        require_once("dhl_gks_shipmentconfigurator.php");
        
ggf. diese Schreibweise verwenden

        require_once(__DIR__ . "/dhl_gks_shipmentconfigurator.php");


-------------------------------------------------------------------
Unterstützte API Methoden
-------------------------------------------------------------------
Im Moment unterstützt der Wrapper lediglich die beiden Methoden zum Erstellen und Stornieren von Labels:

	- createShipmentOrder  (welche ggf. auch gleichzeitig die Exportdokumente erzeugt)
	- deleteShipmentOrder

Auf einen Tagesabschluss verzichte ich, da dieser von DHL ohnehin automatisch durchgeführt wird.
Und auch über den Sinn und Unsinn der anderen API Methoden kann man streiten.

-------------------------------------------------------------------
Noch nicht umgesetzt
-------------------------------------------------------------------
In der aktuellen Version sind die Optionen für die DHL Services noch nicht frei einstellbar.
So kann für internationale Pakete z.B. noch nicht gewählt werden, ob Premium verschickt werden soll oder nicht. (Default: Nein)
Oder ob der Empfänger benachrichtigt werden soll oder nicht. (Default: Ja)
Etc.

-------------------------------------------------------------------
# ENDE
-------------------------------------------------------------------
  
