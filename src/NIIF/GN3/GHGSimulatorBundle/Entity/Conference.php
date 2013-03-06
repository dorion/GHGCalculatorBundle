<?php
namespace NIIF\GN3\GHGSimulatorBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

class Conference
{
  protected $confLocation;

  protected $confDuration;

  protected $participants;

  public function __construct()
  {
    $this->participants = new ArrayCollection();
  }

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

  function getConfDurationInSeconds() {
    $duration = $this->confDuration->format('H:i');
    $durTemp = explode(':', $duration);
    return ($durTemp[0] * 60) + $durTemp[1];
  }

  function setParticipants(ArrayCollections $participants) {
    $this->participants = $participants;
  }

  function getParticipants() {
    return $this->participants;
  }

}
