$ = jQuery;

class StepManager {
  constructor() {
    this.current = 1;

    this.total = Number($("#bcob-form").data("total-steps"));

    this.bind();

    this.buttons();
  }

  bind() {
    $(document).off(".bcobStepManager");
    const self = this;

    $(document).on("click.bcobStepManager", ".bcob-next", function () {
      self.next();
    });

    $(document).on("click.bcobStepManager", ".bcob-prev", function () {
      self.previous();
    });

    $(document).on("click.bcobStepManager", ".bcob-step-item", function () {
      const target = Number($(this).data("step"));

      self.goto(target);
    });

    $(document).on(
      "keydown.bcobStepManager",
      ".bcob-step-item",
      function (event) {
        if (event.key !== "Enter" && event.key !== " ") {
          return;
        }

        event.preventDefault();

        const target = Number($(this).data("step"));

        self.goto(target);
      },
    );

    $(document).on(
      "blur.bcobStepManager",
      ".bcob-validator input, .bcob-validator textarea",
      function () {
        BCOB.validator.validateField($(this).closest(".bcob-validator"));
        BCOB.renderer.evaluateConditions();
      },
    );

    $(document).on(
      "change.bcobStepManager",
      ".bcob-validator select, .bcob-validator input[type=checkbox], .bcob-validator input[type=radio], .bcob-validator input[type=file]",
      function () {
        BCOB.validator.validateField($(this).closest(".bcob-validator"));
        BCOB.renderer.evaluateConditions();
      },
    );
  }

  destroy() {
    $(document).off(".bcobStepManager");
  }

  next() {
    if (!BCOB.validator.validateStep(this.current)) {
      BCOB.validator.focusFirstInvalid(this.current);

      return;
    }

    if (window.BCOBGoogleTags) {
      BCOBGoogleTags.event("step_complete", {
        step_number: this.current,
        step_name: this.getStepName(this.current),
      });
    }

    if (this.current >= this.total) {
      return;
    }

    this.goto(this.current + 1);
  }

  previous() {
    if (this.current <= 1) {
      return;
    }

    this.goto(this.current - 1);
  }

  goto(step) {
    if (step < 1 || step > this.total) {
      return;
    }

    $(".bcob-step").removeClass("is-active");

    $('.bcob-step[data-step="' + step + '"]').addClass("is-active");

    this.current = step;

    if (window.BCOBGoogleTags) {
      BCOBGoogleTags.event("step_view", {
        step_number: step,
        step_name: this.getStepName(step),
      });
    }

    if (BCOB.submission) {
      BCOB.submission.collect();
    }

    if (BCOB.StepEditor) {
      BCOB.StepEditor.refresh();
    }

    BCOB.progress.set(step);

    BCOB.sidebar.show(step);

    BCOB.validator.validateStep(step);

    this.buttons();

    const $activeStep = $('.bcob-step[data-step="' + step + '"]');

    $activeStep.attr("tabindex", "-1");

    $activeStep.trigger("focus");

    this.announceStep(step);

    window.scrollTo({
      top: $("#bcob-form").offset().top - 30,
      behavior: "smooth",
    });
  }

  announceStep(step) {
    let $live = $("#bcob-step-announcement");

    if (!$live.length) {
      $live = $(
        '<div id="bcob-step-announcement" class="bcob-sr-only" aria-live="polite" aria-atomic="true"></div>',
      );

      $("#bcob-form").prepend($live);
    }

    $live.text(
      "Step " + step + " of " + this.total + ": " + this.getStepName(step),
    );
  }

  getStepName(step) {
    const steps = Array.isArray(BCOB.form?.steps) ? BCOB.form.steps : [];

    return steps[Number(step) - 1]?.title || "";
  }

  buttons() {
    $(".bcob-prev").toggle(this.current > 1);

    $(".bcob-next").toggle(this.current < this.total);

    $(".bcob-submit").toggle(this.current === this.total);
  }
}
