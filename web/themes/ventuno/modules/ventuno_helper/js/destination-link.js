/**
 * @param Drupal
 * @param once
 * @file
 * Adds the redirect destination to the links marked with 'data-destination'.
 */

((Drupal, once) => {
  const getDestination = () => {
    const { pathname, search } = window.location;
    return new URLSearchParams(search).get("destination") ?? pathname + search;
  };

  Drupal.behaviors.destinationLink = {
    attach(context) {
      const destination = getDestination();
      once("destination-link", "[data-destination]", context).forEach(
        (link) => {
          const url = new URL(link.href);
          if (url.searchParams.has("t")) {
            url.searchParams.set("t", Math.random().toString(16).slice(2));
          }
          url.searchParams.set("destination", destination);
          link.href = `${url.pathname}${url.search}`;
        },
      );
    },
  };
})(Drupal, once);
