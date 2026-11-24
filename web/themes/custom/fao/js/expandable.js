"use strict";

(function ($, viewport) {

  Drupal.behaviors.expandable = {

    attach: function (context, settings) {

      window.expandable = {}

      expandable.init = function () {

        let $collapsible = $('.accordion');

        $collapsible.bind('show.bs.collapse', function () {
          let $this = $(this);
          $this.siblings('.summary').hide();
          $this.find('button .label').html('click to collapse')
        })

        $collapsible.bind('hide.bs.collapse', function () {
          let $this = $(this);
          $this.siblings('.summary').show();
          $this.find('button .label').html('expand to read more')
        })

      }

      expandable.init();
    }
  }
})(jQuery, Drupal);
