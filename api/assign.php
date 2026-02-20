<?php

require_once __DIR__ . '/_helpers.php';

handle_options();
require_method('GET', 'POST');

// error_log('CONTENT TYPE: ' . ($_SERVER['CONTENT_TYPE'] ?? 'none'));
// error_log('RAW POST: ' . file_get_contents('php://input'));
// error_log('PARSED $_POST: ' . print_r($_POST, true));

// $data = $_POST;

// // Optional: if using Jotform rawRequest wrapper
// if (isset($data['rawRequest'])) {
//     $data = json_decode($data['rawRequest'], true);
// }

// if (!is_array($data) || empty($data)) {
//     error_response('Invalid or missing request body', 400);
// }



error_log('PARSED $_GET: ' . print_r($_GET, true));
error_log('PARSED: ' . $_GET['applicationId'], true);
error_log('require_param: ' . require_param('applicationId', true));


//$formId = require_param('form_id');
$applicationId = require_param('applicationId');
$targetFormId = '260492349743464';

// The 4 available reviewers
$reviewers = ['alya@mranti.my', 'muhammadhafiz.h@mranti.my', 'badzlan.khan@mranti.my', 'sharienna@mranti.my'];

$client = get_client();

try {
    // =========================================================================
    // 1. Fetch all submissions from the main table (target form)
    // =========================================================================
    $allSubmissions = [];
    $offset = 0;
    $limit = 100;

    do {
        $batch = $client->getFormSubmissions($targetFormId, $offset, $limit);
        $allSubmissions = array_merge($allSubmissions, $batch);
        $offset += $limit;
    } while (count($batch) === $limit);

    // =========================================================================
    // 2. Find QID mapping for reviewer1, reviewer2 and Application ID fields
    // =========================================================================
    $reviewer1Qid = null;
    $reviewer2Qid = null;
    $appStatusQid = null;
    $appIdQid = null;
    $targetSubmission = null;

    // First pass: find the target submission and field QIDs
    foreach ($allSubmissions as $sub) {
        if (!isset($sub['answers'])) continue;

        foreach ($sub['answers'] as $qid => $answer) {
            $text = strtolower(trim($answer['text'] ?? ''));

            if ($text === 'reviewer1') $reviewer1Qid = $qid;
            if ($text === 'reviewer2') $reviewer2Qid = $qid;
            if ($text === 'applicationstatus') $appStatusQid = $qid;
            if (stripos($answer['text'] ?? '', 'Application ID') !== false) $appIdQid = $qid;
        }
        print "<pre>";
        print "review1: ".$answer['text']." ".$reviewer1Qid."\n"; 
        print "review2: ".$answer['text']." ".$reviewer2Qid."\n";
        print "appstatus: ".$answer['text']." ".$appStatusQid."\n";
        print "appId: ".$answer['text']." ".$appIdQid."\n";
        exit;
        // Check if this is our target submission
        if ($appIdQid !== null) {
            $val = $sub['answers'][$appIdQid]['answer'] ?? '';
            if (is_string($val) && strcasecmp($val, $applicationId) === 0) {
                $targetSubmission = $sub;
            }
        }

        // Stop scanning once we have all QIDs
        if ($reviewer1Qid !== null && $reviewer2Qid !== null && $appStatusQid !== null && $appIdQid !== null && $targetSubmission !== null) {
            break;
        }
    }

    if (!$reviewer1Qid || !$reviewer2Qid) {
        error_response('Could not find reviewer1 or reviewer2 fields in the main table', 404);
    }

    if (!$appStatusQid) {
        error_response('Could not find applicationStatus field in the main table', 404);
    }

    if (!$targetSubmission) {
        error_response("No submission found in main table with Application ID = {$applicationId}", 404);
    }

    print "king";
    exit;

    // =========================================================================
    // 3. Verify the target submission has applicationStatus = "Submitted"
    // =========================================================================
    $currentStatus = trim($targetSubmission['answers'][$appStatusQid]['answer'] ?? '');
    if (strcasecmp($currentStatus, 'Submitted') !== 0) {
        json_response([
            'status' => 'skipped',
            'message' => "Application status is \"{$currentStatus}\", not \"Submitted\". No action taken.",
            'application_id' => $applicationId,
            'application_status' => $currentStatus,
        ]);
    }

    // =========================================================================
    // 4. Check if reviewers are already assigned for this submission
    // =========================================================================
    $currentReviewer1 = trim($targetSubmission['answers'][$reviewer1Qid]['answer'] ?? '');
    $currentReviewer2 = trim($targetSubmission['answers'][$reviewer2Qid]['answer'] ?? '');

    if ($currentReviewer1 !== '' && $currentReviewer2 !== '') {
        json_response([
            'status' => 'already_assigned',
            'message' => 'Both reviewers are already assigned for this application.',
            'application_id' => $applicationId,
            'reviewer1' => $currentReviewer1,
            'reviewer2' => $currentReviewer2,
        ]);
    }

    // =========================================================================
    // 5. Count reviewer assignments across "Submitted" submissions to find least assigned
    // =========================================================================
    $reviewerCounts = [];
    foreach ($reviewers as $r) {
        $reviewerCounts[$r] = 0;
    }

    foreach ($allSubmissions as $sub) {
        if (!isset($sub['answers'])) continue;

        // Only count reviewers from submissions with applicationStatus = "Submitted"
        $subStatus = trim($sub['answers'][$appStatusQid]['answer'] ?? '');
        if (strcasecmp($subStatus, 'Submitted') !== 0) continue;

        $r1 = trim($sub['answers'][$reviewer1Qid]['answer'] ?? '');
        $r2 = trim($sub['answers'][$reviewer2Qid]['answer'] ?? '');

        if ($r1 !== '' && isset($reviewerCounts[$r1])) {
            $reviewerCounts[$r1]++;
        }
        if ($r2 !== '' && isset($reviewerCounts[$r2])) {
            $reviewerCounts[$r2]++;
        }
    }

    // Sort reviewers by assignment count (least first)
    asort($reviewerCounts);

    // =========================================================================
    // 6. Determine which reviewers to assign
    // =========================================================================
    $assignReviewer1 = $currentReviewer1;
    $assignReviewer2 = $currentReviewer2;

    if ($assignReviewer1 === '' && $assignReviewer2 === '') {
        // Both empty: assign the 2 least assigned reviewers
        $sorted = array_keys($reviewerCounts);
        $assignReviewer1 = $sorted[0];
        $assignReviewer2 = $sorted[1];
    } elseif ($assignReviewer1 === '') {
        // Only reviewer1 is empty: pick the least assigned who isn't reviewer2
        foreach ($reviewerCounts as $name => $count) {
            if ($name !== $assignReviewer2) {
                $assignReviewer1 = $name;
                break;
            }
        }
    } elseif ($assignReviewer2 === '') {
        // Only reviewer2 is empty: pick the least assigned who isn't reviewer1
        foreach ($reviewerCounts as $name => $count) {
            if ($name !== $assignReviewer1) {
                $assignReviewer2 = $name;
                break;
            }
        }
    }

    // =========================================================================
    // 7. Update the main table submission
    // =========================================================================
    $updateData = [];
    if ($currentReviewer1 === '') {
        $updateData[(string) $reviewer1Qid] = $assignReviewer1;
    }
    if ($currentReviewer2 === '') {
        $updateData[(string) $reviewer2Qid] = $assignReviewer2;
    }
    $updateData[(string) $appStatusQid] = 'already_assigned';

    $editResult = $client->editSubmission($targetSubmission['id'], $updateData);

    json_response([
        'status' => 'success',
        'application_id' => $applicationId,
        'target_form_id' => $targetFormId,
        'target_submission_id' => $targetSubmission['id'],
        'reviewer1' => $assignReviewer1,
        'reviewer2' => $assignReviewer2,
        'reviewer_load' => $reviewerCounts,
        'edit_result' => $editResult,
    ]);

} catch (Exception $e) {
    error_response($e->getMessage(), $e->getCode() ?: 500);
}