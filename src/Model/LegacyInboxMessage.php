<?php
namespace Telltale\Model;

class LegacyInboxMessage implements \JsonSerializable {

  private $id;
  private $origin;
  private $status;
  private $activity_date;
  private $created_at;
  private $updated_at;
  private $title;
  private $content;
  private $body;
  private $format;
  private $template;
  private $actions;

  public function __construct() {

    // Decode and unpack the content field
    if ($this->content) {
      $content = array_change_key_case(json_decode(base64_decode($this->content, true), true), CASE_LOWER);
      
      foreach ($content as $field => $value) {
        $this->$field = $value;
      }
      
      $this->format = 'html'; // means $this->body is a string

      if (is_array($this->body)) {

        $this->format = 'json'; // means $this->body is an array

        // Can't trust body to be all lower case
        $body = array_change_key_case($this->body, CASE_LOWER);

        // Sometimes template can be nested in body as 'type'
        if (array_key_exists('type', $body)) {
          $this->template = $body['type'];
        }

        // Sometimes actions can be nested in body
        if (array_key_exists('actions', $body)) {
          $this->actions = $body['actions'];
        }

      }
    }
  }

  public function __get($prop) {
    if (property_exists($this, $prop)) {
      return $this->$prop;
    }
  }

  /**
   * Return user friendly response in the same format as the legacy api
   *
   */ 
  public function jsonSerialize() {
    return [
      'id' => $this->id,
      'origin' => $this->origin,
      'severity' => $this->severity,
      'status' => $this->status,
      'activity_date' => $this->activity_date,
      'created_at' => $this->created_at,
      'updated_at' => $this->updated_at,
      'title' => $this->title,
      'body' => $this->body,
      'format' => $this->format,
      'template' => $this->template,
      'actions' => $this->actions,
    ];
  }

}