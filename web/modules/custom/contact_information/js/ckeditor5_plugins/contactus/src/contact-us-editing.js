/**
 * @file CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * This file has the logic for defining the contactUs model, and for how it is
 * converted to standard DOM markup.
 */
// cSpell:ignore contact-us-command

// eslint-disable-next-line import/no-unresolved
import { Plugin } from "ckeditor5/src/core";
// eslint-disable-next-line import/no-unresolved
import { toWidget, Widget } from "ckeditor5/src/widget";
import ContactUsCommand from "./contact-us-command";

export default class ContactUsEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this.attrs = [
      "data-joinup-contact-information",
      "data-joinup-contact-information-href",
      "data-joinup-contact-information-login",
      "data-joinup-contact-information-label",
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
        const contactInformationLabel = viewElement.getAttribute(
          "data-joinup-contact-information-label"
        );

        return writer.createElement("contactUs", {
          "data-joinup-contact-information": contactInformation,
          "data-joinup-contact-information-href": contactInformationHref,
          "data-joinup-contact-information-login": contactInformationLogin,
          "data-joinup-contact-information-label": contactInformationLabel,
        });
      },
    });

    editor.conversion.for("dataDowncast").elementToElement({
      model: "contactUs",
      view: (modelElement, { writer }) => {
        const dataLabel = modelElement.getAttribute(
          "data-joinup-contact-information-label"
        );

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
          // eslint-disable-next-line func-names
          function (domElement) {
            domElement.textContent = dataLabel;
          }
        );
      },
    });

    editor.conversion.for("editingDowncast").elementToElement({
      model: "contactUs",
      view: (modelElement, { writer }) => {
        const dataLabel = modelElement.getAttribute(
          "data-joinup-contact-information-label"
        );

        const contactUsDataPreviewElement = writer.createRawElement(
          "a",
          null,
          // eslint-disable-next-line func-names
          function (domElement) {
            domElement.textContent = dataLabel;
          }
        );

        const contactUsWidgetContainer = writer.createContainerElement(
          "a",
          null,
          contactUsDataPreviewElement
        );

        return toWidget(contactUsWidgetContainer, writer, {
          label: dataLabel,
        });
      },
    });
  }
}
