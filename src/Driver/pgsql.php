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
* A database layer class that relies on the PostgreSQL PHP extension.
*****************************************************************************/

declare(strict_types=1);

namespace MythDB\Driver;

use \MythDB\Result as DBResult;

// Make sure we have built in support for PostgreSQL
if (!function_exists('\pg_connect'))
	throw new \Exception('This PHP environment doesn\'t have PostgreSQL support built in. PostgreSQL support is required if you want to use a PostgreSQL database.');


class pgsql implements \MythDB\Driver\DBLayer
{
	private \PgSql\Connection|false $link_id;
	private \PgSql\Result|bool $query_result;
	private array $last_query_text = [];
	private $in_transaction = 0;

	private array $saved_queries = [];
	private int $num_queries = 0;
	public string $last_query = '';

	//private int $error_no = 0;
	private string|false $error_msg = 'Unknown';


	public function __construct(string $db_host, string $db_username, string $db_password, string $db_name, bool $p_connect)
		{
		if ($db_host)
			{
			if (strpos($db_host, ':') !== false)
			{
				list($db_host, $dbport) = explode(':', $db_host);
				$connect_str[] = 'host='.$db_host.' port='.$dbport;
			}
			else
				$connect_str[] = 'host='.$db_host;
			}

		if ($db_name)
			$connect_str[] = 'dbname='.$db_name;

		if ($db_username)
			$connect_str[] = 'user='.$db_username;

		if ($db_password)
			$connect_str[] = 'password='.$db_password;

		if ($p_connect)
			$this->link_id = @\pg_pconnect(implode(' ', $connect_str));
		else
			$this->link_id = @\pg_connect(implode(' ', $connect_str));

		if (!$this->link_id)
			throw new \Exception('Unable to connect to PostgreSQL server.');

		//return $this->link_id;
		}


	public function start_transaction(): bool
		{
		++$this->in_transaction;

		return (@pg_query($this->link_id, 'BEGIN')) ? true : false;
		}


	public function end_transaction(): bool
		{
		--$this->in_transaction;

		if (@pg_query($this->link_id, 'COMMIT'))
			return true;
		else
			{
			@pg_query($this->link_id, 'ROLLBACK');
			return false;
			}
		}


	public function query(string $sql, bool $unbuffered = false): DBResult	// $unbuffered is ignored since there is no pgsql_unbuffered_query()
		{
		if (strlen($sql) > 140000)
			exit('Insane query. Aborting.');

		if (strrpos($sql, 'LIMIT') !== false)
			$sql = preg_replace('#LIMIT ([0-9]+),([ 0-9]+)#', 'LIMIT \\2 OFFSET \\1', $sql);

		if (defined('MDB_DB_DEBUG'))
			$q_start = \MythDB\get_microtime();

		@pg_send_query($this->link_id, $sql);
		$this->query_result = @pg_get_result($this->link_id);

		if (pg_result_status($this->query_result) != PGSQL_FATAL_ERROR)
			{
			if (defined('MDB_DB_DEBUG'))
				$this->saved_queries[] = array($sql, sprintf('%.5f', \MythDB\get_microtime() - $q_start));
			
			$this->last_query = $sql;

			++$this->num_queries;

			$this->last_query_text[$this->query_result] = $sql;

			return new DBResult(true, pgsql: $this->query_result);
			}
		else
			{
			if (defined('MDB_DB_DEBUG'))
				$this->saved_queries[] = array($sql, 0);
			
			$this->last_query = $sql;

			$this->error_msg = @pg_result_error($this->query_result);

			if ($this->in_transaction)
				@pg_query($this->link_id, 'ROLLBACK');

			--$this->in_transaction;

			return new DBResult(false);
			}
		}


	public function result(DBResult $query_id, int $row = 0, int $col = 0): mixed
		{
		return ($query_id->is_valid()) ? @pg_fetch_result($query_id->pgsql, $row, $col) : false;
		}


	public function fetch_assoc(DBResult $query_id): array|null|false
		{
		return ($query_id->is_valid()) ? @pg_fetch_assoc($query_id->pgsql) : false;
		}


	public function fetch_row(DBResult $query_id): array|null|false
		{
		return ($query_id->is_valid()) ? @pg_fetch_row($query_id->pgsql) : false;
		}


	public function num_rows(DBResult $query_id): int|false
		{
		return ($query_id->is_valid()) ? @pg_num_rows($query_id->pgsql) : false;
		}


	public function affected_rows(): int|false
		{
		return ($this->query_result) ? @pg_affected_rows($this->query_result) : false;
		}


	public function insert_id(): int|false
		{
		$query_id = $this->query_result;

		if ($query_id && $this->last_query_text[$query_id] != '')
			{
			if (preg_match('/^INSERT INTO ([a-z0-9\_\-]+)/is', $this->last_query_text[$query_id], $table_name))
				{
				// Hack (don't ask)
				if (substr($table_name[1], -6) == 'groups')
					$table_name[1] .= '_g';

				$temp_q_id = @pg_query($this->link_id, 'SELECT currval(\''.$table_name[1].'_id_seq\')');
				return ($temp_q_id) ? intval(@pg_fetch_result($temp_q_id, 0)) : false;
				}
			}

		return false;
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
		if ($this->query_result)
			@pg_free_result($this->query_result);

		if ($query_id->is_valid())
			@pg_free_result($query_id->pgsql);
		
		unset($query_id);
		}


	public function escape(string $str): string
		{
		return pg_escape_string($str);
		}


	public function error(): array
		{
		$result['error_sql'] = $this->last_query;
		$result['error_no'] = 0;
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

				@pg_query($this->link_id, 'COMMIT');
				}

			if ($this->query_result)
				@pg_free_result($this->query_result);

			return @pg_close($this->link_id);
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
		return false;
		}


	public function get_version(): array
		{
		$result = $this->query('SELECT VERSION()');

		return [
			'name'		=> 'PostgreSQL',
			'version'	=> preg_replace('/^[^0-9]+([^\s,-]+).*$/', '\\1', $this->result($result))
			];
		}
}
