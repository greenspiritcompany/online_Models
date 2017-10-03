<?php
namespace Telltale\Model;

class ChoiceDocument implements \JsonSerializable {

  /**
   * Primary key of the choice_documents_with_data row
   *
   * @var int -
   **/
  private $id;

  /**
   * The id of the original choice document in the Global db
   *
   * @var int.
  **/
  private $source_doc_id;

  /**
   * The name of the game that this choice doc is associated with
   *
   * @var string
  **/
  private $game_name;

  /**
   * The type of choice document this is.
   *
   * @var string
  **/
  private $choice_doc_type;

  /**
   * The when the original choice document was created.
   *
   * @var string
  **/
  private $source_doc_date;

  /**
   * When this record was updated
   *
   * @var string
  **/
  private $updated_at;

  /**
   * When this record was created
   *
   * @var string
  **/
  private $created_at;

  /**
   * The entire JSON choice document
   *
   * @var string
  **/
  private $json_data;

  protected $data;

  public function __construct() {

    // Decode json data when set by PDO
    if (isset($this->json_data)) {
      $this->data = json_decode($this->json_data, true);
    }

  }

  public function getId() {
    return $this->id;
  }

  public function setId(string $id): self {
    $this->id = $id;
    return $this;
  }

  public function getSourceDocId() {
    return $this->source_doc_id;
  }

  public function setSourceDocId(string $source_doc_id): self {
    $this->source_doc_id = $source_doc_id;
    return $this;
  }

  public function getGameName() {
    return $this->game_name;
  }

  public function setGameName(string $game_name): self {
    $this->game_name = $game_name;
    return $this;
  }

  public function getChoiceDocType() {
    return $this->choice_doc_type;
  }

  public function setChoiceDocType(string $choice_doc_type): self {
    $this->choice_doc_type = $choice_doc_type;
    return $this;
  }

  public function getSourceDocDate() {
    return $this->source_doc_date;
  }

  public function setSourceDocDate(string $source_doc_date): self {
    $this->source_doc_date = $source_doc_date;
    return $this;
  }

  public function setUpdatedAt(string $updated_at): self {
    $this->updated_at = $updated_at;
    return $this;
  }

  public function getUpdatedAt() {
    return $this->updated_at;
  }

  public function setCreatedAt(string $created_at): self {
    $this->created_at = $created_at;
    return $this;
  }

  public function getCreatedAt() {
    return $this->created_at;
  }

  public function setJsonData(array $data): self {
    $this->data = $data;
    $this->json_data = json_encode($json_data);
    return $this;
  }

  public function getJsonData() {
    return $this->json_data;
  }

  public function getData() {
    return $this->data;
  }

  /**
   * Returns an array of all fields that have been set along with their values
   *
   * @return array
   **/
  public function queryHelper(): array {
    $reflection = new \ReflectionClass($this);
    $properties = $reflection->getProperties(\ReflectionProperty::IS_PRIVATE);

    $key = ''; // Used to cache
    $keys = [];
    $values = [];

    foreach ($properties as $property) {
      $name = $property->getName();
      if (isset($this->{$name})) {
          $keys[] = $name;
          $value = $this->{$name};
          $values[] = $value;
          $key .= $name.':'.$value.'|';
      }
    }

    return ['keys' => $keys, 'values' => $values, 'key' => sha1($key)];
  }

  /**
   * Returns an array of jsonSerialize values safe to display to the public
   *
   * @return array
   **/
  public function jsonSerialize(): array {
    return [
      'id' => $this->getId(),
      'game_name' => $this->getGameName(),
      'choice_doc_type' => $this->getChoiceDocType(),
      'source_doc_date' => $this->getSourceDocDate(),
      'created_at' => $this->getCreatedAt(),
      'updated_at' => $this->getUpdatedAt(),
      'data' => $this->getData(),
    ];
  }

}