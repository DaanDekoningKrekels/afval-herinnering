# Afval Herinnering


Dit PHP script is een Slack 'bot' die medeleiding eraan herinnert om het vuil buiten te zetten op de scouts. Iedere week op woensdag wordt er een bericht geplaatst in het Slack kanaal met de mededeling dat het vuil niet mag vergeten worden en welk vuil er buiten moet.

Dit script maakt onderliggend gebruik van de RecycleApp als databron.

## Werking RecycleApp

De RecycleApp is een website die enkel werkt met JavaScript draaiende. Daarom kan de informatie niet zomaar gescraped worden. De API van de RecycleApp maakt gebruik van autorisatie en geeft data terug in JSON formaat. Die autorisatie-token moet eerst gevonden worden in de broncode van de webpagina.

`Homepagina ophalen` -> `main.xxx.chunk.js vinden en ophalen` -> `secret extraheren` -> `accessToken aanvragen via API` -> `Ophaalkalender API aanspreken met verkregen accessToken`

