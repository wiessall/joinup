import { Plugin } from "ckeditor5/src/core";
import { toWidget, Widget } from "ckeditor5/src/widget";
import { ContactUsCommand } from "./contact-us-command";

export class ContactUsEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this.attrs = [
      "data-joinup-contact-information",
      "data-joinup-contact-information-href",
      "data-joinup-contact-information-login",
    ];
    this._defineSchema();
    this._defineConverters();
    this.editor.commands.add("contactUs", new ContactUsCommand(this.editor));
  }

  _defineSchema() {
    const { schema } = this.editor.model;

    schema.register("contactUs", {
      inheritAllFrom: "$inlineObject",
      allowAttributes: this.attrs,
    });
  }

  _defineConverters() {
    const { editor } = this;

    editor.conversion.for("upcast").elementToElement({
      view: {
        name: "a",
        attributes: this.attrs,
        label: "Contact us",
      },
      model: (viewElement, { writer }) => {
        const contactInformation = viewElement.getAttribute(
          "data-joinup-contact-information"
        );
        const contactInformationHref = viewElement.getAttribute(
          "data-joinup-contact-information-href"
        );
        const contactInformationLogin = viewElement.getAttribute(
          "data-joinup-contact-information-login"
        );

        return writer.createElement("contactUs", {
          "data-joinup-contact-information": contactInformation,
          "data-joinup-contact-information-href": contactInformationHref,
          "data-joinup-contact-information-login": contactInformationLogin,
        });
      },
    });

    editor.conversion.for("dataDowncast").elementToElement({
      model: "contactUs",
      view: (modelElement, { writer }) => {
        return writer.createRawElement(
          "a",
          {
            "data-joinup-contact-information": modelElement.getAttribute(
              "data-joinup-contact-information"
            ),
            "data-joinup-contact-information-href": modelElement.getAttribute(
              "data-joinup-contact-information-href"
            ),
            "data-joinup-contact-information-login": modelElement.getAttribute(
              "data-joinup-contact-information-login"
            ),
          },
          function (domElement) {
            domElement.textContent = "Contact us";
          }
        );
      },
    });

    editor.conversion.for("editingDowncast").elementToElement({
      model: "contactUs",
      view: (modelElement, { writer }) => {
        const contactUsDataPreviewElement = writer.createRawElement(
          "a",
          null,
          function (domElement) {
            domElement.textContent = "Contact us";
          }
        );

        const contactUsWidgetContainer = writer.createContainerElement(
          "a",
          null,
          contactUsDataPreviewElement
        );

        return toWidget(contactUsWidgetContainer, writer, {
          label: "Contact us",
        });
      },
    });
  }
}
