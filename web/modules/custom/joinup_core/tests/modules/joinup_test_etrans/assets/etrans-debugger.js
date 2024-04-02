/**
 * @file
 * Exposes an 'outdated content' notice if case.
 */
((Drupal, once) => {
  Drupal.eTransDebugger = {
    includedSelector: "",
    excludedSelector: "",
    /**
     * Return the text of elements matching the etranslate selectors.
     *
     * @return {string}
     *   The text which will be sent to translate. Line breaks are representing
     *   block-level element boundaries.
     */
    getTextToTranslate: () => {
      if (!Drupal.eTransDebugger.includedSelector) {
        return "";
      }
      let text = "";
      document
        .querySelectorAll(Drupal.eTransDebugger.includedSelector)
        .forEach((element) => {
          const cleanedElement =
            Drupal.eTransDebugger.getCleanedElement(element);
          text += `${Drupal.eTransDebugger.getCleanedText(cleanedElement)} `;
        });
      return text.replace(/\n\s*\n/g, "\n").trim();
    },
    /**
     * Returns a node element without the elements which should be excluded.
     *
     * @param {Element} node
     *   The element which should be translatable.
     * @return {Element}
     *   A new node element without the elements which should be excluded.
     */
    getCleanedElement: (node) => {
      const element = node.cloneNode(true);
      if (Drupal.eTransDebugger.excludedSelector) {
        element
          .querySelectorAll(Drupal.eTransDebugger.excludedSelector)
          .forEach((elem) => {
            elem.remove();
          });
      }
      return element;
    },
    /**
     * Returns the cleaned text content of the given element.
     *
     * @param {Element} element
     *   The element to get the text content of.
     * @return {string}
     *   The cleaned text content of the given element.
     */
    getCleanedText: (element) => {
      const text = [];
      let textcontent = "";
      let current = 0;
      let isInline = false;
      element.childNodes.forEach((child) => {
        isInline = Drupal.eTransDebugger.inlineElements.includes(
          child.nodeName
        );
        if (isInline) {
          textcontent = child.textContent.replace(/\n+/, " ");
        } else {
          textcontent = `\n${Drupal.eTransDebugger.getCleanedText(child)}\n`;
        }
        if (textcontent.trim()) {
          current += !isInline ? 1 : 0;
          text[current] = (text[current] ?? "") + textcontent;
        }
      });

      return (
        text
          .join("\n")
          // Replace long whitespaces.
          .replace(
            /[ \f\r\t\v\u00a0\u1680\u2000-\u200a\u2028\u2029\u202f\u205f\u3000\ufeff]{2,}/g,
            " "
          )
          // Replace line breaks and their leading / trailing whitespaces.
          .replace(/ ?\n+ ?/g, "\n")
      );
    },
    /**
     * Elements which can contain only flow content or are interpreted as inline.
     */
    inlineElements: [
      "#text",
      "A",
      "ABBR",
      "ACRONYM",
      "B",
      "BDO",
      "BIG",
      "BR",
      "BUTTON",
      "CITE",
      "CODE",
      "DFN",
      "EM",
      "I",
      "IMG",
      "INPUT",
      "KBD",
      "LABEL",
      "MAP",
      "OBJECT",
      "OUTPUT",
      "Q",
      "SAMP",
      "SCRIPT",
      "SELECT",
      "SMALL",
      "SPAN",
      "STRONG",
      "SUB",
      "SUP",
      "TEXTAREA",
      "TIME",
      "TT",
      "VAR",
    ],
  };
  /**
   * Makes a notice available in the window object if the page displays an
   * entity whose content is outdated so that the theme layer can render it.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches outdated content behaviors.
   */
  Drupal.behaviors.eTransDebugger = {
    attach(context) {
      once(
        "etrans-debugger",
        "body script[type='application/json']",
        context
      ).forEach((element) => {
        const json = JSON.parse(element.textContent);
        if (!json.service || json.service !== "etrans") {
          return;
        }
        const { include, exclude } = json;

        if (include) {
          Drupal.eTransDebugger.includedSelector += Drupal.eTransDebugger
            .includedSelector
            ? `,${include}`
            : include;
        }
        if (exclude) {
          Drupal.eTransDebugger.excludedSelector += Drupal.eTransDebugger
            .excludedSelector
            ? `,${exclude}`
            : exclude;
        }
      });

      if (Drupal.eTransDebugger.includedSelector) {
        document
          .querySelectorAll(Drupal.eTransDebugger.includedSelector)
          .forEach((element) => {
            element.classList.remove("etrans-debugger-excluded");
            element.classList.add("etrans-debugger-included");
          });
      }

      if (Drupal.eTransDebugger.excludedSelector) {
        document
          .querySelectorAll(Drupal.eTransDebugger.excludedSelector)
          .forEach((element) => {
            element.classList.remove("etrans-debugger-included");
            element.classList.add("etrans-debugger-excluded");
          });
      }
    },
  };
})(Drupal, once);
