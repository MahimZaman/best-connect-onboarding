jQuery(function ($) {
  let overlayTrigger = null;

  async function refreshNonces() {
    const response = await $.ajax({
      url: BCOBMembership.ajax,
      type: "POST",
      dataType: "json",
      cache: false,
      data: {
        action: "bcob_refresh_nonces",
      },
    });

    if (
      !response.success ||
      !response.data?.frontend ||
      !response.data?.payment ||
      !response.data?.load_form
    ) {
      throw new Error("Unable to initialize the secure form session.");
    }

    BCOBMembership.nonce = response.data.load_form;

    if (window.BCOB) {
      BCOB.nonce = response.data.frontend;
    }

    if (window.BCOBStripe) {
      BCOBStripe.nonce = response.data.payment;
    }

    if (window.BCOBUploader) {
      BCOBUploader.nonce = response.data.frontend;
    }

    if (window.BCOBCouponLocals) {
      BCOBCouponLocals.nonce = response.data.frontend;
    }

    if (window.BCOBPdfReview) {
      BCOBPdfReview.nonce = response.data.frontend;
    }

    if (window.BCOBResendConfirmation) {
      BCOBResendConfirmation.nonce = response.data.frontend;
    }
  }

  function headerHeight() {
    let h = 0;

    $("header:visible").each(function () {
      h = Math.max(h, $(this).outerHeight());
    });

    $("#wpadminbar").each(function () {
      h += $(this).outerHeight();
    });

    return h;
  }

  function openOverlay(html) {
    const offset = headerHeight();

    if (
      document.activeElement &&
      !$(document.activeElement).closest(".bcob-form-overlay").length
    ) {
      overlayTrigger = document.activeElement;
    }

    $(".bcob-form-overlay").css({
      top: offset,
      height: "calc(100vh - " + offset + "px)",
    });

    $(".bcob-form-container").html(html);

    $(".bcob-form-overlay").addClass("active").attr("aria-hidden", "false");

    $("html, body").css("overflow", "hidden");

    history.pushState(
      {
        bcobOverlay: true,
      },
      "",
      window.location.href,
    );

    window.setTimeout(function () {
      const $focusable = $(".bcob-form-overlay")
        .find(
          'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
        )
        .filter(":visible")
        .first();

      if ($focusable.length) {
        $focusable.trigger("focus");
      } else {
        $(".bcob-form-overlay").trigger("focus");
      }
    }, 0);
  }

  function closeOverlay() {
    if (!$(".bcob-form-overlay").hasClass("active")) {
      return;
    }

    history.back();
  }

  function saveCurrentForm() {
    if (!$("#bcob-form").length) {
      return;
    }

    if (BCOB.storage && typeof BCOB.storage.save === "function") {
      BCOB.storage.save();
    }
  }

  function destroyOverlay() {
    saveCurrentForm();

    if (BCOB.frontend && typeof BCOB.frontend.destroy === "function") {
      BCOB.frontend.destroy();
    }

    $(".bcob-form-overlay").removeClass("active");

    $(".bcob-form-overlay").removeClass("active").attr("aria-hidden", "true");

    $(".bcob-form-container").empty();

    $("html, body").css("overflow", "");

    $('input[name="bcob_membership"]').prop("checked", false);

    if (overlayTrigger && document.contains(overlayTrigger)) {
      $(overlayTrigger).trigger("focus");
    }

    overlayTrigger = null;
  }

  $(document).on(
    "keydown.bcobOverlay",
    ".bcob-form-overlay.active",
    function (event) {
      if (event.key === "Escape") {
        event.preventDefault();

        closeOverlay();

        return;
      }

      if (event.key !== "Tab") {
        return;
      }

      const $focusable = $(this)
        .find(
          'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
        )
        .filter(":visible");

      if (!$focusable.length) {
        event.preventDefault();

        $(this).trigger("focus");

        return;
      }

      const first = $focusable[0];

      const last = $focusable[$focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();

        $(last).trigger("focus");

        return;
      }

      if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();

        $(first).trigger("focus");
      }
    },
  );

  $(document).on("change", 'input[name="bcob_membership"]', async function () {
    const membership = $(this).val();
    const form = $(this).data("form");
    const portalSn = $(this).data("portal");
    const offerSignature = $(this).data("offer-signature") || "";

    if (!membership || !form || !offerSignature) {
      return;
    }

    openOverlay(
      `<div class="bcob--loader"><div class="bcob-loader__spinner"></div><div class="bcob-loader__text">Bitte warten…</div></div>`,
    );

    try {
      await refreshNonces();

      const response = await $.ajax({
        url: BCOBMembership.ajax,
        type: "POST",
        dataType: "json",
        data: {
          action: "bcob_load_form",
          nonce: BCOBMembership.nonce,
          membership: membership,
          portal_index: portalSn,
          offer_signature: offerSignature,
        },
      });

      if (!response.success) {
        destroyOverlay();
        return;
      }

      BCOB.membership = response.data.membership;

      openOverlay(response.data.form);

      await BCOB.frontend.init();

      if (response.data.tracking && window.BCOBGoogleTags) {
        BCOBGoogleTags.push(response.data.tracking);
      }
    } catch (error) {
      destroyOverlay();
    }
  });

  window.addEventListener("popstate", function () {
    if ($(".bcob-form-overlay").hasClass("active")) {
      destroyOverlay();
    }
  });

  $(document).on("click", ".bcob-step-summary__edit-membership", function () {
    destroyOverlay();
  });

  $(document).on("click", ".bcob-btn-back", function () {
    destroyOverlay();
  });
});
