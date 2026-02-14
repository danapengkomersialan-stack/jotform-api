# JotForm API - PHP Client Summary

## Overview

This is a PHP wrapper/client for the [JotForm REST API](https://api.jotform.com/docs/). It provides a single `JotForm` class that abstracts all HTTP communication (GET, POST, PUT, DELETE) with JotForm's API endpoints using cURL.

- **Package**: `jotform/jotform-api-php`
- **License**: GPL-3.0+
- **PHP Requirement**: >= 5.3.0
- **Output Formats**: JSON (default), XML

## Architecture

### Core Components

| Component | Description |
|-----------|-------------|
| `JotForm` class | Main API client. Handles auth, HTTP requests, and response parsing. |
| `JotFormException` class | Custom exception extending PHP's `Exception` for API errors. |

### Authentication

- All requests are authenticated via an **API key** passed as an `APIKEY` HTTP header.
- The API key is provided in the constructor.
- On initialization, the client calls `getUser()` to detect EU accounts and automatically switches the base URL from `https://api.jotform.com` to `https://eu-api.jotform.com`.

### HTTP Layer

The private `executeHttpRequest()` method handles all communication:

- Builds URLs as `{baseURL}/{apiVersion}/{path}` (API version is `v1`)
- Appends `.xml` to the path when XML output is selected
- GET params are appended as query strings; POST/PUT params are sent as form fields
- SSL verification is **disabled** (`CURLOPT_SSL_VERIFYPEER = false`)
- User agent is set to `JOTFORM_PHP_WRAPPER`

### Error Handling

| HTTP Status | Exception Message |
|-------------|-------------------|
| 400, 403, 404 | API response `message` field |
| 401 | "Invalid API key or Unauthorized API call" |
| 503 | "Service is unavailable, rate limits etc exceeded!" |
| Other | API response `info` field |

### Query Helpers

- **`createConditions()`** - Builds pagination/filter params: `offset`, `limit`, `filter` (JSON-encoded), `orderby`
- **`createHistoryQuery()`** - Builds history query params: `action`, `date`, `sortBy`, `startDate`, `endDate`

## API Methods

### User Endpoints

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getUser()` | GET | `/user` | Account details (type, avatar, name, email, limits) |
| `getUsage()` | GET | `/user/usage` | Monthly submission counts and upload space |
| `getForms()` | GET | `/user/forms` | List all forms (supports pagination/filter/order) |
| `getSubmissions()` | GET | `/user/submissions` | List all submissions (supports pagination/filter/order) |
| `getSubusers()` | GET | `/user/subusers` | List sub users and access privileges |
| `getFolders()` | GET | `/user/folders` | List form folders |
| `getReports()` | GET | `/user/reports` | List report URLs (Excel, CSV, charts, HTML) |
| `getSettings()` | GET | `/user/settings` | User settings (timezone, language) |
| `updateSettings($settings)` | POST | `/user/settings` | Update user settings |
| `getHistory()` | GET | `/user/history` | Activity log (filterable by action, date range) |

### Form Endpoints

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getForm($formID)` | GET | `/form/{id}` | Form info (status, dates, submission count) |
| `createForm($form)` | POST | `/user/forms` | Create a single form |
| `createForms($forms)` | PUT | `/user/forms` | Create multiple forms |
| `deleteForm($formID)` | DELETE | `/form/{id}` | Delete a form |
| `cloneForm($formID)` | POST | `/form/{id}/clone` | Clone a form |

### Form Questions

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getFormQuestions($formID)` | GET | `/form/{id}/questions` | List all questions |
| `getFormQuestion($formID, $qid)` | GET | `/form/{id}/question/{qid}` | Get single question details |
| `createFormQuestion($formID, $question)` | POST | `/form/{id}/questions` | Add one question |
| `createFormQuestions($formID, $questions)` | PUT | `/form/{id}/questions` | Add multiple questions |
| `editFormQuestion($formID, $qid, $props)` | POST | `/form/{id}/question/{qid}` | Edit question properties |
| `deleteFormQuestion($formID, $qid)` | DELETE | `/form/{id}/question/{qid}` | Delete a question |

### Form Submissions

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getFormSubmissions($formID)` | GET | `/form/{id}/submissions` | List submissions (paginated) |
| `createFormSubmission($formID, $data)` | POST | `/form/{id}/submissions` | Submit data to a form |
| `createFormSubmissions($formID, $data)` | PUT | `/form/{id}/submissions` | Bulk submit data |

### Form Properties

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getFormProperties($formID)` | GET | `/form/{id}/properties` | List all properties |
| `getFormProperty($formID, $key)` | GET | `/form/{id}/properties/{key}` | Get single property |
| `setFormProperties($formID, $props)` | POST | `/form/{id}/properties` | Set properties |
| `setMultipleFormProperties($formID, $props)` | PUT | `/form/{id}/properties` | Set multiple properties |

### Form Files & Webhooks

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getFormFiles($formID)` | GET | `/form/{id}/files` | List uploaded files |
| `getFormWebhooks($formID)` | GET | `/form/{id}/webhooks` | List webhooks |
| `createFormWebhook($formID, $url)` | POST | `/form/{id}/webhooks` | Add a webhook |
| `deleteFormWebhook($formID, $webhookID)` | DELETE | `/form/{id}/webhooks/{wid}` | Delete a webhook |

### Form Reports

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getFormReports($formID)` | GET | `/form/{id}/reports` | List form reports |
| `createReport($formID, $report)` | POST | `/form/{id}/reports` | Create a new report |

### Submission Endpoints

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getSubmission($sid)` | GET | `/submission/{sid}` | Get submission data and answers |
| `editSubmission($sid, $data)` | POST | `/submission/{sid}` | Edit a submission |
| `deleteSubmission($sid)` | DELETE | `/submission/{sid}` | Delete a submission |

### Report Endpoints

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getReport($reportID)` | GET | `/report/{id}` | Get report details |
| `deleteReport($reportID)` | DELETE | `/report/{id}` | Delete a report |

### Folder Endpoints

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getFolder($folderID)` | GET | `/folder/{id}` | Get folder details and forms |
| `createFolder($props)` | POST | `/folder` | Create a folder |
| `updateFolder($folderID, $props)` | PUT | `/folder/{id}` | Update folder properties |
| `deleteFolder($folderID)` | DELETE | `/folder/{id}` | Delete folder and subfolders |
| `addFormToFolder($folderID, $formID)` | PUT | `/folder/{id}` | Add one form to folder |
| `addFormsToFolder($folderID, $formIDs)` | PUT | `/folder/{id}` | Add multiple forms to folder |

### Auth & System Endpoints

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `registerUser($details)` | POST | `/user/register` | Register new user |
| `loginUser($credentials)` | POST | `/user/login` | Login and get app key |
| `logoutUser()` | GET | `/user/logout` | Logout user |
| `getPlan($planName)` | GET | `/system/plan/{name}` | Get plan details (FREE, PREMIUM, etc.) |

## Usage Example

```php
<?php
include "JotForm.php";

$jotform = new JotForm("YOUR_API_KEY");

// List forms
$forms = $jotform->getForms();

// Get submissions for a form
$submissions = $jotform->getFormSubmissions($forms[0]["id"]);

// Submit data
$jotform->createFormSubmission($formID, [
    "1" => "Simple answer",
    "2_first" => "John",   // compound field: qid_subfield
    "2_last"  => "Doe",
]);
```

## Notable Design Details

1. **Submission field format**: Fields with underscores (e.g., `2_first`) are split into `submission[qid][subfield]` format for compound question types (name, address, etc.). The `created_at` key is excluded from this splitting logic in `editSubmission()`.

2. **EU auto-detection**: The constructor calls `getUser()` on every instantiation to check for `euOnly` flag and switch the base URL accordingly. This means every client instantiation makes one API call.

3. **Debug mode**: When enabled, request URLs, parameters, and responses are printed via `print_r`/`var_dump` for troubleshooting.

4. **No dependency on external libraries**: Uses only PHP's built-in cURL extension.
