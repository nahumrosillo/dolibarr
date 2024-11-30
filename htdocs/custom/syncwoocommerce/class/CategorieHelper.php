<?php

class CategorieHelper
{
	public DoliDB $db;

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	public function getChildrenTree($categoryId = 0): array
	{
		$categories = $this->getChildrenData($categoryId);
		foreach ($categories as $key => $category) {
			$categories[$key]['children'] = $this->getChildrenTree($category['rowid']);
		}
		return $categories;
	}

	public function getChildrenData($parentCategoryId): array
	{
		$sql = "SELECT * FROM " . MAIN_DB_PREFIX . "categorie WHERE fk_parent = $parentCategoryId ORDER BY position ASC";
		$resql = $this->db->query($sql);

		if ($resql) {
			$rows = [];
			while ($obj = $this->db->fetch_object($resql)) {
				$rows[] = [
					'rowid' => $obj->rowid,
					'label' => $obj->label,
					'description' => $obj->description,
					'position' => $obj->position,
					'fk_parent' => $obj->fk_parent
				];
			}
			return $rows;
		} else {
			return [];
		}
	}
}
