(function () {
  'use strict';

  document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('claim-facet-select')) {
      return;
    }
    var urls = JSON.parse(e.target.getAttribute('data-urls') || '{}');
    var selected = e.target.value;
    var url = urls[selected];
    if (url) {
      window.location.href = url;
    }
  });
})();
