# Assessments API – Postman Collection (Local)
This repository contains a **Postman collection** for interacting with the **Assessments API (local/development environment)**.
The collection demonstrates the full request flow:
- Obtaining an OAuth2 access token
- Calling public (unauthenticated) GET endpoints
- Submitting assessments via authenticated POST requests

## Installing Postman
To use this collection, you can install Postman on your machine.

You can download it here:
https://www.postman.com/downloads/

Postman provides installers for Windows, macOS, and Linux.<br>
Once installed, you can import the collection JSON file and start making requests immediately.

---
## How to obtain Client ID & Client Secret
To access protected endpoints, you must request API credentials.
1. Log in to the **Joinup** website
2. Request an **API client** for the Assessments API
3. Once approved:
  - You will be able to view your **Client ID**
  - You must set a **Client Secret**

No additional configuration or documentation is required beyond this process.

---
## How to use the collection

### Step 1: Import the collection JSON file into Postman
 - In the Postman app, on top of the left sidebar you have **New** and **Import** buttons, click on the import button and select `Assessments API.postman_collection.json`.
 - Once this is done you will see the collection under **Assessment API** in the left sidebar.
 - If you click the down-arrow you will notice the example requests.

---

### Step 2: Configure the base URL
 If you click on any of the example requests you will notice the `{{baseURL}}` placeholder.<br>
 The **baseURL** will be your local URL (in case of ddev it would be something like https://joinup.ddev.local).

To set the `baseURL` variable you need to:
 - click on the **Variables** tab in your center screen. There you will see the **baseURL** under **Variable**.
 - Next to it, under **Value** set your local or target URL.

---
### Step 3: Request an access token
1. Open the **oauth/token** request
2. click on the **Body** tab as the values are under the POST body.
2. Fill in:<br>
  `client_id`<br>
  `client_secret`
3. Send the request
4. Copy the value of `access_token` from the JSON response

---
### Step 4: Use the Bearer token for POST requests
1. Open a Request (for example Assessments)
2. Open the **Authorization** tab, select **Bearer Token** from the **Auth Type** select field.
3. Paste the access token
4. Send the request, you should receive a JSON response.

⚠️ Tokens expire after a short period and must be re‑requested when invalid.

---
## Payload examples
The POST requests include complete, working JSON payloads that demonstrate:
- Providers
- Binding requirements
- Expressions
- Participations
- Assets
- Results and judgements

These can be used as:
- Reference implementations
- Templates for client integrations
- Validation examples

---
## Notes
- This collection targets a **local development environment**
- Token handling is manual to keep the authentication flow explicit
- Client secrets must **never** be committed to version control
