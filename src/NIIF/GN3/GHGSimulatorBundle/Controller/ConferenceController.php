<?php

namespace NIIF\GN3\GHGSimulatorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;

use NIIF\GN3\GHGSimulatorBundle\Entity\Conference;

use NIIF\GN3\GHGSimulatorBundle\Form\Type\ConferenceType;


class ConferenceController extends Controller
{
    function __construct() {
      //Google map api key
      define('GOOGLE_MAP_API_KEY', 'AIzaSyBjBQ3ho3wTYIDgxSa8g_3ryCpNfrSAn0U');
      //
      ////CO2 emission constants
      define('CO2_EMISSION_CAR', 176); // g/km
      define('CO2_EMISSION_TRAIN', 60); // g/km
      define('CO2_EMISSION_AEROPLANE_800', 160); // g/km
      define('CO2_EMISSION_AEROPLANE_800_PLUS', 100); // g/km
      define('CO2_EMISSION_MCU', 0.0856); // g/s
      define('CO2_EMISSION_GATEKEEPER', 0.0176); // g/s
      define('CO2_EMISSION_VIDCONF_ENDPOINT', 0.0073); // g/s
      define('CO2_EMISSION_VIDCONF_ENDPOINT_DISPLAY', 0.0117); // g/s
      //
      ////Vehicel distance limit in km
      define('CO2_DISTANCE_CAR', 300);
      define('CO2_DISTANCE_TRAIN', 500);
      define('CO2_DISTANCE_AEROPLANE', 800);
      //
      ////Average speed for the time etimate
      define('CO2_AVERAGE_SPEED_AEROPLANE', 236); //m/s
    }

    public function indexAction(Request $request)
    {
        $conference = new Conference();
        
        $form = $this->createForm(new ConferenceType(), $conference);

        if ($request->getMethod() == 'POST') {
            $form->bindRequest($request);

            if ($form->isValid()) {

              $duration = $conference->getConfDuration()->format('H:i');
              $durTemp = explode(':', $duration);
              $duration = ($durTemp[0] * 60) + $durTemp[1];

              $confData = $this->calculateGHGSaving(
                $conference->particpantLocations,
                $conference->getConfLocation(),
                $duration
              ); 

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
                  'gmapskey'    => GOOGLE_MAP_API_KEY
                )
              );
            }
        }

        return $this->render('NIIFGN3GHGSimulatorBundle:Conference:index.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    private function calculateGHGSaving($participantLocations, $confLocation, $duration) {
      
      $tableData['conf']['coord'] = $this->resolveCooridinates($confLocation);
      foreach ($participantLocations AS $participant) {
        //resolv coordinate by partipant
        $tableData[$participant]['coord'] = $this->resolveCooridinates($participant);
      }
      
      foreach ($tableData AS $index => $row){
        if ($index !== 'conf') {
          $res = $this->resolveDistanceAndTime($tableData['conf']['coord'], $row['coord']);
          $tableData[$index]['duration'] = $this->humanTimeFormat($res['duration']);
          $tableData[$index]['distance'] = $res['distance'];
        }
      }

      $numberOfParticipants = count($tableData) - 1;

      foreach ($tableData AS $index => $row) {
        if ($index !== 'conf') {
          $res = $this->emissionComputing($row['distance'], $duration, $numberOfParticipants);
          $tableData[$index]['save'] = $res['save'];
          $tableData[$index]['vehicle_co2'] = $res['vehicle_co2'];
          $tableData[$index]['vidconf_co2'] = $res['vidconf_co2'];
        }
      }

      #var_dump($tableData);
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
          $time = round($dist / CO2_AVERAGE_SPEED_AEROPLANE);

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
     *   the vehicle GHG emission(key: vehicle_co2)
     *   and the vidconf equipments GHG emission(key: vidconf_co2).
     */
    private function emissionComputing($distance, $duration, $part_num) {
      $save = 0;

      $vehicle_co2      = $this->vehicle_co2($distance);
      $vidconf_env_co2  = $this->vidconf_env_co2($part_num);
      $vehicle_emission = ( $distance / 1000 ) * $vehicle_co2 * 2; //round-trip vehicle CO2 emission in gramm
      $vidconf_emission = $duration * $vidconf_env_co2; //CO2 emission in gramm
      $save             = $vehicle_emission - $vidconf_emission;

      return array('save' => $save, 'vehicle_co2' => $vehicle_emission, 'vidconf_co2' => $vidconf_emission);
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
    private function vehicle_co2($distance) {
      $distance = $distance / 1000;
      if ($distance > 0 AND $distance <= CO2_DISTANCE_CAR) {
        return CO2_EMISSION_CAR;
      }
      elseif ($distance > CO2_DISTANCE_CAR AND $distance <= CO2_DISTANCE_TRAIN) {
        return CO2_EMISSION_TRAIN;
      }
      elseif ($distance > CO2_DISTANCE_TRAIN AND $distance <= CO2_DISTANCE_AEROPLANE) {
        return CO2_EMISSION_AEROPLANE_800;
      }
      elseif ($distance > CO2_DISTANCE_AEROPLANE) {
        return CO2_EMISSION_AEROPLANE_800_PLUS;
      }
      else {
        return 0;
      }
    }

    /**
     * Give back the GHG emission value of the videoconference equipments.
     *
     * @return
     *   Amount of the MCU and the endpoints GHG emission in g/hour.
     */
    private function vidconf_env_co2($part_num = 2) {
      if ($part_num > 2) {
        return ((CO2_EMISSION_MCU + CO2_EMISSION_GATEKEEPER) / $part_num) + CO2_EMISSION_VIDCONF_ENDPOINT_DISPLAY + CO2_EMISSION_VIDCONF_ENDPOINT;
      }
      elseif($part_num === 2) {
        return (CO2_EMISSION_GATEKEEPER / $part_num) + CO2_EMISSION_VIDCONF_ENDPOINT_DISPLAY + CO2_EMISSION_VIDCONF_ENDPOINT;
      }
    }

    /**
     * Convert seconds to days hours minutes second fromat
     *
     * @param $seconds
     *   Seconds to convert.
     *
     * @return
     *   Return whit string which is containing the input seconds converted to "# day # hour # minute # second" format.
     */
    private function humanTimeFormat($seconds) {
      $time = '';

      $minutes  = floor( $seconds / 60);
      $hours    = floor( $minutes / 60 );
      $days     = floor( $hours / 24 );
      $years    = floor( $days / 365 );

      $display_seconds  = $seconds - ( $minutes * 60 );
      $display_minutes  = $minutes - ( $hours * 60 );
      $display_hours    = $hours - ( $days * 24 );
      $display_days     = $days - ( $years * 365 );

      $time .= $years           ? $this->get('translator')->transChoice('1 year|%count% years', 2, array('%count%' => $years)) .' ' : NULL;
      $time .= $display_days    ? $this->get('translator')->transChoice('1 day|%count% days', 2, array('%count%' => $display_days)) .' ' : NULL;
      $time .= $display_hours   ? $this->get('translator')->transChoice('1 hour|%count% hours', 2, array('%count%' => $display_hours)) .' ' : NULL;
      $time .= $display_minutes ? $this->get('translator')->transChoice('1 minute|%count% minutes', 2, array('%count%' => $display_minutes)) .' ' : NULL;
      $time .= $display_seconds ? $this->get('translator')->transChoice('1 second|%count% seconds', 2, array('%count%' => $display_seconds)) : NULL;

      return $time;
    }
}
