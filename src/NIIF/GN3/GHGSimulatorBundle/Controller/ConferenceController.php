<?php

namespace NIIF\GN3\GHGSimulatorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;

use NIIF\GN3\GHGSimulatorBundle\Entity\Conference;

use NIIF\GN3\GHGSimulatorBundle\Form\Type\ConferenceType;


class ConferenceController extends Controller
{
    public function indexAction(Request $request)
    {
        $conference = new Conference();
        $conference->setParticipantLocations('asasdasd');
        
        $form = $this->createForm(new ConferenceType(), $conference);

        if ($request->getMethod() == 'POST') {
            $form->bindRequest($request);

            if ($form->isValid()) {
                // perform some action, such as saving the task to the database

                return $this->redirect($this->generateUrl('ghgsimulator'));
            }
        }

        return $this->render('NIIFGN3GHGSimulatorBundle:Conference:index.html.twig', array(
            'form' => $form->createView(),
        ));
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

    private function resolveCooridinates ($address) {
      $url = 'http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address='. $address;

      $result = $this->connectToGoogleAPI($url);
      if ($result['status'] === 'OK') {
          return array(
            'lat' => $result['results']['geometry']['location']['lat'],
            'lng' => $result['results']['geometry']['location']['lng'],
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
          $dist = $this->coord_distance($start_point, $end_point);
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
    function emissionComputing($distance, $duration, $part_num) {
      $save = 0;

      $vehicle_co2      = vehicle_co2($distance);
      $vidconf_env_co2  = vidconf_env_co2($part_num);
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
    function vehicle_co2($distance) {
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
    function vidconf_env_co2($part_num = 2) {
      if ($part_num > 2) {
        return ((CO2_EMISSION_MCU + CO2_EMISSION_GATEKEEPER) / $part_num) + CO2_EMISSION_VIDCONF_ENDPOINT_DISPLAY + CO2_EMISSION_VIDCONF_ENDPOINT;
      }
      elseif($part_num === 2) {
        return (CO2_EMISSION_GATEKEEPER / $part_num) + CO2_EMISSION_VIDCONF_ENDPOINT_DISPLAY + CO2_EMISSION_VIDCONF_ENDPOINT;
      }
    }
}
