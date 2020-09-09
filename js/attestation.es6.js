/**
 * @file
 * Implements attestation behavior.
 */
(({ behaviors, t }, { webauthn }) => {
  const webAuthnAttestationListener = () => {
    const publicKey = webauthn.attestation;

    publicKey.challenge = Uint8Array.from(
      window.atob(
        behaviors.webauthnAttestation.base64url2base64(publicKey.challenge)
      ),
      c => c.charCodeAt(0)
    );
    publicKey.user.id = Uint8Array.from(window.atob(publicKey.user.id), c =>
      c.charCodeAt(0)
    );
    if (publicKey.excludeCredentials) {
      publicKey.excludeCredentials = publicKey.excludeCredentials.map(
      data => {
        // eslint-disable-next-line no-param-reassign
        data.id = Uint8Array.from(
          window.atob(behaviors.webauthnAttestation.base64url2base64(data.id)),
          c => c.charCodeAt(0)
        );
        return data;
      });
    }

    navigator.credentials
      .create({ publicKey })
      .then(data => {
        const publicKeyCredential = {
          id: data.id,
          type: data.type,
          rawId: behaviors.webauthnAttestation.arrayToBase64String(
            new Uint8Array(data.rawId)
          ),
          response: {
            clientDataJSON: behaviors.webauthnAttestation.arrayToBase64String(
              new Uint8Array(data.response.clientDataJSON)
            ),
            attestationObject: behaviors.webauthnAttestation.arrayToBase64String(
              new Uint8Array(data.response.attestationObject)
            )
          }
        };

        document.querySelector('[name="response"]').value = JSON.stringify(
          publicKeyCredential
        );
        document.querySelector('[data-trigger="webauthn"]').click();
      })
      .catch(error => {
        console.error(error);
      });
  };

  // eslint-disable-next-line no-param-reassign
  behaviors.webauthnAttestation = {
    arrayToBase64String: a => btoa(String.fromCharCode(...a)),
    base64url2base64: input => {
      let output = input
        .replace(/=/g, "")
        .replace(/-/g, "+")
        .replace(/_/g, "/");

      const pad = input.length % 4;
      if (pad) {
        if (pad === 1) {
          throw new Error(t('InvalidLengthError: Input base64url string is the wrong length to determine padding'));
        }
        output += new Array(5 - pad).join("=");
      }

      return output;
    },
    attach: () => {
      if (document.body.classList.contains('webAuthnAttestation')) {
        return;
      }

      document.body.classList.add('webAuthnAttestation');
      document.body.addEventListener(
        'webAuthnAttestation',
        webAuthnAttestationListener
      );
      document.body.dispatchEvent(new Event('webAuthnAttestation'));
    },
    detach: () => {
      document.body.classList.remove('webAuthnAttestation');
      document.body.removeEventListener(
        'webAuthnAttestation',
        webAuthnAttestationListener
      );
    }
  };
})(Drupal, drupalSettings);
