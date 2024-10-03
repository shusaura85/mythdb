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

/****************************************************************************
* A database layer class that relies on the MySQLi PHP extension.
*****************************************************************************/

declare(strict_types=1);

namespace MythDB\Driver;

use \MythDB\Result as DBResult;

// Make sure we have built in support for MySQL
if (!function_exists('mysqli_connect'))
	throw new \Exception('This PHP environment doesn\'t have Improved MySQL (mysqli) support built in. Improved MySQL support is required if you want to use a MySQL or MariaDB database.');


class mysqli implements \MythDB\Driver\DBLayer
{
	private \mysqli|false|null $link_id;
	private \mysqli_result|bool|null $query_result;
    private int $in_transaction = 0;

	private array $saved_queries = [];
	private int $num_queries = 0;
	public string $last_query = '';


	public function __construct(string $db_host, string $db_username, string $db_password, string $db_name, bool $p_connect = false)
		{
		// Was a custom port supplied with $db_host?
		if (strpos($db_host, ':') !== false)
			list($db_host, $db_port) = explode(':', $db_host);

		// check for persistent connection
		if ($p_connect) { $db_host = 'p:'.$db_host; }

		if (isset($db_port))
			$this->link_id = @mysqli_connect($db_host, $db_username, $db_password, $db_name, $db_port);
		else
			$this->link_id = @mysqli_connect($db_host, $db_username, $db_password, $db_name);

		if (!$this->link_id)
			throw new \Exception('Unable to connect to MySQL and select database. MySQL reported: '.mysqli_connect_error() );

	//	return $this->link_id;
		}


	public function start_transaction(): bool
		{
		++$this->in_transaction;

		return (mysqli_query($this->link_id, 'START TRANSACTION') instanceof mysqli_result);
		}


	public function end_transaction(): bool
		{
		--$this->in_transaction;

		if (mysqli_query($this->link_id, 'COMMIT'))
			return true;
		else
			{
			mysqli_query($this->link_id, 'ROLLBACK');
			return false;
			}
		}


	public function query(string $sql, bool $unbuffered = false): DBResult
		{
		if (strlen($sql) > 140000)
			exit('Insane query. Aborting.');

		if (defined('MDB_DB_DEBUG'))
			$q_start = \MythDB\get_microtime();

		$this->query_result = @mysqli_query($this->link_id, $sql);

		if ($this->query_result)
			{
			if (defined('MDB_DB_DEBUG'))
				$this->saved_queries[] = array($sql, sprintf('%.5f', \MythDB\get_microtime() - $q_start));
			
			$this->last_query = $sql;

			++$this->num_queries;

			return new DBResult(true, mysqli: $this->query_result);
			}
		else
			{
			if (defined('MDB_DB_DEBUG'))
				$this->saved_queries[] = array($sql, 0);
			
			$this->last_query = $sql;

			if ($this->in_transaction)
				mysqli_query($this->link_id, 'ROLLBACK');

			--$this->in_transaction;
			
			return new DBResult(false);
			}
		}


	public function result(DBResult $query_id, int $row = 0, int $col = 0): mixed
		{
		if ($query_id->is_valid())
			{
			if ($row)
				@mysqli_data_seek($query_id->mysqli, $row);

			$cur_row = @mysqli_fetch_row($query_id->mysqli);
			return $cur_row[$col];
			}
		else
			return false;
		}


	public function fetch_assoc(DBResult $query_id):array|null|false
		{
		return ($query_id->is_valid()) ? @mysqli_fetch_assoc($query_id->mysqli) : false;
		}


	public function fetch_row(DBResult $query_id):array|null|false
		{
		return ($query_id->is_valid()) ? @mysqli_fetch_row($query_i->mysqlid) : false;
		}


	public function num_rows(DBResult $query_id):int|false
		{
		return ($query_id->is_valid()) ? @mysqli_num_rows($query_id->mysqli) : false;
		}


	public function affected_rows():int|false
		{
		return ($this->link_id) ? @mysqli_affected_rows($this->link_id) : false;
		}


	public function insert_id():int|false
		{
		return ($this->link_id) ? @mysqli_insert_id($this->link_id) : false;
		}


	public function get_num_queries():int
		{
		return $this->num_queries;
		}


	public function get_saved_queries():array
		{
		return $this->saved_queries;
		}


	public function free_result(DBResult $query_id): void
		{
		if ($query_id->is_valid())
			@mysqli_free_result($query_id->mysqli);
		
		unset($query_id);
		}


	public function escape(string $str): string
		{
		return mysqli_real_escape_string($this->link_id, $str);
		}


	public function error(): array
		{
		$result = [];
		$result['error_sql'] = $this->last_query;
		$result['error_no'] = @mysqli_errno($this->link_id);
		$result['error_msg'] = @mysqli_error($this->link_id);

		return $result;
		}


	public function close(): bool
		{
		if ($this->link_id)
			{
			if ($this->query_result)
				@mysqli_free_result($this->query_result);

			return @mysqli_close($this->link_id);
			}
		else
			return false;
		}


	public function set_names(string $names): DBResult
		{
		return $this->query('SET NAMES \''.$this->escape($names).'\'');
		}


	public function set_charset(string $charset): bool
		{
		return mysqli_set_charset($this->link_id, $charset);
		}


	public function get_version(): array
		{
		$result = $this->query('SELECT VERSION()');

		return [
			'name'		=> 'MySQLi',
			'version'	=> preg_replace('/^([^-]+).*$/', '\\1', $this->result($result))
			];
		}
}
