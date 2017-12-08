<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Scans extends MY_Model {
	protected $table  = 'scans';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'scans_covers',
			'foreign'   => 'id_scans',
			'variable'  => 'cover'
		));

		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'release_groups',
			'foreign'   => 'group_id',
			'variable'  => 'releases'
		));
	}

	/*
	 * Obtiene los releases y series del scan
	 *
	 * @autor dvaJi
	*/
	public function getReleases($releases) {

		if ($releases == NULL) {
			return null;
		}

		$releasesArray = array();
		foreach ($releases as $key => $value) {

			$release = $this->releases->find($value->release_id);
			$serie = $this->series->find($release->series_id);
			$serie->name = $this->seriesaltnames->find(array('id_series' => $serie->id, 'def' => 1))->name;

			$value->serie = new \stdClass;
			$value->serie->name = $serie->name;
			$value->serie->publicationDate = $serie->publication_date;
			$value->serie->id = $serie->id;
			$value->serie->stub = $serie->stub;

			$value->chapter = $release->chapter;
			$value->volume = $release->volume;
			$value->created = $release->created;
			unset($value->group_id);
			unset($value->release_id);
			array_push($releasesArray, $value);
		}

		return $releasesArray;
	}

	/*
	 * Query para la búsqueda de scans por su nombre
	 *
	 * @autor dvaJi
	*/
	public function searchScans($q) {
		$this->db->select('scans.id, scans.name, scans.stub, scans.uniqid, GROUP_CONCAT(scans_covers.filename) AS covers');
    $this->db->from('scans');
    $this->db->join('scans_covers', 'scans.id = scans_covers.id_scans', 'left');
		$this->db->like('scans.name', $q);
		$this->db->group_by("scans.id");
    $query = $this->db->get();
		$result = $query->result();

    if (!empty($result)) {
			foreach ($result as $key => $value) {

				if ($value->covers != NULL) {
					$covers = explode(',', $value->covers);
					$value->covers = array_values(array_unique($covers));
					$value->covers = $value->image_url_full = "/api/content/scans/" . $value->stub . "_" . $value->uniqid ."/" . $value->covers[0];

				} else {
					$value->covers = 'default.png';
					$value->image_url_full = "/api/content/scans/" . $value->covers;
				}

			}
      return $result;
    } else {
      return $result;
    }
	}
}
