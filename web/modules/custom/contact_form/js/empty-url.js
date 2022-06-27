(function (Drupal) {
  Drupal.behaviors.emptyUrlContactForm = {
    attach: function () {
      const
        url = document.querySelector("input[name*='field_contact_url']"),
        category = document.querySelector("select[name='field_contact_category']");
      if (url === null || url === undefined || category === null || category === undefined) {
        return
      }
      category.addEventListener('change', (event) => {
        if(!['bug','report'].includes(event.target.value)) {
          url.value = '';
        }
      });
    }
  }

})(Drupal);
