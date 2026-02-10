/**
 * @file
 * Clears the judgement field when interpretation is set to N/A.
 */

(function (Drupal) {
  const sections = ["legal", "organisational", "semantic", "technical"];

  Drupal.behaviors.assessmentsClearJudgement = {
    attach(context) {
      sections.forEach((section) => {
        const prefix = `${section}[0][inline_entity_form]`;
        const radios = context.querySelectorAll(
          `input[name="${prefix}[interpretation]"]`,
        );

        radios.forEach((radio) => {
          radio.addEventListener("change", (e) => {
            if (e.target.value !== "_none") {
              return;
            }

            const judgement = document.querySelector(
              `textarea[name="${prefix}[judgement][0][value]"]`,
            );
            if (judgement) {
              judgement.value = "";
            }
          });
        });
      });
    },
  };
})(Drupal);
