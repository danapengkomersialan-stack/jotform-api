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


//$applicationId = require_param('applicationId');
$targetFormId = '260193165468058';
//$targetFormId = '260492349743464';

// The 4 available reviewers
$reviewers = ['alya@mranti.my', 'badzlan.khan@mranti.my', 'muhammadhafiz.h@mranti.my', 'sharienna@mranti.my'];
//$reviewers = ['ng.kiat@mranti.my', 'ng_king@yahoo.com', 'ngking80@gmail.com', 'derekfoo87@gmail.com'];
$client = get_client();

try {
    // =========================================================================
    // 1. Fetch all submissions from the main table (target form)
    // =========================================================================
    $allSubmissions = [];
    $offset = 0;
    $limit = 10000;

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
            $name = strtolower(trim($answer['name'] ?? ''));

            if ($name === 'reviewer1') $reviewer1Qid = $qid;
            if ($name === 'reviewer2') $reviewer2Qid = $qid;
            if ($name === 'applicationstatus') $appStatusQid = $qid;
            if ($name === 'applicationid') $appIdQid = $qid;
            
            //if (stripos($answer['text'] ?? '', 'Application ID') !== false) $appIdQid = $qid;
        }

        $val = $sub['answers'][$appIdQid]['answer'];
       
        // Check if this is our target submission
        // if ($appIdQid !== null) {
        //     $val = $sub['answers'][$appIdQid]['answer'] ?? '';
        //     if (is_string($val) && strcasecmp($val, $applicationId) === 0) {
        //         $targetSubmission = $sub;
        //     }
        // }

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

    // if (!$targetSubmission) {
    //     error_response("No submission found in main table with Application ID = {$applicationId}", 404);
    // }

    // =========================================================================
    // 3. Verify the target submission has applicationStatus = "Submitted"
    // =========================================================================
    $currentStatus = trim($targetSubmission['answers'][$appStatusQid]['answer'] ?? '');
    if (strcasecmp($currentStatus, 'Submitted') !== 0) {
        json_response([
            'status' => 'skipped',
            'message' => "Application status is \"{$currentStatus}\", not \"Submitted\". No action taken.",
            'application_id' => $appIdQid,
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
            'application_id' => $appIdQid,
            'reviewer1' => $currentReviewer1,
            'reviewer2' => $currentReviewer2,
        ]);
    }

    // =========================================================================
    // 5. Generate all unique reviewer pairs and find the next pair in rotation
    // =========================================================================
    $pairs = [];
    for ($i = 0; $i < count($reviewers); $i++) {
        for ($j = $i + 1; $j < count($reviewers); $j++) {
            $pairs[] = [$reviewers[$i], $reviewers[$j]];
        }
    }

    // Count how many submissions already have reviewers assigned to determine rotation position
    $assignedCount = 0;
    foreach ($allSubmissions as $sub) {
        if (!isset($sub['answers'])) continue;
        $subRev1 = trim($sub['answers'][$reviewer1Qid]['answer'] ?? '');
        $subRev2 = trim($sub['answers'][$reviewer2Qid]['answer'] ?? '');
        if ($subRev1 !== '' && $subRev2 !== '') {
            $assignedCount++;
        }
    }

    $pairIndex = $assignedCount % count($pairs);
    $nextPair = $pairs[$pairIndex];

    // =========================================================================
    // 6. Determine which reviewers to assign
    // =========================================================================
    $assignReviewer1 = $currentReviewer1 !== '' ? $currentReviewer1 : $nextPair[0];
    $assignReviewer2 = $currentReviewer2 !== '' ? $currentReviewer2 : $nextPair[1];

    

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

    // // Debug: print all submissions' ApplicationID, Reviewer1, Reviewer2
    // echo "All submissions (AppID | R1 | R2):\n";
    // foreach ($allSubmissions as $sub) {
    //     if (!isset($sub['answers'])) continue;
    //     $subAppId = $sub['answers'][$appIdQid]['answer']     ?? '';
    //     $subRev1  = $sub['answers'][$reviewer1Qid]['answer'] ?? '';
    //     $subRev2  = $sub['answers'][$reviewer2Qid]['answer'] ?? '';
    //     echo "  AppID={$subAppId} | R1={$subRev1} | R2={$subRev2}\n";
    // }

    // // Debug: print the new values about to be written
    // echo "\nAbout to editSubmission => AppID={$applicationId} | R1={$assignReviewer1} | R2={$assignReviewer2}\n\n";
    print "<pre>";
    print $targetSubmission['id']." ".
    print_r($updateData);
    
    // $editResult = $client->editSubmission($targetSubmission['id'], $updateData);

    // json_response([
    //     'status' => 'success',
    //     'application_id' => $applicationId,
    //     'target_form_id' => $targetFormId,
    //     'target_submission_id' => $targetSubmission['id'],
    //     'reviewer1' => $assignReviewer1,
    //     'reviewer2' => $assignReviewer2,
    //     'pair_index' => $pairIndex,
    //     'total_pairs' => count($pairs),
    //     'edit_result' => $editResult,
    // ]);

} catch (Exception $e) {
    error_response($e->getMessage(), $e->getCode() ?: 500);
}