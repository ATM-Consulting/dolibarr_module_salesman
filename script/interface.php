<?php

	require '../config.php';
	
	$get = GETPOST('get');
	
	switch($get) {
		
		case 'company-address':
	
			dol_include_once('/societe/class/societe.class.php');
			
			$s=new Societe($db);
			if($s->fetch(GETPOST('fk_soc'))>0) {
				
				$address = $s->address.','.$s->zip.' '.$s->town.','.$s->country;
				$url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($address).'&key='.urlencode($conf->global->SALESMAN_GOOGLE_API_KEY);
//				var_dump($url);
				$json = file_get_contents($url);
				
				echo $json;
							
				
			}
			else{
				echo __out($s->error,'json');
			}
			
			
			break;
		
	}
