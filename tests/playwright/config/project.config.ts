/**
 * @file
 * Playwright project configuration.
 */

export const projectConfig = {
  baseUrl: process.env.DRUPAL_BASE_URL,
  logInUrl: "user/login",
  users: {
    administrator: {
      name: "administrator",
      email: "administrator@example.com",
      password: "administrator",
    },
    developer: {
      name: "developer",
      email: "developer@example.com",
      password: "developer",
    },
    moderator: {
      name: "moderator",
      email: "moderator@example.com",
      password: "moderator",
    },
    translator: {
      name: "translator",
      email: "translator@example.com",
      password: "translator",
    },
    user: {
      name: "user",
      email: "user@example.com",
      password: "user",
    },
  },
};
