<?php

	// shortcut
		/* http://www.bmelv-statistik.de/de/daten-tabellen-suche/abkuerzungen-der-bundeslaender/ */
		function getShortcut($state) {
			$shortcut = array(
				"Baden-Württemberg"			=>	"BW",
				"Bayern"					=>	"BY",
				"Berlin"					=>	"BE",
				"Brandenburg"				=>	"BB",
				"Bremen"					=>	"HB",
				"Hamburg"					=>	"HH",
				"Hessen"					=>	"HE",
				"Mecklenburg-Vorpommern"	=>	"MV",
				"Niedersachsen"				=>	"NI",
				"Nordrhein-Westfalen"		=>	"NW",
				"Rheinland-Pfalz"			=>	"RP",
				"Saarland"					=>	"SL",
				"Sachsen"					=>	"SN",
				"Sachsen-Anhalt"			=>	"ST",
				"Schleswig-Holstein"		=>	"SH",
				"Thüringen"					=>	"TH",
			);

			return $shortcut[$state];
		}

?>
