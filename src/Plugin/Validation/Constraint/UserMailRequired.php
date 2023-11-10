<?php

declare(strict_types=1);

namespace Drupal\webauthn\Plugin\Validation\Constraint;

use Drupal\user\Plugin\Validation\Constraint\UserMailRequired as BaseConstraint;

/**
 * Checks if the user's email address is required.
 *
 * From WebAuthn specification the user mail is not mandatory, thus a user
 * can register an authenticator without using an email.
 *
 * This constraint extends from core's mail constraint and skip mail
 * validation if the user has _skipUserMailRequiredConstraint property is set,
 * otherwise fall back to the parent validator.
 *
 * @Constraint(
 *   id = "WebAuthnUserMailRequired",
 *   label = @Translation("User email required", context = "Validation")
 * )
 */
class UserMailRequired extends BaseConstraint {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return UserMailRequiredValidator::class;
  }

}
