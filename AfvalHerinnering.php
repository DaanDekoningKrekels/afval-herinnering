<?php

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

    function get_pickupdata($streetId, $houseNumber, $date)
    {


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://recycleapp.be/api/app/v1/collections?zipcodeId=2610-11002&streetId=' . $streetId . '&houseNumber=' . $houseNumber . '&fromDate=2022-07-05&untilDate=2022-07-19&size=100',
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
        echo $response;

        $pickupdata = $response;

        return $pickupdata;
    }
    //   function get_name() {
    //     return $this->name;
    //   }
}

$app = new AfvalHerinnering();
echo "hallo";
echo $app->set_token();

// var_dump($app->token);

// print(get_string_between($app->token, '<script src="', '.chunk.js"></script>'));
// preg_match('/var n\=\"(.*?)\",r\=\"\/api\/app\/v1\/assets\/\/\"/', $app->token, $match);
// echo $match[0];
// if (preg_match('/var n\=\"(.*?)\",r\=\"\/api\/app\/v1\/assets\/\"/', $app->token, $match) == 1) {
//     echo $match[1];
// }
