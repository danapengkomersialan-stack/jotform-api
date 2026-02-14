# JotForm API - Vercel Serverless PHP Endpoints

## Overview

Serverless PHP API endpoints deployed on Vercel, wrapping the [JotForm REST API](https://api.jotform.com/docs/) for listing, viewing, editing, and searching form submissions by Application ID.

- **Runtime**: `vercel-community/php@0.9.0` (includes cURL)
- **Auth**: `JOTFORM_API_KEY` environment variable (set in Vercel dashboard)
- **Output**: JSON with CORS headers

## File Structure

```
vercel.json               # Vercel runtime config
.env.example              # Documents required env vars
.gitignore                # Ignores .vercel/, .env, node_modules/
JotForm.php               # Original JotForm PHP client library
api/
  _JotForm.php            # Copy of JotForm.php (underscore = not exposed as endpoint)
  _helpers.php            # Shared helpers: init client, JSON response, error handling
  test.php                # GET /api/test - health check and connectivity test
  forms.php               # GET /api/forms - list all forms
  submissions.php         # GET /api/submissions - list submissions for a form
  submission.php          # GET/POST /api/submission - view/edit a single submission
  search.php              # GET /api/search - search submissions by Application ID
```

Files prefixed with `_` are helpers and are **not** exposed as HTTP endpoints by Vercel.

## API Endpoints

### GET /api/test

Health check endpoint. Tests PHP environment, required extensions, and JotForm API connectivity.

**Response:**
```json
{
  "status": "ok",
  "php_version": "8.x.x",
  "timestamp": "2026-02-14T00:00:00+00:00",
  "env": { "JOTFORM_API_KEY": "set (32 chars)" },
  "extensions": { "curl": true, "json": true },
  "jotform": {
    "connected": true,
    "username": "...",
    "email": "...",
    "account_type": "..."
  }
}
```

### GET /api/forms

Returns all forms for the account.

**Response:**
```json
{
  "forms": [
    { "id": "123", "title": "My Form", "count": "5", "created_at": "...", "status": "ENABLED" }
  ]
}
```

### GET /api/submissions

Returns paginated submissions for a form.

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `form_id` | Yes | — | JotForm form ID |
| `offset` | No | `0` | Pagination offset |
| `limit` | No | `20` | Results per page |

**Response:**
```json
{
  "form_id": "123",
  "offset": 0,
  "limit": 20,
  "count": 5,
  "submissions": [ ... ]
}
```

### GET /api/submission

Returns a single submission with all answers.

| Parameter | Required | Description |
|-----------|----------|-------------|
| `id` | Yes | Submission ID |

**Response:**
```json
{ "submission": { "id": "456", "form_id": "123", "answers": { ... }, ... } }
```

### POST /api/submission

Edits a submission. Send a JSON body with field ID/value pairs.

| Parameter | Required | Description |
|-----------|----------|-------------|
| `id` | Yes | Submission ID (query param) |

**Request body:**
```json
{ "field_id": "new value", "5_first": "John", "5_last": "Doe" }
```

**Response:**
```json
{ "result": { ... } }
```

### GET /api/search

Searches submissions by Application ID. Fetches all submissions and filters client-side (JotForm API does not support filtering by custom answer fields).

| Parameter | Required | Description |
|-----------|----------|-------------|
| `form_id` | Yes | JotForm form ID |
| `application_id` | Yes | Application ID value to search for |

**Response:**
```json
{
  "form_id": "123",
  "application_id": "APP001",
  "total_searched": 150,
  "count": 2,
  "submissions": [ ... ]
}
```

## Shared Helpers (`api/_helpers.php`)

| Function | Description |
|----------|-------------|
| `cors_headers()` | Sets `Content-Type: application/json` and CORS headers |
| `handle_options()` | Returns 204 for OPTIONS preflight requests |
| `json_response($data, $status)` | Sends JSON response with status code and exits |
| `error_response($message, $status)` | Sends JSON error response and exits |
| `require_method(...$methods)` | Returns 405 if request method is not allowed |
| `get_client()` | Initializes `JotForm` client from `JOTFORM_API_KEY` env var |
| `require_param($name)` | Returns query parameter value or 400 error if missing |

## Error Responses

All errors return JSON with an `error` field:

```json
{ "error": "Missing required parameter: form_id" }
```

| Status | Cause |
|--------|-------|
| 400 | Missing or invalid parameters |
| 401 | Invalid API key |
| 405 | Wrong HTTP method |
| 500 | Server error or missing `JOTFORM_API_KEY` |

## Deployment

1. Install Vercel CLI: `npm i -g vercel`
2. Set env var in Vercel dashboard: **Settings > Environment Variables > `JOTFORM_API_KEY`**
3. Deploy: `vercel deploy`
4. Test:
   ```bash
   curl https://your-app.vercel.app/api/test
   curl https://your-app.vercel.app/api/forms
   curl https://your-app.vercel.app/api/submissions?form_id=XXX
   curl https://your-app.vercel.app/api/submission?id=XXX
   curl https://your-app.vercel.app/api/search?form_id=XXX&application_id=APP001
   ```

## JotForm PHP Client

The underlying `JotForm` class (`JotForm.php`) provides full access to the JotForm REST API v1. Key details:

- **Auth**: API key sent as `APIKEY` HTTP header
- **EU auto-detection**: Constructor calls `getUser()` to detect EU accounts and switches base URL to `https://eu-api.jotform.com`
- **Submission field format**: Fields with underscores (e.g., `5_first`) are split into `submission[qid][subfield]` format for compound question types
- **No external dependencies**: Uses only PHP's built-in cURL extension
