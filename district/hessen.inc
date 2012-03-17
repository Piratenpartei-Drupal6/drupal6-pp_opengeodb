<?php

	// district
		function getDistrict($city) {
			$district = array(
				"Bergstraße"				=>	"Darmstadt",
				"Darmstadt, Stadt"			=>	"Darmstadt",
				"Darmstadt-Dieburg"			=>	"Darmstadt",
				"Frankfurt am Main, Stadt"	=>	"Darmstadt",
				"Groß-Gerau"				=>	"Darmstadt",
				"Hochtaunuskreis"			=>	"Darmstadt",
				"Main-Kinzig-Kreis"			=>	"Darmstadt",
				"Main-Taunus-Kreis"			=>	"Darmstadt",
				"Odenwaldkreis"				=>	"Darmstadt",
				"Offenbach"					=>	"Darmstadt",
				"Offenbach am Main, Stadt"	=>	"Darmstadt",
				"Rheingau-Taunus-Kreis"		=>	"Darmstadt",
				"Wetteraukreis"				=>	"Darmstadt",
				"Wiesbaden"					=>	"Darmstadt",

				"Gießen"					=>	"Gießen",
				"Lahn-Dill-Kreis"			=>	"Gießen",
				"Limburg-Weilburg"			=>	"Gießen",
				"Marburg-Biedenkopf"		=>	"Gießen",
				"Vogelsbergkreis"			=>	"Gießen",

				"Fulda"						=>	"Kassel",
				"Hersfeld-Rotenburg"		=>	"Kassel",
				"Kassel"					=>	"Kassel",
				"Kassel, Stadt"				=>	"Kassel",
				"Schwalm-Eder-Kreis"		=>	"Kassel",
				"Waldeck-Frankenberg"		=>	"Kassel",
				"Werra-Meißner-Kreis"		=>	"Kassel",
			);

			return $district[$city];
		}

?>
