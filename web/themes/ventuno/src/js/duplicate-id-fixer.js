/**
 * @file
 * Duplicate ID fixer for cloned priority plus elements.
 *
 * Priority plus clones navigation elements which can lead to duplicate IDs in
 * the DOM. This script updates the IDs in cloned elements to ensure they are
 * unique after the priority plus script has run.
 */
((Drupal, once) => {
  Drupal.behaviors.duplicateIdFixer = {
    attach(context) {
      // Run after a delay to ensure priority plus has completed
      setTimeout(() => {
        once("duplicate-id-fix", ".p-plus-container", context).forEach(
          (container) => {
            // Find all cloned navigations in this container
            const clonedNavs = container.querySelectorAll(
              '.p-plus--clone .nav, [data-clone="true"] .nav',
            );

            clonedNavs.forEach((clonedNav, navIndex) => {
              // Modify IDs in the cloned elements only
              clonedNav
                .querySelectorAll("[id]")
                .forEach((element, elementIndex) => {
                  const oldId = element.id;
                  const newId = `${oldId}-clone-${navIndex}-${elementIndex}`;
                  element.id = newId;

                  // Update aria-labelledby references in the same clone
                  clonedNav
                    .querySelectorAll(`[aria-labelledby="${oldId}"]`)
                    .forEach((referencingElement) => {
                      referencingElement.setAttribute("aria-labelledby", newId);
                    });
                });
            });
          },
        );
        // 100ms delay to ensure priority plus has finished
      }, 100);
    },
  };
})(Drupal, once);
