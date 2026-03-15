<?php
// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params
// vaccine_functions.php

/**
 * Calculate child's age in weeks from date of birth
 */
function get_age_in_weeks($dob) {
    $birth = new DateTime($dob);
    $today = new DateTime();
    $days = $birth->diff($today)->days;
    return floor($days / 7);
}

/**
 * Get all vaccines a child needs (due + overdue)
 */
function get_child_vaccine_status($child_id, $conn) {
    // Get child's DOB
    $child = $conn->query("SELECT date_of_birth FROM children WHERE child_id = $child_id")->fetch_assoc();
    if (!$child) return [];
    
    $age_weeks = get_age_in_weeks($child['date_of_birth']);
    
    // Get all vaccines ordered by recommended age
    $all_vaccines = $conn->query("SELECT * FROM vaccines ORDER BY min_age_weeks");
    
    // Get vaccines already given to this child
    $given = $conn->query("SELECT vaccine_id, date_administered FROM vaccination_records WHERE child_id = $child_id");
    $given_vaccines = [];
    while ($row = $given->fetch_assoc()) {
        $given_vaccines[$row['vaccine_id']] = $row['date_administered'];
    }
    
    $status = [
        'given' => [],
        'due' => [],
        'overdue' => [],
        'upcoming' => []
    ];
    
    while ($vax = $all_vaccines->fetch_assoc()) {
        $vax_id = $vax['vaccine_id'];
        
        // Check if already given
        if (isset($given_vaccines[$vax_id])) {
            $vax['date_given'] = $given_vaccines[$vax_id];
            $status['given'][] = $vax;
            continue;
        }
        
        // Check if age qualifies for this vaccine
        if ($age_weeks >= $vax['min_age_weeks']) {
            // Check if overdue (past max age)
            if ($vax['max_age_weeks'] && $age_weeks > $vax['max_age_weeks']) {
                $status['overdue'][] = $vax;
            } else {
                $status['due'][] = $vax;
            }
        } else {
            $status['upcoming'][] = $vax;
        }
    }
    
    return $status;
}

/**
 * Check if a vaccine can be given (age check + interval check)
 */
function can_give_vaccine($child_id, $vaccine_id, $conn) {
    // Get child's age
    $child = $conn->query("SELECT date_of_birth FROM children WHERE child_id = $child_id")->fetch_assoc();
    $age_weeks = get_age_in_weeks($child['date_of_birth']);
    
    // Get vaccine details
    $vax = $conn->query("SELECT * FROM vaccines WHERE vaccine_id = $vaccine_id")->fetch_assoc();
    
    // Check age
    if ($age_weeks < $vax['min_age_weeks']) {
        return "Too early. Minimum age is " . $vax['min_age_weeks'] . " weeks.";
    }
    
    // Check if already given
    $check = $conn->query("SELECT * FROM vaccination_records WHERE child_id = $child_id AND vaccine_id = $vaccine_id");
    if ($check->num_rows > 0) {
        return "Vaccine already given to this child.";
    }
    
    // Check interval from previous dose if applicable
    if ($vax['interval_weeks']) {
        // Get the previous dose of same vaccine family
        $prev = $conn->query("
            SELECT vr.* FROM vaccination_records vr
            JOIN vaccines v ON vr.vaccine_id = v.vaccine_id
            WHERE vr.child_id = $child_id 
            AND v.vaccine_name = '{$vax['vaccine_name']}'
            ORDER BY vr.date_administered DESC
            LIMIT 1
        ");
        
        if ($prev->num_rows > 0) {
            $prev_dose = $prev->fetch_assoc();
            $prev_date = new DateTime($prev_dose['date_administered']);
            $today = new DateTime();
            $weeks_since = floor($prev_date->diff($today)->days / 7);
            
            if ($weeks_since < $vax['interval_weeks']) {
                return "Must wait " . $vax['interval_weeks'] . " weeks between doses. Only $weeks_since weeks passed.";
            }
        }
    }
    
    return true;
}

/**
 * Record a vaccine as given
 */
function record_vaccine($child_id, $vaccine_id, $doctor_id, $date_given, $batch, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO vaccination_records 
        (child_id, vaccine_id, date_administered, administered_by, batch_number, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'Completed', NOW())
    ");
    $stmt->bind_param("iisis", $child_id, $vaccine_id, $date_given, $doctor_id, $batch);
    
    if ($stmt->execute()) {
        return true;
    } else {
        return false;
    }
    $stmt->close();
}