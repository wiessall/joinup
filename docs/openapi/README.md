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

**Example:**
- `/api/assessments/v1/assessment/12345678-1234-1234-1234-123456789abc`

---

---
### 5\. Get Assets (Public)
**GET** `/api/assessments/v1/asset`
- Authentication: ❌ Not required
- Purpose: Retrieve asset data
- Query parameters (optional):
  - `page`: Page number for pagination (e.g., `0`, `1`, `2`)

**Example:**
- `/api/assessments/v1/asset?page=0` – Get first page of assets

---
### 6\. Post Assessment (Authenticated)
**POST** `/api/assessments/v1/assessment`
- Authentication: ✅ Bearer Token required
- Purpose: Submit a complete assessment payload

The collection includes two example POST requests:
- `Post-complete`
- `Post-complete european`

These demonstrate valid payload structures for different provider types.
---
## How to use the collection
### Step 1: Import the collection
Import the Postman collection JSON file into Postman.
---
### Step 2: Configure the base URL
Set the `baseURL` variable to match your local or target environment.
---
### Step 3: Request an access token
1. Open the **oauth/token** request
2. Fill in:
  - `client_id`
  - `client_secret`
3. Send the request
4. Copy the value of `access_token`

---
### Step 4: Use the Bearer token for POST requests
1. Open a POST request
2. Go to **Authorization → Bearer Token**
3. Paste the access token
4. Send the request

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
