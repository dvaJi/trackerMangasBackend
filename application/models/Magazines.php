<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Magazines extends MY_Model {
	protected $table  = 'magazines';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'serie_magazines',
			'foreign'   => 'id_magazines',
			'variable'  => 'series'
		));

		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'magazines_covers',
			'foreign'   => 'id_magazine',
			'variable'  => 'cover'
		));
	}

	/*
	 * Obtiene las series de la revista
	 *
	 * @autor dvaJi
	*/
	public function getSeries($series) {

		if ($series == NULL) {
			return null;
		}
		$seriesArray = array();
		foreach ($series as $key => $value) {

			$serie = $this->series->find($value->id_series);
			$serie->name = $this->seriesaltnames->find(array('id_series' => $value->id_series, 'def' => 1))->name;

			$value->name = $serie->name;
			$value->publicationDate = $serie->publication_date;
			$value->serieId = $serie->id;
			$value->stub = $serie->stub;
			unset($value->id_magazines);
			array_push($seriesArray, $value);
		}

		return $seriesArray;
	}

	/*
	 * Query para la búsqueda de revistas por su nombre
	 *
	 * @autor dvaJi
	*/
	public function searchMagazines($q) {
		$this->db->select('magazines.id, magazines.name, magazines.stub, magazines.uniqid, GROUP_CONCAT(magazines_covers.filename) AS covers');
    $this->db->from('magazines');
    $this->db->join('magazines_covers', 'magazines.id = magazines_covers.id_magazine', 'left');
		$this->db->like('magazines.name', $q);
		$this->db->group_by("magazines.id");
    $query = $this->db->get();
		$result = $query->result();

    if (!empty($result)) {
			foreach ($result as $key => $value) {

				if ($value->covers != NULL) {
					$covers = explode(',', $value->covers);
					$value->covers = array_values(array_unique($covers));
					$value->covers = $value->image_url_full = "/api/content/magazine/" . $value->stub . "_" . $value->uniqid ."/" . $value->covers[0];

				} else {
					$value->covers = 'default.png';
					$value->image_url_full = "/api/content/magazine/" . $value->covers;
				}

			}
      return $result;
    } else {
      return $result;
    }
	}

}
