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
* Interface database layer class used as base for all other database layers.
*****************************************************************************/
declare(strict_types=1);

namespace MythDB\Driver;

use \MythDB\Result as DBResult;

interface DBLayer {

	public function start_transaction(): bool;

	public function end_transaction(): bool;

	public function query(string $sql, bool $unbuffered = false): DBResult;

	public function result(DBResult $query_id, int $row = 0, int $col = 0): mixed;

	public function fetch_assoc(DBResult $query_id): array|null|false;

	public function fetch_row(DBResult $query_id): array|null|false;

	public function num_rows(DBResult $query_id): int|false;

	public function affected_rows(): int|false;

	public function insert_id(): int|false;

	public function get_num_queries(): int;

	public function get_saved_queries(): array;
	
	public function free_result(DBResult $query_id): void;

	public function escape(string $str): string;

	public function error(): array;

	public function close(): bool;

	public function set_names(string $names): DBResult;

	public function set_charset(string $charset): bool;

	public function get_version(): array;
}
