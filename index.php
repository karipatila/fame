<?php
	/*
		Created by:					karipatila [QSF-E]
		Quickybaby.com/forum:		karipatila
		Throwaway project email:	sulo.patila@mail.com
		
		index.php 			=> Loads battle schedule and requests request.php for player data.
		request.php 		=> Loads player data and fame points. Needs to be called asynchronously or by cron job due to high API request count.
		css/style.css		=> Styling for the HTML output.
		js/stupidtable.min 	=> Simple HTML table sorting.
		phpfastcache/		=> Simple filesystem/memcached caching package.
		.htaccess			=> Clean URLs for clan requests. http://www.host.com/QSF-E/ points to http://www.host.com?clan=QSF-E
		
		Date format is date('r') and later converted to users locale with JavaScript based on browser settings
		
		Clan IDs:
		QSF		500017963
		QSF-C	500029108
		QSF-E	500031759
		QSF-L	500035013
		QSF-X	500022842
		
		EFE Clan ID (for testing with earlier battle schedules): 500006779
	*/
	
	$baseURL = 'http://apcr.hol.es/fame/';
	date_default_timezone_set("GMT");
	# define the allowed inputs and Wargaming Clan IDs. restricted to QSF clans for now.
	$clans = array('QSF'=>500017963,'QSF-C'=>500029108,'QSF-E'=>500031759,'QSF-L'=>500035013,'QSF-X'=>500022842);
	# defaults to QSF if no input is given
	$input = isset($_GET['clan']) ? $_GET['clan']:'';
	# we want the clan names always in uppercase
	$input = strtoupper($input);
	# timestamp for script execution, used later for "last updated"
	$refreshed = date("r", time());
	# set $pass initially to false, so request.php won't run with bad user input
	$pass = false;
	# clan variable, initially null
	$clan = null;
	# check for user input being a string of 5 characters or less and existing in the clans array defined on line 23
	if(is_string($input) && strlen($input) <= 5 && array_key_exists($input, $clans)) {
		$pass = true;
		# user input passed, so we can assign it as the requested clan name
		$clan = $input;
	}
	if(empty($_GET['clan'])){
		$pass = true;
		$clan = 'ALL';
	}
	# ID of the map (globalmap, eventmap, etc.)
	$mapID = 'eventmap';
	
	# Application ID registered to Wargaming (by karipatila)
	$applicationID = 'b4319422268043312f2b98daad5e7040';
	$APIErrorMessage = '<p><strong>Error: API did not respond.</strong></p><br />';
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<base href="<?php echo $baseURL; ?>" />
		<title><?php echo $clan != 'ALL' ? $clan : 'Community'; ?> Fame Points</title>
		<meta name="description" content="Fame Point tracker for QSF Clans">
		<meta name="author" content="karipatila [QSF-E]">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		<link rel="stylesheet" href="<?php echo $baseURL; ?>css/style.css">
		<link rel="shortcut icon" href="<?php echo $baseURL; ?>favicon.ico" type="image/x-icon">
		<link rel="icon" href="<?php echo $baseURL; ?>favicon.ico" type="image/x-icon">
		<link href='http://fonts.googleapis.com/css?family=Source+Sans+Pro:400,700' rel='stylesheet' type='text/css'>
	</head>
	<body>
	<?php
	if($clan != 'ALL'){
		if($pass === true){
	?>
		<div class="battleSchedule">
			<h2>Scheduled Battles</h2>
	<?php
		# API request for CW battles
		$get_battles_api_request = 'http://api.worldoftanks.eu/wot/globalwar/battles/?application_id='.$applicationID.'&map_id='.$mapID.'&clan_id='.$clans[$clan];
		$json = file_get_contents($get_battles_api_request);
		$obj = json_decode($json);
		if(isset($obj->data)){
			$battles = $obj->data->$clans[$clan];
			
			# Returned battle type needs to be a bit more readable
			foreach($battles as $battle){
				switch($battle->type){
					case 'for_province': $type = 'Battle for Province';break;
					case 'meeting_engagement': $type = 'Encounter battle';break;
					case 'landing': $type = 'Battle for landing';break;
					default: $type = 'Battle for Province';
				}
				echo '<div class="battle';
				if(!empty($battle->time)){
					echo ' battletime';
					echo strtotime('now') > strtotime(date('r', $battle->time)) ? ' over':'';
					echo '">';
					echo '<span class="largeTime">';
					echo date('r',$battle->time).'</span><br />';
				}
				echo empty($battle->time) ? ' upcoming">':'';
				if(empty($battle->time)){
					$primetimeAPIRequest = 'http://api.worldoftanks.eu/wot/globalwar/provinces/?application_id='.$applicationID.'&map_id='.$mapID.'&province_id='.$battle->provinces_i18n[0]->province_id;
					$getJson = file_get_contents($primetimeAPIRequest);
					$obj2 = json_decode($getJson);
					$prime = null;
					if(isset($obj2->data)){
						$provinceID = $battle->provinces_i18n[0]->province_id;
						$prime = $obj2->data->$provinceID->prime_time;
						$prime = $prime.':00';
						$prime = date('r',strtotime($prime));
					}
					echo !empty($prime) ? '<span class="largeTime">'.$prime.'</span> Prime Time<br />':'';
				}
				echo $type . ': <strong>';
				echo '<a href="http://worldoftanks.eu/clanwars/maps/'.$mapID.'/?province='.$battle->provinces_i18n[0]->province_id.'">'.$battle->provinces_i18n[0]->name_i18n . '</a></strong> on <strong class="nobreak">'.$battle->arenas[0]->name_i18n.'</strong></div>';
			}
			
			if(empty($battles)){
				echo '<div class="battle"><p>No scheduled battles as of <span class="nobattles battletime">'.$refreshed.'</span></p></div>';
			}
		} else {
			echo $APIErrorMessage.' index';
		}
	?>
			<p><a href="http://eu.wargaming.net/clans/<?php echo $clans[$clan]; ?>/battles/">Battle Schedule</a> on worldoftanks.eu</p>
		</div>
	<?php }} ?>
		<div id="data">
		<ul>
			<li><a<?php echo $clan == 'ALL' ? ' class="selected"':''; ?> id="qsf-all" href="<?php echo $baseURL; ?>">QSF Community</a></li>
			<li><a<?php echo $clan == 'QSF' ? ' class="selected"':''; ?> id="qsf" href="<?php echo $baseURL; ?>QSF/">QSF</a></li>
			<li><a<?php echo $clan == 'QSF-C' ? ' class="selected"':''; ?> id="qsf-c" href="<?php echo $baseURL; ?>QSF-C/">QSF-C</a></li>
			<li><a<?php echo $clan == 'QSF-E' ? ' class="selected"':''; ?> id="qsf-e" href="<?php echo $baseURL; ?>QSF-E/">QSF-E</a></li>
			<li><a<?php echo $clan == 'QSF-L' ? ' class="selected"':''; ?> id="qsf-l" href="<?php echo $baseURL; ?>QSF-L/">QSF-L</a></li>
			<li><a class="last<?php echo $clan == 'QSF-X' ? ' selected':''; ?>" id="qsf-x" href="<?php echo $baseURL; ?>QSF-X/">QSF-X</a></li>
		</ul>
		<?php
			if($clan != 'ALL') {
				# API request for list of clan members
				$get_members_api_request = 'https://api.worldoftanks.eu/wot/clan/info/?application_id='.$applicationID.'&clan_id='.$clans[$clan];
				$json = file_get_contents($get_members_api_request);
				$obj = json_decode($json);
				if(isset($obj->data)){
					# URL of the clan icon
					$clanIcon = $obj->data->$clans[$clan]->emblems->large;
					$output = '<header class="logo-holder">';
					$output .= '<h1>'.$clan.'</h1>';
					$output .= '<p>'.$obj->data->$clans[$clan]->name.'</p>';
					$output .= '</header>';
					$output .= '<div id="tabularData"><p>Waiting for the API to respond. Please be patient.</p></div>';
					echo $output;
				} else {
					echo $APIErrorMessage;
				}
			# Up until this point no output gets cached. The user should have some useful information readily available.
			# Line below is the placeholder for the user who lands on a page which has no cached Fame Point results and has to make the actual API requests.
			} else {
				$output = '<header class="logo-holder">';
				$output .= '<h1>Community Hall of Fame</h1>';
				$output .= '</header>';
				$output .= '<div id="tabularData"><p>Waiting for the API to respond. Please be patient.</p></div>';
				echo $output;
			}
			?>
		</div>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
	<script>window.jQuery || document.write('<script src="js/jquery.min.js"><\/script>');</script>
	<?php
		if($pass === true){
	?>
	<script>
	// Changing timestamps to local time and requesting the request.php file with a clan name.
	// Data from requests.php will replace the placeholder message in <div id="tabularData">.
	$( document ).ready(function() {
		$('div.battle span:not(:empty)').each(function() {
			var battleDate = new Date($(this).html());
			battleDate.toString();
			$(this).html(battleDate.getHours()+':'+(battleDate.getMinutes()<10?'0':'') + battleDate.getMinutes());
		});
		$.get( "request.php", {clan: '<?php echo $clan; ?>'})
		.done(function( data ) {
			$('#tabularData').html(data);
			$( "tbody tr:odd" ).addClass( "odd" );
			$('.fameUpdated:not(:empty)').each(function() {
				var fameDate = new Date($(this).html());
				fameDate.toString();
				$(this).html(fameDate.getHours()+':'+(fameDate.getMinutes()<10?'0':'') + fameDate.getMinutes());
			});
		});
	});
	<?php } ?>
	</script>
	</body>
</html>