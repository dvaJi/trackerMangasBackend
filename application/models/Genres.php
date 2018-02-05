<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Genres extends MY_Model {
	protected $table  = 'type_genres';

	public function getGenresBySerie($idSerie) {
		$this->db->select('genres.id_typegenres as id');
    $this->db->from('genres');
		$this->db->where('genres.id_series', $idSerie);
		$this->db->group_by("genres.id_typegenres");
    $query = $this->db->get();
		$result = $query->result();

		return $result;
	}
}
