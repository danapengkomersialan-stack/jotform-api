<?php

require_once __DIR__ . '/_helpers.php';

handle_options();
require_method('GET', 'POST');

$formId = '260313088135047';
$applicationId = require_param('applicationId');
//$targetFormId = '260193165468058';
$targetFormId = '260492349743464';
$client = get_client();

try {
    // =========================================================================
    // 1. Search source form for submissions matching application_id
    // =========================================================================
    $allSubmissions = [];
    $offset = 0;
    $limit = 10000;

    do {
        $batch = $client->getFormSubmissions($formId, $offset, $limit);
        $allSubmissions = array_merge($allSubmissions, $batch);
        $offset += $limit;
    } while (count($batch) === $limit);

    // Find submissions matching applicationId
    $matched = [];
    foreach ($allSubmissions as $submission) {
        if (!isset($submission['answers'])) continue;
        foreach ($submission['answers'] as $answer) {
            $val = $answer['answer'] ?? '';
            if (is_string($val) && strcasecmp($val, $applicationId) === 0) {
                $matched[] = $submission;
                break;
            }
        }
    }

    if (count($matched) < 2) {
        json_response([
            'status' => 'skipped',
            'message' => 'Less than 2 submissions found. Need at least 2 to calculate averages.',
            'application_id' => $applicationId,
            'submissions_found' => count($matched),
        ]);
    }

    // =========================================================================
    // 2. Extract Section 1-6 Scoring from matched submissions and average by 2
    // =========================================================================

    // Find QIDs for "Section X Scoring" fields from the first matched submission
    print "<pre>";
    $sectionQids = []; // ['Section 1' => qid, 'Section 2' => qid, ...]
    $sixScoreFieldName = array('section1Score', 'section2Score', 'section3Score', 'section4Score', 'section5Score','section6Score');
    $firstAnswers = $matched[0]['answers'];
    foreach ($firstAnswers as $qid => $answer) {
        $name = $answer['name'] ?? '';
        print $name."\n";
        if (in_array($name,  $sixScoreFieldName)) {
            print "inarray\n";
            (int) $sectionNum = substr($name, 8, 1);
            print $sectionNum."\n"; 
            if ($sectionNum >= 1 && $sectionNum <= 6) {
                print "insection\n";
                 $sectionQids[$sectionNum] = $qid;
            }
        } else {
            print "notinarray\n";
        }
        // if (preg_match('/^Section\s+(\d+)\s+Scoring$/i', $name, $m)) {
        //     $sectionNum = (int) $m[1];
        //     if ($sectionNum >= 1 && $sectionNum <= 6) {
        //         $sectionQids[$sectionNum] = $qid;
        //     }
        // }
    }

    
    print_r($sectionQids)."\n";
    exit;

    if (empty($sectionQids)) {
        error_response('No "Section X Scoring" fields found in source submissions', 404);
    }

    

    // Calculate averages across first 2 submissions
    $sub1 = $matched[0]['answers'];
    $sub2 = $matched[1]['answers'];
    $averages = [];

    foreach ($sectionQids as $sectionNum => $qid) {
        $score1 = floatval($sub1[$qid]['answer'] ?? 0);
        $score2 = floatval($sub2[$qid]['answer'] ?? 0);
        $averages[$sectionNum] = ($score1 + $score2) / 2;
    }

    // =========================================================================
    // 3. Find target submission in target form where Application ID matches
    // =========================================================================
    $targetSubmissions = [];
    $offset = 0;

    do {
        $batch = $client->getFormSubmissions($targetFormId, $offset, $limit);
        $targetSubmissions = array_merge($targetSubmissions, $batch);
        $offset += $limit;
    } while (count($batch) === $limit);

    // Find target submission with matching Application ID
    $targetSubmission = null;
    foreach ($targetSubmissions as $sub) {
        if (!isset($sub['answers'])) continue;
        foreach ($sub['answers'] as $answer) {
            $text = $answer['text'] ?? '';
            $val = $answer['answer'] ?? '';
            if (stripos($text, 'Application ID') !== false && is_string($val) && strcasecmp($val, $applicationId) === 0) {
                $targetSubmission = $sub;
                break 2;
            }
        }
    }

    if (!$targetSubmission) {
        error_response("No submission found in target form with Application ID = {$applicationId}", 404);
    }

    // =========================================================================
    // 4. Map averages to target form's "Section X Total Score" fields and update
    // =========================================================================
    $targetQids = []; // sectionNum => qid in target form
    foreach ($targetSubmission['answers'] as $qid => $answer) {
        $text = $answer['text'] ?? '';
        if (preg_match('/^Section\s+(\d+)\s+Total\s+Score$/i', $text, $m)) {
            $sectionNum = (int) $m[1];
            if ($sectionNum >= 1 && $sectionNum <= 6) {
                $targetQids[$sectionNum] = $qid;
            }
        }
    }

    if (empty($targetQids)) {
        error_response('No "Section X Total Score" fields found in target form', 404);
    }

    // Build update payload: { "qid" => "averaged value" }
    $updateData = [];
    $updatedSections = [];
    foreach ($averages as $sectionNum => $avg) {
        if (isset($targetQids[$sectionNum])) {
            $updateData[(string) $targetQids[$sectionNum]] = (string) $avg;
            $updatedSections["Section {$sectionNum}"] = [
                'target_qid' => $targetQids[$sectionNum],
                'average' => $avg,
            ];
        }
    }

    $editResult = $client->editSubmission($targetSubmission['id'], $updateData);

    json_response([
        'status' => 'success',
        'application_id' => $applicationId,
        'source_form_id' => $formId,
        'target_form_id' => $targetFormId,
        'target_submission_id' => $targetSubmission['id'],
        'submissions_used' => 2,
        'scores' => [
            'submission_1_id' => $matched[0]['id'],
            'submission_2_id' => $matched[1]['id'],
        ],
        'averages' => $updatedSections,
        'edit_result' => $editResult,
    ]);

} catch (Exception $e) {
    error_response($e->getMessage(), $e->getCode() ?: 500);
}