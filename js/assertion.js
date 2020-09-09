"use strict";

function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }

function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && Symbol.iterator in Object(iter)) return Array.from(iter); }

function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }

function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }

/**
 * @file
 * Implements assertion behavior.
 */
(function (_ref, _ref2) {
  var behaviors = _ref.behaviors,
      t = _ref.t;
  var webauthn = _ref2.webauthn;

  var webAuthnAssertionListener = function webAuthnAssertionListener(e) {
    e.stopImmediatePropagation();
    var publicKey = webauthn.assertion;
    publicKey.challenge = Uint8Array.from(window.atob(behaviors.webauthnAssertion.base64url2base64(publicKey.challenge)), function (c) {
      return c.charCodeAt(0);
    });

    if (publicKey.allowCredentials) {
      publicKey.allowCredentials = publicKey.allowCredentials.map(function (data) {
        // eslint-disable-next-line no-param-reassign
        data.id = Uint8Array.from(window.atob(behaviors.webauthnAssertion.base64url2base64(data.id)), function (c) {
          return c.charCodeAt(0);
        });
        return data;
      });
    }

    navigator.credentials.get({
      publicKey: publicKey
    }).then(function (data) {
      var publicKeyCredential = {
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
      document.querySelector('[name="response"]').value = JSON.stringify(publicKeyCredential);
      document.querySelector('[data-trigger="webauthn"]').click();
    }).catch(function (error) {
      console.error(error);
    });
  }; // eslint-disable-next-line no-param-reassign


  behaviors.webauthnAssertion = {
    arrayToBase64String: function arrayToBase64String(a) {
      return btoa(String.fromCharCode.apply(String, _toConsumableArray(a)));
    },
    base64url2base64: function base64url2base64(input) {
      var output = input.replace(/-/g, '+').replace(/_/g, '/');
      var pad = input.length % 4;

      if (pad) {
        if (pad === 1) {
          throw new Error(t('InvalidLengthError: Input base64url string is the wrong length to determine padding'));
        }

        output += new Array(5 - pad).join("=");
      }

      return output;
    },
    attach: function attach() {
      if (document.body.classList.contains('webAuthnAssertion')) {
        return;
      }

      document.body.classList.add('webAuthnAssertion');
      document.body.addEventListener('webAuthnAssertion', webAuthnAssertionListener);
      document.body.dispatchEvent(new Event('webAuthnAssertion'));
    },
    detach: function detach() {
      document.body.classList.remove('webAuthnAssertion');
      document.body.removeEventListener('webAuthnAssertion', webAuthnAssertionListener);
    }
  };
})(Drupal, drupalSettings);
