(function ($) {
  "use strict";

  $(document).on("click", ".bcob-resend-confirmation", function (e) {
    e.preventDefault();

    const $button = $(this);

    if ($button.hasClass("is-loading")) {
      return;
    }

    const params = new URLSearchParams(window.location.search);

    const leadToken = params.get("success");

    if (!leadToken) {
      window.alert("Der Lead konnte nicht gefunden werden.");

      return;
    }

    $button.addClass("is-loading");

    $button.attr("aria-disabled", "true");

    $.ajax({
      url: BCOBResendConfirmation.ajax_url,

      type: "POST",

      data: {
        action: "bcob_resend_confirmation",

        nonce: BCOBResendConfirmation.nonce,

        lead_token: leadToken,
      },

      success: function (response) {
        if (response.success) {
          window.alert(response.data.message);

          return;
        }

        window.alert(
          response.data?.message || "Die E-Mail konnte nicht gesendet werden.",
        );
      },

      error: function () {
        window.alert("Die Anfrage konnte nicht verarbeitet werden.");
      },

      complete: function () {
        $button.removeClass("is-loading");

        $button.removeAttr("aria-disabled");
      },
    });
  });
})(jQuery);
