<?php

$config = parse_ini_file('config.ini.php');

function get_string_between($string, $start, $end)
{
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

class AfvalHerinnering
{
    // Properties
    public $token;
    //   public $color;
    private $hostname = "https://recycleapp.be/";

    // Methods
    function set_token()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->hostname,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        // Extraheer de link naar het gewenste .js bestand (bv. static/js/main.55996be8.chunk.js)
        preg_match('/static\/js\/main\..*?\.chunk\.js/', $response, $match);

        /*
            We hebben het gewenste .js bestand
            Nu secret achterhalen dat verstopt zit in het .js bestand
        */
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->hostname . $match[0],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        // Extraheer de secret om een token te kunnen aanvragen voor de API
        preg_match('/var n\=\"(.*?)\",r\=\"\/api\/app\/v1\/assets\/\"/', $response, $match);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://recycleapp.be/api/app/v1/access-token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'x-secret: ' . $match[1],
                'x-consumer: recycleapp.be',
                'Accept: application/json, text/plain, */*'
            ),
        ));

        $response = curl_exec($curl);
        $token = json_decode($response, true)['accessToken'];

        curl_close($curl);
        // echo $response;


        $this->token = $token;
        return $this->token;
    }

    function get_pickupdata($zipcodeId, $streetId, $houseNumber, $data)
    {
        $fromDate = $data; // 2022-07-05 YYYY-mm-dd
        $nextPickup = new DateTime($data); // Komende donderdag waarop het vuil wordt opgehaald 
        $nextPickup = $nextPickup->modify('next thursday')->format('Y-m-d');
        $untilDate = date('Y-m-d', strtotime($data . ' + 14 days')); // 2022-07-05 +2 dagen


        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://recycleapp.be/api/app/v1/collections?zipcodeId=' . $zipcodeId . '&streetId=' . $streetId . '&houseNumber=' . $houseNumber . '&fromDate=' . $fromDate . '&untilDate=' . $untilDate . '&size=100',
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
                'Authorization: ' . $this->token
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
    //   function get_name() {
    //     return $this->name;
    //   }
}



class SlackBediener
{
    function __construct($APItoken)
    {
        $this->APItoken = $APItoken;
    }

    function sendReminder($channelId, $pickupdata)
    {
        $afval = "";
        foreach ($pickupdata["afvaltype"] as $key => $value) {
            if ($key != 0) {
                $afval .= ", ";
            }
            $afval .= $value;
        }
        $content = array(array(
            'type' => 'section',
            'text' => array(
                'type' => 'mrkdwn',
                'text' => 'Reminder: "Om rattenvoer te beperken wordt het vuil elke woensdag buitengezet door tak van dienst. Zij waren uiteraard al van plan om dit klusje vanavond te klaren! :wastebasket::rat:\nPS. *Het is deze week: ' . $afval . '*'
            )
        ));

        var_dump($content);

        var_dump(str_replace("\\n", "\n", json_encode($content)));
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
            CURLOPT_POSTFIELDS => 'channel=' . $channelId . '&blocks=' . urlencode(str_replace("\n", "\\n", json_encode($content))) . '&pretty=1',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $this->APItoken,
                'Content-Type: application/x-www-form-urlencoded'
            ),
        );

        var_dump($params);

        curl_setopt_array($curl, $params);
        $response = curl_exec($curl);

        curl_close($curl);
        echo $response;
    }
}

$app = new AfvalHerinnering();

$app->set_token();

$pickupdata = $app->get_pickupdata($config['zipcodeId'], $config['streetId'], $config['houseNumber'], date("Y-m-d"));

var_dump($pickupdata);

$slack = new SlackBediener($config['APItoken']);

$slack->sendReminder($config['channelId'], $pickupdata);


/*
 TODO: Fix \\n in bericht
 TODO: Bericht fallback tekst toevoegen (voor meldingen)
 TODO: Emoticons afhankelijk van soort afval?
*/





// var_dump($app->token);

// print(get_string_between($app->token, '<script src="', '.chunk.js"></script>'));
// preg_match('/var n\=\"(.*?)\",r\=\"\/api\/app\/v1\/assets\/\/\"/', $app->token, $match);
// echo $match[0];
// if (preg_match('/var n\=\"(.*?)\",r\=\"\/api\/app\/v1\/assets\/\"/', $app->token, $match) == 1) {
//     echo $match[1];
// }
