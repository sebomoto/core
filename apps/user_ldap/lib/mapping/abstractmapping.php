<?php
/**
* Copyright (c) 2014 Arthur Schiwon <blizzz@owncloud.com>
* This file is licensed under the Affero General Public License version 3 or
* later.
* See the COPYING-README file.
*/

namespace OCA\UserLDAP\Mapping;

/**
* Class AbstractMapping
* @package OCA\UserLDAP\Mapping
*/
abstract class AbstractMapping {
	/**
	 * @var \OCP\IDBConnection $dbc
	 */
	protected $dbc;

	/**
	 * returns the DB table name which holds the mappings
	 * @return string
	 */
	abstract protected function getTableName();

	/**
	 * @param \OCP\IDBConnection $dbc
	 */
	public function __construct(\OCP\IDBConnection $dbc) {
		$this->dbc = $dbc;
	}

	/**
	 * checks whether a provided string represents an exisiting table col
	 * @param string $col
	 * @return bool
	 */
	private function isColNameValid($col) {
		switch($col) {
			case 'ldap_dn':
			case 'owncloud_name':
			case 'directory_uuid':
				return true;
				break;
			default:
				return false;
		}
	}

	/**
	 * Gets the value of one column based on a provided value of another column
	 * @param string $fetchCol
	 * @param string $compareCol
	 * @param string $search
	 * @throws \Exception
	 * @return string|bool
	 */
	protected function getXbyY($fetchCol, $compareCol, $search) {
		if(!$this->isColNameValid($fetchCol)) {
			//this is used internally only, but we don't want to risk
			//having SQL injection at all.
			throw new \Exception('Invalid Column Name');
		}
		$query = $this->dbc->prepare('
			SELECT `' . $fetchCol . '`
			FROM `'. $this->getTableName() .'`
			WHERE `' . $compareCol . '` = ?
		');

		return $query->execute(array($search))->fetchColumn();
	}

	/**
	 * Gets the LDAP DN based on the provided name.
	 * Replaces Access::ocname2dn
	 * @param string $name
	 * @return string|bool
	 */
	public function getDNByName($name) {
		return $this->getXbyY('ldap_dn', 'owncloud_name', $name);
	}

	/**
	 * Updates the DN based on the given UUID
	 * @param string $fdn
	 * @param string $uuid
	 * @return bool
	 */
	public function setDNbyUUID($fdn, $uuid) {
		$query = $this->dbc->prepare('
			UPDATE `' . $this->getTableName() . '`
			SET `ldap_dn` = ?
			WHERE `directory_uuid` = ?
		');
		return $query->execute(array($fdn, $uuid));
	}

	/**
	 * Gets the name based on the provided LDAP DN.
	 * @param string $fdn
	 * @return string|bool
	 */
	public function getNameByDN($fdn) {
		return $this->getXbyY('owncloud_name', 'ldap_dn', $fdn);
	}

	/**
	 * Searches mapped names by the giving string in the name column
	 * @param string $search
	 * @return string[]
	 */
	public function getNamesBySearch($search) {
		$query = $this->dbc->prepare('
			SELECT `owncloud_name`
			FROM `'. $this->getTableName() .'`
			WHERE `owncloud_name` LIKE ?
		');

		$res = $query->execute(array($search))->fetchAll();
		$names = array();
		while($row = $res->fetchRow()) {
			$names[] = $row['owncloud_name'];
		}
		return $names;
	}

	/**
	 * Gets the name based on the provided LDAP DN.
	 * @param string $fdn
	 * @return string|bool
	 */
	public function getNameByUUID($uuid) {
		return $this->getXbyY('owncloud_name', 'directory_uuid', $uuid);
	}

	/**
	 * attempts to map the given entry
	 * @param string $fdn fully distinguished name (from LDAP)
	 * @param string $name
	 * @param string $uuid a unique identifier as used in LDAP
	 * @return bool
	 */
	public function map($fdn, $name, $uuid) {
		$row = array(
			'ldap_dn'        => $fdn,
			'owncloud_name'  => $name,
			'directory_uuid' => $uuid
		);

		return $this->dbc->insertIfNotExist($this->getTableName(), $row);
	}
}
