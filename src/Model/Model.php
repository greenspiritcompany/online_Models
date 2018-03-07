<?php
namespace Telltale\Model;

use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;

abstract class Model implements JsonSerializable {

   /**
   * Stores what properties of the model have been set and now differ from their initial values
   * Used by queryUpdateHelper to help build query statements
   *
   * @var array updatedProperties
   **/
  private $updatedProperties = [];

  /**
   * Returns an array of all fields that have been set along with their values and a cache key
   *
   * @return array
   **/
  public function queryHelper(): array {
    $reflection = new ReflectionClass($this);
    $reflectionProperties = $reflection->getProperties(ReflectionProperty::IS_PROTECTED);

    $keys = [];
    $values = [];

    foreach ($reflectionProperties as $property) {
      $name = $property->getName();
      if (isset($this->{$name})) {
        $keys[] = $name;
        $values[] = $value = $this->{$name};
      }
    }

    return [
      'keys' => $keys,
      'values' => $values
    ];
  }

  /**
   * Returns an array of all props that have been updated along with their values
   *
   * @return array
   **/
  public function queryUpdateHelper(): array {
    $keys = array_values(array_unique($this->updatedProperties));
    $values = [];

    foreach ($keys as $key) {
      $values[] = $this->{$key};
    }

    // Clear record of updated props
    $this->updatedProperties = [];

    return [
      'keys' => $keys,
      'values' => $values
    ];
  }

  /**
   * Has this model mutated?
   * Useful to see if and update should be sent to the DataMapper
   *
   * @return bool
   **/
  public function hasUpdates(): bool {
    return !empty($this->updatedProperties);
  }

  /**
   * Update a field in the model
   *
   * @param string $fieldName
   * @param mixed $value
   * @return self
   */
  public function updateField(string $name, $value): self {

    // Compare current value with one being set
    if ($this->{$name} !== $value) {
      $this->updatedProperties[] = $name;
    }

    $this->{$name} = $value;
    return $this;
  }

  public function jsonSerialize(): array {
    return [];
  }
}