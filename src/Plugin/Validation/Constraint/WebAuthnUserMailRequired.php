<?php

namespace Drupal\webauthn\Plugin\Validation\Constraint;

use Drupal\user\Plugin\Validation\Constraint\UserMailRequired;
use Symfony\Component\Validator\Constraint;

/**
 * Checks if the user's email address is required.
 *
 * From WebAuthn specification the user mail is not mandatory, thus a user
 * can register an authenticator without using an email.
 *
 * This constraint extends from core's mail constraint and skip mail
 * validation if the user has _skipUserMailRequiredConstraint property is set, otherwise
 * fallback to the parent validator.
 *
 * @Constraint(
 *   id = "WebAuthnUserMailRequired",
 *   label = @Translation("User email required", context = "Validation")
 * )
 */
class WebAuthnUserMailRequired extends UserMailRequired {

}
