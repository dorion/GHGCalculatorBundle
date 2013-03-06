<?php

namespace NIIF\GN3\GHGSimulatorBundle\Entity;

class Participant
{
  protected $participantLocation;

  protected $travelingBy;

  function setParticipantLocation($participantLocation) {
    $this->participantLocation = $participantLocation;
  }

  function getParticipantLocation() {
    return $this->participantLocation;
  }

  function setTravelingBy($travelingBy) {
    $this->travelingBy = $travelingBy;
  }

  function getTravelingBy() {
    return $this->travelingBy;
  }

}
