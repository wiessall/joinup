import { Command } from "ckeditor5/src/core";

export class ContactUsCommand extends Command {
  execute() {
    const { editor } = this;
    const { selection } = editor.model.document;

    editor.model.change((writer) => {
      const contactUsWidget = writer.createElement("contactUs", {
        ...Object.fromEntries(selection.getAttributes()),
        "data-joinup-contact-information":
          editor.config.get("contactus").contactInformation_disable,
        "data-joinup-contact-information-href":
          editor.config.get("contactus").contactInformation_url,
        "data-joinup-contact-information-login":
          editor.config.get("contactus").contactInformation_login,
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
