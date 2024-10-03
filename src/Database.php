<?php
/*-
 * Copyright © 2024 Shu Saura
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * “Software”), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
declare(strict_types=1);

namespace MythDB;

use \MythDB\Result as DBResult;

// Return current timestamp (with microseconds) as a float (used in dblayer)
if (defined('MDB_DB_DEBUG'))
	{
	function get_microtime(): float
		{
		list($usec, $sec) = explode(' ', microtime());
		return ((float)$usec + (float)$sec);
		}
	}


class Database {	
	private string $db_driver	= '';		// driver name to use. currently supports: mysqli, pgsql, sqlite
	private string $db_host		= '';
	private string $db_username	= '';
	private string $db_password	= '';
	private string $db_name		= '';
	private bool $p_connect		= false;
	private string $names_utf8	= '';
	//private array $arr		= null;
	
	public array $saved_queries = [];
	public ?\MythDB\Driver\DBLayer $driver = null;	// DB Layer Object
	
	public function __construct(string $db_driver, string $db_host, string $db_username, string $db_password, string $db_name, bool $p_connect = false, string $set_names_utf8 = '')
		{
		$this->db_driver	= $db_driver;
		$this->db_host		= $db_host;
		$this->db_username	= $db_username;
		$this->db_password	= $db_password;
		$this->db_name		= $db_name;
		$this->p_connect	= $p_connect;
		$this->names_utf8	= $set_names_utf8;
		}

	private function connect(): void
		{
		$driver_file = dirname(__FILE__)."/Driver/".$this->db_driver.".php";

		if (is_readable($driver_file))
			{
			$driver_class = "\\MythDB\\Driver\\".$this->db_driver;
			
			try {
				$this->driver = new $driver_class($this->db_host, $this->db_username, $this->db_password, $this->db_name, $this->p_connect);
				}
			catch(\Exception $e)
				{
				// to do: replace with nicer error
				echo $e->getMessage();
				echo '<hr />';
				echo 'Critical error encountered!';
				exit;
				}
			
			// clear stored data
			$this->db_driver	= '';
			$this->db_host		= '';
			$this->db_username	= '';
			$this->db_password	= '';
			$this->db_name		= '';
			$this->p_connect	= false;
			}
		else
			{
			throw new \Exception('\''.$this->db_driver.'\' is not a valid database type. Please check your database settings.');
			}
		
		if ($this->names_utf8 != '')
			{
			$this->set_names($this->names_utf8);
			$this->set_charset($this->names_utf8);
			}
		/*
		if ($this->names_utf8 == 'utf8mb4')
			{
			$this->set_names('utf8mb4');
			$this->set_charset('utf8mb4');
			}
		else
		if ($this->names_utf8)
			{
			$this->set_names('utf8');
			$this->set_charset('utf8');
			}*/
		}
	

	public function is_active(): bool
		{
		return (null !== $this->driver);
		}


	public function start_transaction(): bool
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->start_transaction();
		}


	public function end_transaction(): bool
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->end_transaction();
		}


	public function query(string $sql, bool $unbuffered = false): DBResult
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->query($sql, $unbuffered);
		}


	public function result(DBResult $query_id, int $row = 0, int $col = 0): mixed
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->result($query_id, $row, $col);
		}


	public function fetch_assoc(DBResult $query_id):array|null|false
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->fetch_assoc($query_id);
		}


	public function fetch_row(DBResult $query_id):array|null|false
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->fetch_row($query_id);
		}


	public function num_rows(DBResult $query_id):int|false
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->num_rows($query_id);
		}


	public function affected_rows():int|false
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->affected_rows();
		}


	public function insert_id():int|false
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->insert_id();
		}


	public function get_num_queries():int
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->get_num_queries();
		}


	public function get_saved_queries(): array
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->get_saved_queries();
		}


	public function free_result(DBResult $query_id): void
		{
		if (null === $this->driver) { $this->connect(); }
		$this->driver->free_result($query_id);
		}


	public function escape(string $str): string
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->escape($str);
		}


	public function error(): array
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->error();
		}


	public function close(): bool
		{
		return $this->driver->close();
		}


	public function set_names(string $names): DBResult
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->set_names($names);
		}


	public function set_charset(string $charset): bool
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->set_charset($charset);
		}


	public function get_version(): array
		{
		if (null === $this->driver) { $this->connect(); }
		return $this->driver->get_version();
		}
}

