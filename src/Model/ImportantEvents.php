<?php
namespace Telltale\Model;

class ImportantEvents extends Model {

  /**
   * The "purpose" whose action created this record
   *
   * @var string
   **/
  protected $purpose;

  /**
   * The "event_type" identifies type of system that created this record.
   *
   * @var string
  **/
  protected $event_type;

  /**
   * The "int_event_type" performed. Human readable string describing the change.
   *
   * @var string
  **/
  protected $int_event_type;

  /**
   * The "event_value" performed. Human readable string describing the change.
   *
   * @var string
  **/
  protected $event_value;

  /**
   * The "b64_event_value" performed. Human readable string describing the change.
   *
   * @var string
  **/
  protected $b64_event_value;

  /**
   * The "cohort_name" performed. Human readable string describing the change.
   *
   * @var string
  **/
  protected $cohort_name;

  /**
   * When this record was created
   *
   * @var datetime|null
  **/
  protected $created_at;


  public function getPurpose() {
    return $this->purpose;
  }

  public function setPurpose(string $purpose): self {
    $this->purpose = $purpose;
    return $this;
  }

  public function getEventType() {
    return $this->event_type;
  }

  public function setEventType(string $event_type): self {
    $this->event_type = $event_type;
    return $this;
  }

  public function getIntEventType() {
    return $this->int_event_type;
  }

  public function setIntEventType(string $int_event_type): self {
    $this->int_event_type = $int_event_type;
    return $this;
  }

  public function getEventValue() {
    return $this->event_value;
  }

  public function setEventValue(string $event_value): self {
    $this->event_value = $event_value;
    return $this;
  }

  public function getB64EventValue() {
    return $this->b64_event_value;
  }

  public function setB64EventValue(string $b64_event_value): self {
    $this->b64_event_value = $b64_event_value;
    return $this;
  }

  public function getCohortName() {
    return $this->cohort_name;
  }

  public function setCohortName(string $cohort_name): self {
    $this->cohort_name = $cohort_name;
    return $this;
  }

  public function setCreatedAt(string $created_at): self {
    $this->created_at = $created_at;
    return $this;
  }

  public function getCreatedAt() {
    return $this->created_at;
  }

  /**
   * Returns an array of jsonSerialize values safe to display to the public
   *
   * @return array
   **/
  public function jsonSerialize(): array {
    return [
      'purpose' => $this->getPurpose(),
      'event_type' => $this->getEventType(),
      'int_event_type' => $this->getIntEventType(),
      'event_value' => $this->getEventValue(),
      'b64_event_value' => $this->getB64EventValue(),
      'cohort_name' => $this->getCohortName(),
      'created_at' => $this->getCreatedAt(),
    ];
  }

}