/**
 * @file
 * Autocomplete additional behaviors for invite forms.
 */

((Drupal) => {
  /**
   * Attaches the invite autocomplete enhancements to all required fields.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the invite autocomplete behaviors.
   * @prop {Drupal~behaviorDetach} detach
   *   Detaches the invite autocomplete behaviors.
   */
  Drupal.behaviors.inviteAutocomplete = {
    attach(context) {
      // This behavior is used by both the invitation form and the add form.
      // The 2 forms have different IDs but they are both based in the
      // InviteFormBase which passes the class 'invite-form' to the form.
      const form = document.querySelector(".invite-form");
      const list = document.getElementById("ui-id-2");
      const input = document.querySelector("input.invite-autocomplete");
      let preventDoubleEnter = false;
      // Allow typing straight away.
      input.focus();
      // Automatically press the add button when a username is clicked
      list.addEventListener("click", () => {
        if (input.value !== "") {
          // Avoid keydown events if the selection was submitted with the enter key.
          preventDoubleEnter = true;
          form.submit();
          return false;
        }
      });
      // Listen for "Enter" keydown if user validates selection with keyboard
      context.addEventListener("keydown", (event) => {
        if (event.code === "Enter") {
          // When an autocomplete option is selected by pressing the enter key,
          // the add button has already been pressed in the select handler.
          // The original keydown event will be executed too, and there is no way to
          // understand if it is happening on the autocomplete text field or on the
          // suggestions. So we use a flag to avoid clicking twice on the button.
          // Note that Drupal.ajax should prevent this already, as the ajax request
          // should be still executing, but it's better to cover this explicitly.
          if (!preventDoubleEnter) {
            form.submit();
          }
          preventDoubleEnter = false;
        }
      });
    },
  };
})(Drupal);
