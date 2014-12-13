<?php
	/*
		Clan IDs:
		QSF		500017963
		QSF-C	500029108
		QSF-E	500031759
		QSF-L	500035013
		QSF-X	500022842
		
		EFE Clan ID (for testing with earlier battle schedules): 500006779
		
		Get clan members:
		https://api.worldoftanks.eu/wot/clan/info/?application_id=b4319422268043312f2b98daad5e7040&clan_id=[CLAN]
		
		Get fame points:
		https://api.worldoftanks.eu/wot/globalwar/famepoints/?application_id=b4319422268043312f2b98daad5e7040&map_id=[MAPID]&account_id=[XXX]
		
		Get battles
		http://api.worldoftanks.eu/wot/globalwar/battles/?application_id=b4319422268043312f2b98daad5e7040&map_id=[MAPID]&clan_id=[CLAN]
	*/
	
	# ID of the map (globalmanp, eventmap, etc.)
	$mapID = 'eventmap';
	
	# Application ID registered to Wargaming (by karipatila)
	$applicationID = 'b4319422268043312f2b98daad5e7040';
	$APIErrorMessage = '<p><strong>Error: API did not respond. (request)</strong></p><br />';
	
	# The clan array and the checks present in index.php are duplicated in this file, since it needs to be called individually. Check index.php for comments.
	$clans = array('QSF'=>500017963,'QSF-C'=>500029108,'QSF-E'=>500031759,'QSF-L'=>500035013,'QSF-X'=>500022842);
	if($_GET['clan'] != 'ALL'){
		$input = $_GET['clan'];
	} else {
		$clan = null;
	}
	$input = strtoupper($input);
	$pass = false;
	$clan = null;

	if(is_string($input) && strlen($input) <= 5 && array_key_exists($input, $clans)) {
		$pass = true;
		$clan = $input;
	}
	if($_GET['clan'] == 'ALL'){
		$clan = null;
		$pass = true;
	}
	date_default_timezone_set("CET");

	# Caching is required, since making an API call per clan member is taking a relatively long time.
	# If memcached is available, change "files" to "memcached".
	# include("phpfastcache/phpfastcache.php");
	# $cache = new phpFastCache("files");
	#$cache = new phpFastCache("memcached");
	
	# Number of seconds before making a new set of API calls.
	$cacheDuration = 120;
?>

<?php
	# Check if a cached version already exists.
	# $output = $cache->get('fame_points_'.$clan);
	# Set to null because output caching isn't required at the moment. ,'QSF-C','QSF-E','QSF-L','QSF-X'
	$output = null;
	$clanList = empty($clan) ? array('QSF','QSF-C','QSF-E','QSF-L','QSF-X') : array($clan);
	if($output == null) {
		if($pass === true) {
			$get_11k_fame_api_request = 'http://api.worldoftanks.eu/wot/globalwar/accountpointsrating/?application_id='.$applicationID.'&map_id=eventmap&page_no=110';
			$json = file_get_contents($get_11k_fame_api_request);
			$obj = json_decode($json);
			$array = $obj->data;
			$array = array_pop($array);
			$fame11k = $array->points;
			$fameDebug = $array->position;
		
			# Sets up sortable table for the player data.
			$playerData = array();
			$count = 1;
			foreach($clanList as $clan){
				$accountIDArray = array();
				$get_clan_fame_api_request = 'http://api.worldoftanks.eu/wot/globalwar/clanpoints/?application_id='.$applicationID.'&map_id='.$mapID.'&clan_id='.$clans[$clan];
				$json = file_get_contents($get_clan_fame_api_request);
				$obj = json_decode($json);
				$clanFame = array();
				if(!empty($obj->data->$clans[$clan]) && count($clanList) == 1){
					$clanFame['updated_at'] = $obj->data->$clans[$clan]->updated_at;
					$clanFame['bonus_points'] = number_format($obj->data->$clans[$clan]->bonus_points);
					$clanFame['players_points'] = number_format($obj->data->$clans[$clan]->players_points);
					$clanFame['position'] = $obj->data->$clans[$clan]->position;
					$clanFame['points'] = number_format($obj->data->$clans[$clan]->points);
				}
			
				$player = array();
				$playerPos = array();
			
				$get_members_api_request = 'https://api.worldoftanks.eu/wot/clan/info/?application_id='.$applicationID.'&clan_id='.$clans[$clan];
				$json = file_get_contents($get_members_api_request);
				# Data object from API.
				$obj = json_decode($json);
				if(isset($obj->data)){
					# Contains all member data.
					$members = $obj->data->$clans[$clan]->members;
					# Counts number of players in clan.
					$memberCounter = 0;
					# Counts number of players actually processed by the script later on.
					$debugCounter = 0;
					
					# Puts all clan member IDs into an array for imploding to a comma-separated list later.
					foreach($members as $member){
						$accountIDArray[] = $member->account_id;
					}
					$accountIDList = implode(',',$accountIDArray);
					
					# Request for fame points. Last variable is a list for all of the clan member IDs so we only need to make one request.
					$get_fame_api_request = 'https://api.worldoftanks.eu/wot/globalwar/famepoints/?application_id='.$applicationID.'&map_id='.$mapID.'&account_id='.$accountIDList;
					$json = file_get_contents($get_fame_api_request);
					$obj = json_decode($json);
					
					foreach($members as $member){
						if(isset($obj->data)){
							# $obj->data->[playerID] should be an object with the following fields when campaign is live.
							# account_id	numeric
							# fame_points	numeric
							# position		numeric
							# updated_at	timestamp
							$id = $member->account_id;
							$rank = $member->role;
							$accountName = $member->account_name;
							if(count($clanList) > 1){
								$accountName .= ' ['.$clan.']';
							}
							$fame = null;
							$position = null;
							$updated = null;
							if(!empty($obj->data->$id)){
								$position = $obj->data->$id->position;
								$fame = $obj->data->$id->fame_points;
								$updated = date('r',$obj->data->$id->updated_at);
							}
							$playerData[] = array('accountName' => $accountName, 'fame' => $fame, 'position' => $position, 'updated_at' => $updated, 'rank' => $rank);
							++$debugCounter;
						}
						++$memberCounter;
					}
				}
			} #new foreach loop
			if(!empty($playerData)){
				# Arrays for sorting the table initially.
				foreach ($playerData as $key => $value) {
					$player[$key]  = $value['accountName'];
					$playerPos[$key] = $value['fame'];
				}
				
				# Sort players by fame points initially.
				array_multisort($playerPos, SORT_DESC, $player, SORT_ASC, $playerData);
				$output .= '
				<div role="main" class="main-wrapper">
					<section class="main-content">
					';
					
				if(!empty($clanFame)){
					$output .= '<header class="clan-header">
									<div class="clanFame first"><em>Rank</em><br />'.$clanFame['position'].'</div>
									<div class="clanFame"><em>Fame</em><br />'.$clanFame['points'].'</div>
									<div class="clanFame"><em>Player\'s Points</em><br />'.$clanFame['players_points'].'</div>
									<div class="clanFame"><em>Bonus Points</em><br />'.$clanFame['bonus_points'].'</div>
									<div class="clanFame" title="Average daily Fame needed to maintain the 11,000th spot"><em>11,000th spot avg/d</em><br />4,500</div>
									<div class="clanFame"><em>Updated at</em><br /><span class="fameUpdated" title="'.date('r',$clanFame['updated_at']).'">'.date('r',$clanFame['updated_at']).'</span></div>
								</header>';
				}
				# This sets up the HTML table for player data and adds $debugCounter as an HTML comment.
				$output .= '
					<table id="simpleTable">
						<thead>
							<tr>
								<th>&nbsp;</th>
								<th>Player</th>
								<th title="Position on the Alley of Fame">Position</th>
								<th>Fame</th>
								<th>Fame Needed</th>
								<th>Updated</th>
							</tr>
						</thead>
						<tbody>';
				
				foreach($playerData as $player){
					$color = null;
					$pastTime = null;
					$accountName = $player['accountName'];
					$position = $player['position'];
					$famePoints = $player['fame'];
					$lastUpdate = $player['updated_at'];
					if(strtotime('-2 hours', strtotime('now')) > strtotime($lastUpdate)){
						$pastTime = ' past';
					}
					$delta = $fame11k - $famePoints;
					$rank = $player['rank'];
					if(!empty($position) && $position <= 20000){
						$color = 'orange';
					}
					if(!empty($position) && $position <= 11000){
						$color = 'green';
					}
					if(is_string($color)){
						$color = ' class="'.$color.'"';
					}
					$output .= '<tr'.$color.'><td>'.$count.'</td><td class="accountName '.$rank.'">'.$accountName.'</td><td class="pos">'.$position.'</td><td class="fame">'.$famePoints.'</td><td class="fame11k">'.$delta.'</td><td class="fameUpdated'.$pastTime.'" title="'.$lastUpdate.'">'.$lastUpdate.'</td></tr>';
					++$count;
				}
				
				$output .= '
						</tbody>
					</table>
					<!-- Players processed: '.$debugCounter.' -->
					<!-- Fame 11k position check => '.$fameDebug.' -->
					</section>
					<footer>
						<p>Created by: karipatila [QSF-E]</p>
					</footer>
				</div>
				';
				# Return the output.
				echo $output;
				# Save the output into cache.
				# $cache->set('fame_points_'.$clan, $output, $cacheDuration);
			} else {
				echo $APIErrorMessage;
			}
		} else {
			echo "<p>Enter a valid QSF clan name.</p>";
		}
	} else {
		# Cached version available, all we have to do is print it.
		echo $output;
		echo '<!-- Read from Cache. -->';
	}
?>