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
* A database layer class that relies on the SQLite PHP extension.
*****************************************************************************/

/***********************************
* SQLite3 database doesn't use the $db_host and $db_username constructor params
* $db_password is the encryption key used (if supported)
************************************/
declare(strict_types=1);

namespace MythDB\Driver;

use \MythDB\Result as DBResult;

// Make sure we have built in support for SQLite
if (!class_exists('\SQLite3'))
	throw new \Exception('This PHP environment doesn\'t have SQLite3 support built in. SQLite3 support is required if you want to use a SQLite3 database.');

class sqlite3 implements \MythDB\Driver\DBLayer
{
	public \SQLite3 $link_id;
	public \SQLite3Result|bool $query_result;
	public int $in_transaction = 0;

	public array $saved_queries = [];
	public int $num_queries = 0;
	public string $last_query = '';

	public int $error_no = 0;
	public string $error_msg = 'Unknown';

	public function __construct(string $db_host, string $db_username, string $db_password, string $db_name, bool $p_connect = false)
		{
		if (strtolower(substr($db_name, -8)) !== '.sqlite3')
			$db_name .= '.sqlite3';

		if (!file_exists($db_name))
			{
			@touch($db_name);
			@chmod($db_name, 0666);
			if (!file_exists($db_name))
				error('Unable to create new database \''.$db_name.'\'. Permission denied.', __FILE__, __LINE__);
			}

		if (!is_readable($db_name))
			error('Unable to open database \''.$db_name.'\' for reading. Permission denied.', __FILE__, __LINE__);

		if (!is_writable($db_name))
			error('Unable to open database \''.$db_name.'\' for writing. Permission denied.', __FILE__, __LINE__);

		@$this->link_id = new \SQLite3($db_name, SQLITE3_OPEN_READWRITE, $db_password);

		if (!$this->link_id)
			throw new \Exception('Unable to open database:  \''.$db_name.'\'.' );
		
		//return $this->link_id;
		}

	public function start_transaction(): bool
		{
		++$this->in_transaction;

		return ($this->link_id->exec('BEGIN TRANSACTION')) ? true : false;
		}

	public function end_transaction(): bool
		{
		--$this->in_transaction;

		if ($this->link_id->exec('COMMIT'))
			return true;
		else
			{
			$this->link_id->exec('ROLLBACK');
			return false;
			}
		}

	public function query(string $sql, bool $unbuffered = false): DBResult
		{
		if (strlen($sql) > 140000)
			exit('Insane query. Aborting.');

		if (defined('MDB_DB_DEBUG'))
			$q_start = get_microtime();

		$this->query_result = $this->link_id->query($sql);

		if ($this->query_result)
			{
			if (defined('MDB_DB_DEBUG'))
				$this->saved_queries[] = array($sql, sprintf('%.5f', get_microtime() - $q_start));
			
			$this->last_query  = $sql;

			++$this->num_queries;

			return new DBResult(true, sqlite3: $this->query_result);
			}
		else
			{
			if (defined('MDB_DB_DEBUG'))
				$this->saved_queries[] = array($sql, 0);
			
			$this->last_query = $sql;

			$this->error_no = $this->link_id->lastErrorCode();
			$this->error_msg = $this->link_id->lastErrorMsg();

			if ($this->in_transaction)
				$this->link_id->exec('ROLLBACK');

			--$this->in_transaction;

			return new DBResult(false);
			}
		}


	public function result(DBResult $query_id, int $row = 0, int $col = 0): mixed
		{
		if ($query_id->is_valid())
			{
			if ($row != 0)
				{
				$result_rows = array();
				while ($cur_result_row = @$query_id->sqlite3->fetchArray(SQLITE3_NUM))
					{
					$result_rows[] = $cur_result_row;
					}

				$cur_row = array_slice($result_rows, $row);
				}
			else
				$cur_row = @$query_id->sqlite3->fetchArray(SQLITE3_NUM);

			return $cur_row[$col];
			}
		else
			return false;
		}

	public function fetch_assoc(DBResult $query_id): array|null|false
		{
		if ($query_id->is_valid())
			{
			$cur_row = @$query_id->sqlite3->fetchArray(SQLITE3_ASSOC);
			if ($cur_row)
				{
				// Horrible hack to get rid of table names and table aliases from the array keys
				foreach ($cur_row as $key => $value)
					{
					$dot_spot = strpos($key, '.');
					if ($dot_spot !== false)
						{
						unset($cur_row[$key]);
						$key = substr($key, $dot_spot+1);
						$cur_row[$key] = $value;
						}
					}
				}

			return $cur_row;
			}
		else
			return false;
		}

	public function fetch_row(DBResult $query_id): array|null|false
		{
		return ($query_id->is_valid()) ? @$query_id->sqlite3->fetchArray(SQLITE3_NUM) : false;
		}

	public function num_rows(DBResult $query_id): int|false
		{
		return false;
		}

	public function affected_rows(): int|false
		{
		return ($this->query_result) ? $this->link_id->changes() : false;
		}

	public function insert_id(): int|false
		{
		return ($this->link_id) ? $this->link_id->lastInsertRowID() : false;
		}

	public function get_num_queries(): int
		{
		return $this->num_queries;
		}

	public function get_saved_queries(): array
		{
		return $this->saved_queries;
		}

	public function free_result(DBResult $query_id): void
		{
		if ($query_id->is_valid())
			{
			@$query_id->sqlite3->finalize();
			}
		
		unset($query_id);
		}

	public function escape(string $str): string
		{
		return $this->link_id->escapeString($str);
		}

	public function error(): array
		{
		$result = [];
		$result['error_sql'] = $this->last_query;
		$result['error_no'] = $this->error_no;
		$result['error_msg'] = $this->error_msg;

		return $result;
		}

	public function close(): bool
		{
		if ($this->link_id)
			{
			if ($this->in_transaction)
				{
				if (defined('MDB_DB_DEBUG'))
					$this->saved_queries[] = array('COMMIT', 0);

				$this->link_id->exec('COMMIT');
				}

			return @$this->link_id->close();
			}
		else
			return false;
		}

	public function set_names(string $names): DBResult
		{
		return new DBResult(false);
		}

	public function set_charset(string $charset): bool
		{
		return false;
		}

	public function get_version(): array
		{
		$info = \SQLite3::version();

		return [
			'name'		=> 'SQLite3',
			'version'	=> $info['versionString']
			];
		}
}
