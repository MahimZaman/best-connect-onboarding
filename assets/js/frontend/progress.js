class ProgressManager {
  constructor() {
    this.total = Number($("#bcob-form").data("total-steps"));
  }

  refresh() {
    this.set(BCOB.steps.current);
  }

  set(step) {
    $(".bcob-step-item")
      .removeClass("is-active is-complete")
      .attr("aria-current", "false");

    $(".bcob-step-item").each(function () {
      const current = Number($(this).data("step"));

      if (current < step) {
        $(this).addClass("is-complete");
      }

      if (current === step) {
        $(this).addClass("is-active").attr("aria-current", "step");
      }
    });

    const percent = Math.round((step / this.total) * 100);

    $(".bcob-current-step").text(step);

    $(".bcob-form-progress-percent").text(percent + " %");

    $(".bcob-form-progress-fill").css("width", percent + "%");

    $(".bcob-form-progress").attr({
      "aria-valuenow": step,
      "aria-valuemax": this.total,
      "aria-valuetext": "Step " + step + " of " + this.total,
    });
  }
}
