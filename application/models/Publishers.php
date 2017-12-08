<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Publishers extends MY_Model {
	protected $table  = 'publisher';

	/*
	 * Query para la búsqueda de revistas por su nombre
	 *
	 * @autor dvaJi
	*/
	public function searchPublisher($q) {
		$this->db->select('publisher.id, publisher.name, publisher.stub, publisher.uniqid');
    $this->db->from('publisher');
		$this->db->like('publisher.name', $q);
		$this->db->group_by("publisher.id");
    $query = $this->db->get();
		$result = $query->result();

    return $result;
	}

}
