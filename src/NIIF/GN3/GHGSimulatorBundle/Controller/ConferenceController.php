<?php
namespace NIIF\GN3\GHGSimulatorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;

use NIIF\GN3\GHGSimulatorBundle\Entity\Conference;

use NIIF\GN3\GHGSimulatorBundle\Form\Type\ConferenceType;

class ConferenceController extends Controller
{
    //CO2 emission constants
    const CO2_EMISSION_CAR = 176; // g/km
    const CO2_EMISSION_TRAIN = 60; // g/km
    const CO2_EMISSION_AEROPLANE_800 = 160; // g/km
    const CO2_EMISSION_AEROPLANE_800_PLUS = 100; // g/km
    const CO2_EMISSION_MCU = 0.0856; // g/s
    const CO2_EMISSION_GATEKEEPER = 0.0176; // g/s
    const CO2_EMISSION_VIDCONF_ENDPOINT = 0.0073; // g/s
    const CO2_EMISSION_VIDCONF_ENDPOINT_DISPLAY = 0.0117; // g/s

    //Vehicel distance limit in km
    const CO2_DISTANCE_CAR = 300; //km
    const CO2_DISTANCE_TRAIN = 500; //km
    const CO2_DISTANCE_AEROPLANE = 800; //km

    //Average speed for the time etimate
    const CO2_AVERAGE_SPEED_AEROPLANE = 236; //m/s

    //Google map api key
    const GOOGLE_MAP_API_KEY = 'AIzaSyBjBQ3ho3wTYIDgxSa8g_3ryCpNfrSAn0U';

    private $conference;

    function __construct() {
        $this->conference = new Conference();
    }  

    public function indexAction(Request $request)
    {
        $form = $this->createForm(new ConferenceType(), $this->conference);

        if ($request->getMethod() == 'POST') {
            $form->bindRequest($request);

            if ($form->isValid()) {
              $confData = $this->calculateGHGSaving(); 

              $origins = NULL;
              $destinations = NULL;

              foreach ($confData AS $key => $value) {
                if ($key !== 'conf') {
                  $origins      .= 'new google.maps.LatLng('. $conf['lat'] .', '. $conf['lng'] .'), ';
                  $destinations .= 'new google.maps.LatLng('. $value['coord']['lat'] .', '. $value['coord']['lng'] .'), ';
                }
                else {
                  $conf['lat'] = $value['coord']['lat'];
                  $conf['lng'] = $value['coord']['lng'];
                }
              }

              return $this->render(
                'NIIFGN3GHGSimulatorBundle:Conference:saving.html.twig',
                array(
                  'data'        => $confData,
                  'origins'     => $origins,
                  'destinations'=> $destinations,
                  'gmapskey'    => self::GOOGLE_MAP_API_KEY
                )
              );
            }
        }

        return $this->render('NIIFGN3GHGSimulatorBundle:Conference:index.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    private function calculateGHGSaving() {
      $tableData['conf']['coord'] = $this->resolveCooridinates($this->conference->getConfLocation());
      foreach ($this->conference->getParticipants() AS $participant) {
        //resolve coordinate by partipant
        $tableData[$participant->getParticipantLocation()]['coord'] = $this->resolveCooridinates($participant->getParticipantLocation());
        $tableData[$participant->getParticipantLocation()]['travelBy'] = $participant->getTravelingBy();
      }
      
      foreach ($tableData AS $index => $row){
        if ($index !== 'conf') {
          $res = $this->resolveDistanceAndTime($tableData['conf']['coord'], $row['coord']);
          $tableData[$index]['duration'] = $res['duration'];
          $tableData[$index]['distance'] = $res['distance'];
        }
      }

      $numberOfParticipants = count($tableData) - 1;

      foreach ($tableData AS $index => $row) {
        if ($index !== 'conf') {
          $res = $this->emissionComputing(
            $row['distance'],
            $this->conference->getConfDurationInSeconds(),
            $row['travelBy'],
            $numberOfParticipants
          );
          $tableData[$index]['save'] = $res['save'];
          $tableData[$index]['vehicleCO2'] = $res['vehicleCO2'];
          $tableData[$index]['vidconfCO2'] = $res['vidconfCO2'];
        }
      }
      
      $tableData['conf']['summary'] = array(
        'save' => 0,
        'vehicleCO2' => 0,
        'duration' => 0
      );

      foreach ($tableData AS $index => $row) {
        if ($index !== 'conf') {
          $tableData['conf']['summary']['save'] += $row['save'];
          $tableData['conf']['summary']['vehicleCO2'] += $row['vehicleCO2'];
          $tableData['conf']['summary']['duration'] += $row['duration'];
        }
      }

      return $tableData;
    }

    private function connectToGoogleAPI($url) {
      $link = curl_init();
      curl_setopt($link, CURLOPT_URL, $url);
      curl_setopt($link, CURLOPT_RETURNTRANSFER, TRUE);

      $result = curl_exec($link);
      $http_code = curl_getinfo($link, CURLINFO_HTTP_CODE);

      $error_msg = curl_error($link);
      curl_close($link);

      if ($error_msg) {
        return $error_msg;
      }

      if ($http_code != '200') {
        return 'Http error: '. $http_code;
      }

      return json_decode($result, TRUE);

    }

    private function resolveCooridinates($address) {
      $url = 'http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address='. urlencode($address);

      $result = $this->connectToGoogleAPI($url);
      if ($result['status'] === 'OK') {
          return array(
            'lat' => $result['results'][0]['geometry']['location']['lat'],
            'lng' => $result['results'][0]['geometry']['location']['lng'],
          );
      }
      else {
        return $result['status'];
      } 
    }

    private function resolveDistanceAndTime($start_point, $end_point) {
      $url = 'http://maps.googleapis.com/maps/api/distancematrix/json'
        .'?units=metric&sensor=false'
        .'&origins='. $start_point['lat'] .','. $start_point['lng']
        .'&destinations='. $end_point['lat'] .','. $end_point['lng'];

      $result = $this->connectToGoogleAPI($url);

      if ($result['status'] === 'OK') {
        if ($result['rows'][0]['elements'][0]['status'] === 'OK') {
          return array(
            'distance' => $result['rows'][0]['elements'][0]['distance']['value'],
            'duration' => $result['rows'][0]['elements'][0]['duration']['value']
          );
        }
        elseif($result['rows'][0]['elements'][0]['status'] === 'ZERO_RESULTS') {
          $dist = $this->coordDistance($start_point, $end_point);
          $time = round($dist / self::CO2_AVERAGE_SPEED_AEROPLANE);

          if (is_float($dist) AND !empty($time)) {
            return array('distance' => $dist, 'duration' => $time);
          }
          else {
            return 'I can not calculate distance and duration!';
          }
        }
        else {
          return $result['rows'][0]['elements'][0]['status'];
        }
      }
      else {
        return $result['status'];
      }
    }

    private function coordDistance($start, $end) {
      $delta_lat = $end['lat'] - $start['lat'];
      $delta_lon = $end['lng'] - $start['lng'];

      $earth_radius = 6372797.0; //in meter

      $alpha  = $delta_lat / 2;
      $beta   = $delta_lon / 2;
      $a      = sin(deg2rad($alpha)) * sin(deg2rad($alpha)) + cos(deg2rad($start['lat'])) * cos(deg2rad($end['lat'])) * sin(deg2rad($beta)) * sin(deg2rad($beta));
      $c      = asin(min(1, sqrt($a)));
      $distance = 2 * $earth_radius * $c;
      $distance = round($distance);

      return $distance; //in meter
    }

//*****************************
    /**
     * Emission save computing
     *
     * @param $distance
     *   Participant distance from the conferende place in meter.
     * @param $duration
     *   Conference duration in second.
     *
     * @return
     *   An array which is containing the GHG emission save(key: save),
     *   the vehicle GHG emission(key: vehicleCO2)
     *   and the vidconf equipments GHG emission(key: vidconfCO2).
     */
    private function emissionComputing($distance, $duration, $travelBy, $numberOfParticipants) {
      $save = 0;

      $vehicleCO2       = $this->vehicleCO2($distance, $travelBy);
      $vidconfEnvCO2    = $this->vidconfEnvCO2($numberOfParticipants);
      $vehicleEmission = ( $distance / 1000 ) * $vehicleCO2 * 2; //round-trip vehicle CO2 emission in gramm
      $vidconfEmission = $duration * $vidconfEnvCO2; //CO2 emission in gramm
      $save             = $vehicleEmission - $vidconfEmission;

      return array('save' => $save, 'vehicleCO2' => $vehicleEmission, 'vidconfCO2' => $vidconfEmission);
    }

    /**
     * Give back the vehicle GHG emission based on the distance in g/km
     *
     * @param $distance
     *   Distance in meter.
     *
     * @return
     *   Vehicle GHG emission value in g/km.
     */
    private function vehicleCO2($distance, $travelBy = NULL) {
      $distance = $distance / 1000; //Convert meters to km

      if($travelBy != NULL) {
        if ($distance > 0 AND $distance <= self::CO2_DISTANCE_CAR) {
          return self::CO2_EMISSION_CAR;
        }
        elseif ($distance > self::CO2_DISTANCE_CAR AND $distance <= self::CO2_DISTANCE_TRAIN) {
          return self::CO2_EMISSION_TRAIN;
        }
        elseif ($distance > self::CO2_DISTANCE_TRAIN AND $distance <= self::CO2_DISTANCE_AEROPLANE) {
          return self::CO2_EMISSION_AEROPLANE_800;
        }
        elseif ($distance > self::CO2_DISTANCE_AEROPLANE) {
          return self::CO2_EMISSION_AEROPLANE_800_PLUS;
        }
        else {
          return 0;
        }
      }
      else {
        switch ($travelBy) {
          case 'car':       return self::CO2_EMISSION_CAR;
          case 'train':     return self::CO2_EMISSION_TRAIN;
          case 'plane':     return self::CO2_DISTANCE_AEROPLANE;
          case 'plane800':  return self::CO2_DISTANCE_AEROPLANE_800_PLUS;
          default:          return 0;
        } 
      }
    }

    /**
     * Give back the GHG emission value of the videoconference equipments.
     *
     * @return
     *   Amount of the MCU and the endpoints GHG emission in g/hour.
     */
    private function vidconfEnvCO2($numberOfParticipants = 2) {
      if ($numberOfParticipants > 2) {
        return ((self::CO2_EMISSION_MCU + self::CO2_EMISSION_GATEKEEPER) / $numberOfParticipants) + self::CO2_EMISSION_VIDCONF_ENDPOINT_DISPLAY + self::CO2_EMISSION_VIDCONF_ENDPOINT;
      }
      elseif($numberOfParticipants === 2) {
        return (self::CO2_EMISSION_GATEKEEPER / $numberOfParticipants) + self::CO2_EMISSION_VIDCONF_ENDPOINT_DISPLAY + self::CO2_EMISSION_VIDCONF_ENDPOINT;
      }
    }
}
