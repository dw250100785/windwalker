<?php
/**
 * Part of Windwalker project. 
 *
 * @copyright  Copyright (C) 2011 - 2014 SMS Taiwan, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Windwalker\DataMapper\Compare;

/**
 * Class InCompare
 *
 * @since 1.0
 */
class InCompare extends Compare
{
	/**
	 * Property operator.
	 *
	 * @var  string
	 */
	protected $operator = 'IN';

	/**
	 * Property separator.
	 *
	 * @var  string
	 */
	protected $separator = ',';

	/**
	 * compare
	 *
	 * @return  mixed
	 */
	public function compare()
	{
		$compare2 = is_string($this->compare2) ? explode($this->separator, $this->compare2) : (array) $this->compare2;

		$compare2 = array_map('trim', $compare2);

		return in_array($this->compare1, $compare2);
	}

	/**
	 * getSeparator
	 *
	 * @return  string
	 */
	public function getSeparator()
	{
		return $this->separator;
	}

	/**
	 * setSeparator
	 *
	 * @param   string $separator
	 *
	 * @return  InCompare  Return self to support chaining.
	 */
	public function setSeparator($separator)
	{
		$this->separator = $separator;

		return $this;
	}
}