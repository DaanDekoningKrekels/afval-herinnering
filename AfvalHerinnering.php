<?php

/**
 * @file AfvalHerinnering.php
 * @author Daan Dekoning Krekels
 * @date July 2022
 * @brief Dit PHP script is een Slack 'bot' die medeleiding eraan herinnert om het vuil buiten te zetten op de scouts.
 */

$config = parse_ini_file('config.ini.php');

/**
 * Deze klasse maakt het mogelijk om voor recycleapp.be de ophaalkalender te raadplegen.
 */
class AfvalHerinnering
{
    /**
     * Vraag de informatie op van de volgende afvalophaling.
     *
     * @param string $zipcodeId : Specifiek type postcode bv: 2610-11002
     * @param string $streetId : Specifieke url gelinkt aan je straat https://data.vlaanderen.be/id/xxx
     * @param int|string $houseNumber : Huisnummer
     * @param date("T-m-d") $data : Datum uit een week waar we de volgende ophaling willen weten 
     * @return array Array met datum van ophaling en de verschillende afvaltypes 
     */
    function get_pickupdata($zipcodeId, $streetId, $houseNumber, $data)
    {
        $fromDate = $data; // 2022-07-05 YYYY-mm-dd
        $nextPickup = new DateTime($data); // Komende donderdag waarop het vuil wordt opgehaald 
        $nextPickup = $nextPickup->modify('next thursday')->format('Y-m-d');
        $untilDate = date('Y-m-d', strtotime($data . ' + 14 days')); // 2022-07-05 +2 dagen


        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.fostplus.be/recyclecms/public/v1/collections?zipcodeId=' . $zipcodeId . '&streetId=' . $streetId . '&houseNumber=' . $houseNumber . '&fromDate=' . $fromDate . '&untilDate=' . $untilDate . '&size=100',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'User-Agent:  Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:102.0) Gecko/20100101 Firefox/102.0',
                'Accept:  application/json, text/plain, */*',
                'Referer:  https://recycleapp.be/home',
                'x-consumer:  recycleapp.be',
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        // echo $response;

        $pickupdata = json_decode($response, true);
        // Template voor data volgende ophaling
        $cleanedPickupdata = array(
            "date" => $nextPickup, // Datum van ophaling
            "afvaltype" => array(), // Array voor alle type afval (PMD, REST, ...)
        );
        // echo print_r($pickupdata);
        echo "\n--- Volgende ophaling ---";
        // Door alle items in de data gaan
        // Dit is data voor  weken, we willen enkel de volgende ophaling
        foreach ($pickupdata['items'] as $key => $afvaltype) {
            // Als het enkel de volgende ophaling betreft
            if (date('Y-m-d', strtotime($afvaltype['timestamp'])) == $nextPickup) {
                // Even weergeven in terminal
                echo "\n" . $key . "\t";
                echo $afvaltype['fraction']['name']['nl'];

                // Naam van afvaltype toevoegen aan vooraf gemaakte template
                $cleanedPickupdata["afvaltype"][] = $afvaltype['fraction']['name']['nl'];
            }
        }

        return $cleanedPickupdata;
    }
}


/**
 * Deze klasse maakt het mogelijk om een bericht met de afvalinformatie te plaatsen in een slack kanaal
 */
class SlackBediener
{
    function __construct($APItoken, $WebHook)
    {
        $this->APItoken = $APItoken;
        $this->WebHook = $WebHook;
    }

    /**
     *  Stuurt naar een specifiek kanaal (waar de bot toegang to heeft) een bericht met welk afval er die week kan worden verwacht.
     *
     * @param string $channelId : Het ID van een Slack kanaal, einde van een URL 
     * @param array $pickupdata : De array die je krijgt na een succesvolle aanvraag uit `AfvalHerinnering->get_pickupdata()`
     * @return void : Informatie afhankelijk van de response van Slack
     */
    function sendReminder($channelId, $pickupdata)
    {

        $afval = "";
        foreach ($pickupdata["afvaltype"] as $key => $value) {
            // Ieder type afval dat wordt meegegeven uit de databank wordt in een string gezet
            // Hier kan eventueel nog een emoticon worden toegevoegd afhankelijk van het type afval.
            if ($key != 0) {
                // Een komma behalve voor het eerste type afval
                $afval .= ", ";
            }
            $afval .= $value;
        }

        $specific_waste = "";
        if ($afval != "") {
            // Als de $afval variabele nog leeg is dan is er iets misgelopen en voegen we dit stukje niet toe
            // Recycleapp.be kan zijn API op ieder moment aanpassen of niet meer laten werken...
            $specific_waste = '\nPS. *Het is deze week: ' . $afval . '*.';
        }

        // Array in 'blocks' formaat voor Slack om berichten vorm te geven
        $content = array(array(
            'type' => 'section',
            'text' => array(
                'type' => 'mrkdwn',
                'text' => 'Reminder: "Om rattenvoer te beperken wordt het vuil elke woensdag buitengezet door tak van dienst. Zij waren uiteraard al van plan om dit klusje vanavond te klaren! :wastebasket::rat: ' . $specific_waste . '"'
            )
        ));

        // var_dump($content);

        // Slack bericht plaatsen via een POST request
        $curl = curl_init();
        $params = array(
            CURLOPT_URL => 'https://slack.com/api/chat.postMessage',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'channel=' . $channelId . '&blocks=' . urlencode(str_replace("\\n", "n", json_encode($content))) . '&text=' . urlencode(str_replace("\\n", "", $content[0]['text']['text'])) . '&pretty=1',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $this->APItoken,
                'Content-Type: application/x-www-form-urlencoded'
            ),
        );
        curl_setopt_array($curl, $params);

        $response = json_decode(curl_exec($curl), true);


        curl_close($curl);
        if ($response["ok"] == true) {
            echo "\nHet bericht is succesvol geplaatst!\n\t" . $response["message"]["text"];
        } else {
            echo "\nEr ging wat mis :(( \n";
            var_dump($response);
        }

        // Discrod bericht plaatsen via een POST request
        $curl = curl_init();
        $params = array(
            CURLOPT_URL => $this->WebHook . "/slack",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'channel=' . $channelId . '&blocks=' . urlencode(str_replace("\\n", "n", json_encode($content))) . '&text=' . urlencode(str_replace("\\n", "", $content[0]['text']['text'])) . '&pretty=1',
        );
        curl_setopt_array($curl, $params);

        $response = json_decode(curl_exec($curl), true);


        curl_close($curl);
        if ($response["ok"] == true) {
            echo "\nHet bericht is succesvol geplaatst!\n\t" . $response["message"]["text"];
        } else {
            echo "\nEr ging wat mis :(( \n";
            var_dump($response);
        }
    }
}


$afval = new AfvalHerinnering();

// Verkrijg een array met de pickupdata voor deze week
$pickupdata = $afval->get_pickupdata($config['zipcodeId'], $config['streetId'], $config['houseNumber'], date("Y-m-d"));

// var_dump($pickupdata);

// Initialiseer SlackBediener klasse, API token wordt opgeslagen
$slack = new SlackBediener($config['APItoken'], $config['WebHook']);

// Verstuur het bericht naar het gewenste kanaal
$slack->sendReminder($config['channelId'], $pickupdata);


/*
 TODO: Emoticons afhankelijk van soort afval?
*/
