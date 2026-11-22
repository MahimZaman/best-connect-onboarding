$(document).on("click", "#bcob-connect-stripe", function () {
  const button = $(this);

  button.prop("disabled", true);

  button.find(".bcob-button-text").attr("hidden", true);

  button.find(".bcob-button-loader").removeAttr("hidden");

  $("#bcob-stripe-connect-status").html("");

  $.ajax({
    url: BCOBStripe.ajax_url,

    type: "POST",

    dataType: "json",

    data: {
      action: "bcob_connect_stripe",
      nonce: BCOBStripe.nonce,

      stripe_mode: $('select[name="settings[stripe_mode]"]').val(),

      stripe_test_publishable_key: $(
        'input[name="settings[stripe_test_publishable_key]"]',
      ).val(),
      stripe_test_secret_key: $(
        'input[name="settings[stripe_test_secret_key]"]',
      ).val(),

      stripe_live_publishable_key: $(
        'input[name="settings[stripe_live_publishable_key]"]',
      ).val(),
      stripe_live_secret_key: $(
        'input[name="settings[stripe_live_secret_key]"]',
      ).val(),
    },

    success(response) {
      if (response.success) {
        $("#bcob-stripe-connect-status").html(
          '<div class="notice notice-success inline">' +
            "<p><strong>" +
            response.data.message +
            "</strong></p>" +
            "<p><strong>Business:</strong> " +
            response.data.account.business_name +
            "</p>" +
            "<p><strong>Country:</strong> " +
            response.data.account.country +
            "</p>" +
            "<p><strong>Email:</strong> " +
            response.data.account.email +
            "</p>" +
            "<p><strong>Account ID:</strong> " +
            response.data.account.id +
            "</p>" +
            "</div>",
        );
      } else {
        $("#bcob-stripe-connect-status").html(
          '<div class="notice notice-error inline"><p>' +
            response.data.message +
            "</p></div>",
        );
      }
    },

    error() {
      $("#bcob-stripe-connect-status").html(
        '<div class="notice notice-error inline"><p>An unexpected error occurred.</p></div>',
      );
    },

    complete() {
      button.prop("disabled", false);

      button.find(".bcob-button-loader").attr("hidden", true);

      button.find(".bcob-button-text").removeAttr("hidden");
    },
  });
});
