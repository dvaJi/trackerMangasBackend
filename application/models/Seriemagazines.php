<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Seriemagazines extends MY_Model {
	protected $table  = 'serie_magazines';

	public function generateMagazinesArray($magazines, $idSerie) {
		$magazines = array();
		foreach ($magazines as $key => $magazine) {
			$magazineObj = new \stdClass;
			$magazineObj->id_series = $idSerie;
			$magazineObj->id_magazines = intval($magazine->id_magazine);
			array_push($magazines, $magazineObj);
		}

		return $magazines;
	}
}
