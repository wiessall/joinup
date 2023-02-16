import { Plugin } from "ckeditor5/src/core";
import { ButtonView } from "ckeditor5/src/ui";
import icon from "../../../../icons/contact.svg";

export class ContactUsUi extends Plugin {
  init() {
    if (this.editor.config.get("contactus").contactInformation_disable) {
      const { editor } = this;
      const { t } = editor;
      const contactUsWidgetCommand = editor.commands.get("contactUs");
      editor.ui.componentFactory.add("contactUs", (locale) => {
        const button = new ButtonView(locale);

        button.set({
          label: t("Contact us"),
          tooltip: true,
          withText: false,
          icon,
        });

        // Disable the external data widget button when the command is disabled.
        button.bind("isEnabled").to(contactUsWidgetCommand);

        // Execute the command when the button is clicked (executed).
        button.on("execute", () => {
          editor.execute("contactUs");
          // Set focus on the editor content
          editor.editing.view.focus();
        });

        return button;
      });
    }
  }
}
