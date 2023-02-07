/**
 * @file
 * Contact us plugin definition.
 */

/**
 * Get HTML of a selection.
 * @param {string} selection selected text.
 * @return {html} The selected html with text.
 */
const getSelectionHtml = (selection) => {
  const ranges = selection.getRanges();
  let html = "";
  for (let i = 0; i < ranges.length; i++) {
    const content = ranges[i].extractContents();
    html += content.getHtml();
  }
  return html;
};

CKEDITOR.plugins.add("contactus", {
  init(editor) {
    editor.addCommand("contactus_template", {
      exec() {
        let selectedHtml = "";
        const selection = editor.getSelection();
        if (selection) {
          selectedHtml = getSelectionHtml(selection);
        }
        if (!selectedHtml) {
          selectedHtml = "Contact us";
        }
        editor.insertHtml(
          `<a data-joinup-contact-information data-joinup-contact-information-href="${editor.config.contactInformation_url}" data-joinup-contact-information-login="${editor.config.contactInformation_login}" href="">${selectedHtml}</a>`
        );
      },
    });
    if (editor.config.contactInformation_disable) {
      editor.ui.addButton("contactus", {
        label: "Contact us",
        toolbar: "insert",
        command: "contactus_template",
        icon: `${this.path}contact.png`,
      });
    }
  },
});
