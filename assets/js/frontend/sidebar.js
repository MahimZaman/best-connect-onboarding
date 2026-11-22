$ = jQuery;

class SidebarManager {
  show(step) {
    $(".bcob-sidebar-step").removeClass("is-active");

    $('.bcob-sidebar-step[data-step="' + step + '"]').addClass("is-active");
  }
}
