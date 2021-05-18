# DHL Geschäftskundenversand: Going LIVE - HowTo

1. https://entwickler.dhl.de/group/ep/myapps
Hier muss unter "Meine Applikationen" in der Zeile Ihrer verwendeten APP der Status "Freigabe erteilt" stehen. Tut er das?
Ansonsten müssen Sie hier erst noch die Freigabe beantragen, bevor Sie in der Produktivebene frankieren können.

2. Bei define( '_DHL_Entwickler_ID_', 'Your DHL Entwickler-ID');
müssen Sie anstatt 'Your DHL Entwickler-ID' die "AppID" eintragen, welche Sie in der gleichen Zeile wie unter Punkt 1. finden

3. Bei define( 'DHL_TOKEN', 'Your Token');
müssen Sie anstatt 'Your Token' das von DHL erhaltene Token eintragen. 

Sie finden es, wenn Sie in der APP-Zeile auf den Button "DETAILS" klicken.
Dort ganz unten muss ein Button "Token anzeigen" sein. Ist der noch nicht da, müssen Sie das Token erst beantragen.
Dazu müssen Sie erst in der Liste "Verwendete Operationen" bei Geschäftskundenversand auf das kleine rote "+" klicken.
Aktivieren Sie dort alle Checkboxes und dann klicken Sie danach auf "Token generieren" oder so ähnlich.
Danach zeigt Ihnen DHL eine längere Zeichenkette aus Zahlen, Buchstaben, etc. an. Das ist der Token.

4. Sind Sie beim DHL Geschäftskundenportal registriert? (https://www.dhl-geschaeftskundenportal.de)
Das ist Voraussetzung. 

Denn nachdem Sie mit 2. und 3. im ersten Schritt ihre Verbindung zum Entwicklerportal angegeben haben, müssen Sie der API nun auch noch die Verbindung zu Ihrem Account beim Geschäftskundenportal mitteilen, damit DHL weiß, mit wem die Verrechnung der beantragten Label vonstatten gehen soll.

* Anstatt $user = "2222222222_01"; müssen Sie anstatt "2222222222_01" hier Ihren Login-Benutzernamen zum  Geschäftskundenportal angeben - und zwar in Kleinbuchstaben.
* Anstatt $signature = "pass"; müssen Sie anstatt "pass" hier ihr Passwort für das Geschäftskundenportal - und zwar genau in der von Ihnen gewählten Schreibweise (Groß/Klein)
* Anstatt $ekp = '2222222222'; müssen Sie anstatt '2222222222' Ihre EKP Abrechnungsnummer angeben. Sie finden diese im Geschäftskundenportal, wenn Sie rechts mit der Maus über Ihren Namen fahren und und dann auf "Vertragsdaten" klicken. In der erscheinenden Seite steht dann ihre Kundennummer. Diese benötigen Sie hier.

5. Teilnahmenummer
Nun kann es nur noch an der Teilnahmenummer scheitern, falls Sie für ein und dasselbe DHL Produkt mehrere Teilnehmer beantragt haben. 
Wann das der Fall ist? Ich denke, wenn Sie beispielsweise ein Paket National mit GoGreen und ein Paket National ohne GoGreen nutzen.
Dann erfolgt die Unterscheidung des gewählten Produkts wahrscheinlich anhand der Teilnahmenummer. So meine Lesart.
Falls dieser unwahrscheinliche Fall auftritt finden Sie die Teilnahmenummer im Geschäftskundenportal unter dem in Punkt 4. genannten Link, allerdings im Reiter "Vertragspositionen". 
Sieht man sich eine der dort gelisteten Abrechnungsnummern genauer an, dann merkt man dass diese zusammengesetzt sind aus der EKP-Nummer, dem 2-stelligen Produktkürzel und danach der 2-stelligen Teilnahmenummer. Schreiben Sie die Nummer von dort einfach ab.

     TIPP: Dort sehen Sie auch, welche Produkte Sie frankieren können. 
     Falls Sie z.B. DHL Warenpost National dort nicht finden (......62..), 
     dann können Sie das Produkt hier mit dem roten Button "Vertragsdatenänderung anfragen" beantragen.
     
 ---
