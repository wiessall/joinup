import { Plugin } from "ckeditor5/src/core";
import { ContactUsUi } from "./contact-us-ui";
import { ContactUsEditing } from "./contact-us-editing";

export class ContactUs extends Plugin {
  static get requires() {
    return [ContactUsEditing, ContactUsUi];
  }
}
