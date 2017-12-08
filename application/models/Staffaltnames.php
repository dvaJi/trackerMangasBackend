<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Staffaltnames extends MY_Model {
	protected $table  = 'associated_names_mangaka';


	/*
	 * Query para la búsqueda de staff por su nombre
	 *
	 * @autor dvaJi
	*/
	public function getStaffByName($q) {
		$this->db->select('staff.id,associated_names_mangaka.name, staff.stub, staff.uniqid, GROUP_CONCAT(staff_covers.filename) AS covers');
    $this->db->from('associated_names_mangaka');
    $this->db->join('staff', 'staff.id = associated_names_mangaka.id_staff');
		$this->db->join('staff_covers', 'staff_covers.id_staff = staff.id', 'left');
		$this->db->like('associated_names_mangaka.name', $q);
		$this->db->group_by("associated_names_mangaka.name");
    $query = $this->db->get();
		$result = $query->result();

    if (!empty($result)) {
			$staffs = array();
			foreach ($result as $key => $value) {
				/*$names = explode(',', $value->names);
				$value->names = array_values(array_unique($names));*/

				if ($value->covers != NULL) {
					$covers = explode(',', $value->covers);
					$value->covers = array_values(array_unique($covers));
					$value->covers = $value->image_url_full = "/api/content/staff/" . $value->stub . "_" . $value->uniqid ."/" . $value->covers[0];

				} else {
					$value->covers = 'default.png';
					$value->image_url_full = "/api/content/staff/" . $value->covers;
				}

			}
      return $result;
    } else {
      return $result;
    }
	}

}
