<?php

declare(strict_types=1);

namespace Drupal\webauthn\Plugin\Validation\Constraint;

use Drupal\user\Plugin\Validation\Constraint\UserMailRequiredValidator as BaseValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Checks if the user's email address is required.
 *
 * From WebAuthn specification the user mail is not mandatory, thus a user
 * can register an authenticator without using an email.
 *
 * This constraint extends from core's mail constraint and skip mail
 * validation if the user has _skipUserMailRequiredConstraint property is set,
 * otherwise fallback to the parent validator.
 */
class UserMailRequiredValidator extends BaseValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint): void {
    /** @var \Drupal\Core\Field\FieldItemListInterface $items */
    /** @var \Drupal\user\UserInterface $account */
    $account = $items->getEntity();
    if (!isset($account) || !empty($account->_skipUserMailRequiredConstraint)) {
      return;
    }

    parent::validate($items, $constraint);
  }

}
