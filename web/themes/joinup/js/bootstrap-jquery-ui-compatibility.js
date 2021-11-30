/**
 * @file
 * Solves a compatibility problem between Bootstrap and jQuery UI.
 *
 * Both jQuery UI and Bootstrap provide a `.button()` method, and these conflict
 * with each other.
 *
 * This invokes compatibility mode for Bootstrap.
 *
 * @see https://github.com/twbs/bootstrap/issues/6094
 */

(function ($) {
  "use strict";

  $(window).on('load', function () {
    $.fn.bootstrapBtn = $.fn.button.noConflict();
  });
})(jQuery);
