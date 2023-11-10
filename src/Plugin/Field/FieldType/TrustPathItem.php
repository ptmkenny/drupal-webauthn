<?php

declare(strict_types=1);

namespace Drupal\webauthn\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Webauthn\TrustPath\EmptyTrustPath;
use Webauthn\TrustPath\TrustPath;

/**
 * Field-type implementation to store trust-path value for public keys.
 *
 * @see \Webauthn\TrustPath\TrustPath
 *
 * @FieldType(
 *   id = "trust_path",
 *   label = @Translation("Trust path"),
 *   description = @Translation("Stores trust path information"),
 *   no_ui = "true",
 *   cardinality = 1
 * )
 */
class TrustPathItem extends MapItem {

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    return (new EmptyTrustPath())->jsonSerialize();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE): void {
    if ($values instanceof TrustPath) {
      $values = $values->jsonSerialize();
    }

    parent::setValue($values, $notify);
  }

}
