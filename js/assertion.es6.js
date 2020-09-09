/**
 * @file
 * Implements assertion behavior.
 */
(({behaviors, t}, {webauthn}) => {
  const webAuthnAssertionListener = (e) => {
    e.stopImmediatePropagation();
    const publicKey = webauthn.assertion;

    publicKey.challenge = Uint8Array.from(
      window.atob(
        behaviors.webauthnAssertion.base64url2base64(publicKey.challenge)
      ),
      c => c.charCodeAt(0)
    );

    if (publicKey.allowCredentials) {
      publicKey.allowCredentials = publicKey.allowCredentials.map(
        data => {
          // eslint-disable-next-line no-param-reassign
          data.id = Uint8Array.from(
            window.atob(behaviors.webauthnAssertion.base64url2base64(data.id)),
            c => c.charCodeAt(0)
          );
          return data;
        });
    }

    navigator.credentials
      .get({publicKey})
      .then(function (data) {
        const publicKeyCredential = {
          id: data.id,
          type: data.type,
          rawId: behaviors.webauthnAssertion.arrayToBase64String(new Uint8Array(data.rawId)),
          response: {
            authenticatorData: behaviors.webauthnAssertion.arrayToBase64String(new Uint8Array(data.response.authenticatorData)),
            clientDataJSON: behaviors.webauthnAssertion.arrayToBase64String(new Uint8Array(data.response.clientDataJSON)),
            signature: behaviors.webauthnAssertion.arrayToBase64String(new Uint8Array(data.response.signature)),
            userHandle: data.response.userHandle ? behaviors.webauthnAssertion.arrayToBase64String(new Uint8Array(data.response.userHandle)) : null
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
  behaviors.webauthnAssertion = {
    arrayToBase64String: a => btoa(String.fromCharCode(...a)),
    base64url2base64: input => {
      let output = input
        .replace(/-/g, '+')
        .replace(/_/g, '/');

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
      if (document.body.classList.contains('webAuthnAssertion')) {
        return;
      }

      document.body.classList.add('webAuthnAssertion');
      document.body.addEventListener(
        'webAuthnAssertion',
        webAuthnAssertionListener
      );
      document.body.dispatchEvent(new Event('webAuthnAssertion'));
    },
    detach: () => {
      document.body.classList.remove('webAuthnAssertion');
      document.body.removeEventListener(
        'webAuthnAssertion',
        webAuthnAssertionListener
      );
    }
  };
})(Drupal, drupalSettings);
