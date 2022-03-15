/**
 * @file Listen to a 'click' on the searchbox trigger and focus accordingly.
 */
 Drupal.behaviors.searchboxFocus = {
  attach: function () {
    const trigger = document.querySelector('#heading-search .accordion-button');
    const box = document.querySelector('#collapse-search');
    const input = document.querySelector('#collapse-search #edit-keys');
    if (trigger === null
      || trigger === undefined
      || input === null
      || input === undefined) {
      return
    }
    // Listen for click
    trigger.addEventListener('click', function() {
      // If click was for closing, blur.
      if (box.classList.contains('show')) {
        input.blur();
      // If click was for opening, focus.
      }
      else {
        input.focus();
      }
    });
    // Listen for enter key (keyboard navigation)
    trigger.addEventListener('keypress', (event)=> {
      if (event.keyCode === 13) {
        // If click was for closing, blur.
        if (box.classList.contains('show')) {
          input.blur();
        // If click was for opening, blur.
        }
        else {
          input.focus();
        }
      }
    });
  }
};
