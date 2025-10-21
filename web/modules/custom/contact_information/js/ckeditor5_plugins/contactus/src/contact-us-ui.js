/**
 * @file registers the contactUs toolbar button and binds functionality to it.
 */

// eslint-disable-next-line import/no-unresolved
import { Plugin } from "ckeditor5/src/core";
// eslint-disable-next-line import/no-unresolved
import { ButtonView } from "ckeditor5/src/ui";
import icon from "../../../../icons/contact.svg";

export default class ContactUsUi extends Plugin {
  init() {
    if (this.editor.config.get("contactus").contactInformation_enabled) {
      const { editor } = this;
      const { t } = editor;
      editor.ui.componentFactory.add("contactUs", (locale) => {
        const button = new ButtonView(locale);

        button.set({
          label: Drupal.t("Contact us"),
          tooltip: true,
          withText: false,
          icon,
        });

        // Execute the command when the button is clicked (executed).
        button.on("execute", () => {
          let selectedText;
          const range = editor.model.document.selection.getFirstRange();

          // eslint-disable-next-line no-restricted-syntax
          for (const item of range.getItems()) {
            selectedText = item.data;
          }

          const href = editor.config.get("contactus").contactInformation_url;
          const contactUsText = Drupal.t("Contact us");
          const text = !selectedText ? contactUsText : selectedText;
          // eslint-disable-next-line no-unused-vars
          editor.model.change((writer) => {
            const contactLinkHtml = `<a href="${href}" title="${contactUsText}">${text}</a>`;
            const viewFragment = editor.data.processor.toView(contactLinkHtml);
            const modelFragment = editor.data.toModel(viewFragment);
            editor.model.insertContent(modelFragment);
          });
        });

        return button;
      });
    }
  }
}
