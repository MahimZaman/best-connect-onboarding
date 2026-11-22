jQuery(function ($) {
  "use strict";

  $(document).on("click", ".bcob-prefill-copy", function () {
    const $button = $(this);
    const value = $button.data("copy");
    const original = $button.html();

    navigator.clipboard.writeText(value).then(function () {
      $button.html('<i class="ri-check-line"></i> Copied');

      setTimeout(function () {
        $button.html(original);
      }, 1200);
    });
  });

  $(document).on("change", ".bcob-prefill-expiry", function () {
    const $select = $(this);
    const $row = $select.closest("tr");
    const previous = $select.data("previous") || $select.val();

    $select.data("previous", previous);
    $select.prop("disabled", true);

    $row.addClass("is-updating");

    $.post(BCOBPrefillAdmin.ajax_url, {
      action: "bcob_update_prefill_expiry",
      nonce: BCOBPrefillAdmin.nonce,
      id: $select.data("id"),
      expiry: $select.val(),
    })
      .done(function (response) {
        if (!response.success) {
          $select.val(previous);

          alert(response.data?.message || "Unable to update expiry.");

          return;
        }

        $select.data("previous", $select.val());

        $row.find(".bcob-expiry-date").text(response.data.expires_at);

        const $status = $row.find(".bcob-prefill-status");

        $status
          .toggleClass("is-expired", response.data.expired)
          .toggleClass("is-active", !response.data.expired);

        $status
          .find("strong")
          .text(response.data.expired ? "Expired" : "Active");
      })
      .fail(function () {
        $select.val(previous);

        alert("Unable to update expiry.");
      })
      .always(function () {
        $select.prop("disabled", false);
        $row.removeClass("is-updating");
      });
  });

  $(document).on("click", ".bcob-prefill-delete", function () {
    const $button = $(this);
    const $row = $button.closest("tr");
    const id = $button.data("id");

    if (!window.confirm("Delete this pre-fill link permanently?")) {
      return;
    }

    $button.prop("disabled", true);

    $row.addClass("is-deleting");

    $.post(BCOBPrefillAdmin.ajax_url, {
      action: "bcob_delete_prefill",
      nonce: BCOBPrefillAdmin.nonce,
      id: id,
    })
      .done(function (response) {
        if (!response.success) {
          alert(
            response.data?.message || "Unable to delete the pre-fill link.",
          );

          $button.prop("disabled", false);
          $row.removeClass("is-deleting");

          return;
        }

        $row.fadeOut(250, function () {
          $(this).remove();

          const $count = $(".bcob-prefill-count strong");
          const current = parseInt($count.text().replace(/\D/g, ""), 10) || 0;

          $count.text(new Intl.NumberFormat().format(Math.max(0, current - 1)));
        });
      })
      .fail(function () {
        alert("Unable to delete the pre-fill link.");

        $button.prop("disabled", false);
        $row.removeClass("is-deleting");
      });
  });
});
