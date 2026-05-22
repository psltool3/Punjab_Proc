<?php
require('../util/Connection.php');
require('../util/SessionCheck.php');
require('../util/Logger.php');

if (isset($_POST['uniqueid'])) {
    $uniqueid = $_POST['uniqueid'];
    
    // Get the current active table
    $query = "SELECT id FROM optimised_table_leg1 ORDER BY last_updated DESC LIMIT 1";
    $result = mysqli_query($con, $query);
    if ($row = mysqli_fetch_array($result)) {
        $id = $row["id"];
        $tablename = "optimiseddata_leg1_" . $id;

        // format of uniqueid: fromid_toid_commodity
        $parts = explode("_", $uniqueid, 3);
        if (count($parts) === 3) {
            $fromid = $parts[0];
            $toid = $parts[1];
            $commodity = $parts[2];
            
            // Revert characters replaced in JS if any
            $toid = str_replace('_', '.', $toid);
            $commodity = str_replace('_', '.', $commodity);

            $query = "UPDATE $tablename SET 
                      approve_admin = NULL, 
                      new_id_admin = NULL, 
                      new_name_admin = NULL, 
                      new_distance_admin = NULL, 
                      reason_admin = NULL 
                      WHERE from_id = '$fromid' AND to_id = '$toid' AND commodity = '$commodity'";
            
            if (mysqli_query($con, $query)) {
                writeLog("User -> Reset Approval Leg 1 | " . $_SESSION['user'] . " | $fromid - $toid - $commodity");
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => mysqli_error($con)]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid unique ID format']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No active optimization table found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Missing unique ID']);
}

mysqli_close($con);
?>
