<?php

class Service_add extends EPLc_Service_IAbstractService {
	
	function perform($userinfo, $groupinfo, $serviceargs) {
		$m = 'Performing add for ' . print_r($serviceargs, true) . ' for user ' . $userinfo->_userId;
		if ($groupinfo != null) { $m .= ' and group ' . $groupinfo->_groupId; }
		Logger_Log::debug($m, 'Service_add');
		
		/* add means
		 * argument 0 is the new padname
		 * create it for the group with groupId from $groupinfo->_groupId
		 * ... and be done!
		 */
		$oEPLclient = new EtherpadLiteClient(ETHERPADLITE_APIKEY, ETHERPADLITE_BASEURL);
		
		$padname = $serviceargs[0];
		$ep_group = $oEPLclient->createGroupIfNotExistsFor($groupinfo->_groupId);
	
		$ep_new_pad = $oEPLclient->createGroupPad($ep_group->groupID, $padname, "{$padname}\n\nThis is a new group pad created using the JACSON portal.");
		Logger_Log::debug("Created new GroupPad with id '{$ep_new_pad->padID}'", 'Service_add');

        $storage = new EPLc_Storage('padowners');
        $paddata = array(
            'padId' => $ep_new_pad->padID,
            'groupId' => $groupinfo->_groupId,
            'userId' => $userinfo->_userId
        );
        error_log("Storing in DB: " . var_export($paddata,true));
        $storage->set('paddata', $paddata['padId'], $paddata['userId'], $paddata);

		$result = EPLc_Service_Response::create(true, "Pad created succesfully.");
		$result->setData(array(
				'padId' => $ep_new_pad->padID,
			));
		
		return $result;
	}
	
	
}
