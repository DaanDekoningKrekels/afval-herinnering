# Afval Herinnering

TODO: Emoticons afhankelijk van soort afval?



Dit PHP script is een Slack 'bot' die medeleiding eraan herinnert om het vuil buiten te zetten op de scouts. Iedere week op woensdag wordt er een bericht geplaatst in het Slack kanaal met de mededeling dat het vuil niet mag vergeten worden en welk vuil er buiten moet.

Dit script maakt onderliggend gebruik van de RecycleApp.be als databron.

## Werking RecycleApp

De RecycleApp is een website die enkel werkt met JavaScript draaiende. Daarom kan de informatie niet zomaar gescraped worden. De API van de RecycleApp maakt gebruik van autorisatie en geeft data terug in JSON formaat. Die autorisatie-token moet eerst gevonden worden in de broncode van de webpagina.

`Homepagina ophalen` -> `main.xxx.chunk.js vinden en ophalen` -> `secret extraheren` -> `accessToken aanvragen via API` -> `Ophaalkalender API aanspreken met verkregen accessToken`

In de `set_token` functie van de `AfvalHerinnering` klasse wordt deze API-sleutel opgehaald.

## Werking script

In `config.ini.php` kunnen configuratieinstellingen opgeslagen worden.

De `AfvalHerinnering` klasse heeft de functie `set_token` om een token aan te maken voor de RecycleApp API. De token wordt in de instantie opgeslagen. De functie `get_pickupdata` retourneert het afval van de volgende ophaling afkomstig van de API.

```php
$afval = new AfvalHerinnering();

// Probeer een token te pakken te krijgen
$afval->set_token();

// Verkrijg een array met de pickupdata voor deze week
$pickupdata = $afval->get_pickupdata($config['zipcodeId'], $config['streetId'], $config['houseNumber'], date("Y-m-d"));
```

De `SlackBediener` klasse wordt geïnitialiseerd met een API-sleutel van je Slack bot. De `sendReminder` functie stuurt een bericht naar het gewenste kanaal met de informatie over de volgende afvalophaling.

```php
// Initialiseer SlackBediener klasse, API token wordt opgeslagen
$slack = new SlackBediener($config['APItoken']);

// Verstuur het bericht naar het gewenste kanaal
$slack->sendReminder($config['channelId'], $pickupdata);
```