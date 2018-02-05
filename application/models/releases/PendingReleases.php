<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class PendingReleases extends MY_Model {
	protected $table  = 'pending_releases';

	protected function setRelations() {

    $this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'pending_releases_groups',
			'foreign'   => 'id_pending_releases',
			'variable'  => 'scans'
		));

    $this->addRelation(array(
			'primary'   => 'id_user',
			'table'     => 'users',
			'foreign'   => 'id',
			'variable'  => 'user'
		));

		$this->addRelation(array(
			'primary'   => 'id_serie',
			'table'     => 'associated_names_series',
			'foreign'   => 'id_series',
			'variable'  => 'serie'
		));
	}

	public function getSerieName($names) {
		foreach ($names as $key => $value) {
			if ($value->def == 1) {
				return $value->name;
			}
		}

		return $name;
	}

	public function getGroups($groups) {
		$groupsArray = array();
		foreach($groups as $key => $value) {
			$group = $this->scans->find($value->id_scan);
			$value->name = $group->name;
			$value->stub = $group->stub;
			unset($value->id_pending_release);
			array_push($groupsArray, $value);
		}

		return $groupsArray;
	}

}
