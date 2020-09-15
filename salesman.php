<?php

	require 'config.php';

	llxHeader();
//	dol_fiche_head();

	if(empty($conf->global->SALESMAN_GOOGLE_API_KEY)) {

		echo '<div class="error">'.$langs->trans('GoogleAPIKeyNotDefined').'</div>';

	}
	else {


		_script();
		_card();

	}

//	dol_fiche_end();

	llxFooter();


function _script() {

	global $conf,$user,$langs;

	?><script src="https://maps.googleapis.com/maps/api/js?v=3.exp&key=<?php echo $conf->global->SALESMAN_GOOGLE_API_KEY ?>"></script>
<!--	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js" type="text/javascript"></script>-->
	<script type="text/javascript">
	var map;
	var directionsDisplay = null;
	var directionsService;
	var polylinePath;

	// @see MAX_DIMENSIONS_EXCEEDED => https://developers.google.com/maps/documentation/javascript/distancematrix
	// donc en théorie, la valeur max c'est 25
	var maxNode = <?php echo (is_numeric($conf->global->SALESMAN_MAXNODE)) ? $conf->global->SALESMAN_MAXNODE : 8; ?>;

	var nodes = [];
	var prevNodes = [];
	var markers = [];
	var durations = [];

	// Initialize google maps
	function initializeMap() {
	    // Map options
	    var opts = {
	        center: new google.maps.LatLng(44.93, 4.9),
	        zoom: 10,
	        streetViewControl: false,
	        mapTypeControl: true,
	    };
	    map = new google.maps.Map(document.getElementById('map-canvas'), opts);

	    // Create map click event
	    google.maps.event.addListener(map, 'click', function(event) {

	        if (nodes.length > maxNode) {
	            alert('<?php echo $langs->transnoentities('MaxPath') ?>');
	            return;
	        }

	        // If there are directions being shown, clear them
	        clearDirections();

	        // Add a node to map
	        marker = new google.maps.Marker({position: event.latLng, map: map});
	        markers.push(marker);

	        // Store node's lat and lng
	        nodes.push(event.latLng);

	        // Update destination count
	        $('#destinations-count').html(nodes.length);
	    });

	    // Add "my location" button
	    var myLocationDiv = document.createElement('div');
	    new getMyLocation(myLocationDiv, map);

	    map.controls[google.maps.ControlPosition.TOP_RIGHT].push(myLocationDiv);

	    function getMyLocation(myLocationDiv, map) {
	        var myLocationBtn = document.createElement('button');
	        myLocationBtn.innerHTML = "<?php echo $langs->transnoentities('MyLocation'); ?>";
	        myLocationBtn.className = 'butAction';
	        document.getElementById('point-depart').appendChild(myLocationBtn);

	        google.maps.event.addDomListener(myLocationBtn, 'click', function() {

	            navigator.geolocation.getCurrentPosition(function(success) {

	            	$("#starting-point").val(success.coords.latitude+', '+success.coords.longitude);

	            	var latLong = new google.maps.LatLng(success.coords.latitude, success.coords.longitude);
	                map.setCenter(latLong);
	                map.setZoom(12);

	                clearDirections();

			        // Add a node to map
			        marker = new google.maps.Marker({position: latLong, map: map});
			        markers.push(marker);

			        // Store node's lat and lng
			        nodes.push(latLong);


	            });
	        });
	    }

	}

	// Get all durations depending on travel type
	function getDurations(callback) {
	    var service = new google.maps.DistanceMatrixService();
	    service.getDistanceMatrix({
	        origins: nodes,
	        destinations: nodes,
	        travelMode: google.maps.TravelMode[$('#travel-type').val()],
	        avoidHighways: parseInt($('#avoid-highways').val()) > 0 ? true : false,
	        avoidTolls: false,
	    }, function(distanceData, status) {

	    	// @see https://developers.google.com/maps/documentation/javascript/distancematrix
	    	switch (status) {
				case "INVALID_REQUEST":
					alert("API Google return code INVALID_REQUEST: The provided request was invalid. This is often due to missing required fields.");
					break
				case "MAX_ELEMENTS_EXCEEDED":
					alert("API Google return code MAX_ELEMENTS_EXCEEDED: The product of origins and destinations exceeds the per-query limit.");
					break;
				case "MAX_DIMENSIONS_EXCEEDED":
					alert("API Google return code MAX_DIMENSIONS_EXCEEDED: Your request contained more than 25 origins, or more than 25 destinations.");
					break;
				case "OVER_QUERY_LIMIT":
					alert("API Google return code OVER_QUERY_LIMIT: Your application has requested too many elements within the allowed time period. The request should succeed if you try again after a reasonable amount of time.");
					break;
				case "REQUEST_DENIED":
					alert("API Google return code REQUEST_DENIED: The service denied use of the Distance Matrix service by your web page.");
					break;
				case "UNKNOWN_ERROR":
					alert("API Google return code UNKNOWN_ERROR: A Distance Matrix request could not be processed due to a server error. The request may succeed if you try again.");
					break;
				case "NOT_FOUND":
					alert("API Google return code NOT_FOUND: The origin and/or destination of this pairing could not be geocoded.");
					break;
				case "ZERO_RESULTS":
					alert("API Google return code ZERO_RESULTS: No route could be found between the origin and destination.");
					break;
				case "OK":
					// Create duration data array
					var nodeDistanceData;
					for (originNodeIndex in distanceData.rows) {
						nodeDistanceData = distanceData.rows[originNodeIndex].elements;
						durations[originNodeIndex] = [];
						for (destinationNodeIndex in nodeDistanceData) {
							if (durations[originNodeIndex][destinationNodeIndex] = nodeDistanceData[destinationNodeIndex].duration == undefined) {
								alert('Error: couldn\'t get a trip duration from API');
								return;
							}
							durations[originNodeIndex][destinationNodeIndex] = nodeDistanceData[destinationNodeIndex].duration.value;
						}
					}

					if (callback != undefined) {
						callback();
					}
					break;
			}


	    });
	}

	// Removes markers and temporary paths
	function clearMapMarkers() {
	    for (index in markers) {
	        markers[index].setMap(null);
	    }

	    prevNodes = nodes;
	    nodes = [];

	    if (polylinePath != undefined) {
	        polylinePath.setMap(null);
	    }

	    markers = [];

	    $('#ga-buttons').show();
	}

	function getMarkers()
	{
		return markers;
	}
	// Removes map directions
	function clearDirections() {
	    // If there are directions being shown, clear them
	    if (directionsDisplay != null) {
	        directionsDisplay.setMap(null);
	        directionsDisplay = null;
	    }
	}
	// Completely clears map
	function clearMap() {
	    clearMapMarkers();
	    clearDirections();

	    $('#destinations-count').html('0');
		$('.selectSociete').prop('checked', false).attr('disabled', false);
	}

	// Initial Google Maps
	google.maps.event.addDomListener(window, 'load', initializeMap);

	function setStartingPoint() {

		clearMap();
			clearDirections();

			var address = $("#starting-point").val();

			$.ajax({
				url:"script/interface.php"
				,data:{
					"get":"geolocalize"
					,"address":address
				}
				,dataType:"json"
			}).done(function(data) {
				  myPosition = data.results[0].geometry.location;

				  if(myPosition) {
					  marker = new google.maps.Marker({position: myPosition, map: map});
		       		  markers.push(marker);
					  nodes.push(myPosition);

		              $('#destinations-count').html(nodes.length);
						map.setCenter(myPosition);

				  }
				  else{
				  	alert('Erreur');

				  }
			});

	}

	// Create listeners
	$(document).ready(function() {
	    $('#clear-map').click(clearMap);

		var table = $("#listevent").DataTable({
			"order": [[ 3, "asc" ]]
			,initComplete: function () {
				this.api().columns().every( function () {
					var column = this;
					var rejectedCols = [0,2,3,4,9];
					var options = [];
					if (! rejectedCols.includes(column.index()))
					{
						// console.log($(column.header()).parent().prev().find('th'))
						var select = $('<select id="select'+column.index()+'"><option></option></select>')
							.appendTo( $(column.header()).parent().prev().find('th')[column.index()] )
							.on( 'change', function () {
								var val = $(this).val();

								column
									.search( val ? val : '', true, false )
									.draw();
							} );

						column.data().unique().sort().each( function ( d, j ) {
							$element = $('<p>'+d+'</p>');

							if ($element.find('a').length > 0)
							{
								$element.find('a').each(function(){

									if (! options.includes($(this).text()))
									{
										var text = $(this).text();

										options.push(text);
										select.append( '<option value="'+text+'">'+text+'</option>' )
									}
								})
							}
							else select.append( '<option value="'+d+'">'+d+'</option>' )
						} );
					}

				} );

			}
		});

		// les select sont des select2
		$('#listevent').find('tr:first-child').find('select').each(function() {
			$(this).select2();
		})

		// on prefiltre par le user courant
		$('#select8').val('<?php echo $user->getFullName($langs); ?>');
		$('#select8').change();

		// on vire la recherche générale
		$('#listevent_filter').hide();

		// multiselect de type d'événement
		$('#select1, #select6, #select7').attr('multiple', true);
		$('#select1').attr('name', 'select1[]');
		$('#select6').attr('name', 'select6[]');
		$('#select7').attr('name', 'select7[]');
		$('#select1, #select6, #select7').select2({multiple:true});

		// déselection de l'optionvide
		$('#select1, #select6, #select7').find('option:selected').each(function(){
			$(this).prop("selected", false);
		})
		$('#select1, #select6, #select7').change();

		$('#select1').on('change', function(){
			var search = [];

			var regEx = $(this).find(':selected').map(function() {
				return $( this ).text();
			})
				.get()
				.join( "|" );
			console.log(search)
			table.column(1).search(regEx, true, false).draw();
		});
		$('#select6').on('change', function(){
			var search = [];

			var regEx = $(this).find(':selected').map(function() {
				return $( this ).text();
			})
				.get()
				.join( "|" );
			console.log(search)
			table.column(6).search(regEx, true, false).draw();
		});
		$('#select7').on('change', function(){
			var search = [];

			var regEx = $(this).find(':selected').map(function() {
				return $( this ).text();
			})
				.get()
				.join( "|" );
			console.log(search)
			table.column(7).search(regEx, true, false).draw();
		});

		$(".selectSociete").click(function (){
			var fk_soc = $(this).data("socid");
			addCompany(fk_soc);
		});

		setStartingPoint();
		$("#start-from_this-point").click(function() {

			setStartingPoint();

		});

		function addCompany(fk_soc) {

			clearDirections();

			$.ajax({
				url:"script/interface.php"
				,data:{
					"get":"company-address"
					,"fk_soc":fk_soc
				}
				,dataType:"json"
			}).done(function(data) {
				console.log(data);
				myPosition = data.results[0].geometry.location;

				if(myPosition) {
					marker = new google.maps.Marker({position: myPosition, map: map, socid: data.socid});
					markers.push(marker);

					nodes.push(myPosition);

					map.setCenter(myPosition);

					$('#destinations-count').html(nodes.length);
					$('input[data-socid="'+data.socid+'"]').attr('disabled', true).prop('checked', true);

				}
				else{
					alert('Erreur');

				}


			});

		}

		$("#add-company").click(function() {
			var fk_soc = $('#fk_soc').val();
			addCompany(fk_soc);
		});

	    // Start GA
	    $('#find-route').click(function() {
	        if (nodes.length < 2) {
	            if (prevNodes.length >= 2) {
	                nodes = prevNodes;
	            } else {
	                alert('Click on the map to select destination points');
	                return;
	            }
	        }

	        if (directionsDisplay != null) {
	            directionsDisplay.setMap(null);
	            directionsDisplay = null;
	        }

	        $('#ga-buttons').hide();

	        // Get route durations
	        getDurations(function(){
	            $('.ga-info').show();

	            // Get config and create initial GA population
	            ga.getConfig();
	            var pop = new ga.population();
	            pop.initialize(nodes.length);
	            var route = pop.getFittest().chromosome;

	            ga.evolvePopulation(pop, function(update) {
	                $('#generations-passed').html(update.generation);
	                $('#best-time').html((update.population.getFittest().getDistance() / 60).toFixed(2) + ' Mins');

	                // Get route coordinates
	                var route = update.population.getFittest().chromosome;
	                var routeCoordinates = [];
	                for (index in route) {
	                    routeCoordinates[index] = nodes[route[index]];
	                }
	                routeCoordinates[route.length] = nodes[route[0]];

	                // Display temp. route
	                if (polylinePath != undefined) {
	                    polylinePath.setMap(null);
	                }
	                polylinePath = new google.maps.Polyline({
	                    path: routeCoordinates,
	                    strokeColor: "#0066ff",
	                    strokeOpacity: 0.75,
	                    strokeWeight: 2,
	                });
	                polylinePath.setMap(map);
	            }, function(result) {
	                // Get route
	                route = result.population.getFittest().chromosome;

	                // Add route to map
	                directionsService = new google.maps.DirectionsService();
	                directionsDisplay = new google.maps.DirectionsRenderer();
	                directionsDisplay.setMap(map);
	                var waypts = [];

	                for (var i = 1; i < route.length; i++) {
	                    waypts.push({
	                        location: nodes[route[i]],
	                        stopover: true
	                    });
	                }

	                // Add final route to map
	                var request = {
	                    origin: nodes[route[0]],
	                    destination: nodes[route[0]],
	                    waypoints: waypts,
	                    travelMode: google.maps.TravelMode[$('#travel-type').val()],
	                    avoidHighways: parseInt($('#avoid-highways').val()) > 0 ? true : false,
	                    avoidTolls: false
	                };
	                directionsService.route(request, function(response, status) {

	                    if (status == google.maps.DirectionsStatus.OK) {

	                    	if(response.routes[0].legs) {
	                    		$('#itineraire').empty();

	                    		for(it in response.routes[0].legs) {

	                    			var oujevais = response.routes[0].legs[it];
	                    			//console.log(oujevais);
	                    			$('#itineraire').append('<strong>'+(parseInt(it)+1)+' - '+oujevais.distance.text+" </strong><br /> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
	                    			+oujevais.start_address+' <strong> <?php echo $langs->transnoentities('To') ?> </strong> '
	                    			+oujevais.end_address+'</br><hr />');

	                    		}



	                    	}

	                        directionsDisplay.setDirections(response);
	                    }
	                    clearMapMarkers();
	                });
	            });
	        });
	    });
	});

	// GA code
	var ga = {
	    // Default config
	    "crossoverRate": 0.5,
	    "mutationRate": 0.1,
	    "populationSize": 50,
	    "tournamentSize": 5,
	    "elitism": true,
	    "maxGenerations": 50,

	    "tickerSpeed": 60,

	    // Loads config from HTML inputs
	    "getConfig": function() {
	        ga.crossoverRate = parseFloat($('#crossover-rate').val());
	        ga.mutationRate = parseFloat($('#mutation-rate').val());
	        ga.populationSize = parseInt($('#population-size').val()) || 50;
	        ga.elitism = parseInt($('#elitism').val()) || false;
	        ga.maxGenerations = parseInt($('#maxGenerations').val()) || 50;
	    },

	    // Evolves given population
	    "evolvePopulation": function(population, generationCallBack, completeCallBack) {
	        // Start evolution
	        var generation = 1;
	        var evolveInterval = setInterval(function() {
	            if (generationCallBack != undefined) {
	                generationCallBack({
	                    population: population,
	                    generation: generation,
	                });
	            }

	            // Evolve population
	            population = population.crossover();
	            population.mutate();
	            generation++;

	            // If max generations passed
	            if (generation > ga.maxGenerations) {
	                // Stop looping
	                clearInterval(evolveInterval);

	                if (completeCallBack != undefined) {
	                    completeCallBack({
	                        population: population,
	                        generation: generation,
	                    });
	                }
	            }
	        }, ga.tickerSpeed);
	    },

	    // Population class
	    "population": function() {
	        // Holds individuals of population
	        this.individuals = [];

	        // Initial population of random individuals with given chromosome length
	        this.initialize = function(chromosomeLength) {
	            this.individuals = [];

	            for (var i = 0; i < ga.populationSize; i++) {
	                var newIndividual = new ga.individual(chromosomeLength);
	                newIndividual.initialize();
	               // console.log(newIndividual);
	                this.individuals.push(newIndividual);
	            }
	        };

	        // Mutates current population
	        this.mutate = function() {
	            var fittestIndex = this.getFittestIndex();

	            for (index in this.individuals) {
	                // Don't mutate if this is the elite individual and elitism is enabled
	                if (ga.elitism != true || index != fittestIndex) {
	                    this.individuals[index].mutate();
	                }
	            }
	        };

	        // Applies crossover to current population and returns population of offspring
	        this.crossover = function() {
	            // Create offspring population
	            var newPopulation = new ga.population();

	            // Find fittest individual
	            var fittestIndex = this.getFittestIndex();
	            for (index in this.individuals) {
	                // Add unchanged into next generation if this is the elite individual and elitism is enabled

	                if (ga.elitism == true && index == fittestIndex) {
	                    // Replicate individual
	                    var eliteIndividual = new ga.individual(this.individuals[index].chromosomeLength);
	                    eliteIndividual.setChromosome(this.individuals[index].chromosome.slice());
	                    //console.log('fitest', this.individuals[index].chromosome.slice());
	                    newPopulation.addIndividual(eliteIndividual);
	                } else {
	                    // Select mate
	                    var parent = this.tournamentSelection();
	                    // Apply crossover
	                  //  console.log('nofitest after', this.individuals[index].chromosome.slice());
	                    this.individuals[index].crossover(parent, newPopulation);
	                  //  console.log('nofitest after', this.individuals[index].chromosome.slice());
	                }
	            }

	            return newPopulation;
	        };

	        // Adds an individual to current population
	        this.addIndividual = function(individual) {
	            this.individuals.push(individual);
	        };

	        // Selects an individual with tournament selection
	        this.tournamentSelection = function() {
	            // Randomly order population
	            for (var i = 0; i < this.individuals.length; i++) {
	                var randomIndex = Math.floor(Math.random() * this.individuals.length);
	                var tempIndividual = this.individuals[randomIndex];
	                this.individuals[randomIndex] = this.individuals[i];
	                this.individuals[i] = tempIndividual;
	            }

	            // Create tournament population and add individuals
	            var tournamentPopulation = new ga.population();
	            for (var i = 0; i < ga.tournamentSize; i++) {
	                tournamentPopulation.addIndividual(this.individuals[i]);
	            }

	            return tournamentPopulation.getFittest();
	        };

	        // Return the fittest individual's population index
	        this.getFittestIndex = function() {
	            var fittestIndex = 0;

	            // Loop over population looking for fittest
	            for (var i = 1; i < this.individuals.length; i++) {
	                if (this.individuals[i].calcFitness() > this.individuals[fittestIndex].calcFitness()) {
	                    fittestIndex = i;
	                }
	            }

	            return fittestIndex;
	        };

	        // Return fittest individual
	        this.getFittest = function() {
	            return this.individuals[this.getFittestIndex()];
	        };
	    },

	    // Individual class
	    "individual": function(chromosomeLength) {
	        this.chromosomeLength = chromosomeLength;
	        this.fitness = null;
	        this.chromosome = [];

	        // Initialize random individual
	        this.initialize = function() {
	            this.chromosome = [];

	            // Generate random chromosome
	            for (var i = 0; i < this.chromosomeLength; i++) {
	                this.chromosome.push(i);
	            }
	            for (var i = 1; i < this.chromosomeLength; i++) {
	                var randomIndex = Math.floor(Math.random() * (this.chromosomeLength )) ;
	                if(randomIndex>0) {
		                var tempNode = this.chromosome[randomIndex];
		                this.chromosome[randomIndex] = this.chromosome[i];
		                this.chromosome[i] = tempNode;
	                }
	            }
	        };

	        // Set individual's chromosome
	        this.setChromosome = function(chromosome) {
	            this.chromosome = chromosome;
	        };

	        // Mutate individual
	        this.mutate = function() {
	            this.fitness = null;

	            // Loop over chromosome making random changes
	            for (index in this.chromosome) {
	                if (ga.mutationRate > Math.random() && index>0) {
	                    var randomIndex = Math.floor(Math.random() * (this.chromosomeLength));
	                    if(randomIndex>0) {
		                    var tempNode = this.chromosome[randomIndex];
		                    this.chromosome[randomIndex] = this.chromosome[index];
		                    this.chromosome[index] = tempNode;

	                    }
	                }
	            }
	        };

	        // Returns individuals route distance
	        this.getDistance = function() {
	            var totalDistance = 0;

	            for (index in this.chromosome) {
	                var startNode = this.chromosome[index];
	                var endNode = this.chromosome[0];
	                if ((parseInt(index) + 1) < this.chromosome.length) {
	                    endNode = this.chromosome[(parseInt(index) + 1)];
	                }

	                totalDistance += durations[startNode][endNode];
	            }

	            totalDistance += durations[startNode][endNode];

	            return totalDistance;
	        };

	        // Calculates individuals fitness value
	        this.calcFitness = function() {
	            if (this.fitness != null) {
	                return this.fitness;
	            }

	            var totalDistance = this.getDistance();

	            this.fitness = 1 / totalDistance;
	            return this.fitness;
	        };

	        // Applies crossover to current individual and mate, then adds it's offspring to given population
	        this.crossover = function(individual, offspringPopulation) {
	            var offspringChromosome = [];

	            // Add a random amount of this individual's genetic information to offspring
	            var startPos = Math.floor(this.chromosome.length * Math.random() );
	            var endPos = Math.floor(this.chromosome.length * Math.random());

				var i = startPos;
	            while (i != endPos) {

	               if(i!=0) {
		                offspringChromosome[i] = individual.chromosome[i];
	               }

	                i++

	                if (i >= this.chromosome.length) {
	                    i = 0;
	                }
	            }

	            // Add any remaining genetic information from individual's mate
	            //console.log('indi', individual.chromosome);
	            for (parentIndex in individual.chromosome) {

	                var node = individual.chromosome[parentIndex];

	                var nodeFound = false;
	                for (offspringIndex in offspringChromosome) {

	   	            		if (offspringChromosome[offspringIndex] == node) {
		                        nodeFound = true;
		                        break;
		                    }
	                }

	                if (nodeFound == false) {
	                    for (var offspringIndex = 0; offspringIndex < individual.chromosome.length; offspringIndex++) {
	                        if (offspringChromosome[offspringIndex] == undefined) {
	                            offspringChromosome[offspringIndex] = node;
	                            break;
	                        }
	                    }
	                }
	            }

	            // Add chromosome to offspring and add offspring to population
	            var offspring = new ga.individual(this.chromosomeLength);
	            //console.log('offA',offspring.chromosome);
	            offspring.setChromosome(offspringChromosome);
	            //console.log('offB',offspring.chromosome);
	            offspringPopulation.addIndividual(offspring);
	        };
	    },
	};


	</script><?php


}

function _card() {
	global $conf,$user,$langs, $form, $db, $mysoc;

	$address = $mysoc->address.', '.$mysoc->zip.' '.$mysoc->town.', '.$mysoc->country;

  	?>
  	<style type="text/css">
  		@media print
		{
		    .no-print, .no-print *, #id-top, #id-left, .side-nav
		    {
		        display: none !important;
		    }
		}
  	</style>
  	<div id="point-depart" class="no-print">
  		<?php echo $langs->trans('StartingPoint') ?>
  		<input type="text" value="<?php echo $address; ?>" name="starting-point" id="starting-point" size="80" />
  		<button id="start-from_this-point" class="butAction"><?php echo $langs->trans('StartFromThisAddress') ?></button>
  	</div>

  	<div id="map-canvas" style="width:100%; height:500px;"></div>

	  <div class="tabsAction no-print" id="ga-buttons">
	  	<?php
			$form=new Form($db);
	  		echo $form->select_company(-1,'fk_soc', "", 1);
	  	?>
	  	<button id="add-company" class="butAction"><?php echo $langs->trans('AddCompanyOnMap') ?></button>
	  	&nbsp;
	  	<button id="find-route" class="butAction"><?php echo $langs->trans('FindRoute') ?></button>
	  	<!-- <a id="download-map" class="butAction" onclick="downloadCanvas(this);"><?php echo $langs->trans('Download') ?></a> -->
	  	<button id="clear-map" class="butAction"><?php echo $langs->trans('ClearDestination') ?></button>
	  </div>

	   <div style="display:none;" class="no-print">
	    <table>
	        <tr>
	            <td colspan="2"><b>Configuration</b></td>
	        </tr>
	        <tr>
	            <td>Travel Mode: </td>
	            <td>
	                <select id="travel-type">
	                    <option value="DRIVING">Car</option>
	                    <option value="BICYCLING">Bicycle</option>
	                    <option value="WALKING">Walking</option>
	                </select>
	            </td>
	        </tr>
	        <tr>
	            <td>Avoid Highways: </td>
	            <td>
	                <select id="avoid-highways">
	                    <option value="1">Enabled</option>
	                    <option value="0" selected="selected">Disabled</option>
	                </select>
	            </td>
	        </tr>
	        <tr>
	            <td>Population Size: </td>
	            <td>
	                <select id="population-size">
	                    <option value="5">5</option>
	                    <option value="10">10</option>
	                    <option value="20">20</option>
	                    <option value="50" selected="selected">50</option>
	                    <option value="100">100</option>
	                    <option value="200">200</option>
	                </select>
	            </td>
	        </tr>
	        <tr>
	            <td>Mutation Rate: </td>
	            <td>
	                <select id="mutation-rate">
	                    <option value="0.00">0.00</option>
	                    <option value="0.05">0.01</option>
	                    <option value="0.05">0.05</option>
	                    <option value="0.1" selected="selected">0.1</option>
	                    <option value="0.2">0.2</option>
	                    <option value="0.4">0.4</option>
	                    <option value="0.7">0.7</option>
	                    <option value="1">1.0</option>
	                </select>
	            </td>
	        </tr>
	        <tr>
	            <td>Crossover Rate: </td>
	            <td>
	                <select id="crossover-rate">
	                    <option value="0.0">0.0</option>
	                    <option value="0.1">0.1</option>
	                    <option value="0.2">0.2</option>
	                    <option value="0.3">0.3</option>
	                    <option value="0.4">0.4</option>
	                    <option value="0.5" selected="selected">0.5</option>
	                    <option value="0.6">0.6</option>
	                    <option value="0.7">0.7</option>
	                    <option value="0.8">0.8</option>
	                    <option value="0.9">0.9</option>
	                    <option value="1">1.0</option>
	                </select>
	            </td>
	        </tr>
	        <tr>
	            <td>Elitism: </td>
	            <td>
	                <select id="elitism">
	                    <option value="1" selected="selected">Enabled</option>
	                    <option value="0">Disabled</option>
	                </select>
	            </td>
	        </tr>
	        <tr>
	            <td>Max Generations: </td>
	            <td>
	                <select id="generations">
	                    <option value="20">20</option>
	                    <option value="50" selected="selected">50</option>
	                    <option value="100">100</option>
	                </select>
	            </td>
	        </tr>
	        <tr>
	            <td colspan="2"><b>Debug Info</b></td>
	        </tr>
	        <tr>
	            <td>Destinations Count: </td>
	            <td id="destinations-count">0</td>
	        </tr>
	        <tr class="ga-info" style="display:none;">
	            <td>Generations: </td><td id="generations-passed">0</td>
	        </tr>
	        <tr class="ga-info" style="display:none;">
	            <td>Best Time: </td><td id="best-time">?</td>
	        </tr>

	    </table>
	  </div>

		<?php
		$fk_user = GETPOST('fk_user', 'int');
		if (empty($fk_user)) $fk_user = $user->id;
		$socid = GETPOST('socid', 'int');
		if ($socid < 0) $socid = 0;

		$toselect = GETPOST('toselect', 'array');

		$sql = "SELECT ac.id, ac.code, ac.label, ac.datep as dp, ac.datep2 as dp2, ac.code, ac.location, c.code as type_code, c.libelle as type_label, GROUP_CONCAT(ua.rowid) as user_assigned";
		$sql.= " ,s.nom as societe, s.rowid as socid, s.address, s.town, s.client, s.email as socemail";
		$sql.= " FROM ".MAIN_DB_PREFIX."actioncomm as ac";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_actioncomm as c ON ac.fk_action = c.id";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = ac.fk_soc";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = ac.fk_user_author";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."actioncomm_resources as ar ON ar.fk_actioncomm = ac.id AND ar.element_type = 'user'";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as ua ON ua.rowid = ar.fk_element";
		$sql.= " WHERE ac.fk_soc IS NOT NULL";
		$sql.= " AND ac.percent = 0";
		$sql.= " AND ac.datep >= '".date('Y-m-d 00:00:00', strtotime('-2 weeks'))."'";
		$sql.= " AND ac.datep < '".date('Y-m-d 00:00:00', strtotime('+2 weeks'))."'";
//		$sql.= " AND ar.fk_element = ".$fk_user;
		if (!empty($socid)) $sql.= " AND ac.fk_soc = ".$socid;
		if (!empty($conf->global->SALESMAN_EVENTTYPE_TO_FILTER_LIST)) $sql.= " AND ac.code IN ('".implode("','",json_decode($conf->global->SALESMAN_EVENTTYPE_TO_FILTER_LIST, true))."')";
		$sql.= " GROUP BY ac.id";

//		print $sql;

		$resql = $db->query($sql);
		if ($resql)
		{

		?>
			<form method="POST" id="searchFormList" class="listactionsfilter" action="<?php $_SERVER['PHP_SELF']; ?>">

			<div class="div-table-responsive">
				<table id="listevent" class="tagtable liste">
			<thead>
					<tr class="liste_titre_filter">
						<th class="liste_titre"></th>
						<th class="liste_titre"></th>
						<th class="liste_titre"></th>
						<th class="liste_titre"></th>
						<th class="liste_titre"></th>
						<th class="liste_titre"></th>
						<th class="liste_titre"></th>
						<th class="liste_titre"></th>
						<th class="liste_titre"></th>
						<th class="liste_titre"></th>
					</tr>
					<tr class="liste_titre">
						<th class="liste_titre"><?php echo $langs->trans('Ref'); ?></th>
						<th class="liste_titre"><?php echo $langs->trans('Type'); ?></th>
						<th class="liste_titre"><?php echo $langs->trans('Label'); ?></th>
						<th class="liste_titre"><?php echo $langs->trans('DateStart'); ?></th>
						<th class="liste_titre"><?php echo $langs->trans('DateEnd'); ?></th>
						<th class="liste_titre"><?php echo $langs->trans('ThirdParty'); ?></th>
						<th class="liste_titre"><?php echo $langs->trans('Address'); ?></th>
						<th class="liste_titre"><?php echo $langs->trans('Town'); ?></th>
						<th class="liste_titre"><?php echo $langs->trans('ActionAssignedTo'); ?></th>
						<th class="liste_titre"></th>
					</tr>
			</thead>
			<tbody>
<?php

			if ($db->num_rows($resql))
			{
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/cactioncomm.class.php';
				$caction = new CActionComm($db);
				$arraylist = $caction->liste_array(1, 'code', '', (empty($conf->global->AGENDA_USE_EVENT_TYPE) ? 1 : 0), '', 1);

				$actionstatic = new ActionComm($db);
				$societestatic = new Societe($db);
				$u = new User($db);

				while ($obj = $db->fetch_object($resql))
				{
					$actionstatic = new ActionComm($db);
					$actionstatic->id = $obj->id;
					$actionstatic->ref = $obj->id;
					$actionstatic->code = $obj->code;
					$actionstatic->type_code = $obj->type_code;
					$actionstatic->type_label = $obj->type_label;
					$actionstatic->label = $obj->label;
					$actionstatic->location = $obj->location;

					$actionstatic->fetchResources();
					$assigned = "";
					if (!empty ($actionstatic->userassigned))
					{
						foreach ($actionstatic->userassigned as $userInfos)
						{
							$res = $u->fetch($userInfos['id']);
							if ($res) $assigned.=$u->getNomUrl(1)."<br>";
						}
					}

					$imgpicto = '';
					if ($actionstatic->type_code == 'AC_RDV')         $imgpicto = img_picto('', 'object_group', '', false, 0, 0, '', 'paddingright').' ';
					elseif ($actionstatic->type_code == 'AC_TEL')     $imgpicto = img_picto('', 'object_phoning', '', false, 0, 0, '', 'paddingright').' ';
					elseif ($actionstatic->type_code == 'AC_FAX')     $imgpicto = img_picto('', 'object_phoning_fax', '', false, 0, 0, '', 'paddingright').' ';
					elseif ($actionstatic->type_code == 'AC_EMAIL')   $imgpicto = img_picto('', 'object_email', '', false, 0, 0, '', 'paddingright').' ';
					elseif ($actionstatic->type_code == 'AC_INT')     $imgpicto = img_picto('', 'object_intervention', '', false, 0, 0, '', 'paddingright').' ';
					elseif ($actionstatic->type_code == 'AC_OTH' && $actionstatic->code == 'TICKET_MSG') $imgpicto = img_picto('', 'object_conversation', '', false, 0, 0, '', 'paddingright').' ';
					elseif (!preg_match('/_AUTO/', $actionstatic->type_code)) $imgpicto = img_picto('', 'object_other', '', false, 0, 0, '', 'paddingright').' ';

					$labeltype = $obj->type_code;
					if (empty($conf->global->AGENDA_USE_EVENT_TYPE) && empty($arraylist[$labeltype])) $labeltype = 'AC_OTH';
					if ($actionstatic->type_code == 'AC_OTH' && $actionstatic->code == 'TICKET_MSG') {
						$labeltype = $langs->trans("Message");
					} elseif (!empty($arraylist[$labeltype])) $labeltype = $arraylist[$labeltype];


					print "<tr>";
					print "<td>".$actionstatic->getNomUrl(1, -1)."</td>";
					print "<td>"./*$imgpicto.*/dol_trunc($labeltype, 28)."</td>";
					print "<td>".$actionstatic->label."</td>";
					print "<td>".dol_print_date($db->jdate($obj->dp), 'dayhour')."</td>";
					print "<td>".dol_print_date($db->jdate($obj->dp2), 'dayhour')."</td>";

					$societestatic->id = $obj->socid;
					$societestatic->client = $obj->client;
					$societestatic->name = $obj->societe;
					$societestatic->email = $obj->socemail;
					$societestatic->address = $obj->address;
					$societestatic->town = $obj->town;

					print "<td>".$societestatic->getNomUrl(1, '', 28)."</td>";
					print "<td>".$societestatic->address."</td>";
					print "<td>".$societestatic->town."</td>";
					print "<td>".$assigned."</td>";
					print '<td><input class="selectSociete" type="checkbox" value="'.$actionstatic->id.'" data-socid="'.$societestatic->id.'"></td>';
					print "</tr>";
				}
			}
			else
			{
				print "<tr><td colspan='8' align='center'>".$langs->trans('Empty')."</td></tr>";
			}
			print "</tbody>";

			print "</table></form></div>";

			print '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.css">';
			print '<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.js"></script>';

		}

		print '<div id="itineraire"></div>';

 }

