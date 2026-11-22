jQuery(document).ready(function ($) {
  $(".bcob-copy").on("click", function () {
    const $button = $(this);
    const token = $button.attr("data-copy");

    if (!token || !navigator.clipboard) {
      return;
    }

    const original = $button.text();

    navigator.clipboard.writeText(token).then(function () {
      $button.text("Copied").addClass("is-copied");

      setTimeout(function () {
        $button.text(original).removeClass("is-copied");
      }, 1500);
    });
  });
});
