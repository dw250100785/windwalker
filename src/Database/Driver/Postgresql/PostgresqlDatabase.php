<?php
/**
 * Part of Windwalker project. 
 *
 * @copyright  Copyright (C) 2014 - 2015 LYRASOFT. All rights reserved.
 * @license    GNU Lesser General Public License version 3 or later.
 */

namespace Windwalker\Database\Driver\Postgresql;

use Windwalker\Database\Command\AbstractDatabase;
use Windwalker\Query\Postgresql\PostgresqlQueryBuilder;

/**
 * Class PostgresqlDatabase
 *
 * @since 2.0
 */
class PostgresqlDatabase extends AbstractDatabase
{
	/**
	 * Property tablesCache.
	 *
	 * @var  array
	 */
	protected static $tablesCache = array();

	/**
	 * select
	 *
	 * @return  static
	 */
	public function select()
	{
		$this->db->disconnect();

		$this->db->setDatabaseName($this->getName());

		return $this;
	}

	/**
	 * createDatabase
	 *
	 * @param bool   $ifNotExists
	 * @param string $charset
	 *
	 * @return  static
	 */
	public function create($ifNotExists = false, $charset = 'utf8')
	{
		$query = PostgresqlQueryBuilder::createDatabase($this->database, $charset);

		$this->db->setQuery($query)->execute();

		return $this;
	}

	/**
	 * dropDatabase
	 *
	 * @param bool $ifExists
	 *
	 * @return  static
	 */
	public function drop($ifExists = false)
	{
		if ($this->getName() == $this->db->getDatabase()->getName())
		{
			$this->db->disconnect();
			$this->db->setDatabaseName(null);
			$this->db->connect();
		}

		$pid = version_compare($this->db->getVersion(), '9.2', '>=') ? 'pid' : 'procpid';

		$query = $this->db->getQuery(true);

		$query->select('pg_terminate_backend(' . $pid . ')')
			->from('pg_stat_activity')
			->where('datname = ' . $query->quote($this->getName()));

		$this->db->setQuery($query)->execute();

		$query = PostgresqlQueryBuilder::dropDatabase($this->database, $ifExists);

		$this->db->setQuery($query)->execute();

		return $this;
	}

	/**
	 * exists
	 *
	 * @return  boolean
	 */
	public function exists()
	{
		$databases = $this->db->listDatabases();

		return in_array($this->database, $databases);
	}

	/**
	 * renameDatabase
	 *
	 * @param string  $newName
	 * @param boolean $returnNew
	 *
	 * @return  static
	 */
	public function rename($newName, $returnNew = true)
	{
		// Postgresql 5.1.7 do not have RENAME DATABASE syntax anymore, so we use rename tables to do that.
		// @see: http://stackoverflow.com/questions/67093/how-do-i-quickly-rename-a-postgresql-database-change-schema-name?page=1&tab=votes#tab-top
		$newDatabase = $this->db->getDatabase($newName)->create();

		$tables = $this->db->getReader(PostgresqlQueryBuilder::showDbTables($this->database))->loadObjectList();

		foreach ($tables as $table)
		{
			$name = $table->Name;

			$this->db->setQuery(
				sprintf(
					'RENAME TABLE %s.%s TO %s.%s',
					$this->db->quoteName($this->database),
					$this->db->quoteName($name),
					$this->db->quoteName($newName),
					$this->db->quoteName($name)
				)
			)->execute();
		}

		$this->drop(true);

		if ($returnNew)
		{
			return $newDatabase;
		}

		return $this;
	}

	/**
	 * Method to get an array of all tables in the database.
	 *
	 * @param bool $refresh
	 *
	 * @return  array  An array of all the tables in the database.
	 *
	 * @since   2.0
	 */
	public function getTables($refresh = false)
	{
		if (empty(static::$tablesCache) || $refresh)
		{
			static::$tablesCache = array_keys($this->getTableDetails(false));
		}

		return static::$tablesCache;
	}

	/**
	 * getTableDetails
	 *
	 * @return  object[]
	 */
	public function getTableDetails()
	{
		$query = PostgresqlQueryBuilder::showDbTables($this->database);

		return $this->db->setQuery($query)->loadAll('Name');
	}

	/**
	 * getTableDetail
	 *
	 * @param bool $table
	 *
	 * @return  mixed
	 */
	public function getTableDetail($table)
	{
		$table = $this->db->replacePrefix($table);

		$query = PostgresqlQueryBuilder::showDbTables($this->database, 'Name = ' . $this->db->quote($table));

		$table = $this->db->setQuery($query)->loadOne();

		if (!$table)
		{
			return false;
		}

		return $table;
	}

	/**
	 * tableExists
	 *
	 * @param string $table
	 *
	 * @return  boolean
	 */
	public function tableExists($table)
	{
		return (bool) $this->getTableDetail($table);
	}
}

