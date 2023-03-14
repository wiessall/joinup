/**
 * @file defines ContactUsCommand, which is executed when the contactUs
 * toolbar button is pressed.
 */
// eslint-disable-next-line import/no-unresolved
import { Command } from "ckeditor5/src/core";

export default class ContactUsCommand extends Command {
  execute() {
    let selectedText;
    const { editor } = this;
    const { selection } = editor.model.document;
    const range = selection.getFirstRange();

    // eslint-disable-next-line no-restricted-syntax
    for (const item of range.getItems()) {
      selectedText = item.data;
    }

    editor.model.change((writer) => {
      const contactUsWidget = writer.createElement("contactUs", {
        ...Object.fromEntries(selection.getAttributes()),
        "data-joinup-contact-information":
          editor.config.get("contactus").contactInformation_disable,
        "data-joinup-contact-information-href":
          editor.config.get("contactus").contactInformation_url,
        "data-joinup-contact-information-login":
          editor.config.get("contactus").contactInformation_login,
        "data-joinup-contact-information-label": !selectedText
          ? "Contact us"
          : selectedText,
      });

      editor.model.insertContent(contactUsWidget);
    });
  }

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    const isAllowed = model.schema.checkChild(
      selection.focus.parent,
      "contactUs"
    );

    this.isEnabled = isAllowed;
  }
}
