<?php

/**
 * JotForm API - Submission Test Script
 *
 * Tests: List all submissions, get single submission, edit submission,
 * and filter by custom field (Application ID).
 */

require_once __DIR__ . '/JotForm.php';

$apiKey = '259c72ebc428cde3103c72b9f24c067c';

try {
    $jotform = new JotForm($apiKey);

    // =========================================================================
    // 1. LIST ALL FORMS (to find form IDs)
    // =========================================================================
    echo "=== 1. LISTING FORMS ===\n";
    $forms = $jotform->getForms(0, 10);

    if (empty($forms)) {
        echo "No forms found in this account.\n";
        exit;
    }

    foreach ($forms as $form) {
        echo "  Form ID: {$form['id']} | Title: {$form['title']} | Submissions: {$form['count']}\n";
    }

    // Use the first form with submissions
    $targetFormID = null;
    foreach ($forms as $form) {
        if ((int)$form['count'] > 0) {
            $targetFormID = $form['id'];
            echo "\nUsing form: {$form['title']} (ID: {$targetFormID})\n";
            break;
        }
    }

    if (!$targetFormID) {
        echo "\nNo forms with submissions found.\n";
        exit;
    }

    // =========================================================================
    // 2. LIST FORM QUESTIONS (to find the Application ID field)
    // =========================================================================
    echo "\n=== 2. FORM QUESTIONS (looking for 'Application ID' field) ===\n";
    $questions = $jotform->getFormQuestions($targetFormID);

    $applicationIdQid = null;
    foreach ($questions as $qid => $question) {
        $name = $question['name'] ?? '';
        $text = $question['text'] ?? '';
        echo "  QID: {$qid} | Name: {$name} | Text: {$text} | Type: {$question['type']}\n";

        // Look for Application ID field (case-insensitive match)
        if (stripos($text, 'application id') !== false || stripos($name, 'applicationid') !== false || stripos($name, 'application_id') !== false) {
            $applicationIdQid = $qid;
            echo "  >>> Found 'Application ID' field at QID: {$qid}\n";
        }
    }

    // =========================================================================
    // 3. LIST ALL SUBMISSIONS
    // =========================================================================
    echo "\n=== 3. LIST ALL SUBMISSIONS ===\n";
    $submissions = $jotform->getFormSubmissions($targetFormID, 0, 20);

    if (empty($submissions)) {
        echo "No submissions found.\n";
        exit;
    }

    foreach ($submissions as $sub) {
        echo "  Submission ID: {$sub['id']} | Created: {$sub['created_at']} | Status: {$sub['status']}\n";

        // Print Application ID value if field was found
        if ($applicationIdQid && isset($sub['answers'][$applicationIdQid])) {
            $answer = $sub['answers'][$applicationIdQid];
            $value = $answer['answer'] ?? '(empty)';
            echo "    -> Application ID: {$value}\n";
        }
    }

    // =========================================================================
    // 4. GET SINGLE SUBMISSION
    // =========================================================================
    $firstSubmissionID = $submissions[0]['id'];
    echo "\n=== 4. SINGLE SUBMISSION (ID: {$firstSubmissionID}) ===\n";
    $single = $jotform->getSubmission($firstSubmissionID);

    echo "  ID:         {$single['id']}\n";
    echo "  Form ID:    {$single['form_id']}\n";
    echo "  Created:    {$single['created_at']}\n";
    echo "  Updated:    {$single['updated_at']}\n";
    echo "  Status:     {$single['status']}\n";
    echo "  IP:         {$single['ip']}\n";
    echo "  Answers:\n";

    if (isset($single['answers'])) {
        foreach ($single['answers'] as $qid => $answer) {
            $text = $answer['text'] ?? 'N/A';
            $value = '';
            if (isset($answer['answer'])) {
                $value = is_array($answer['answer']) ? json_encode($answer['answer']) : $answer['answer'];
            }
            echo "    [{$qid}] {$text}: {$value}\n";
        }
    }

    // =========================================================================
    // 5. EDIT SUBMISSION (demo - updates status to ACTIVE)
    // =========================================================================
    echo "\n=== 5. EDIT SUBMISSION (ID: {$firstSubmissionID}) ===\n";
    echo "  Setting status back to ACTIVE...\n";

    // Edit with a safe operation: set status
    // To edit a field answer, use: ["qid_subfield" => "value"] or ["qid" => "value"]
    $editResult = $jotform->editSubmission($firstSubmissionID, [
        'new' => '1',  // Mark as new/unread
    ]);
    echo "  Result: ";
    print_r($editResult);
    echo "\n";

    // =========================================================================
    // 6. FILTER SUBMISSIONS BY APPLICATION ID
    // =========================================================================
    echo "\n=== 6. FILTER BY APPLICATION ID ===\n";

    if ($applicationIdQid) {
        // Get a sample Application ID value from existing submissions
        $sampleAppId = null;
        foreach ($submissions as $sub) {
            if (isset($sub['answers'][$applicationIdQid]['answer'])) {
                $sampleAppId = $sub['answers'][$applicationIdQid]['answer'];
                if (!empty($sampleAppId)) break;
            }
        }

        if ($sampleAppId) {
            echo "  Searching for Application ID: {$sampleAppId}\n";

            // JotForm filter uses the format: {"qid:field_name": "value"}
            // For answer-based filtering, the key format is "q{QID}:answer"
            // However, JotForm submission filters only support standard fields:
            //   id, form_id, ip, created_at, updated_at, status, new, flag
            //
            // Custom field filtering is NOT directly supported by the API filter.
            // Workaround: fetch all and filter client-side.

            echo "\n  NOTE: JotForm API filters only work on standard submission\n";
            echo "  fields (id, created_at, status, etc.), NOT on custom form\n";
            echo "  field answers like 'Application ID'.\n\n";
            echo "  Workaround: Fetch submissions and filter client-side:\n\n";

            $allSubs = $jotform->getFormSubmissions($targetFormID, 0, 1000);
            $matched = [];

            foreach ($allSubs as $sub) {
                if (isset($sub['answers'][$applicationIdQid]['answer'])) {
                    $val = $sub['answers'][$applicationIdQid]['answer'];
                    if ($val === $sampleAppId) {
                        $matched[] = $sub;
                    }
                }
            }

            echo "  Found " . count($matched) . " submission(s) with Application ID = {$sampleAppId}\n";
            foreach ($matched as $m) {
                echo "    Submission ID: {$m['id']} | Created: {$m['created_at']}\n";
            }
        } else {
            echo "  No submissions have an Application ID value to search for.\n";
        }
    } else {
        echo "  'Application ID' field was NOT found in this form's questions.\n";
        echo "  Available fields are listed in section 2 above.\n";
        echo "\n";
        echo "  If the field exists with a different name, update the search in\n";
        echo "  section 2 of this script, or use the QID directly.\n";
    }

    echo "\n=== DONE ===\n";

} catch (JotFormException $e) {
    echo "JotForm API Error [{$e->getCode()}]: {$e->getMessage()}\n";
} catch (Exception $e) {
    echo "Error [{$e->getCode()}]: {$e->getMessage()}\n";
}
