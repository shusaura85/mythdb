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

namespace MythDB;

class Result {
	public string			$type = 'invalid';

	public \mysqli_result	$mysqli;
	public \SQLite3Result	$sqlite3;
	public \PgSql\Result	$pgsql;
	
	private bool			$is_valid;
	
	public function __construct(bool $valid, \mysqli_result|bool|null $mysqli = null, \SQLite3Result|bool|null $sqlite3 = null, \PgSql\Result|bool|null $pgsql = null)
		{
		$this->is_valid = $valid;
		
		if ($mysqli instanceof \mysqli_result)
			{
			$this->mysqli = $mysqli;
			$this->type = 'mysqli';
			}
		else
		if ($sqlite3 instanceof \SQLite3Result)
			{
			$this->sqlite3 = $sqlite3;
			$this->type = 'sqlite3';
			}
		else
		if ($pgsql instanceof \PgSql\Result)
			{
			$this->pgsql = $pgsql;
			$this->type = 'pgsql';
			}
		}

	public function is_valid(): bool
		{
		return $this->is_valid;
		}
	
	
	public function __destruct()
		{
		if ( isset($this->mysqli) && ($this->mysqli instanceof \mysqli_result) )
			{
			mysqli_free_result($this->mysqli);
			}
		else
		if ( isset($this->sqlite3) && ($this->sqlite3 instanceof \SQLite3Result) )
			{
			$this->sqlite3->finalize();
			}
		else
		if ( isset($this->pgsql) && ($this->pgsql instanceof \PgSql\Result) )
			{
			pg_free_result($this->pgsql);
			}
		
		unset($this->mysqli, $this->sqlite3, $this->pgsql);
		}
}
