<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class SerieChangelogDetail extends MY_Model {
	protected $table  = 'serie_changelog_detail';

	public function createChangelogDetail($id, $table, $column, $columnName, $oldValue, $newValue) {
		$change = new \stdClass;
		$change->id_serie_changelog = $id;
		$change->serie_table = $table;
		$change->public_name_column = $columnName;
		$change->column_table = $column;
		$change->old_value = $oldValue;
		$change->new_value = $newValue;
		$change->created = date("Y-m-d H:i:s");
		$change->updated = date("Y-m-d H:i:s");

		return $change;
	}
}
