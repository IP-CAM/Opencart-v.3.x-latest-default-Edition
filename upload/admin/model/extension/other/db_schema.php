<?php
/**
 * Class DB Schema
 *
 * @package Admin\Model\Extension\Other
 */
class ModelExtensionOtherDbSchema extends Model {
	/**
	 * Get Table
	 *
	 * @param string               $name
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getTable(string $name, array $data = []): array {
		$implode = [];

		foreach ($data as $result) {
			$implode[] = "COLUMN_NAME = '" . $result . "'";
		}

		$sql = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . $name . "'";

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Get Tables
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getTables(array $data = []): array {
		$sql = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "'";

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Get Indexes
	 *
	 * @param string               $name
	 * @param array<string, mixed> $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getIndexes(string $name, array $data): array {
		$implode = [];

		foreach ($data as $result) {
			$implode[] = "COLUMN_NAME = '" . $result . "'";
		}

		$sql = "SELECT * FROM information_schema.STATISTICS WHERE 1 = 1 AND TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . $name . "'";

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Get Total Tables
	 *
	 * @return int
	 */
	public function getTotalTables(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . DB_DATABASE . "'");

		return (int)$query->row['total'];
	}
}
