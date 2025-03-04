<?php

/**
* Submodul: Alarm
*
**/


/**
* Function: turn_off_alarms --> turns off all Sonos alarms
*
* @param: empty
* @return: disabled alarms
**/
function turn_off_alarms() {
	global $sonoszone, $master, $psubfolder, $home, $alarm_off_file;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$alarm = $sonos->ListAlarms();
	file_put_contents($alarm_off_file, json_encode($alarm, JSON_PRETTY_PRINT));
	$quan = count($alarm);
	for ($i=0; $i<$quan; $i++) {
		$sonos->UpdateAlarm($alarm[$i]['ID'], $alarm[$i]['StartTime'], $alarm[$i]['Duration'], $alarm[$i]['Recurrence'], 
		$alarm[$i]['Enabled'] = 0, $alarm[$i]['RoomUUID'], $alarm[$i]['ProgramURI'], $alarm[$i]['ProgramMetaData'], 
		$alarm[$i]['PlayMode'], $alarm[$i]['Volume'], $alarm[$i]['IncludeLinkedZones']);
	}
	LOGGING("alarm.php: All Sonos alarms has been turned off.", 6);
}


/**
* Function: restore_alarms --> turns on all previous saved Sonos alarms
*
* @param: empty
* @return: disabled alarms
**/
function restore_alarms() {
	global $sonos, $sonoszone, $psubfolder, $home, $master, $alarm_off_file;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	if (file_get_contents($alarm_off_file) === false)   {
		LOGGING("alarm.php: Sonos alarms could not be restored, can't open file!", 6);
		exit;
	} else {
		$alarm = json_decode(file_get_contents($alarm_off_file), TRUE);
	}
	$quan = count($alarm);
	for ($i=0; $i<$quan; $i++) {
		$sonos->UpdateAlarm($alarm[$i]['ID'], $alarm[$i]['StartTime'], $alarm[$i]['Duration'], $alarm[$i]['Recurrence'], 
		$alarm[$i]['Enabled'], $alarm[$i]['RoomUUID'], $alarm[$i]['ProgramURI'], $alarm[$i]['ProgramMetaData'], 
		$alarm[$i]['PlayMode'], $alarm[$i]['Volume'], $alarm[$i]['IncludeLinkedZones']);
	}
	@unlink($alarm_off_file);
	LOGGING("alarm.php: Previous saved Sonos alarms has been restored.", 6);
		
}



/**
* Function: sleeptimer --> setzt einen Sleeptimer
*
* @param: empty
* @return: 
**/
function sleeptimer() {
	
	global $sonoszone, $master;
	
	if(isset($_GET['timer']) && is_numeric($_GET['timer']) && $_GET['timer'] > 0 && $_GET['timer'] <= 120) {
		$timer = $_GET['timer'];
		if($_GET['timer'] < 10) {
			$hours = "00";
			$minutes = "0".$_GET['timer'];
			$seconds = "00";
		} else if ($_GET['timer'] > 60) {
			$hours = "0".intval($timer / 60);
			$minutes = intval($timer % 60);
			if ($minutes < 10)  {
				$minutes = "0".$minutes;
			} else {
				$minutes;
			}
			$seconds = "00";
		} else {
			$hours = "00";
			$minutes = $_GET['timer'];
			$seconds = "00";
		}
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$sonos->SetSleeptimer($hours, $minutes, $seconds);
		LOGGING("alarm.php: Sleeptimer has been switched on. Time to sleep for Zone '".$master."' is '".$timer."' Minutes.", 6);
	} else {
		LOGGING('alarm.php: The entered time is not correct, please correct (minutes between 0 and 120 are allowed)', 4);
	}
}


/**
* Function: turn_off_alarm --> disable specific Sonos alarms
*
* @param: empty
* @return: disable alarm
**/
function turn_off_alarm() {
	
	global $master, $sonoszone, $lbpdatadir, $psubfolder, $home, $single_alarm_off;
	
	$alarmid = $_GET['id'];
	$single_alarm_off = $lbpdatadir."/s4lox_alarm_ID_".$alarmid."_off.json";				// path/file for specific Alarm turned off
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$alarm = $sonos->ListAlarms();
	foreach ($alarm as $key => $value)    {
		$rinc = $value['RoomUUID'];
		$search = recursive_array_search($rinc, $sonoszone);
		if ($search === false)    {
			$alarm[$key]['Room'] = "NO ROOM";
		} else {
			$alarm[$key]['Room'] = $search;
		}
	}
	$alarmi = str_replace(' ','',$alarmid); 
	$alarmarr = explode(',', $alarmi);
	foreach ($alarmarr as $alarmid)  {
		$arrid = recursive_array_search($alarmid, $alarm);
		if ($arrid === false) {
			LOGGING("alarm.php: The entered Alarm-ID 'ID=".$alarmid."' seems to be not valid. Please run '...action=listalarms' in Browser and doublecheck your syntax!", 3);
			continue;
		}
		$sonos->UpdateAlarm($alarm[$arrid]['ID'], $alarm[$arrid]['StartTime'], $alarm[$arrid]['Duration'], $alarm[$arrid]['Recurrence'], 
		$alarm[$arrid]['Enabled'] = 0, $alarm[$arrid]['RoomUUID'], ($alarm[$arrid]['ProgramURI']), ($alarm[$arrid]['ProgramMetaData']), 
		$alarm[$arrid]['PlayMode'], $alarm[$arrid]['Volume'], $alarm[$arrid]['IncludeLinkedZones']);
		LOGGING("alarm.php: Sonos Alarm-ID='".$alarmid."' for Player '".$alarm[$arrid]['Room']."' has been turned off.", 6);
	}
	#print_r($alarm);
	file_put_contents($single_alarm_off, json_encode($alarm, JSON_PRETTY_PRINT));
	LOGGING("alarm.php: File has been saved.", 6);
}


/**
* Function: restore_alarm --> enable specific Sonos alarms
*
* @param: empty
* @return: enable alarm
**/
function restore_alarm() {
	
	global $sonoszone, $psubfolder, $lbpdatadir, $home, $master, $single_alarm_off;
	
	$alarmid = $_GET['id'];
	$single_alarm_off = $lbpdatadir."/s4lox_alarm_ID_".$alarmid."_off.json";				// path/file for specific Alarm turned off
	if (!file_exists($single_alarm_off))   {
		LOGERR("alarm.php: File for Alarm-ID '".$alarmid."' does not exist. We have to abort :-(", 6);
		exit(1);
	}
	$alarm = json_decode(file_get_contents($single_alarm_off), TRUE);
	$sonos = new SonosAccess($sonoszone[$master][0]);
	#$alarm = $sonos->ListAlarms();
	$alarmi = str_replace(' ','',$alarmid); 
	$alarmarr = explode(',', $alarmi);
	foreach ($alarmarr as $alarmid)  {
		$arrid = recursive_array_search($alarmid, $alarm);
		if ($arrid === false) {
			LOGGING("alarm.php: The entered Alarm-ID 'ID=".$alarmid."' seems to be not valid. Please run '...action=listalarms' in Browser and doublecheck your syntax!", 3);
			continue;
		}
		$sonos->UpdateAlarm($alarm[$arrid]['ID'], $alarm[$arrid]['StartTime'], $alarm[$arrid]['Duration'], $alarm[$arrid]['Recurrence'], 
		$alarm[$arrid]['Enabled'] = 1, $alarm[$arrid]['RoomUUID'], ($alarm[$arrid]['ProgramURI']), ($alarm[$arrid]['ProgramMetaData']), 
		$alarm[$arrid]['PlayMode'], $alarm[$arrid]['Volume'], $alarm[$arrid]['IncludeLinkedZones']);
		LOGGING("alarm.php: Sonos Alarm-ID='".$alarmid."' for Player '".$alarm[$arrid]['Room']."' has been enabled.", 6);
	}
	#print_r($alarm);
	@unlink($single_alarm_off);
}



?>