<?php

class Service_grouppadlist extends EPLc_Service_IAbstractService {

	function perform($userinfo, $groupinfo, $serviceargs) {
		$m = 'Performing grouppadlist for ' . print_r($serviceargs, true) . ' for user ' . $userinfo->_userId;
		if ($groupinfo != null) { $m .= ' and group ' . $groupinfo->_groupId; }
		Logger_Log::debug($m, 'Service_grouppadlist');
		
		/* add means
		 * argument 0 is the new padname
		 * create it for the group with groupId from $groupinfo->_groupId
		 * ... and be done!
		 */
		$oEPLclient = new EtherpadLiteClient(ETHERPADLITE_APIKEY, ETHERPADLITE_BASEURL);
		
		$padname = $serviceargs[0];
        error_log("Padname: " . $padname);
        //error_log("Group info: " . $groupinfo);
        error_log("Group ID: " . $groupinfo->_groupId);
		$ep_group = $oEPLclient->createGroupIfNotExistsFor($groupinfo->_groupId);
		
        error_log($ep_group->groupID);
		$ep_group_pads = $oEPLclient->listPads($ep_group->groupID);
		
		$JSONpads = array();
	
		foreach ($ep_group_pads->padIDs as $p => $v) {
			$o = new EPLc_Pad($v);
			$JSONpads[] = $o->toJSONArray();
		}
		
		Logger_Log::debug("Created new session with id '{$sID}'", 'Service_groupsession');

        error_log(var_export($JSONpads,true));
		$result = EPLc_Service_Response::create(true, "Padlist retrieved; (" . count($JSONpads) . ") pads");
		$result->setData($JSONpads);
		
		Logger_Log::debug(print_r($result, true), 'Service_grouppadlist');
		
		return $result;
	}
	
}

