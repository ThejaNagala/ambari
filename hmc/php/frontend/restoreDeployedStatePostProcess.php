<?php

include_once '../util/Logger.php';
include_once '../conf/Config.inc';
include_once 'localDirs.php';
include_once "../util/lock.php";
include_once '../db/HMCDBAccessor.php';
include_once '../util/clusterState.php';

include_once 'commandUtils.php';
include_once "../util/HMCTxnUtils.php";

// common post process function for manage services, post deploy add nodes
// Updates state back to DEPLOYED and associated success/failure
function restoreDeployedStatePostProcess($clusterName, $user, $txnId, $progress)
{
  $logger = new HMCLogger("ManageServicesPostProcess");
  $dbAccessor = new HMCDBAccessor($GLOBALS["DB_PATH"]);

  $result = 0;
  $error = "";

  /* Safe fallbacks, in case the call to getClusterState() below fails. */
  $state = "DEPLOYED";
  $displayName = "Deployed successfully";
  $context = array (
    'status' => TRUE
  );


  LockAcquire(HMC_CLUSTER_STATE_LOCK_FILE_SUFFIX);
  $clusterStateResponse = $dbAccessor->getClusterState($clusterName);
  LockRelease(HMC_CLUSTER_STATE_LOCK_FILE_SUFFIX);

  if ($clusterStateResponse['result'] != 0) {
    $logger->log_error("Failed to fetch cluster state (for restoration of stashed state)");

    $result = $clusterStateResponse["result"];
    $error = $clusterStateResponse["error"];
  }
  else {
    $clusterState = json_decode($clusterStateResponse['state'], true);

    $stashedDeployState = $clusterState["context"]["stashedDeployState"]; 
    /* Restore the cluster's state to that stashed at the time of beginning the
     * service management. 
     */
    $state = $stashedDeployState["state"];
    $displayName = $stashedDeployState["displayName"];
    $context = $stashedDeployState["context"];
  }

  // update state of the cluster 
  LockAcquire(HMC_CLUSTER_STATE_LOCK_FILE_SUFFIX);
  $retval = updateClusterState($clusterName, $state, $displayName, $context);
  LockRelease(HMC_CLUSTER_STATE_LOCK_FILE_SUFFIX);

  if ($retval['result'] != 0) {
    $logger->log_error("Update cluster state failed");
    $result = $retval['result'];
    $error = $retval['error'];
  }

  return (array("result" => $result, "error" => $error));
}

?>
