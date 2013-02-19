<?php

namespace NIIF\GN3\GHGSimulatorBundle\Entity;

class Conference
{
  protected $confernceName;

  protected $location;

  protected $duration;

  protected $participants;

  function setConferenceName($confrenceName) {
    $this->conferenceName = $confrenceName;
  }

  function getConferenceName() {
    return $this->conferenceName;
  }

  function setLocation(Location $location) {
    $this->location = $location;
  }

  function getLocation() {
    return $this->location;
  }

  function setDuration($duration) {
    $this->duration = $duration;
  }

  function getDuration() {
    return $this->duration;
  }

  function setParticipants(Paticipants $participant) {
    $this->particpants[] = $participant;
  }

  function getParticipants() {
    return $this->participants;
  }

  function delParticipant($partipantId) {
    unset($this->participants[$participantId]);
  }
}

class Participant
{
  protected $name;

  protected $location;

  function setName($name) {
    $this->name = $name;
  }

  function getName() {
    return $this->name;
  }

  function setLocation(Location $location) {
    $this->location = $location;
  }

  function getLocation() {
    return $this->location;
  }
}

class Location
{
  protected $latitude;

  protected $longitude;

  function setLatitude($latitude) {
    $this->latitude = $latitude;
  }

  function getLatitude() {
    return $this->latitude;
  }

  function setLongitude($longitude) {
    $this->longitude = $longitude;
  }

  function getLongitude() {
    return $this->longitude;
  }
}
