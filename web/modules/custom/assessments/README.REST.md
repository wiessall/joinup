Assessments REST API examples

Base paths: /entity and /assessments

Authentication: Cookie authentication (user session). Ensure you are logged in or include a CSRF token for unsafe methods.

1. Compose an assessment with nested entities

POST /assessments/compose
Content-Type: application/json
X-CSRF-Token: <token>

{
  "status": true,
  "organization": { "label": "City of Example", "classification": "government" },
  "requirement": {
    "label": "EU Directive 123",
    "description": "Accessibility",
    "participant": [
      {
        "identifier": "P-001",
        "role": "regulator",
        "agent": [ { "name": "Ministry of Digital" } ]
      }
    ],
    "affected_party": [
      { "identifier": "svc-001", "name": "Digital ID" },
      { "identifier": "svc-002", "name": "Citizen Portal" }
    ],
    "assets": [
      { "label": "Website", "identifier": "ext-123", "asset_type": "software", "asset_uri": "https://example.org" },
      { "label": "API", "asset_type": "service" }
    ]
  },
  "statements": [
    {
      "name": "Statement A",
      "description": "...",
      "services": [ { "identifier": "svc-003", "name": "Portal" } ]
    },
    { "name": "Statement B" }
  ]
}

Response: 201 Created (returns the created assessment and created children)

Notes
- Supported formats: json, xml (entity endpoints); json (composer endpoint)
- Supported methods: GET, POST, PATCH, DELETE (entity endpoints); POST (composer endpoint)
- Authentication: cookie
- CSRF token endpoint: /session/token
