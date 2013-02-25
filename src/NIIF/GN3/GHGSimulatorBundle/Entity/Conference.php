<?php

namespace NIIF\GN3\GHGSimulatorBundle\Entity;

class Conference
{
  protected $confLocation;

  protected $confDuration;

  protected $participantLocations = array();

  function setConfLocation($location) {
    $this->confLocation = $location;
  }

  function getConfLocation() {
    return $this->confLocation;
  }

  function setConfDuration($duration) {
    $this->confDuration = $duration;
  }

  function getConfDuration() {
    return $this->confDuration;
  }

  function setParticipantLocations($participantLocation) {
    $this->particpantLocations[] = $participantLocation;
  }

  function getParticipantLocations() {
    return $this->participantLocations;
  }

  function delParticipantLocation($partipantLocationId) {
    unset($this->participantLocations[$participantLocationId]);
  }
}
