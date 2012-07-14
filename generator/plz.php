<?php
	
	// const
		define("OPEN_GEO_DB_NAME",		500100000);
		define("OPEN_GEO_DB_TYPE",		500300000);		
		define("OPEN_GEO_DB_KEY",		500600000);
		define("OPEN_GEO_DB_VORWAHL",	500400000);

	// includes
	ini_set('memory_limit', '256M');
	echo "memory limit: ".ini_get('memory_limit')."\n\n";
	require_once("PHPExcel/PHPExcel.php");
		
	// db
		$db = "geodb";
		$user = "geodb";
		$pass = "h9RPAsxUXp326BPe";
		
		$dbh = new PDO('mysql:host=localhost;dbname='.$db, $user, $pass);

	// text data
		$text_data_sql = "SELECT DISTINCT text_val, text_type, loc_id FROM geodb_textdata WHERE loc_id = :loc_id AND text_type IN(".OPEN_GEO_DB_NAME.", ".OPEN_GEO_DB_TYPE.", ".OPEN_GEO_DB_KEY.", ".OPEN_GEO_DB_VORWAHL.")";
		$text_data_sth = $dbh->prepare($text_data_sql);

	// tree
		$tree = array();

	// special plz
	$spezial_plz = array();
		
	// deutschland
		$land_ids = array();
		$sql = "SELECT loc_id FROM `geodb_textdata` WHERE `text_val` LIKE 'Deutschland'";
		$sth = $dbh->prepare($sql);
		$sth->execute();
		$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
		#print_r($rows);
		foreach($rows as $row) {
			array_push($land_ids, $row["loc_id"]);
		}
		#print_r($land_ids);
		#die();

	// bundesländer
		$bundesland_ids = array();
		$sql = "SELECT DISTINCT id_lvl3 FROM geodb_hierarchies WHERE id_lvl2 = :id_lvl2 AND id_lvl3 <> ''";
		$sth = $dbh->prepare($sql);
		foreach ($land_ids as $id) {
			$sth->execute(array(':id_lvl2' => $id));
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
			#print_r($rows);

			foreach ($rows as $row) {
				array_push($bundesland_ids, $row["id_lvl3"]);
				
				$text_data_sth->execute(array(':loc_id' => $row["id_lvl3"]));
				$text_rows = $text_data_sth->fetchAll(PDO::FETCH_ASSOC);
				#print_r($text_rows);
				
				$tree[$row["id_lvl3"]] = createLeaf();
				fillLeaf($tree[$row["id_lvl3"]], $text_rows);
			}
		}
		#print_r($tree);

		
	// get  rps
		$rp_ids = array();
		$sql = "SELECT DISTINCT id_lvl4 FROM geodb_hierarchies WHERE id_lvl3 = :id_lvl3 AND id_lvl4 <> ''";
		$sth = $dbh->prepare($sql);
		foreach ($bundesland_ids as $id) {
			$sth->execute(array(':id_lvl3' => $id));
			$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
			#echo $id."\n";
			#print_r($rows);
			
			$rp_ids[$id] = array();
			
			foreach($rows as $row) {
				array_push($rp_ids[$id], $row["id_lvl4"]);
				
				$text_data_sth->execute(array(':loc_id' => $row["id_lvl4"]));
				$text_rows = $text_data_sth->fetchAll(PDO::FETCH_ASSOC);
				#print_r($text_rows);
				
				$tree[$id]["children"][$row["id_lvl4"]] = createLeaf();
				fillLeaf($tree[$id]["children"][$row["id_lvl4"]], $text_rows);
			}
			
			if (empty($tree[$id]["children"])) {
				$tree[$id]["children"][0] = createLeaf();
			}
		}
		#print_r($rp_ids);
		#print_r($tree);
		#die();

	// get lks
		$lk_ids = array();
		$sql = "SELECT DISTINCT id_lvl5 FROM geodb_hierarchies WHERE id_lvl4 = :id_lvl4 AND id_lvl5 <> ''";
		$sth = $dbh->prepare($sql);
		$sql_b = "SELECT DISTINCT id_lvl5 FROM geodb_hierarchies WHERE id_lvl3 = :id_lvl3 AND id_lvl5 <> ''";
		$sth_b = $dbh->prepare($sql_b);
		foreach ($rp_ids as $bundesland_id => $ids) {
			/*if ($bundesland_id != 113)
				continue;/**/
		
			#echo $bundesland_id."\n";
			if (empty($ids)) {
				#echo "no RPs\n";
				$sth_b->execute(array(':id_lvl3' => $bundesland_id));
				$rows = $sth_b->fetchAll(PDO::FETCH_ASSOC);
				#if ($bundesland_id == 113)
				#	print_r($rows);

				foreach ($rows as $row) {
				
					$text_data_sth->execute(array(':loc_id' => $row["id_lvl5"]));
					$text_rows = $text_data_sth->fetchAll(PDO::FETCH_ASSOC);
					#print_r($text_rows);
					
					$tree[$bundesland_id]["children"][0]["children"][$row["id_lvl5"]] = createLeaf();
					fillLeaf($tree[$bundesland_id]["children"][0]["children"][$row["id_lvl5"]], $text_rows);
				}

			} else {
				foreach ($ids as $id) {
					$sth->execute(array(':id_lvl4' => $id));
					$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
					#if ($bundesland_id == 113)
					#	print_r($rows);
					
					foreach ($rows as $row) {
				
						$text_data_sth->execute(array(':loc_id' => $row["id_lvl5"]));
						$text_rows = $text_data_sth->fetchAll(PDO::FETCH_ASSOC);
						#print_r($text_rows);
						
						$tree[$bundesland_id]["children"][$id]["children"][$row["id_lvl5"]] = createLeaf();
						fillLeaf($tree[$bundesland_id]["children"][$id]["children"][$row["id_lvl5"]], $text_rows);
					}
				}
			}
		}
		#print_r($tree);
		#die();

	// get orte
		$plz = array(array(
			"plz",
			"ort",
			"kreisschlüssel",
			"kreis",
			"länderschlüssel",
			"bundesland",
		));

		$sql = "SELECT DISTINCT id_lvl6 FROM geodb_hierarchies WHERE id_lvl5 = :id_lvl5 AND id_lvl6 <> ''";
		$sth = $dbh->prepare($sql);
		foreach($tree as $bundesland_id => $bundesland) {
			echo "\n".$bundesland["name"]."\n";
			
			foreach($bundesland["children"] as $bezirks_id => $bezirk) {

				/*if ($bundesland["name"] != "Hessen") {
					echo "skip\n";
					break;
				}/**/

				foreach($bezirk["children"] as $landkreis_id => $landkreis) {
					$sth->execute(array(':id_lvl5' => $landkreis_id));
					$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
					/*if ($bundesland_id == 113) {
						echo $landkreis_id."\n";
						if ($landkreis_id == 530) {
							print_r($rows);
						}
						#die();
					}*/
					
					foreach ($rows as $row) {
						#print_r($row);
				
						$text_data_sth->execute(array(':loc_id' => $row["id_lvl6"]));
						$text_rows = $text_data_sth->fetchAll(PDO::FETCH_ASSOC);
						/*if ($bundesland_id == 113) {
							if ($landkreis_id == 530) {
								print_r($text_rows);
								die();
							}
						}/**/
						
						$ret = getOrte($tree[$bundesland_id]["children"][$bezirks_id]["children"][$landkreis_id]["children"], $text_rows, 6);
						$count = $ret["count"];
						if (sizeof($ret["plz"]) > 0)
							$spezial_plz = array_merge($spezial_plz, $ret["plz"]);
						#echo $bezirks_id." ".$landkreis_id." => ".$count."\n";

						// get ortsteile
							#if ($count == 1) {
								for ($i=7; $i<=9; $i++) {
								#for ($i=7; $i<=7; $i++) {
									$sub_sql = "SELECT DISTINCT id_lvl".$i." FROM geodb_hierarchies WHERE id_lvl6 = :id_lvl6 AND id_lvl".$i." <> ''";
									/*if ($bundesland_id == 113 && $count != 1) {
										if ($landkreis_id == 522) {
											print_r($row);
											echo $sub_sql."\n";
										}
									}/**/

									$sub_sth = $dbh->prepare($sub_sql);
									$sub_sth->execute(array(':id_lvl6' => $row["id_lvl6"]));
									$sub_rows = $sub_sth->fetchAll(PDO::FETCH_ASSOC);
									/*if ($bundesland_id == 113 && $count != 1) {
										if ($landkreis_id == 522) {
											print_r($sub_rows);
										}
									}/**/
									
									foreach($sub_rows as $sub_row) {
										$text_data_sth->execute(array(':loc_id' => $sub_row["id_lvl".$i]));
										$sub_text_rows = $text_data_sth->fetchAll(PDO::FETCH_ASSOC);
										/*if ($bundesland_id == 113 && $count != 1) {
											if ($landkreis_id == 522) {
												print_r($sub_text_rows);
											}
										}/**/
										
										getOrte($tree[$bundesland_id]["children"][$bezirks_id]["children"][$landkreis_id]["children"], $sub_text_rows, $i);
									}
								}
							#}
					}

					// write plz list
					foreach ($tree[$bundesland_id]["children"][$bezirks_id]["children"][$landkreis_id]["children"] as $ort) {
						array_push($plz, array(
							$ort["plz"],
							$ort["name"],
							$landkreis["key"],
							$landkreis["name"],
							$bundesland["key"],
							$bundesland["name"],
						));
					}
					#print_r($plz);
					#die();
				}
			}
		}
		#print_r($tree);
		#print_r($tree[113]["children"][171]["children"][530]);
		#print_r($plz[0]);
		#die();

	// clean up
		unset($tree);
		
	// sort
		$plz_sort = array();
		foreach ($plz as $id => $row) {
			if ($id != 0) {
				if (!isset($plz_sort[$row[0]]))
					$plz_sort[$row[0]] = array();

				if (!isset($plz_sort[$row[0]][$row[1]]))
					$plz_sort[$row[0]][$row[1]] = $row;
			}
		}
		foreach($plz_sort as $plzs => $orte) {
			ksort($orte);
			$plz_sort[$plzs] = $orte;
		}
		ksort($plz_sort);
		
		#print_r($plz_sort);
		#die();
				
		// write csv
		$fp = fopen('plz.csv', 'w');
		#print_r($plz[0]);
		
		$row = $plz[0];
		#fputcsv($fp, $row, ";", "'");
		fputs($fp, $row[0].";".$row[1].";".$row[2].";".$row[3].";".$row[4].";".$row[5]."\r\n");

		foreach ($plz_sort as $data) {
			foreach ($data as $row) {
				#fputcsv($fp, $row, ";", "'");
				fputs($fp, cleanPLZ($row[0]).";".cleanName($row[1]).";".$row[2].";".cleanKreis($row[3]).";".$row[4].";".$row[5]."\r\n");
			}
		}
		fclose($fp);
		
		// utf8 convert csv
		$data = file_get_contents("plz.csv");
		$data = mb_convert_encoding($data, 'UTF-8');
		file_put_contents("plz.csv", $data);

		// write excel
		$excel = new PHPExcel();
		$sheet = $excel->getActiveSheet();

		$line = 1;
		$row = $plz[0];
		$sheet->getCellByColumnAndRow(0, $line)->setValueExplicit(mb_convert_encoding($row[0], 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
		$sheet->getCellByColumnAndRow(1, $line)->setValueExplicit(mb_convert_encoding($row[1], 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
		$sheet->getCellByColumnAndRow(2, $line)->setValueExplicit(mb_convert_encoding($row[2], 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
		$sheet->getCellByColumnAndRow(3, $line)->setValueExplicit(mb_convert_encoding($row[3], 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
		$sheet->getCellByColumnAndRow(4, $line)->setValueExplicit(mb_convert_encoding($row[4], 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
		$sheet->getCellByColumnAndRow(5, $line)->setValueExplicit(mb_convert_encoding($row[5], 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
		$line++;
		
		foreach ($plz_sort as $data) {
			foreach ($data as $row) {
				$sheet->getCellByColumnAndRow(0, $line)->setValueExplicit(cleanPLZ($row[0]), PHPExcel_Cell_DataType::TYPE_NUMERIC);
				$sheet->getCellByColumnAndRow(1, $line)->setValueExplicit(mb_convert_encoding(cleanName($row[1]), 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
				$sheet->getCellByColumnAndRow(2, $line)->setValueExplicit($row[2], PHPExcel_Cell_DataType::TYPE_NUMERIC);
				$sheet->getCellByColumnAndRow(3, $line)->setValueExplicit(mb_convert_encoding(cleanKreis($row[3]), 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
				$sheet->getCellByColumnAndRow(4, $line)->setValueExplicit($row[4], PHPExcel_Cell_DataType::TYPE_NUMERIC);
				$sheet->getCellByColumnAndRow(5, $line)->setValueExplicit(mb_convert_encoding($row[5], 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
				$line++;
			}
		}

		$writer = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
		$writer->save('plz.xls');

		

	// ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	// functions
	// ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

	function createLeaf() {
		return array(
				"name"		=>	"",
				"key"		=>	"",
				"children"	=>	array(),
		);
	}
	
	function fillLeaf(&$array, $rows) {
		foreach($rows as $row) {
			switch($row["text_type"]) {
				case OPEN_GEO_DB_NAME:
					$array["name"] = trim($row["text_val"]);
					break;
				case OPEN_GEO_DB_TYPE:
					$array["type"] = trim($row["text_val"]);
					break;
				case OPEN_GEO_DB_KEY:
					$array["key"] = trim($row["text_val"]);
					break;
			}
		}
	}
	
	function getOrte(&$base, $rows, $level) {
		global $spezial_plz;
		
		$array = array(
			"name"	=>	"",
			"plz"	=>	"",
		);

		$count = 0;
		$plz = array();
		
		#print_r($rows);

		$check = false;
		foreach($rows as $row) {
			if ($row["text_type"] == OPEN_GEO_DB_VORWAHL) {
				if (strlen($row["text_val"]) != 0)
					$check = true;
			}
		}
		
		if ($check) {
			foreach($rows as $row) {
				switch($row["text_type"]) {
					case OPEN_GEO_DB_NAME:
						$array["name"] = trim($row["text_val"]);
						break;
					case OPEN_GEO_DB_TYPE:
						$array["plz"] = trim($row["text_val"]);
						
						$notexists = true;
						if ($level > 6) {
							if (in_array($array["plz"], $spezial_plz))
								$notexists = false;
						}
						/*if ($level > 6) {
							foreach($base as $record) {
								if ($record["plz"] == $array["plz"])
									$notexists = false;
							}
						}/**/
							
						if ($notexists) {
							array_push($base, $array);
							$count++;
							if ($count > 1)
								array_push($plz, $array["plz"]);
						} else {
							/*echo $level." ".$count."\n";
							print_r($array);/**/
						}
						break;
				}
			}
		} else {
			#print_r($row);
		}
		
		if ($count == 1)
			$plz = array();
		
		return array(
			"count" => $count,
			"plz" => $plz,
		);
	}

	function cleanPLZ($plz) {
		if ($plz{0} == 0)
			$plz = substr($plz, 1);
		
		return $plz;
	}
	
	function cleanName($name) {
		$name = explode(",", $name);
		/*if (strlen($name[1]) > 4)
			echo $name[1]."\n";/**/
		$name = $name[0];

		$name = explode("/", $name);
		/*if (strlen($name[1]) > 4)
			echo $name[1]."\n";**/
		$name = $name[0];

		$name = explode(" bei ", $name);
		/*if (strlen($name[1]) > 4)
			echo $name[1]."\n";/**/
		$name = $name[0];
		
		$name = trim($name);
		
		return $name;
	}

	function cleanKreis($name) {
		$name = str_replace("Saalkreis (Saale)", "Saalkreis", $name);
		$name = str_replace("Burgenlandkreis (Saale)", "Burgenlandkreis", $name);
		$name = str_replace("Müritz (Müritz)", "Müritz", $name);
		$name = str_replace("Nienburg (Weser)", "Nienburg", $name);
		$name = str_replace("Rotenburg (Wümme)", "Rotenburg", $name);
		$name = str_replace("Birkenfeld (Nahe)", "Birkenfeld", $name);
		$name = str_replace("Stadtverband Saarbrücken,Stadt und Stadtverband Saarbrücken", "Stadtverband Saarbrücken", $name);
		$name = str_replace("Neunkirchen (Saar)", "Neunkirchen", $name);
		$name = str_replace("Landkreis Ludwigshafen/Rhein", "Landkreis Ludwigshafen", $name);
		$name = str_replace("Rhön-Grabfeld a.d.S.", "Rhön-Grabfeld", $name);
		$name = str_replace("Leer / Ostfriesland", "Leer", $name);
		
		$name = trim($name);
		
		return $name;
	}
	
?>