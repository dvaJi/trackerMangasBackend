<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Seriegenres extends MY_Model {
	protected $table  = 'genres';

	public function generateGenresArray($genres, $idSerie) {
		$genresArray = array();
		foreach ($genres as $key => $genre) {
			$genreObj = new \stdClass;
			$genreObj->id_series = $idSerie;
			$genreObj->id_typegenres = intval($genre->id_genre);
			array_push($genresArray, $genreObj);
		}

		return $genresArray;
	}
}
