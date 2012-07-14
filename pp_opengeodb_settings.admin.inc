<?php

/**
 * Admin menu page for the basic settings
 */
function pp_opengeodb_admin_settings_form() {
	// stats
		$stats = array(
					"bundesland" => 0,
					"landkreis" => 0,
					"ort" => 0,
					"plz" => 0,
		);

		$sql = "SELECT COUNT(DISTINCT(`bundesland`)) AS `count` FROM {pp_opengeodb}";
		$result = db_query($sql);
		$count = db_fetch_array($result);
		$stats["bundesland"] = $count["count"];

		$sql = "SELECT COUNT(DISTINCT(`landkreis`)) AS `count` FROM {pp_opengeodb}";
		$result = db_query($sql);
		$count = db_fetch_array($result);
		$stats["landkreis"] = $count["count"];

		$sql = "SELECT COUNT(DISTINCT(`ort`)) AS `count` FROM {pp_opengeodb}";
		$result = db_query($sql);
		$count = db_fetch_array($result);
		$stats["ort"] = $count["count"];

		$sql = "SELECT COUNT(`plz`) AS `count` FROM {pp_opengeodb}";
		$result = db_query($sql);
		$count = db_fetch_array($result);
		$stats["plz"] = $count["count"];

	// form
		$form = array();
		$form['#attributes'] = array('enctype' => "multipart/form-data");

		$form['stats'] = array(
			'#type' => 'fieldset',
			'#title' => t('Statistik'),
			'#description' => t("In der Datenbank befinden sich:<br>Bundesländer: @bundesland<br>Landkreise: @landkreis<br>Orte: @ort<br>PLZ: @plz", array("@bundesland" => $stats["bundesland"], "@landkreis" => $stats["landkreis"], "@ort" => $stats["ort"], "@plz" => $stats["plz"])),
		);

		$form['upload'] = array(
			'#type' => 'fieldset',
			'#title' => t('Datei-Upload'),
			'#description' => t("Es wird eine Excel-Datei von folgender <a href='http://www.manfrin-it.com/postleitzahlen/plz.html'>Website</a> erwartet oder eine durch das <a href='https://github.com/Piratenpartei/drupal6-pp_opengeodb/tree/master/generator'>Generator-Skript</a> erzeugte."),
		);

		$form['upload']['file'] = array(
			'#type' => 'file',
			'#name' => 'files[excel]',
			'#title' => t('Excel-Datei'),
		);

		$form['upload']['submit'] = array(
			'#type' => 'submit',
			'#value' => t('Absenden'),
		);

		$form['clear'] = array(
			'#type' => 'fieldset',
			'#title' => t('Leeren'),
			'#description' => t("Zum Leeren der Datenbank den Button klicken."),
		);

		$form['clear']['submit'] = array(
			'#type' => 'submit',
			'#value' => t('Leeren'),
		);


	// return
		return $form;
}


function pp_opengeodb_admin_settings_form_validate($form, &$form_state) {
	// check for clear db
		if ($form_state['values']['op'] == t('Leeren')) {
			return;
		}

	// check upload itself
		if (isset($_FILES['files']['name']['excel'])) {
			if (empty($_FILES['files']['name']['excel'])) {
				form_set_error('file', t('Es muss eine Excel-Datei hochgeladen werden.'));
				return;
			}

			$ext = pathinfo($_FILES['files']['name']['excel'], PATHINFO_EXTENSION);
			#print_r($ext);
			if (strtolower($ext) != "xls") {
				form_set_error('file', t('Es muss eine Excel-Datei hochgeladen werden.'));
				return;
			}

			module_load_include('php', 'pp_opengeodb', 'lib/PHPExcel/IOFactory');
			try {
				$objPHPExcel = PHPExcel_IOFactory::load($_FILES['files']['tmp_name']['excel']);
				$excel = $objPHPExcel->getSheet()->toArray();
			} catch (Exception $e) {
				form_set_error('file', t('Die Excel-Datei konnte nicht gelesen werden.'));
				return;
			}

			$form_state["storage"]["excel"] = $excel;
		}
}


function pp_opengeodb_admin_settings_form_submit($form, &$form_state) {
	// check for clear db
		if ($form_state['values']['op'] == t('Leeren')) {
			 db_query("TRUNCATE TABLE {pp_opengeodb}");
			drupal_set_message(t("Datenbank geleert"));
			return;
		}

	// excel
		$excel = $form_state["storage"]["excel"];

	// find key
		$keys = array(
				"idx_plz"	=>	"plz",
				"idx_ort"	=>	"ort",
				"idx_lk"	=>	"kreis",
				"idx_bl"	=>	"bundesland"
		);
		foreach($keys as $key => $search) {
			foreach($excel[0] as $k => $v) {
				if ($search == $v) {
					$keys[$key] = $k;
				}
			}
		}

	// store values
		$new = 0;
		$kept = 0;
		do {
			// cell
				$cell = array_pop($excel);
				#print_r($cell);

			// record
				$obj = new stdClass();
				$obj->plz = $cell[$keys["idx_plz"]];
				$obj->ort = $cell[$keys["idx_ort"]];
				$obj->landkreis = $cell[$keys["idx_lk"]];
				$obj->bundesland = $cell[$keys["idx_bl"]];
				#print_r($obj);

			// fixes for hessen
				if ($obj->ort == "Darmstadt" && $obj->landkreis == "Darmstadt, Stadt") {
					$obj->landkreis = "Darmstadt Stadt";
				} else if ($obj->ort == "Frankfurt am Main" && $obj->landkreis == "Frankfurt am Main, Stadt") {
					$obj->landkreis = "Frankfurt Stadt";
				} else if ($obj->ort == "Kassel" && $obj->landkreis == "Kassel, Stadt") {
					$obj->landkreis = "Kassel Stadt";
				} else if ($obj->ort == "Offenbach" && $obj->landkreis == "Offenbach") {
					$obj->landkreis = "Offenbach Stadt";
				} else if ($obj->ort != "Offenbach" && $obj->landkreis == "Offenbach") {
					$obj->landkreis = "Offenbach-Land";
				}

			// find
				$sql = "SELECT COUNT(*) AS `count` FROM {pp_opengeodb} WHERE `plz` = '%s' AND `ort` = '%s' AND `landkreis` = '%s' AND `bundesland` = '%s'";
				$result = db_query($sql, $obj->plz, $obj->ort, $obj->landkreis, $obj->bundesland);
				$record = db_fetch_array($result);

				if ($record["count"] == 0) {
					$result = drupal_write_record('pp_opengeodb', $obj);
					switch ($result) {
						case SAVED_NEW:
							$new++;
							break;
						case SAVED_UPDATED:
							break;
					}
				} else {
					$kept++;
				}
		} while (sizeof($excel) > 1);

	// stats
		drupal_set_message(t("Neue Datensätze: ".$new."<br>Unveränderte Datensätze: ".$kept));
}
